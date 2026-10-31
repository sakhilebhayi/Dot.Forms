<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R-30 Phase 3: multi-rule AND/OR visibility with the widened operator set. */
class LogicV2Test extends TestCase
{
    use RefreshDatabase;

    private function publishedForm(User $owner, string $slug): Form
    {
        return Form::query()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Logic Form',
            'slug' => $slug,
            'settings' => [],
            'is_published' => true,
        ]);
    }

    public function test_all_logic_needs_every_rule_and_any_logic_needs_one(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->publishedForm($owner, 'logic-all-any');

        $severity = $form->fields()->create(['type' => 'select', 'label' => 'Severity', 'options' => ['field_key' => 'sev', 'choices' => ['Minor', 'Serious']], 'validation_rules' => [], 'order' => 1]);
        $injured = $form->fields()->create(['type' => 'yesno', 'label' => 'Injured?', 'options' => ['field_key' => 'inj'], 'validation_rules' => ['in:Yes,No'], 'order' => 2]);
        $escalate = $form->fields()->create(['type' => 'textarea', 'label' => 'Escalation detail', 'options' => [
            'field_key' => 'esc',
            'visibility' => ['logic' => 'all', 'rules' => [
                ['trigger_field_key' => 'sev', 'operator' => 'equals', 'value' => 'Serious'],
                ['trigger_field_key' => 'inj', 'operator' => 'equals', 'value' => 'Yes'],
            ]],
        ], 'validation_rules' => ['required'], 'order' => 3]);

        // ALL: only one rule matching -- the field stays hidden, its required does not bite.
        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$severity->id, 'Serious')
            ->set('answers.'.$injured->id, 'No')
            ->call('submit')
            ->assertHasNoErrors();

        // Flip to ANY on the stored row: one matching rule now shows (and requires) it.
        $escalate->update(['options' => array_merge($escalate->options, [
            'visibility' => ['logic' => 'any', 'rules' => $escalate->options['visibility']['rules']],
        ])]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$severity->id, 'Serious')
            ->set('answers.'.$injured->id, 'No')
            ->call('submit')
            ->assertHasErrors(['answers.'.$escalate->id]);

        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_numeric_and_emptiness_operators(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->publishedForm($owner, 'logic-numeric');

        $amount = $form->fields()->create(['type' => 'currency', 'label' => 'Amount', 'options' => ['field_key' => 'amt'], 'validation_rules' => ['numeric', 'min:0'], 'order' => 1]);
        $motivation = $form->fields()->create(['type' => 'textarea', 'label' => 'Motivation', 'options' => [
            'field_key' => 'mot',
            'visibility' => ['logic' => 'all', 'rules' => [['trigger_field_key' => 'amt', 'operator' => 'greater_than', 'value' => '10000']]],
        ], 'validation_rules' => ['required'], 'order' => 2]);
        $whyEmpty = $form->fields()->create(['type' => 'text', 'label' => 'Why no amount?', 'options' => [
            'field_key' => 'why',
            'visibility' => ['logic' => 'all', 'rules' => [['trigger_field_key' => 'amt', 'operator' => 'empty', 'value' => '']]],
        ], 'validation_rules' => ['required'], 'order' => 3]);

        // Small amount: neither dependent field may block.
        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$amount->id, '500')
            ->call('submit')
            ->assertHasNoErrors();

        // Large amount: the motivation is required.
        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$amount->id, '15000')
            ->call('submit')
            ->assertHasErrors(['answers.'.$motivation->id]);

        // No amount at all: the emptiness rule shows (and requires) the why field.
        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->call('submit')
            ->assertHasErrors(['answers.'.$whyEmpty->id]);
    }

    public function test_the_legacy_single_rule_still_works(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->publishedForm($owner, 'logic-legacy');

        $trigger = $form->fields()->create(['type' => 'radio', 'label' => 'Need support?', 'options' => ['field_key' => 'trig', 'choices' => ['yes', 'no']], 'validation_rules' => [], 'order' => 1]);
        $dependent = $form->fields()->create(['type' => 'textarea', 'label' => 'Describe', 'options' => [
            'field_key' => 'dep',
            'visibility_rule' => ['trigger_field_key' => 'trig', 'operator' => 'equals', 'value' => 'yes'],
        ], 'validation_rules' => ['required'], 'order' => 2]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$trigger->id, 'no')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertArrayNotHasKey((string) $dependent->id, FormSubmission::query()->firstOrFail()->data);
    }

    public function test_the_builder_saves_a_multi_rule_set_that_survives_resaving(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $form = Form::query()->create([
            'team_id' => $team->id, 'user_id' => $owner->id, 'title' => 'Multi', 'slug' => 'multi-rule',
            'settings' => [], 'is_published' => false,
        ]);

        $this->actingAs($owner);

        $component = Livewire::test(Builder::class, ['team' => $team, 'form' => $form])
            ->call('addField', 'select')
            ->call('addField', 'yesno')
            ->call('addField', 'textarea')
            ->set('fields.0.label', 'Severity')
            ->set('fields.0.options', 'Minor, Serious')
            ->set('fields.1.label', 'Injured?')
            ->set('fields.2.label', 'Escalation');

        $sevKey = $component->get('fields.0.key');
        $injKey = $component->get('fields.1.key');

        $component
            ->call('addVisibilityRule', 2)
            ->call('addVisibilityRule', 2)
            ->set('fields.2.visibility.logic', 'any')
            ->set('fields.2.visibility.rules.0.trigger_field_key', $sevKey)
            ->set('fields.2.visibility.rules.0.operator', 'equals')
            ->set('fields.2.visibility.rules.0.value', 'Serious')
            ->set('fields.2.visibility.rules.1.trigger_field_key', $injKey)
            ->set('fields.2.visibility.rules.1.operator', 'equals')
            ->set('fields.2.visibility.rules.1.value', 'Yes')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $check = function () use ($form): void {
            $stored = $form->fields()->where('label', 'Escalation')->firstOrFail()->options['visibility'];
            $this->assertSame('any', $stored['logic']);
            $this->assertCount(2, $stored['rules']);
            $sev = $form->fields()->where('label', 'Severity')->firstOrFail();
            $this->assertSame($sev->options['field_key'], $stored['rules'][0]['trigger_field_key']);
        };

        $form->refresh();
        $check();

        // Fields are deleted and recreated on every save: the key-based rules must survive.
        $component->call('saveDraft')->assertHasNoErrors();
        $form->refresh();
        $check();
    }
}
