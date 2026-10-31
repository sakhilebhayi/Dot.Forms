<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R-30 Phase 4: the document theme presets, accent and width, with legacy mapping. */
class ThemeStudioTest extends TestCase
{
    use RefreshDatabase;

    private function publishedForm(User $owner, array $settings, string $slug): Form
    {
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Theme Form',
            'slug' => $slug,
            'settings' => $settings,
            'is_published' => true,
        ]);

        $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'options' => ['field_key' => 'n'], 'validation_rules' => [], 'order' => 1]);

        return $form;
    }

    public function test_the_builder_persists_preset_width_and_accent(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'T',
            'slug' => 'theme-save', 'settings' => [], 'is_published' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->set('settings.theme_preset', 'letterhead')
            ->set('settings.doc_width', 'wide')
            ->set('settings.brand_color', '#2f6a4a')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $settings = $form->fresh()->settings;
        $this->assertSame('letterhead', $settings['theme_preset']);
        $this->assertSame('wide', $settings['doc_width']);
        $this->assertSame('#2f6a4a', $settings['brand_color']);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form->fresh()])
            ->set('settings.theme_preset', 'neon-disco')
            ->call('saveDraft')
            ->assertHasErrors(['settings.theme_preset']);
    }

    public function test_the_document_renders_its_preset_width_and_accent(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->publishedForm($owner, [
            'theme_preset' => 'midnight', 'doc_width' => 'narrow', 'brand_color' => '#2f6a4a',
        ], 'theme-doc');

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertSeeHtml('cb-doc--midnight')
            ->assertSeeHtml('--cbd-width: 560px')
            ->assertSeeHtml('--cbd-accent: #2f6a4a');
    }

    public function test_a_legacy_theme_maps_onto_a_preset_and_a_bad_accent_is_ignored(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->publishedForm($owner, [
            'theme' => 'dark', 'brand_color' => 'javascript:alert(1)',
        ], 'theme-legacy');

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertSeeHtml('cb-doc--midnight')
            ->assertDontSeeHtml('javascript:alert(1)')
            ->assertDontSeeHtml('--cbd-accent');
    }
}
