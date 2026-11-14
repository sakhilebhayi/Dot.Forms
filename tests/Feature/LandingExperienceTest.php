<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * R-50: the page a person arrives at after signing in through InfoDot. One primary action
 * per role, the original logo, a welcome from real data, an honest empty state, the way
 * back to InfoDot, and no figure that does not come from the company's own records.
 */
class LandingExperienceTest extends TestCase
{
    use RefreshDatabase;

    /** SHA-256 of the original artwork. A changed file fails here: the logo is never redrawn. */
    private const LOGO_SHA256 = '019254e2da82a0b6bb94c4d93e62a4b1c063f392e939cf6b45307be593b48aec';

    private const LOGO_LIGHT_SHA256 = 'b2b0a549c710cf1e0856bb26434f570aaa56075caa45255d2f7bb5cbb27c91e9';

    public function test_owner_lands_on_one_primary_action_that_opens_the_builder(): void
    {
        [$owner, $team] = $this->companyWithRecords();

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();

        $primary = $this->primaryAction($response);
        $this->assertSame('Set a new form', $primary['label']);
        $this->assertSame(route('teams.forms.store', $team), $primary['target']);
        $this->assertSame('POST', $primary['method']);

        $before = Form::withoutTeamScope()->count();
        $started = $this->actingAs($owner)->post($primary['target']);
        $form = Form::withoutTeamScope()->latest('id')->first();

        $this->assertSame($before + 1, Form::withoutTeamScope()->count());
        $this->assertSame($team->id, $form->team_id);
        $this->assertFalse((bool) $form->is_published);
        $started->assertRedirect(route('teams.forms.builder', ['team' => $team, 'form' => $form]));
        $this->actingAs($owner)->get($started->headers->get('Location'))->assertOk();
    }

    public function test_an_editor_gets_the_same_primary_action_and_it_works(): void
    {
        [, $team] = $this->companyWithRecords();
        $editor = $this->member($team, 'editor');

        $primary = $this->primaryAction($this->actingAs($editor)->get('/dashboard')->assertOk());
        $this->assertSame('Set a new form', $primary['label']);

        $started = $this->actingAs($editor)->post($primary['target'])->assertRedirect();
        $this->actingAs($editor)->get($started->headers->get('Location'))->assertOk();
    }

    public function test_a_viewer_gets_a_primary_action_they_are_allowed_to_use(): void
    {
        [$owner, $team] = $this->companyWithRecords();
        $viewer = $this->member($team, 'viewer');

        $response = $this->actingAs($viewer)->get('/dashboard')->assertOk();

        $primary = $this->primaryAction($response);
        $this->assertSame('Open the book', $primary['label']);
        $this->assertSame('GET', $primary['method']);
        $this->assertSame(route('teams.forms', $team), $primary['target']);
        $this->actingAs($viewer)->get($primary['target'])->assertOk();

        $response->assertSee($owner->name)
            ->assertDontSee('Set with AI')
            ->assertDontSee('Open the master');
        $this->actingAs($viewer)->post(route('teams.forms.store', $team))->assertForbidden();
        $this->assertSame(3, Form::withoutTeamScope()->count());
    }

    public function test_the_primary_action_cannot_set_a_form_in_someone_elses_company(): void
    {
        [, $team] = $this->companyWithRecords();
        $stranger = User::factory()->withPersonalTeam()->create();

        $this->actingAs($stranger)->post(route('teams.forms.store', $team))->assertForbidden();
        $this->assertSame(3, Form::withoutTeamScope()->count());
    }

    public function test_the_welcome_uses_the_persons_first_name_and_the_company_name(): void
    {
        $user = User::factory()->withPersonalTeam()->create(['name' => 'Nomvula Zulu-Peters']);
        $user->currentTeam->forceFill(['name' => 'Zulu-Peters Haulage'])->save();

        $this->actingAs($user->fresh())->get('/dashboard')->assertOk()
            ->assertSeeInOrder(['Welcome, <b>Nomvula</b>', '<b>Zulu-Peters Haulage</b>'], false)
            ->assertSee('Set a form once. Number every copy.');
    }

    public function test_a_new_member_with_no_forms_sees_an_honest_empty_state_and_no_figures(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->get('/dashboard')->assertOk();
        $html = $response->getContent();

        $response->assertSee('data-empty-state', false)
            ->assertSee('The book is empty')
            ->assertSee('Nothing is set yet')
            ->assertDontSee('Recent masters')
            ->assertDontSee('data-book-tally', false)
            ->assertDontSee('FRM-');
        $this->assertSame(1, substr_count($html, 'data-primary-action'));
        $this->assertSame('Set a new form', $this->primaryAction($response)['label']);

        // With no records, the landing shows no digit at all: nothing is counted, nothing invented.
        $this->assertDoesNotMatchRegularExpression('/\d/', $this->landingText($html));
    }

    public function test_every_figure_on_the_landing_comes_from_seeded_records(): void
    {
        [$owner, $team] = $this->companyWithRecords();
        // Another company's records must never be counted here.
        $other = User::factory()->withPersonalTeam()->create();
        $foreign = $this->form($other->currentTeam, $other, 'Foreign form', true, ['A']);
        $this->copies($foreign, 9);

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();
        $html = $response->getContent();

        $this->assertSame('3', $this->tally($html, 'forms'));
        $this->assertSame('2', $this->tally($html, 'in-use'));
        $this->assertSame('4', $this->tally($html, 'copies-week'), 'the copy older than 7 days is not counted');

        $response->assertSee('The master '.$team->name.' last worked on')
            ->assertSeeInOrder(['Site induction', 'Full name', 'ID number', 'Site', 'Start date', 'and 1 more field'])
            ->assertDontSee('Signature')
            ->assertDontSee('Foreign form');

        // Every number in the landing's text is one of: a serial, a field number, a seeded count,
        // a relative time, or the fixed "last 7 days" window.
        $text = $this->landingText($html);
        $text = preg_replace('/FRM-\d{3,}/', '', $text);
        $text = preg_replace('/\b\d+(s|m|h|d|w|mo|y|yr|yrs|hr|hrs|min|mins)\b( ago)?/', '', $text);
        $text = str_replace('in the last 7 days', '', $text);
        preg_match_all('/\d+/', $text, $found);
        $allowed = ['01', '02', '03', '04', '0', '1', '2', '3', '4', '5'];
        $this->assertSame([], array_values(array_diff($found[0], $allowed)), 'a figure on the landing has no seeded source');

        $latest = $team->forms()->where('title', 'Site induction')->first();
        $response->assertSee('FRM-'.str_pad((string) $latest->id, 3, '0', STR_PAD_LEFT))
            ->assertSee('5 copies')
            ->assertSee('5 fields');
    }

    public function test_the_landing_shows_the_original_logo_files_unaltered(): void
    {
        $this->assertSame(self::LOGO_SHA256, hash_file('sha256', public_path('images/dot_forms.png')));
        $this->assertSame(self::LOGO_LIGHT_SHA256, hash_file('sha256', public_path('images/dot_forms-light.png')));

        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('src="'.asset('images/dot_forms.png').'" alt="Dot.Forms"', false)
            ->assertSee('srcset="'.asset('images/dot_forms-light.png').'"', false);
    }

    public function test_the_way_back_to_infodot_comes_from_the_ecosystem_configuration(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('<a href="https://infodot.co.za/home">InfoDot</a>', false)
            ->assertSee('<a href="https://infodot.co.za/platforms">Platforms</a>', false);

        config(['ecosystem.platforms.infodot.url' => 'https://infodot.test/']);

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('<a href="https://infodot.test/home">InfoDot</a>', false)
            ->assertSee('<a href="https://infodot.test/platforms">Platforms</a>', false);
    }

    public function test_everything_the_old_desk_offered_is_still_reachable(): void
    {
        [$owner, $team] = $this->companyWithRecords();
        $form = $team->forms()->where('title', 'Site induction')->first();

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();

        foreach ([
            route('teams.forms.templates', $team),
            route('teams.forms.ai-builder', $team),
            route('teams.forms', $team),
            route('teams.forms.builder', ['team' => $team, 'form' => $form]),
            route('teams.forms.submissions', ['team' => $team, 'form' => $form]),
        ] as $url) {
            $response->assertSee('href="'.$url.'"', false);
            $this->actingAs($owner)->get($url)->assertOk();
        }
    }

    public function test_the_landing_has_no_inline_event_handlers_or_inline_styles(): void
    {
        [$owner] = $this->companyWithRecords();

        $landing = $this->landingHtml($this->actingAs($owner)->get('/dashboard')->getContent());

        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $landing);
        $this->assertStringNotContainsString('style=', $landing);
    }

    public function test_the_platform_list_holds_only_current_platforms(): void
    {
        $current = [
            'agents', 'analytics', 'auction', 'central', 'charts', 'design', 'docs', 'dopemine', 'ehail', 'emall',
            'engage', 'files', 'finance', 'forms', 'hr', 'memory', 'mines', 'notify', 'press', 'projects', 'pulse',
            'sheet', 'tasks', 'tutor', 'infodot',
        ];

        $this->assertSame([], array_values(array_diff(array_keys(config('ecosystem.platforms')), $current)));
    }

    /** @return array{0: User, 1: Team} */
    private function companyWithRecords(): array
    {
        $owner = User::factory()->withPersonalTeam()->create(['name' => 'Thandi Mokoena']);
        $team = $owner->currentTeam;
        $team->forceFill(['name' => 'Mokoena Civils'])->save();

        $draft = $this->form($team, $owner, 'Leave request', false, []);
        $plant = $this->form($team, $owner, 'Plant check', true, ['Plant number']);
        $induction = $this->form($team, $owner, 'Site induction', true, ['Full name', 'ID number', 'Site', 'Start date', 'Signature']);

        $this->copies($induction, 4);
        $this->copies($induction, 1, now()->subDays(30));

        $draft->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        $plant->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();
        $induction->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        return [$owner->fresh(), $team->fresh()];
    }

    /** @param  array<int, string>  $fields */
    private function form(Team $team, User $user, string $title, bool $published, array $fields): Form
    {
        $form = Form::query()->create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'settings' => [],
            'is_published' => $published,
        ]);

        foreach ($fields as $order => $label) {
            $form->fields()->create(['type' => 'text', 'label' => $label, 'order' => $order]);
        }

        return $form;
    }

    private function copies(Form $form, int $count, mixed $when = null): void
    {
        for ($i = 0; $i < $count; $i++) {
            $copy = FormSubmission::query()->create(['form_id' => $form->id, 'data' => [], 'submitted_at' => $when ?? now()]);
            $copy->forceFill(['created_at' => $when ?? now()])->save();
        }
    }

    private function member(Team $team, string $role): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team->users()->attach($user, ['role' => $role]);
        $user->switchTeam($team);

        return $user->fresh();
    }

    /**
     * The one element marked data-primary-action: its label, where it leads and how.
     *
     * @return array{label: string, target: string, method: string}
     */
    private function primaryAction(TestResponse $response): array
    {
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'data-primary-action'), 'exactly one primary action');

        if (preg_match('/<a\s[^>]*href="([^"]+)"[^>]*data-primary-action[^>]*>(.*?)<\/a>/s', $html, $link)) {
            return ['label' => trim(strip_tags($link[2])), 'target' => html_entity_decode($link[1]), 'method' => 'GET'];
        }

        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="([^"]+)"[^>]*>(?:(?!<\/form>).)*<button[^>]*data-primary-action[^>]*>(.*?)<\/button>/s',
            $html,
        );
        preg_match('/<form method="POST" action="([^"]+)"[^>]*>(?:(?!<\/form>).)*<button[^>]*data-primary-action[^>]*>(.*?)<\/button>/s', $html, $button);

        return ['label' => trim(strip_tags($button[2])), 'target' => html_entity_decode($button[1]), 'method' => 'POST'];
    }

    private function tally(string $html, string $key): ?string
    {
        return preg_match('/data-tally="'.preg_quote($key, '/').'">(\d+)</', $html, $m) ? $m[1] : null;
    }

    /** The page body between the spine and the scripts: the landing itself, markup included. */
    private function landingHtml(string $html): string
    {
        $this->assertSame(1, preg_match('/<main class="cb-page">(.*?)<\/main>/s', $html, $main));

        return $main[1];
    }

    /** The landing's visible text, without markup, attributes or the hidden CSRF field. */
    private function landingText(string $html): string
    {
        $main = preg_replace('/<input[^>]*>/', '', $this->landingHtml($html));

        return html_entity_decode(strip_tags($main));
    }
}
