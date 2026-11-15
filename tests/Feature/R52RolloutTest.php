<?php

namespace Tests\Feature;

use App\Livewire\Forms\HeaderSearch;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-52: Roboto, the shared header, the search on every page, the two JavaScript defects (builder copy link, Tallies charts),
 * and the public form as a phone page.
 */
class R52RolloutTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Team, 2: Form} */
    private function company(string $name = 'Acme Ltd', string $title = 'Driver incident report'): array
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $form = Form::withoutTeamScope()->create([
            'team_id' => $team->id, 'user_id' => $owner->id, 'title' => $title, 'slug' => str($title)->slug().'-'.str()->random(4),
            'is_published' => true, 'description' => 'Report an incident on the road.',
        ]);

        return [$owner->fresh(), $team, $form];
    }

    // ---- the header ----

    public function test_the_shared_header_replaces_the_spine_on_every_signed_in_page(): void
    {
        [$owner, $team, $form] = $this->company();

        foreach ([
            route('dashboard'), route('dashboard.analytics'), route('teams.forms', $team), route('teams.forms.builder', [$team, $form]),
            route('teams.forms.submissions', [$team, $form]), route('teams.forms.templates', $team), route('profile.show'),
        ] as $url) {
            $html = $this->actingAs($owner)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('<header', $html);
            $this->assertStringContainsString('data-dh', $html, $url);
            $this->assertStringNotContainsString('cb-spine', $html, $url);
            foreach (['The desk', 'The book', 'Tallies', 'Master templates', 'Set with AI', 'Company settings', 'Sign out'] as $label) {
                $this->assertStringContainsString($label, $html, "$label on $url");
            }
            // the mobile menu: a button that opens the drawer, and the drawer itself
            $this->assertStringContainsString('data-dh-menu-button', $html, $url);
            $this->assertStringContainsString('id="dh-drawer"', $html, $url);
            $this->assertStringContainsString('/dot-ui/js/dot-nav.js', $html, $url);
        }
    }

    public function test_the_header_adds_no_inline_style_or_script(): void
    {
        [$owner] = $this->company();

        $html = $this->actingAs($owner)->get(route('dashboard'))->assertOk()->getContent();
        $start = strpos($html, '<header');
        $end = strpos($html, '<main');
        $this->assertGreaterThan($start, $end);
        $header = substr($html, $start, $end - $start);

        $this->assertStringNotContainsString('style=', $header);
        $this->assertStringNotContainsString('<script', $header);
        $this->assertStringNotContainsString('<style', $header);
        $this->assertStringNotContainsString('onclick=', $header);
    }

    public function test_a_person_who_cannot_create_forms_does_not_get_the_set_a_form_group(): void
    {
        [$owner, $team] = $this->company();
        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'viewer']);
        $member->forceFill(['current_team_id' => $team->id])->save();

        $html = $this->actingAs($member->fresh())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Master templates', $html);
        $this->assertStringContainsString('The book', $html);
    }

    // ---- search on every page, scoped to the company ----

    public function test_the_search_is_present_on_every_signed_in_page_type(): void
    {
        [$owner, $team, $form] = $this->company();

        foreach ([
            route('dashboard'), route('dashboard.analytics'), route('teams.forms', $team), route('teams.forms.builder', [$team, $form]),
            route('teams.forms.submissions', [$team, $form]), route('teams.forms.templates', $team), route('teams.forms.ai-builder', $team),
            route('profile.show'), route('teams.show', $team),
        ] as $url) {
            $html = $this->actingAs($owner)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-dh-search', $html, $url);
            $this->assertStringContainsString('role="search"', $html, $url);
            $this->assertStringContainsString('name="q"', $html, $url);
            $this->assertStringContainsString('action="'.route('teams.forms', $team).'"', $html, "$url keeps the book's GET filter");
        }
    }

    public function test_the_search_finds_this_companys_forms_only(): void
    {
        [$owner, $team, $form] = $this->company('Acme Ltd', 'Driver incident report');
        [$other, $otherTeam] = $this->company('Globex', 'Driver secret manifest');

        $this->actingAs($owner);
        Livewire::test(HeaderSearch::class)
            ->set('query', 'driver')
            ->assertSee('Driver incident report')
            ->assertDontSee('Driver secret manifest');

        $this->actingAs($other->fresh());
        Livewire::test(HeaderSearch::class)
            ->set('query', 'driver')
            ->assertSee('Driver secret manifest')
            ->assertDontSee('Driver incident report');
    }

    public function test_the_search_matches_the_description_too_and_escapes_wildcards(): void
    {
        [$owner] = $this->company();

        $this->actingAs($owner);
        Livewire::test(HeaderSearch::class)->set('query', 'on the road')->assertSee('Driver incident report');
        // a literal percent sign or underscore matches nothing, it is not a wildcard
        Livewire::test(HeaderSearch::class)->set('query', '%%')->assertDontSee('Driver incident report')->assertSee('No form matches');
        Livewire::test(HeaderSearch::class)->set('query', 'dr_ver')->assertDontSee('Driver incident report');
    }

    public function test_one_character_and_no_match_are_empty_states(): void
    {
        [$owner] = $this->company();

        $this->actingAs($owner);
        Livewire::test(HeaderSearch::class)->set('query', 'd')->assertSee('Type two letters')->assertDontSee('No form matches');
        Livewire::test(HeaderSearch::class)->set('query', 'zzzz')->assertSee('No form matches');
    }

    public function test_the_empty_box_offers_recent_forms_of_this_company_only(): void
    {
        [$owner] = $this->company('Acme Ltd', 'Driver incident report');
        $this->company('Globex', 'Globex payroll');

        $this->actingAs($owner);
        Livewire::test(HeaderSearch::class)->assertSee('Recent forms')->assertSee('Driver incident report')->assertDontSee('Globex payroll');
    }

    public function test_a_row_leads_a_viewer_to_the_copies_and_a_member_without_rights_to_the_book(): void
    {
        [$owner, $team, $form] = $this->company();
        $viewer = User::factory()->create();
        $team->users()->attach($viewer, ['role' => 'viewer']);
        $viewer->forceFill(['current_team_id' => $team->id])->save();
        $nobody = User::factory()->create();
        $team->users()->attach($nobody, ['role' => 'guest']);
        $nobody->forceFill(['current_team_id' => $team->id])->save();

        $this->actingAs($viewer->fresh());
        Livewire::test(HeaderSearch::class)->set('query', 'driver')
            ->assertSeeHtml('href="'.route('teams.forms.submissions', [$team, $form]).'"');

        $this->actingAs($nobody->fresh());
        $lw = Livewire::test(HeaderSearch::class)->set('query', 'driver');
        $lw->assertSee('Driver incident report');
        $this->assertStringNotContainsString('/submissions', $lw->html());
        $this->assertStringNotContainsString('/builder', $lw->html());
    }

    public function test_enter_goes_to_the_first_match_else_the_books_own_filter(): void
    {
        [$owner, $team, $form] = $this->company();

        $this->actingAs($owner);
        Livewire::test(HeaderSearch::class)->set('query', 'driver')->call('go')
            ->assertRedirect(route('teams.forms.submissions', [$team, $form]));
        Livewire::test(HeaderSearch::class)->set('query', 'nothing here')->call('go')
            ->assertRedirect(route('teams.forms', ['team' => $team, 'q' => 'nothing here']));
    }

    public function test_a_person_with_no_company_gets_the_pill_and_a_plain_line_not_a_missing_control(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['current_team_id' => null])->save();

        $this->actingAs($user);
        Livewire::test(HeaderSearch::class)
            ->assertSee('Find a form')
            ->assertSee('Add your company')
            ->set('query', 'anything')
            ->assertSee('Add your company')
            ->call('go')
            ->assertNoRedirect();
    }

    public function test_search_is_not_a_way_into_another_companys_book(): void
    {
        [$owner, $team] = $this->company('Acme Ltd');
        [$other, $otherTeam] = $this->company('Globex');

        // the pill follows the viewer's own current company; a forged team id is not read from the request
        $html = $this->actingAs($owner)->get(route('dashboard'))->getContent();
        $this->assertStringNotContainsString('action="'.route('teams.forms', $otherTeam).'"', $html);
    }

    // ---- Roboto, self-hosted ----

    public function test_roboto_is_declared_and_its_files_are_served_from_the_apps_origin(): void
    {
        $css = file_get_contents(public_path('dot-ui/css/dot-roboto.css'));
        $this->assertStringContainsString("font-family: 'Roboto'", $css);
        $this->assertFileExists(public_path('dot-ui/fonts/roboto/roboto-latin-wght-normal.woff2'));
        $this->assertFileExists(public_path('dot-ui/fonts/roboto/roboto-latin-ext-wght-normal.woff2'));
        $this->assertFileExists(public_path('dot-ui/fonts/roboto/OFL.txt'));
        $this->assertDoesNotMatchRegularExpression('#https?://#', $css);
    }

    public function test_no_page_names_an_outside_font_or_chart_host(): void
    {
        [$owner, $team, $form] = $this->company();

        $pages = [
            $this->actingAs($owner)->get(route('dashboard'))->getContent(),
            $this->actingAs($owner)->get(route('dashboard.analytics'))->getContent(),
            $this->actingAs($owner)->get(route('teams.forms.builder', [$team, $form]))->getContent(),
            $this->get(route('forms.public', $form->slug))->getContent(),
            $this->actingAs($owner)->get('/')->getContent(),
            $this->post('/logout') && $this->get(route('login'))->getContent(),
        ];

        foreach (array_filter($pages, 'is_string') as $html) {
            foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.jsdelivr.net', 'unpkg.com', 'cdnjs.cloudflare.com'] as $host) {
                $this->assertStringNotContainsString($host, $html);
            }
            $this->assertMatchesRegularExpression('/<html[^>]*class="[^"]*dot-roboto/', $html);
            $this->assertStringContainsString('/dot-ui/css/dot-roboto.css', $html);
        }
    }

    public function test_no_stylesheet_or_view_still_names_the_old_faces(): void
    {
        $files = array_merge(
            glob(resource_path('css/*.css')) ?: [],
            collect(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)))
                ->filter(fn ($f) => str_ends_with($f->getPathname(), '.php') && ! str_contains($f->getPathname(), '/vendor/'))
                ->map(fn ($f) => $f->getPathname())->all(),
        );

        foreach ($files as $file) {
            $body = file_get_contents($file);
            foreach (['Archivo', 'IBM Plex', 'Fraunces', "'Inter'", '"Inter"', "'Sora'", 'Figtree', 'Material+Symbols', 'material-symbols'] as $face) {
                $this->assertStringNotContainsString($face, $body, "$face in $file");
            }
        }
    }

    // ---- the two JavaScript defects ----

    public function test_the_builder_copy_link_is_a_method_not_a_try_block_in_the_click_attribute(): void
    {
        $source = file_get_contents(resource_path('views/livewire/forms/builder.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/@click="\s*try\b/', $source);
        $this->assertStringContainsString('copyLink(', $source);

        [$owner, $team, $form] = $this->company();
        $html = $this->actingAs($owner)->get(route('teams.forms.builder', [$team, $form]))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/@click="\s*try\b|x-on:click="\s*try\b/', $html);
        $this->assertStringContainsString('copyLink(', $html);
    }

    public function test_the_tallies_chart_data_is_parseable_json_not_html_escaped_text(): void
    {
        [$owner, $team, $form] = $this->company();
        FormSubmissionFactoryHelper::lodge($form, 3);

        $html = $this->actingAs($owner)->get(route('dashboard.analytics'))->assertOk()->getContent();

        preg_match_all('/data-chart=\'([^\']+)\'/', $html, $m);
        $this->assertCount(2, $m[1], 'one payload per canvas');
        foreach ($m[1] as $raw) {
            $payload = json_decode(html_entity_decode($raw), true);
            $this->assertIsArray($payload, 'the attribute decodes as JSON: '.$raw);
            $this->assertArrayHasKey('labels', $payload);
            $this->assertArrayHasKey('data', $payload);
        }
        $this->assertStringNotContainsString('&quot;labels&quot;', $html, 'the JSON is not HTML-escaped into text');
        $this->assertStringNotContainsString('<script type="application/json"', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
    }

    public function test_chart_js_is_bundled_with_the_app(): void
    {
        $this->assertStringContainsString('"chart.js"', file_get_contents(base_path('package.json')));
        $this->assertStringContainsString("import('chart.js')", file_get_contents(resource_path('js/tallies.js')));
    }

    // ---- the public form ----

    public function test_the_public_form_page_is_a_phone_page(): void
    {
        [$owner, $team, $form] = $this->company();

        $html = $this->get(route('forms.public', $form->slug))->assertOk()->getContent();

        $this->assertStringContainsString('width=device-width, initial-scale=1', $html);
        $this->assertStringNotContainsString('user-scalable=no', $html);

        $css = file_get_contents(resource_path('css/carbon.css'));
        $this->assertMatchesRegularExpression('/\.cb-q input\[type="text"\][^}]*font-size: 16px/s', $css, '16px controls so iOS never zooms');
        $this->assertStringContainsString('100dvh', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom)', $css);
    }

    public function test_a_respondent_reads_the_questions_name_in_an_error_not_a_property_path(): void
    {
        [$owner, $team, $form] = $this->company();
        $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'order' => 0, 'validation_rules' => ['required']]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subMinute()->timestamp)
            ->call('submit')
            ->assertSee('Please answer “Your name”.')
            ->assertDontSee('The answers.');
    }
}

/** Throwaway helper: a few lodged copies so the Tallies page has data. */
final class FormSubmissionFactoryHelper
{
    public static function lodge(Form $form, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $form->submissions()->create(['data' => ['x' => $i], 'submitted_at' => now()->subDays($i)]);
        }
    }
}
