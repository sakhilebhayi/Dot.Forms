<?php

namespace Tests\Feature;

use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every control a respondent fills in is reachable by its visible label (WCAG 1.3.1, 3.3.2, 4.1.2):
 * the field label points at the control with for/id, so a screen reader announces "Email, edit text"
 * and a click on the label focuses the control.
 */
class PublicFormLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_control_fields_are_linked_to_their_label_and_groups_are_not_given_a_dangling_for(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Labelled Form',
            'slug' => 'labelled-form',
            'settings' => [],
            'is_published' => true,
        ]);

        $single = [];
        foreach (['text' => 'Full name', 'email' => 'Email address', 'textarea' => 'Message', 'select' => 'Topic', 'file' => 'Attachment', 'date' => 'Date'] as $type => $label) {
            $single[$type] = $form->fields()->create([
                'type' => $type,
                'label' => $label,
                'placeholder' => null,
                'options' => $type === 'select' ? ['choices' => ['One', 'Two']] : [],
                'validation_rules' => [],
                'order' => count($single) + 1,
            ]);
        }
        $radio = $form->fields()->create(['type' => 'radio', 'label' => 'Pick one', 'placeholder' => null, 'options' => ['choices' => ['A', 'B']], 'validation_rules' => [], 'order' => 7]);
        $checkbox = $form->fields()->create(['type' => 'checkbox', 'label' => 'Agree', 'placeholder' => null, 'options' => [], 'validation_rules' => [], 'order' => 8]);

        $page = Livewire::test(PublicView::class, ['slug' => $form->slug]);

        foreach ($single as $type => $field) {
            $page->assertSeeHtml('for="field-'.$field->id.'"');
            $this->assertSame(
                1,
                substr_count($page->html(), 'id="field-'.$field->id.'"'),
                "the {$type} field has exactly one control carrying the id its label points at",
            );
        }

        // Radio and checkbox are wrapped in their own labels; a for= on the group heading would point at nothing.
        $page->assertDontSeeHtml('for="field-'.$radio->id.'"');
        $page->assertDontSeeHtml('for="field-'.$checkbox->id.'"');
    }
}
