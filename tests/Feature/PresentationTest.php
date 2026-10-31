<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\User;
use App\Services\FormTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** R-31 Phase 3: the cover (landing presentation), the receipt (completion screen), progress. */
class PresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeForm(User $owner, array $settings = [], bool $published = true): Form
    {
        return Form::query()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Supplier Onboarding',
            'slug' => 'supplier-onboarding-'.uniqid(),
            'settings' => $settings,
            'is_published' => $published,
        ]);
    }

    public function test_the_builder_saves_presentation_and_completion_settings_with_a_cover(): void
    {
        Storage::fake('public');
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, [], false);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->set('settings.presentation', 'landing')
            ->set('coverUpload', UploadedFile::fake()->image('cover.jpg', 1200, 500))
            ->set('settings.completion_title', 'Thank you — received')
            ->set('settings.completion_show_another', true)
            ->set('settings.completion_redirect_url', 'https://infodot.co.za/thanks')
            ->set('settings.completion_redirect_label', 'Back to InfoDot')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $settings = $form->fresh()->settings;
        $this->assertSame('landing', $settings['presentation']);
        $this->assertSame('Thank you — received', $settings['completion_title']);
        $this->assertTrue($settings['completion_show_another']);
        $this->assertSame('https://infodot.co.za/thanks', $settings['completion_redirect_url']);
        $this->assertNotEmpty($settings['cover_path']);
        Storage::disk('public')->assertExists($settings['cover_path']);

        // Removing the cover clears the stored path on the next save.
        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form->fresh()])
            ->call('removeCover')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertNull($form->fresh()->settings['cover_path']);
    }

    public function test_the_builder_refuses_a_non_http_onward_link_and_a_bad_presentation(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, [], false);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->set('settings.completion_redirect_url', 'javascript:alert(1)')
            ->call('saveDraft')
            ->assertHasErrors(['settings.completion_redirect_url']);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->set('settings.presentation', 'carousel')
            ->call('saveDraft')
            ->assertHasErrors(['settings.presentation']);
    }

    public function test_a_landing_form_renders_the_cover_with_begin_and_an_honest_question_count(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, ['presentation' => 'landing']);

        $form->fields()->create(['type' => 'heading', 'label' => 'Welcome', 'options' => ['field_key' => 'h'], 'validation_rules' => [], 'order' => 1]);
        $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'options' => ['field_key' => 'q1'], 'validation_rules' => [], 'order' => 2]);
        $form->fields()->create(['type' => 'text', 'label' => 'Your town', 'options' => ['field_key' => 'q2'], 'validation_rules' => [], 'order' => 3]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertSeeHtml('cb-cover')
            ->assertSee('Begin')
            // the heading element is content, not a question: 2, not 3
            ->assertSee('2 questions')
            ->assertSeeHtml('opened: false');
    }

    public function test_a_document_form_has_no_cover_and_opens_immediately(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner);
        $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'options' => ['field_key' => 'q1'], 'validation_rules' => [], 'order' => 1]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertDontSeeHtml('cb-cover')
            ->assertSeeHtml('opened: true');
    }

    public function test_the_completion_receipt_shows_the_serial_title_and_owner_actions(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, [
            'completion_title' => 'Thank you — received',
            'confirmation_message' => 'We reply within two days.',
            'completion_show_another' => true,
            'completion_redirect_url' => 'https://infodot.co.za/thanks',
            'completion_redirect_label' => 'Back to InfoDot',
        ]);
        $field = $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'options' => ['field_key' => 'q1'], 'validation_rules' => [], 'order' => 1]);

        $serial = 'FRM-'.str_pad((string) $form->id, 3, '0', STR_PAD_LEFT);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$field->id, 'Naledi')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('RECEIVED')
            ->assertSee('Thank you — received')
            ->assertSee('We reply within two days.')
            ->assertSee($serial.' · lodged')
            ->assertSee('Submit another response')
            ->assertSeeHtml('href="https://infodot.co.za/thanks"')
            ->assertSee('Back to InfoDot');
    }

    public function test_a_stored_non_http_onward_link_is_never_rendered(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, ['completion_redirect_url' => 'javascript:alert(1)']);
        $field = $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'options' => ['field_key' => 'q1'], 'validation_rules' => [], 'order' => 1]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$field->id, 'Naledi')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDontSeeHtml('javascript:alert(1)');
    }

    public function test_progress_renders_for_conversational_and_paged_forms_only(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $plain = $this->makeForm($owner);
        $plain->fields()->create(['type' => 'text', 'label' => 'One', 'options' => ['field_key' => 'a'], 'validation_rules' => [], 'order' => 1]);
        Livewire::test(PublicView::class, ['slug' => $plain->slug])->assertDontSeeHtml('cb-progress');

        $chat = $this->makeForm($owner, ['conversational_mode' => true]);
        $chat->fields()->create(['type' => 'text', 'label' => 'One', 'options' => ['field_key' => 'a'], 'validation_rules' => [], 'order' => 1]);
        $chat->fields()->create(['type' => 'text', 'label' => 'Two', 'options' => ['field_key' => 'b'], 'validation_rules' => [], 'order' => 2]);
        Livewire::test(PublicView::class, ['slug' => $chat->slug])->assertSeeHtml('cb-progress');

        $paged = $this->makeForm($owner);
        $paged->fields()->create(['type' => 'text', 'label' => 'One', 'options' => ['field_key' => 'a'], 'validation_rules' => [], 'order' => 1]);
        $paged->fields()->create(['type' => 'page_break', 'label' => 'Page', 'options' => ['field_key' => 'pb'], 'validation_rules' => [], 'order' => 2]);
        $paged->fields()->create(['type' => 'text', 'label' => 'Two', 'options' => ['field_key' => 'b'], 'validation_rules' => [], 'order' => 3]);
        Livewire::test(PublicView::class, ['slug' => $paged->slug])->assertSeeHtml('cb-progress');
    }

    public function test_a_template_saved_from_a_form_never_carries_its_cover_path(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->makeForm($owner, ['presentation' => 'landing', 'cover_path' => 'form-covers/x.jpg']);

        $payload = app(FormTemplateService::class)->payloadFromForm($form);

        $this->assertArrayNotHasKey('cover_path', $payload['settings']);
        $this->assertSame('landing', $payload['settings']['presentation']);
    }
}
