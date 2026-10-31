<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R-31 Phase 4: canvas element tools, selection bookkeeping, and the motion hooks. */
class MotionMobileTest extends TestCase
{
    use RefreshDatabase;

    protected function builderWithThreeFields(User $owner)
    {
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Press',
            'slug' => 'press-'.uniqid(), 'settings' => [], 'is_published' => false,
        ]);

        $this->actingAs($owner);

        return Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', 'text')->set('fields.0.label', 'One')
            ->call('addField', 'text')->set('fields.1.label', 'Two')
            ->call('addField', 'text')->set('fields.2.label', 'Three');
    }

    public function test_selection_follows_the_element_it_was_on_when_moved(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $component = $this->builderWithThreeFields($owner);

        $component->call('selectField', 1)
            ->call('moveFieldUp', 1);
        $this->assertSame('Two', $component->get('fields.0.label'));
        $this->assertSame(0, $component->get('editingFieldIndex'));

        $component->call('moveFieldDown', 0);
        $this->assertSame('Two', $component->get('fields.1.label'));
        $this->assertSame(1, $component->get('editingFieldIndex'));

        // Moving the neighbour across the selected element keeps the selection too.
        $component->call('selectField', 2)
            ->call('moveFieldUp', 1);
        $this->assertSame(2, $component->get('editingFieldIndex'));
        $this->assertSame('Three', $component->get('fields.2.label'));
    }

    public function test_removing_an_element_never_leaves_the_panel_on_a_different_one(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        // Removing the selected element closes the panel.
        $component = $this->builderWithThreeFields($owner);
        $component->call('selectField', 1)->call('removeField', 1);
        $this->assertNull($component->get('editingFieldIndex'));

        // Removing an earlier element shifts the selection with the list.
        $component = $this->builderWithThreeFields($owner);
        $component->call('selectField', 2)->call('removeField', 0);
        $this->assertSame(1, $component->get('editingFieldIndex'));
        $this->assertSame('Three', $component->get('fields.1.label'));
    }

    public function test_the_selected_element_carries_the_touch_toolbar_and_the_panel_marks_editing(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $component = $this->builderWithThreeFields($owner);

        $component->assertDontSeeHtml('cb-el__tools')
            ->assertDontSeeHtml('cb-props--editing');

        $component->call('selectField', 0)
            ->assertSeeHtml('cb-el__tools')
            ->assertSeeHtml('aria-label="Move up"')
            ->assertSeeHtml('aria-label="Move down"')
            ->assertSeeHtml('aria-label="Duplicate"')
            ->assertSeeHtml('aria-label="Remove"')
            ->assertSeeHtml('cb-props--editing');

        $component->call('deselectField')->assertDontSeeHtml('cb-el__tools');
    }

    public function test_the_public_document_carries_the_motion_hooks_and_a_loading_submit(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Chat',
            'slug' => 'chat-motion', 'settings' => ['conversational_mode' => true], 'is_published' => true,
        ]);
        $form->fields()->create(['type' => 'text', 'label' => 'One', 'options' => ['field_key' => 'a'], 'validation_rules' => [], 'order' => 1]);
        $form->fields()->create(['type' => 'text', 'label' => 'Two', 'options' => ['field_key' => 'b'], 'validation_rules' => [], 'order' => 2]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertSeeHtml('cb-pagein')
            ->assertSee('Lodging…')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }
}
