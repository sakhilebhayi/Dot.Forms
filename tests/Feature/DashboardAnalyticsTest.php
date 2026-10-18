<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Analytics;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage confirming App\Livewire\Dashboard\Analytics::mount()
 * already guards Auth::user()->currentTeam being null (a user with no
 * current team, e.g. removed from their last team) rather than fatally
 * dereferencing it -- the same class of bug fixed in Dot.Mines'
 * Dashboard::loadDashboardData() (commit 0cc4362), confirmed here to
 * already be handled via abort_unless($team, 403).
 */
class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_with_no_current_team_gets_403_not_a_crash(): void
    {
        $user = User::factory()->create(['current_team_id' => null]);

        $this->actingAs($user);

        Livewire::test(Analytics::class)
            ->assertForbidden();
    }

    public function test_analytics_page_renders_for_an_authenticated_team_member(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $form = Form::query()->create([
            'team_id' => $user->currentTeam->id,
            'user_id' => $user->id,
            'title' => 'Customer Survey',
            'slug' => 'customer-survey',
            'settings' => [],
            'is_published' => true,
            'views_count' => 4,
        ]);

        $form->submissions()->create([
            'user_id' => null,
            'data' => [],
            'submitted_at' => now(),
            'completion_seconds' => 30,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.analytics'))
            ->assertOk()
            ->assertSee('Team Analytics')
            ->assertSee('Total Submissions')
            ->assertSee('Completion Rate')
            ->assertSee('Avg Time To Complete')
            ->assertSee('Customer Survey', false)
            ->assertSee('submission-chart-data', false);
    }

    public function test_analytics_page_is_a_single_root_livewire_view(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user);

        Livewire::test(Analytics::class)
            ->assertOk()
            ->assertSee('Team Analytics')
            ->assertSet('stats.total_submissions', 0);
    }

    public function test_analytics_page_requires_login(): void
    {
        $this->get(route('dashboard.analytics'))->assertRedirect();
    }
}
