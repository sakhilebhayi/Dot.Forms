<?php

namespace Tests\Feature;

use App\Exports\FormSubmissionsExport;
use App\Livewire\Dashboard\Analytics;
use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Livewire\Forms\Submissions;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\FormTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** R-30 leftovers: draft safety, the funnel, approval stamps, the password gate, QR/embeds. */
class R30LeftoversTest extends TestCase
{
    use RefreshDatabase;

    protected function makeForm(User $owner, array $settings = []): Form
    {
        return Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Gate',
            'slug' => 'gate-'.uniqid(), 'settings' => $settings, 'is_published' => true,
        ]);
    }

    public function test_the_password_gate_hides_the_document_and_opens_only_on_the_right_password(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, ['access_password_hash' => Hash::make('letmein')]);
        $form->fields()->create(['type' => 'text', 'label' => 'Secret question', 'options' => ['field_key' => 'q'], 'validation_rules' => [], 'order' => 1]);

        $component = Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertSee('This form is protected')
            ->assertDontSee('Secret question');

        $component->set('accessPassword', 'wrong')->call('unlock')
            ->assertHasErrors(['accessPassword'])
            ->assertDontSee('Secret question');

        $component->set('accessPassword', 'letmein')->call('unlock')
            ->assertHasNoErrors()
            ->assertSee('Secret question')
            ->assertDontSee('This form is protected');
    }

    public function test_submit_is_refused_while_the_gate_holds_even_if_the_client_tampers(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, ['access_password_hash' => Hash::make('letmein')]);
        $field = $form->fields()->create(['type' => 'text', 'label' => 'Q', 'options' => ['field_key' => 'q'], 'validation_rules' => [], 'order' => 1]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            // a tampering client can set any public property — the gate must not care
            ->set('isPreview', true)
            ->set('answers.'.$field->id, 'sneak')
            ->call('submit')
            ->assertHasErrors(['accessPassword']);

        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_the_builder_stores_only_a_hash_and_can_remove_it_and_templates_never_carry_it(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->set('settings.access_password_new', 'yard-gate-9')
            ->set('settings.allow_embed', true)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $settings = $form->fresh()->settings;
        $this->assertTrue(Hash::check('yard-gate-9', $settings['access_password_hash']));
        $this->assertTrue($settings['allow_embed']);
        $this->assertArrayNotHasKey('access_password_new', $settings);
        $this->assertStringNotContainsString('yard-gate-9', json_encode($settings));

        $payload = app(FormTemplateService::class)->payloadFromForm($form->fresh());
        $this->assertArrayNotHasKey('access_password_hash', $payload['settings']);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form->fresh()])
            ->set('settings.access_password_remove', true)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertNull($form->fresh()->settings['access_password_hash']);
    }

    public function test_a_retried_submit_with_the_same_client_token_never_lodges_a_second_copy(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner);
        $field = $form->fields()->create(['type' => 'text', 'label' => 'Q', 'options' => ['field_key' => 'q'], 'validation_rules' => [], 'order' => 1]);

        $token = (string) Str::uuid();

        foreach ([1, 2] as $attempt) {
            Livewire::test(PublicView::class, ['slug' => $form->slug])
                ->set('startedAt', now()->subSeconds(5)->timestamp)
                ->set('clientToken', $token)
                ->set('answers.'.$field->id, 'Naledi')
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSee('RECEIVED');
        }

        $this->assertSame(1, FormSubmission::query()->where('form_id', $form->id)->count());
        $this->assertSame($token, FormSubmission::query()->firstOrFail()->client_token);
    }

    public function test_the_funnel_counts_a_start_once_per_session_and_never_in_preview(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner);
        $form->fields()->create(['type' => 'text', 'label' => 'Q', 'options' => ['field_key' => 'q'], 'validation_rules' => [], 'order' => 1]);

        $component = Livewire::test(PublicView::class, ['slug' => $form->slug]);
        $component->call('markStarted');
        $component->call('markStarted');

        $this->assertSame(1, $form->fresh()->starts_count);

        $this->actingAs($owner);
        session()->forget('dotforms-started-'.$form->id);
        Livewire::withQueryParams(['preview' => 1])
            ->test(PublicView::class, ['slug' => $form->slug])
            ->call('markStarted');

        $this->assertSame(1, $form->fresh()->starts_count);

        // and the analytics funnel reports it
        $submissionless = Livewire::test(Analytics::class);
        $rows = collect($submissionless->get('funnelRows'));
        $this->assertSame(1, $rows->firstWhere('title', $form->title)['starts']);
    }

    public function test_the_desk_stamps_a_decision_lifts_it_and_refuses_nonsense(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner);
        $submission = $form->submissions()->create(['data' => [], 'submitted_at' => now()]);

        $this->actingAs($owner);

        $component = Livewire::test(Submissions::class, ['team' => $owner->currentTeam, 'form' => $form]);

        $component->call('decide', $submission->id, 'approved');
        $fresh = $submission->fresh();
        $this->assertSame('approved', $fresh->decision);
        $this->assertSame($owner->id, $fresh->decided_by);
        $this->assertNotNull($fresh->decided_at);
        $component->assertSee('APPROVED');

        $component->call('decide', $submission->id, 'maybe');
        $this->assertSame('approved', $submission->fresh()->decision);

        $component->call('decide', $submission->id, null);
        $fresh = $submission->fresh();
        $this->assertNull($fresh->decision);
        $this->assertNull($fresh->decided_by);
        $this->assertNull($fresh->decided_at);
    }

    public function test_embedding_headers_follow_the_owner_flag(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $locked = $this->makeForm($owner);
        $this->get(route('forms.public', ['slug' => $locked->slug]))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeaderMissing('Content-Security-Policy');

        $open = $this->makeForm($owner, ['allow_embed' => true]);
        $this->get(route('forms.public', ['slug' => $open->slug]))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
            ->assertHeaderMissing('X-Frame-Options');
    }

    public function test_the_builder_renders_the_share_section_with_a_qr_and_the_export_carries_the_decision(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->assertSee('Public link')
            ->assertSeeHtml('<svg')
            ->assertSee('QR code');

        $headings = (new FormSubmissionsExport($form->fresh('fields'), []))->headings();
        $this->assertContains('Decision', $headings);
    }
}
