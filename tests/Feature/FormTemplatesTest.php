<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Livewire\Forms\Templates;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use App\Support\FieldTypes;
use App\Support\FormTemplates;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R-30 Phase 3: the built-in library and an organisation's own templates. */
class FormTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_built_in_template_uses_only_registry_types_and_valid_rule_references(): void
    {
        foreach (FormTemplates::all() as $slug => $template) {
            $keys = array_column($template['fields'], 'key');

            foreach ($template['fields'] as $field) {
                $this->assertContains($field['type'], FieldTypes::ALL, $slug.' uses unknown type '.$field['type']);

                foreach (($field['visibility']['rules'] ?? []) as $rule) {
                    $this->assertContains($rule['trigger_field_key'], $keys, $slug.' rule points at a missing key');
                }
            }
        }
    }

    public function test_using_a_template_creates_a_working_draft_with_live_logic(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $this->actingAs($owner);

        Livewire::test(Templates::class, ['team' => $owner->currentTeam])
            ->assertSee('Incident report')
            ->assertSee('Leave application')
            // the buttons must carry the real slugs (groupBy once dropped them)
            ->assertSeeHtml("useTemplate('incident-report')")
            ->call('useTemplate', 'incident-report')
            ->assertRedirect();

        $form = Form::query()->where('team_id', $owner->currentTeam->id)->firstOrFail();
        $this->assertFalse((bool) $form->is_published);
        $this->assertSame('Incident report', $form->title);
        $this->assertSame(8, $form->fields()->count());

        // The remapped logic must actually fire: publish and submit without injuries,
        // then with injuries but no detail.
        $form->update(['is_published' => true]);

        $fields = $form->fields()->orderBy('order')->get()->keyBy('label');
        $required = ['Reported by', 'When it happened', 'Location / site', 'Severity', 'What happened?'];

        $fill = fn ($test) => $test
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$fields['Reported by']->id, 'Thabo')
            ->set('answers.'.$fields['When it happened']->id, '2026-11-01T08:00')
            ->set('answers.'.$fields['Location / site']->id, 'Lichtenburg plant')
            ->set('answers.'.$fields['Severity']->id, 'Minor')
            ->set('answers.'.$fields['What happened?']->id, 'A pallet slipped.');

        $fill(Livewire::test(PublicView::class, ['slug' => $form->slug]))
            ->set('answers.'.$fields['Was anyone injured?']->id, 'No')
            ->call('submit')
            ->assertHasNoErrors();

        $fill(Livewire::test(PublicView::class, ['slug' => $form->slug]))
            ->set('answers.'.$fields['Was anyone injured?']->id, 'Yes')
            ->call('submit')
            ->assertHasErrors(['answers.'.$fields['Who was injured, and how?']->id]);

        $this->assertSame(1, FormSubmission::query()->count());
    }

    public function test_saving_a_master_as_a_team_template_and_using_it_again(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $team = $owner->currentTeam;
        $form = Form::query()->create([
            'team_id' => $team->id, 'user_id' => $owner->id, 'title' => 'Site visit form',
            'slug' => 'site-visit', 'settings' => [], 'is_published' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $team, 'form' => $form])
            ->call('addField', 'text')
            ->set('fields.0.label', 'Visitor name')
            ->set('fields.0.required', true)
            ->call('addField', 'rating')
            ->set('fields.1.label', 'Visit rating')
            ->set('templateName', 'Standard site visit')
            ->call('saveAsTemplate')
            ->assertHasNoErrors();

        $template = FormTemplate::query()->firstOrFail();
        $this->assertSame($team->id, $template->team_id);
        $this->assertSame('Standard site visit', $template->name);

        Livewire::test(Templates::class, ['team' => $team])
            ->assertSee('Standard site visit')
            ->call('useTeamTemplate', $template->id)
            ->assertRedirect();

        $copy = Form::query()->where('team_id', $team->id)->where('id', '!=', $form->id)->firstOrFail();
        $this->assertNotSame($form->slug, $copy->slug);
        $this->assertSame(2, $copy->fields()->count());
        $this->assertContains('required', $copy->fields()->where('label', 'Visitor name')->first()->validation_rules);
    }

    public function test_team_templates_never_cross_tenants(): void
    {
        $ownerA = User::factory()->withPersonalTeam()->create();
        $ownerB = User::factory()->withPersonalTeam()->create();

        $template = FormTemplate::query()->create([
            'team_id' => $ownerA->currentTeam->id,
            'name' => 'Private to A',
            'payload' => ['name' => 'Private to A', 'fields' => []],
            'created_by' => $ownerA->id,
        ]);

        $this->actingAs($ownerB);

        Livewire::test(Templates::class, ['team' => $ownerB->currentTeam])
            ->assertDontSee('Private to A');

        try {
            Livewire::test(Templates::class, ['team' => $ownerB->currentTeam])
                ->call('useTeamTemplate', $template->id);
            $this->fail('Another tenant could use the template.');
        } catch (ModelNotFoundException) {
            // scoped findOrFail refused it, as it must
        }
        $this->assertSame(0, Form::query()->where('team_id', $ownerB->currentTeam->id)->count());

        Livewire::test(Templates::class, ['team' => $ownerB->currentTeam])
            ->call('deleteTeamTemplate', $template->id);
        $this->assertNotNull($template->fresh());
    }
}
