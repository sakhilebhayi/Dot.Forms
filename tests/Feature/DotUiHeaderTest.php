<?php

namespace Tests\Feature;

use App\Livewire\Forms\Index;
use App\Models\Form;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-51: Dot.Forms takes the shared Dot UI. The signed-in spine carries the "Dot Apps" switcher; the in-page filters
 * (the book, the copies) are the shared compact filter. The signed-in spine carries the shared search pill, a plain GET to the book's own filter. Public form
 * pages (outside the signed-in area) get neither.
 */
class DotUiHeaderTest extends TestCase
{
    use RefreshDatabase;

    private const ACME = '11111111-1111-4111-8111-111111111111';

    private const GLOBEX = '22222222-2222-4222-8222-222222222222';

    private string $credential;

    protected function setUp(): void
    {
        parent::setUp();

        $this->credential = Str::random(40);

        // The entitlement check has its own tests; here only the switcher reads InfoDot.
        config(['ecosystem.infodot' => [
            'redeem_url' => 'https://infodot.test/api/ecosystem/redeem', 'platform' => 'forms', 'secret' => $this->credential,
            'timeout' => 2, 'provision' => true, 'entitlement_check' => false,
        ]]);
        config(['ecosystem.platforms.infodot.url' => 'https://infodot.test']);
    }

    private function body(string $uuid, array $rows, bool $personal = false): array
    {
        return ['platform' => 'forms', 'company' => ['uuid' => $uuid, 'personal' => $personal], 'platforms' => $rows];
    }

    private function rows(): array
    {
        return [
            ['key' => 'infodot', 'name' => 'InfoDot', 'status' => 'included', 'entitled' => true, 'is_core' => true],
            ['key' => 'forms', 'name' => 'Dot.Forms', 'status' => 'included', 'entitled' => true, 'is_core' => false],
            ['key' => 'finance', 'name' => 'Dot.Finance', 'status' => 'included', 'entitled' => true, 'is_core' => true],
            ['key' => 'hr', 'name' => 'Dot.HR', 'status' => 'not_activated', 'entitled' => false, 'is_core' => false],
        ];
    }

    private function fake(array $stubs): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($stubs);
        Cache::flush();
    }

    /** @return array{0: User, 1: Team} */
    private function company(string $uuid = self::ACME, string $name = 'Acme Ltd'): array
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false, 'infodot_team_uuid' => $uuid]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        return [$owner->fresh(), $team];
    }

    private function readUrl(string $uuid): string
    {
        return 'https://infodot.test/api/ecosystem/companies/'.$uuid.'/platforms*';
    }

    public function test_the_switcher_shows_for_a_member_with_go_links_and_marks_the_current_platform(): void
    {
        [$owner, $team] = $this->company();
        $this->fake([$this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $this->rows()))]);

        $html = $this->actingAs($owner)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-dsw', $html);
        $this->assertStringContainsString('https://infodot.test/go/finance?company='.self::ACME, $html);
        $this->assertStringContainsString('aria-current="page"', $html, 'this platform is marked, not linked');
        $this->assertStringNotContainsString('/go/forms?', $html);
        $this->assertStringContainsString('/dot-ui/img/platforms/', $html);
        $this->assertStringNotContainsString($this->credential, $html);
        $this->assertStringNotContainsString('token=', $html);
        $this->assertStringNotContainsString('Authorization', $html);
    }

    public function test_the_switcher_is_on_the_book_the_copies_and_the_tallies_too(): void
    {
        [$owner, $team] = $this->company();
        $form = Form::withoutTeamScope()->create(['team_id' => $team->id, 'user_id' => $owner->id, 'title' => 'Induction', 'slug' => 'induction-x', 'is_published' => true]);
        $this->fake([$this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $this->rows()))]);

        foreach ([route('teams.forms', $team), route('teams.forms.submissions', [$team, $form]), route('dashboard.analytics')] as $url) {
            $this->actingAs($owner)->get($url)->assertOk()->assertSee('data-dsw', false);
        }
    }

    public function test_the_book_keeps_its_search_logic_and_uses_the_shared_filter(): void
    {
        [$owner, $team] = $this->company();
        $this->fake([$this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $this->rows()))]);
        Form::withoutTeamScope()->create(['team_id' => $team->id, 'user_id' => $owner->id, 'title' => 'Alpha induction', 'slug' => 'alpha-x', 'is_published' => true]);
        Form::withoutTeamScope()->create(['team_id' => $team->id, 'user_id' => $owner->id, 'title' => 'Beta survey', 'slug' => 'beta-x', 'is_published' => true]);

        $html = $this->actingAs($owner)->get(route('teams.forms', $team))->assertOk()->getContent();
        $this->assertStringContainsString('class="dsr-filter"', $html);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $html);
        // R-51 desk: the spine's pill only GETs the book's filter (?q=); the filter's own wiring is untouched above.
        $this->assertSame(1, substr_count($html, 'class="dsr-input"'), 'one pill, in the spine');
        $this->assertStringContainsString('action="'.route('teams.forms', $team).'"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*name="q"[^>]*class="dsr-input"|<input[^>]*class="dsr-input"[^>]*name="q"/', $html);

        $this->actingAs($owner);
        Livewire::test(Index::class, ['team' => $team])
            ->set('search', 'Alpha')
            ->assertSee('Alpha induction')
            ->assertDontSee('Beta survey');
    }

    public function test_the_book_search_never_finds_another_companys_forms(): void
    {
        [$owner, $team] = $this->company();
        [$other, $otherTeam] = $this->company(self::GLOBEX, 'Globex');
        $this->fake([$this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $this->rows()))]);
        Form::withoutTeamScope()->create(['team_id' => $otherTeam->id, 'user_id' => $other->id, 'title' => 'Globex secret', 'slug' => 'globex-x', 'is_published' => true]);

        $this->actingAs($owner);
        Livewire::test(Index::class, ['team' => $team])->set('search', 'Globex')->assertDontSee('Globex secret');
    }

    public function test_two_companies_never_see_each_others_switcher(): void
    {
        [$ann, $acme] = $this->company(self::ACME, 'Acme Ltd');
        [$bob, $globex] = $this->company(self::GLOBEX, 'Globex');
        $acmeRows = array_merge($this->rows(), [['key' => 'sheet', 'name' => 'Dot.Sheet', 'status' => 'included', 'entitled' => true]]);
        $this->fake([
            $this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $acmeRows)),
            $this->readUrl(self::GLOBEX) => Http::response($this->body(self::GLOBEX, $this->rows())),
        ]);

        $a = $this->actingAs($ann)->get(route('dashboard'))->getContent();
        $b = $this->actingAs($bob)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('/go/sheet?company='.self::ACME, $a);
        $this->assertStringNotContainsString('/go/sheet?company='.self::GLOBEX, $b);
        $this->assertStringNotContainsString(self::GLOBEX, $a);
        $this->assertStringNotContainsString(self::ACME, $b);
    }

    public function test_it_degrades_on_a_503_and_never_says_active(): void
    {
        [$owner] = $this->company();
        $this->fake([$this->readUrl(self::ACME) => Http::response('down', 503)]);

        $res = $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $res->assertSee('Could not check your platforms');
        $this->assertStringNotContainsString('Active', $res->getContent());
        $this->assertStringContainsString('/go/finance?company='.self::ACME, $res->getContent(), 'core tiles still go through InfoDot, which decides');
    }

    public function test_it_degrades_when_infodot_cannot_be_reached(): void
    {
        [$owner] = $this->company();
        $this->fake([$this->readUrl(self::ACME) => fn () => throw new ConnectionException('timed out')]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Could not check your platforms');
    }

    public function test_a_personal_workspace_asks_infodot_nothing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->fake(['*' => Http::response([], 500)]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-dsw', false);

        Http::assertNothingSent();
    }

    public function test_the_request_carries_the_secret_server_side_only(): void
    {
        [$owner] = $this->company();
        $this->fake([$this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $this->rows()))]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk();

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.$this->credential) && $r->hasHeader('X-Dot-Platform', 'forms'));
    }

    public function test_assets_come_from_the_apps_own_origin_with_no_inline_style_or_script(): void
    {
        [$owner] = $this->company();
        $this->fake([$this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $this->rows()))]);

        $html = $this->actingAs($owner)->get(route('dashboard'))->getContent();

        foreach (['dot-fonts', 'dot-tokens', 'dot-search', 'dot-switcher'] as $css) {
            $this->assertFileExists(public_path('dot-ui/css/'.$css.'.css'));
            $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*/dot-ui/css/'.$css.'\.css">#', $html);
        }
        $this->assertFileExists(public_path('dot-ui/js/dot-switcher.js'));
        $this->assertStringContainsString('/dot-ui/js/dot-switcher.js', $html);

        // The shared markup adds no style="" attribute and no inline script (the layout's own x-cloak rule predates it).
        $start = strpos($html, 'data-dsw');
        $end = strpos($html, '</header>');
        $switcher = substr($html, $start, $end - $start);
        $this->assertStringNotContainsString('style=', $switcher);
        $this->assertStringNotContainsString('<script', $switcher);
        $this->assertStringNotContainsString('<style', $switcher);
    }

    public function test_the_interim_way_back_goes_where_the_switcher_replaces_it_and_stays_where_it_does_not(): void
    {
        [$owner] = $this->company();
        $this->fake([$this->readUrl(self::ACME) => Http::response($this->body(self::ACME, $this->rows()))]);

        $res = $this->actingAs($owner)->get(route('dashboard'))->assertOk();
        $res->assertDontSee('cb-land__back', false);
        $this->assertStringContainsString('https://infodot.test/home', $res->getContent(), 'the switcher still leads to InfoDot');
        $this->assertStringContainsString('https://infodot.test/platforms', $res->getContent());

        $personal = User::factory()->withPersonalTeam()->create();
        $this->actingAs($personal)->get(route('dashboard'))->assertOk()->assertSee('cb-land__back', false);
    }

    public function test_the_public_form_page_gets_neither_switcher_nor_search(): void
    {
        [$owner, $team] = $this->company();
        $this->fake(['*' => Http::response([], 500)]);
        $form = Form::withoutTeamScope()->create(['team_id' => $team->id, 'user_id' => $owner->id, 'title' => 'Public one', 'slug' => 'public-one', 'is_published' => true]);

        $html = $this->get(route('forms.public', $form->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-dsw', $html);
        $this->assertStringNotContainsString('dsr', $html);
        $this->assertStringNotContainsString('dot-ui', $html);
        Http::assertNothingSent();
    }

    public function test_the_sign_in_page_gets_neither(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-dsw', $html);
        $this->assertStringNotContainsString('dot-ui', $html);
    }
}
