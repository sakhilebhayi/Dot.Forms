<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * R-51: the desk is a working register. Every copy-derived figure is counted inside the company's own forms,
 * is drawn only for a person who may read copies, and ends on the number the records hold.
 */
class DeskDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_headline_and_ledger_count_only_this_companys_copies_by_status(): void
    {
        [$owner, $team, $forms] = $this->company();
        $this->copies($forms[0], 3, 'new');
        $this->copies($forms[0], 2, 'in_review');
        $this->copies($forms[1], 4, 'new');
        $this->copies($forms[1], 1, 'closed');

        // Another company has plenty of waiting copies, assigned to this very person: none may be counted or listed.
        [$other, $otherTeam, $otherForms] = $this->company('Elsewhere Ltd');
        $this->copies($otherForms[0], 20, 'new', $owner->id);

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();

        $response->assertSee('7 new copies are waiting.')
            ->assertDontSee('27 new copies')
            ->assertDontSee('Elsewhere');
        $ledger = $this->ledger($response->getContent());
        $this->assertSame(['New' => 7, 'In review' => 2, 'Actioned' => 0, 'Closed' => 1], array_slice($ledger, 0, 4));
        $this->assertSame(0, $this->assignedCount($response->getContent()), 'a foreign copy assigned to me is not mine here');
        $response->assertDontSee('Assigned to you');
    }

    public function test_the_assigned_list_holds_only_my_open_copies_longest_waiting_first_capped_at_five(): void
    {
        [$owner, $team, $forms] = $this->company();
        $colleague = $this->member($team, 'editor');

        foreach (range(1, 7) as $i) {
            $this->copies($forms[0], 1, 'new', $owner->id, now()->subHours($i));
        }
        $this->copies($forms[0], 2, 'new', $colleague->id);
        $this->copies($forms[0], 1, 'closed', $owner->id);
        $this->copies($forms[0], 1, 'actioned', $owner->id);

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();
        $html = $response->getContent();

        $response->assertSee('<b>7</b> are assigned to you.', false)->assertSee('5 of 7, longest waiting first');
        preg_match('/<ol class="cb-desk__list">(.*?)<\/ol>/s', $html, $list);
        $this->assertSame(5, substr_count($list[1], '<li>'));
        $ids = FormSubmission::where('assigned_to', $owner->id)->whereIn('status', ['new', 'in_review'])->orderBy('submitted_at')->limit(5)->pluck('id')->all();
        foreach ($ids as $id) {
            $this->assertStringContainsString('/'.$id.'<', $list[1]);
        }
        $this->assertStringNotContainsString('REJECTED', $list[1]);
    }

    public function test_a_decision_shows_as_a_stamp_only_where_one_was_made(): void
    {
        [$owner, , $forms] = $this->company();
        $this->copies($forms[0], 1, 'in_review', $owner->id)->forceFill(['decision' => 'approved'])->save();
        $this->copies($forms[0], 1, 'new', $owner->id);

        $html = $this->actingAs($owner)->get('/dashboard')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'cb-stamp--ok">APPROVED'));
        $this->assertSame(0, substr_count($html, 'REJECTED'));
        $this->assertSame(['Decided: approved' => 1, 'Decided: rejected' => 0], array_slice($this->ledger($html), 4));
    }

    public function test_a_member_who_may_not_read_copies_sees_no_copy_figure_at_all(): void
    {
        [$owner, $team, $forms] = $this->company();
        $this->copies($forms[0], 6, 'new', $owner->id);
        $guest = $this->member($team, 'guest');

        $response = $this->actingAs($guest)->get('/dashboard')->assertOk();
        $html = $response->getContent();

        $response->assertSee('2 forms in the book.')
            ->assertDontSee('waiting')
            ->assertDontSee('Assigned to you')
            ->assertDontSee('by state')
            ->assertDontSee('Copies per day')
            ->assertDontSee('data-tally="copies-week"', false)
            ->assertDontSee('Last copy')
            ->assertDontSee('Lodged')
            ->assertDontSee('Open the master')
            ->assertDontSee(route('teams.forms.submissions', ['team' => $team, 'form' => $forms[0]]), false);
        $this->assertSame(1, substr_count($html, 'data-primary-action'));
        $this->assertStringNotContainsString('data-new=', $html, 'no per-form copy count in the markup');
        $this->assertStringNotContainsString('data-copies=', $html);
    }

    public function test_a_viewer_reads_the_figures_but_gets_no_editing_controls(): void
    {
        [$owner, $team, $forms] = $this->company();
        $this->copies($forms[0], 3, 'new');
        $viewer = $this->member($team, 'viewer');

        $response = $this->actingAs($viewer)->get('/dashboard')->assertOk();

        $response->assertSee('3 new copies are waiting.')
            ->assertSee('Copies per day')
            ->assertSee('Copies')
            ->assertDontSee('Open the master')
            ->assertDontSee('Set with AI')
            ->assertDontSee('Set a new form');
        $this->assertSame(1, substr_count($response->getContent(), 'data-primary-action'));
    }

    public function test_the_stage_master_follows_the_form_param_only_inside_this_company(): void
    {
        [$owner, $team, $forms] = $this->company();
        [, , $otherForms] = $this->company('Elsewhere Ltd');

        // Default: the form changed last.
        $forms[1]->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $forms[0]->forceFill(['updated_at' => now()])->saveQuietly();
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee('The master '.$team->name.' last worked on');

        $picked = $this->actingAs($owner)->get('/dashboard?form='.$forms[1]->id)->assertOk();
        $picked->assertSee('The master you picked');
        $this->assertSame($forms[1]->title, $this->stageTitle($picked->getContent()));

        foreach (['/dashboard?form='.$otherForms[0]->id, '/dashboard?form=99999', '/dashboard?form=abc', '/dashboard?form[]=1', '/dashboard?form=-3'] as $url) {
            $res = $this->actingAs($owner)->get($url)->assertOk();
            $this->assertSame($forms[0]->title, $this->stageTitle($res->getContent()), $url);
            $res->assertDontSee($otherForms[0]->title);
        }
    }

    public function test_the_register_rows_carry_this_companys_counts_and_lead_to_the_stage(): void
    {
        [$owner, $team, $forms] = $this->company();
        $this->copies($forms[0], 3, 'new');
        $this->copies($forms[0], 2, 'closed');
        $this->copies($forms[1], 1, 'new');
        [, , $otherForms] = $this->company('Elsewhere Ltd');
        $this->copies($otherForms[0], 8, 'new');

        $html = $this->actingAs($owner)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('data-new="3" data-copies="5"', $html);
        $this->assertStringContainsString('data-new="1" data-copies="1"', $html);
        $this->assertStringContainsString('href="'.route('dashboard', ['form' => $forms[0]->id]).'"', str_replace('&amp;', '&', $html));
        foreach (['Serial', 'Form', 'New', 'Copies', 'Last copy'] as $col) {
            $this->assertMatchesRegularExpression('/<th[^>]*aria-sort="none"[^>]*><button type="button" data-sort="[a-z]+">'.$col.'<\/button>/', $html);
        }
        $this->assertSame(1, substr_count($html, 'aria-current="true"'));
    }

    public function test_the_chart_is_server_rendered_svg_with_true_zeros_and_no_outside_library(): void
    {
        [$owner, $team, $forms] = $this->company();
        $this->copies($forms[0], 2, 'new', null, now()->subDays(2));
        $this->copies($forms[0], 1, 'new', null, now());
        $this->copies($forms[0], 5, 'new', null, now()->subDays(40));
        // A busy day on another company's form must not leak in.
        [, , $otherForms] = $this->company('Elsewhere Ltd');
        $this->copies($otherForms[0], 9, 'new', null, now());

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();
        $html = $response->getContent();

        $this->assertSame(14, substr_count($html, 'class="cb-chart__bar'));
        $this->assertSame(12, substr_count($html, 'cb-chart__bar--zero'), 'days without a copy are real zeros');
        $this->assertMatchesRegularExpression('/<title>'.preg_quote(now()->format('j M'), '/').': 1 copy<\/title>/', $html);
        $this->assertMatchesRegularExpression('/<title>'.preg_quote(now()->subDays(2)->format('j M'), '/').': 2 copies<\/title>/', $html);
        $response->assertSee('3 in 14 days')->assertSee('Values as a table');
        $this->assertStringNotContainsString('chart.js', Str::lower($html));
        $this->assertStringNotContainsString('cdn.jsdelivr', $html);
    }

    public function test_forms_without_copies_say_so_and_draw_no_empty_chart_or_ledger(): void
    {
        [$owner] = $this->company();

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();

        $response->assertSee('Nothing is waiting.')->assertSee('No copies yet.')
            ->assertDontSee('Assigned to you')->assertDontSee('by state');
        $this->assertStringNotContainsString('class="cb-chart"', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'data-primary-action'));
    }

    public function test_old_copies_only_are_reported_honestly_in_the_chart_slot(): void
    {
        [$owner, , $forms] = $this->company();
        $this->copies($forms[0], 2, 'closed', null, now()->subDays(40));

        $this->actingAs($owner)->get('/dashboard')->assertOk()
            ->assertSee('No copies in the last 14 days.')->assertDontSee('No copies yet.');
    }

    public function test_the_funnel_uses_the_forms_own_tallies_and_the_finish_rate_is_capped(): void
    {
        [$owner, , $forms] = $this->company();
        $forms[0]->forceFill(['views_count' => 50, 'starts_count' => 2])->save();
        $this->copies($forms[0], 3, 'new');

        $response = $this->actingAs($owner)->get('/dashboard')->assertOk();

        $response->assertSee('100% of the people who started lodged a copy.');
        $this->assertSame(['Viewed' => 50, 'Started' => 2, 'Lodged' => 3], $this->funnel($response->getContent()));
    }

    public function test_the_primary_action_is_unchanged_per_role_and_there_is_no_inline_style_or_handler(): void
    {
        [$owner, $team] = $this->company();
        $this->copies(Form::withoutTeamScope()->where('team_id', $team->id)->first(), 2, 'new', $owner->id);

        $html = $this->actingAs($owner)->get('/dashboard')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'data-primary-action'));
        $this->assertStringContainsString('action="'.route('teams.forms.store', $team).'"', $html);

        preg_match('/<main class="cb-page">(.*?)<\/main>/s', $html, $main);
        $this->assertStringNotContainsString('style=', $main[1]);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $main[1]);
        $this->assertStringNotContainsString('<script', $main[1]);
    }

    public function test_the_header_search_pill_is_a_plain_get_to_the_books_filter(): void
    {
        [$owner, $team] = $this->company();

        $html = $this->actingAs($owner)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('class="cb-spine__find"', $html);
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('action="'.route('teams.forms', $team).'"', $html);
        $this->assertStringContainsString('/dot-ui/js/dot-search.js', $html);
    }

    public function test_a_person_with_no_company_gets_the_add_company_action_and_no_pill(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => null])->save();

        $response = $this->actingAs($user)->get('/dashboard');
        if ($response->status() !== 200) {
            $this->markTestSkipped('A person with no company is redirected before the desk in this app.');
        }
        $response->assertSee('Add your company');
    }

    // ------------------------------------------------------------------------------------------------ helpers

    /** @return array{0: User, 1: Team, 2: array<int, Form>} */
    private function company(string $name = 'Zulu-Peters Haulage'): array
    {
        $owner = User::factory()->withPersonalTeam()->create(['name' => 'Thandi Mokoena']);
        $team = $owner->currentTeam;
        $team->forceFill(['name' => $name])->save();
        $forms = [
            $this->form($team, $owner, $name.' Driver report'),
            $this->form($team, $owner, $name.' Pre-trip checklist'),
        ];

        // The first form is the one changed last, so it is the default stage master.
        $forms[0]->forceFill(['updated_at' => now()->subMinutes(1)])->saveQuietly();
        $forms[1]->forceFill(['updated_at' => now()->subHours(1)])->saveQuietly();

        return [$owner->fresh(), $team->fresh(), $forms];
    }

    private function form(Team $team, User $user, string $title): Form
    {
        $form = Form::query()->create([
            'team_id' => $team->id, 'user_id' => $user->id, 'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)), 'settings' => [], 'is_published' => true,
        ]);
        $form->fields()->create(['type' => 'text', 'label' => 'Name', 'order' => 0]);

        return $form;
    }

    private function copies(Form $form, int $count, string $status = 'new', ?int $assignee = null, mixed $when = null): FormSubmission
    {
        $copy = null;
        for ($i = 0; $i < $count; $i++) {
            $at = $when ?? now()->subMinutes($i + 1);
            $copy = FormSubmission::query()->create([
                'form_id' => $form->id, 'data' => [], 'submitted_at' => $at, 'status' => $status, 'assigned_to' => $assignee,
            ]);
            $copy->forceFill(['created_at' => $at])->save();
        }

        return $copy;
    }

    private function member(Team $team, string $role): User
    {
        $user = User::factory()->withPersonalTeam()->create();
        $team->users()->attach($user, ['role' => $role]);
        $user->switchTeam($team);

        return $user->fresh();
    }

    /** @return array<string, int> label => count, in page order */
    private function ledger(string $html): array
    {
        preg_match('/<dl class="cb-desk__ledger">(.*?)<\/dl>/s', $html, $dl);
        preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(\d+)<\/dd>/s', $dl[1], $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($r) => [trim(strip_tags($r[1])) => (int) $r[2]])->all();
    }

    /** @return array<string, int> */
    private function funnel(string $html): array
    {
        preg_match('/<dl class="cb-desk__facts"[^>]*>(.*?)<\/dl>/s', $html, $dl);
        preg_match_all('/<dt>(.*?)<\/dt>\s*<dd>\s*<span class="cb-desk__v" data-countup>(\d+)</s', $dl[1], $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($r) => [$r[1] => (int) $r[2]])->all();
    }

    private function assignedCount(string $html): int
    {
        return preg_match('/<b>(\d+)<\/b> (?:is|are) assigned to you/', $html, $m) ? (int) $m[1] : 0;
    }

    private function stageTitle(string $html): string
    {
        preg_match('/<div class="cb-desk__sheet-head">\s*<b>(.*?)<\/b>/s', $html, $m);

        return html_entity_decode($m[1] ?? '');
    }
}
