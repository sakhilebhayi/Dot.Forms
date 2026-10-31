<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\FieldTypes;
use App\Support\Formula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R-31 Phase 2: address, matrix, ranking, calculated — and save that keeps the publish state. */
class AdvancedFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_formula_evaluator_is_safe_and_honest(): void
    {
        $this->assertSame(345.0, Formula::evaluate('{qty} * {price} * 1.15', ['qty' => 3, 'price' => 100]));
        $this->assertSame(7.5, Formula::evaluate('(1 + 2) * 2.5', []));
        $this->assertNull(Formula::evaluate('{missing} + 1', []));
        $this->assertNull(Formula::evaluate('{qty} + 1', ['qty' => 'not-a-number']));
        $this->assertNull(Formula::evaluate('1 / 0', []));
        $this->assertNull(Formula::evaluate('system("rm -rf /")', []));
        $this->assertNull(Formula::evaluate('1 +', []));
    }

    public function test_save_keeps_a_live_form_live_and_unpublish_is_explicit(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Live',
            'slug' => 'stay-live', 'settings' => [], 'is_published' => true, 'published_at' => now(),
        ]);

        $this->actingAs($owner);

        $component = Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', 'text')
            ->call('saveDraft');

        $this->assertTrue((bool) $form->fresh()->is_published, 'Save took a live form offline.');

        $component->call('unpublish');
        $this->assertFalse((bool) $form->fresh()->is_published);
    }

    public function test_address_matrix_ranking_and_calculated_work_end_to_end(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Advanced',
            'slug' => 'advanced-form', 'settings' => [], 'is_published' => true,
        ]);

        $address = $form->fields()->create(['type' => 'address', 'label' => 'Site address', 'options' => ['field_key' => 'addr'], 'validation_rules' => ['required'], 'order' => 1]);
        $matrix = $form->fields()->create(['type' => 'matrix', 'label' => 'Score the site', 'options' => ['field_key' => 'mx', 'choices' => ['Poor', 'Good'], 'rows' => ['Housekeeping', 'PPE']], 'validation_rules' => ['required'], 'order' => 2]);
        $ranking = $form->fields()->create(['type' => 'ranking', 'label' => 'Priorities', 'options' => ['field_key' => 'rk', 'choices' => ['Safety', 'Cost', 'Speed']], 'validation_rules' => ['required'], 'order' => 3]);
        $qty = $form->fields()->create(['type' => 'number', 'label' => 'Quantity', 'options' => ['field_key' => 'qty'], 'validation_rules' => ['numeric'], 'order' => 4]);
        $price = $form->fields()->create(['type' => 'currency', 'label' => 'Unit price', 'options' => ['field_key' => 'price'], 'validation_rules' => ['numeric', 'min:0'], 'order' => 5]);
        $total = $form->fields()->create(['type' => 'calculated', 'label' => 'Total incl. VAT', 'options' => ['field_key' => 'tot', 'formula' => '{qty} * {price} * 1.15'], 'validation_rules' => [], 'order' => 6]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$address->id, ['street' => '12 Main Rd', 'city' => 'Mahikeng', 'province' => 'North West', 'code' => '2745'])
            ->set('answers.'.$matrix->id, ['Good', 'Poor'])
            ->set('answers.'.$ranking->id, ['Cost', 'Safety', 'Speed'])
            ->set('answers.'.$qty->id, 4)
            ->set('answers.'.$price->id, '250')
            ->call('submit')
            ->assertHasNoErrors();

        $data = FormSubmission::query()->firstOrFail()->data;
        $this->assertSame('Mahikeng', $data[$address->id]['city']);
        $this->assertSame(['Good', 'Poor'], array_values($data[$matrix->id]));
        $this->assertSame(['Cost', 'Safety', 'Speed'], $data[$ranking->id]);
        // The total was computed server-side, never taken from the client.
        $this->assertSame(1150.0, (float) $data[$total->id]);
    }

    public function test_bad_composite_answers_are_refused_and_calculated_cannot_be_forged(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Strict',
            'slug' => 'strict-form', 'settings' => [], 'is_published' => true,
        ]);

        $matrix = $form->fields()->create(['type' => 'matrix', 'label' => 'Score', 'options' => ['field_key' => 'mx', 'choices' => ['Poor', 'Good'], 'rows' => ['A', 'B']], 'validation_rules' => ['required'], 'order' => 1]);
        $ranking = $form->fields()->create(['type' => 'ranking', 'label' => 'Rank', 'options' => ['field_key' => 'rk', 'choices' => ['X', 'Y']], 'validation_rules' => ['required'], 'order' => 2]);
        $address = $form->fields()->create(['type' => 'address', 'label' => 'Where', 'options' => ['field_key' => 'ad'], 'validation_rules' => ['required'], 'order' => 3]);
        $calc = $form->fields()->create(['type' => 'calculated', 'label' => 'Total', 'options' => ['field_key' => 'c', 'formula' => '1 + 1'], 'validation_rules' => [], 'order' => 4]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$matrix->id, ['Excellent']) // off-list and one row short
            ->set('answers.'.$ranking->id, ['X', 'X'])   // a duplicate is not a ranking
            ->set('answers.'.$address->id, ['street' => '1 Road']) // city missing
            ->call('submit')
            ->assertHasErrors([
                'answers.'.$matrix->id,
                'answers.'.$ranking->id.'.*',
                'answers.'.$address->id.'.city',
            ]);

        // A client-sent value for the calculated field is discarded for the real computation.
        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$matrix->id, ['Good', 'Poor'])
            ->set('answers.'.$ranking->id, ['Y', 'X'])
            ->set('answers.'.$address->id, ['street' => '1 Road', 'city' => 'Zeerust'])
            ->set('answers.'.$calc->id, 999999)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(2.0, (float) FormSubmission::query()->firstOrFail()->data[$calc->id]);
    }

    public function test_the_builder_persists_matrix_rows_and_a_formula(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'B',
            'slug' => 'builder-advanced', 'settings' => [], 'is_published' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', 'matrix')
            ->set('fields.0.label', 'Score the site')
            ->set('fields.0.options', 'Poor, Good')
            ->set('fields.0.rows', 'Housekeeping, PPE')
            ->call('addField', 'calculated')
            ->set('fields.1.label', 'Total')
            ->set('fields.1.formula', '{a} + {b}')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $form->refresh();
        $this->assertSame(['Housekeeping', 'PPE'], $form->fields()->where('type', 'matrix')->first()->options['rows']);
        $this->assertSame('{a} + {b}', $form->fields()->where('type', 'calculated')->first()->options['formula']);
        $this->assertContains(FieldTypes::label('matrix'), ['Matrix (rows × choices)']);
    }
}
