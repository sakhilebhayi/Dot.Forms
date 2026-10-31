<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Models\Form;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The production host has no websocket server. Broadcasting must default to
 * the null driver, and saving the builder must not queue a broadcast job that
 * would only be thrown away (every save is a write on the one SQLite file).
 */
class BroadcastingWithoutWebsocketTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_broadcaster_and_the_env_example_are_null(): void
    {
        $this->assertStringContainsString(
            "env('BROADCAST_CONNECTION', 'null')",
            file_get_contents(config_path('broadcasting.php')),
        );

        $example = file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^BROADCAST_CONNECTION=null$/m', $example);
        $this->assertDoesNotMatchRegularExpression('/^VITE_REVERB_APP_KEY=/m', $example, 'a build from .env.example must not create an Echo client');
    }

    public function test_saving_the_builder_queues_no_broadcast_job_when_the_broadcaster_is_null(): void
    {
        config()->set('broadcasting.default', 'null');
        Queue::fake();

        $this->saveABuilder();

        Queue::assertNothingPushed();
    }

    public function test_saving_the_builder_queues_no_broadcast_job_when_the_broadcaster_is_log(): void
    {
        config()->set('broadcasting.default', 'log');
        Queue::fake();

        $this->saveABuilder();

        Queue::assertNothingPushed();
    }

    public function test_saving_the_builder_still_queues_the_broadcast_when_a_real_broadcaster_is_configured(): void
    {
        config()->set('broadcasting.default', 'reverb');
        Queue::fake();

        $this->saveABuilder();

        Queue::assertPushed(BroadcastEvent::class);
    }

    public function test_the_builders_just_saved_banner_is_hidden_until_a_broadcast_arrives(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $form = Form::query()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Banner',
            'slug' => 'banner',
            'settings' => [],
            'is_published' => false,
        ]);

        $html = $this->actingAs($owner)
            ->get(route('teams.forms.builder', [$team, $form]))
            ->assertOk()
            ->getContent();

        foreach (['builderUpdatedBanner', 'activeEditorsBanner'] as $id) {
            $this->assertSame(1, preg_match('/<div id="'.$id.'"([^>]*)>/', $html, $m), "{$id} is missing");

            // An inline `display: flex` beats the `hidden` class, which made the
            // empty "Reload" banner show on every builder page when no websocket
            // server ever sent anything.
            $this->assertDoesNotMatchRegularExpression('/display:\s*(flex|block)/', $m[1], "{$id} must start hidden");
            $this->assertMatchesRegularExpression('/class="[^"]*\bhidden\b[^"]*"|display:\s*none/', $m[1], "{$id} must start hidden");
        }
    }

    private function saveABuilder(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;

        $form = Form::query()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'No websocket',
            'slug' => 'no-websocket',
            'settings' => [],
            'is_published' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $team, 'form' => $form])
            ->set('title', 'Renamed')
            ->call('saveDraft')
            ->assertHasNoErrors();
    }
}
