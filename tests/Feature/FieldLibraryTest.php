<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\FieldTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-30 Phase 2: every type in the registry really stores, validates and records —
 * a field that only renders is banned by the brief (§10).
 */
class FieldLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithForm(string $slug, bool $published = true): array
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Library Form',
            'slug' => $slug,
            'settings' => [],
            'is_published' => $published,
        ]);

        return [$owner, $form];
    }

    public function test_the_builder_saves_every_registry_type_with_its_intrinsic_rules(): void
    {
        [$owner, $form] = $this->ownerWithForm('all-types', false);
        $this->actingAs($owner);

        $component = Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form]);

        foreach (FieldTypes::ALL as $type) {
            $component->call('addField', $type);
        }

        // Choice types need options to be meaningful.
        foreach (FieldTypes::ALL as $index => $type) {
            if (FieldTypes::usesChoices($type)) {
                $component->set('fields.'.$index.'.options', 'Alpha, Beta');
            }
            if ($type === 'hidden') {
                $component->set('fields.'.$index.'.default_value', 'campaign-november');
            }
        }

        $component->call('saveDraft')->assertHasNoErrors();

        $form->refresh();
        $this->assertSame(count(FieldTypes::ALL), $form->fields()->count());

        foreach ($form->fields as $field) {
            $this->assertContains($field->type, FieldTypes::ALL);
            foreach (FieldTypes::storedRules($field->type) as $rule) {
                $this->assertContains($rule, $field->validation_rules, $field->type.' lost its '.$rule.' rule');
            }
            if (FieldTypes::usesChoices($field->type)) {
                $this->assertSame(['Alpha', 'Beta'], $field->options['choices']);
            }
            if ($field->type === 'hidden') {
                $this->assertSame('campaign-november', $field->options['default_value']);
            }
        }
    }

    public function test_a_type_outside_the_registry_is_refused_at_save(): void
    {
        [$owner, $form] = $this->ownerWithForm('bad-type', false);
        $this->actingAs($owner);

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', 'text')
            ->set('fields.0.type', 'paypal_button')
            ->call('saveDraft')
            ->assertHasErrors(['fields.0.type']);
    }

    public function test_duplicate_field_copies_under_the_original_with_its_own_key(): void
    {
        [$owner, $form] = $this->ownerWithForm('dupe', false);
        $this->actingAs($owner);

        $component = Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', 'text')
            ->call('addField', 'email')
            ->set('fields.0.label', 'Full name')
            ->call('duplicateField', 0);

        $this->assertCount(3, $component->get('fields'));
        $this->assertSame('Full name (copy)', $component->get('fields.1.label'));
        $this->assertSame('text', $component->get('fields.1.type'));
        $this->assertNotSame($component->get('fields.0.key'), $component->get('fields.1.key'));
        $this->assertSame('email', $component->get('fields.2.type'));
    }

    public function test_the_new_types_accept_valid_answers_and_record_them(): void
    {
        Storage::fake('public');
        [$owner, $form] = $this->ownerWithForm('happy-path');

        $make = fn (string $type, string $label, array $options = [], int $order = 1) => $form->fields()->create([
            'type' => $type, 'label' => $label,
            'options' => array_merge(['field_key' => $type.'-key'], $options),
            'validation_rules' => FieldTypes::storedRules($type),
            'order' => $order,
        ]);

        $phone = $make('phone', 'Phone', [], 1);
        $urlF = $make('url', 'Site', [], 2);
        $time = $make('time', 'Opens', [], 3);
        $rating = $make('rating', 'Rate us', [], 4);
        $scale = $make('scale', 'Likelihood', [], 5);
        $yesno = $make('yesno', 'VAT registered?', [], 6);
        $multi = $make('multiselect', 'Services', ['choices' => ['Drilling', 'Pumps', 'Surveys']], 7);
        $country = $make('country', 'Country', [], 8);
        $currency = $make('currency', 'Budget', [], 9);
        $percent = $make('percentage', 'Deposit', [], 10);
        $consent = $make('consent', 'POPIA consent', [], 11);
        $hidden = $make('hidden', 'Campaign', ['default_value' => 'nov-2026'], 12);
        $signature = $make('signature', 'Sign here', [], 13);

        $png = base64_encode("\x89PNG\r\n\x1a\n".random_bytes(64));

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$phone->id, '+27 18 000 0000')
            ->set('answers.'.$urlF->id, 'https://mokoena.example')
            ->set('answers.'.$time->id, '14:30')
            ->set('answers.'.$rating->id, 4)
            ->set('answers.'.$scale->id, 9)
            ->set('answers.'.$yesno->id, 'Yes')
            ->set('answers.'.$multi->id, ['Drilling', 'Pumps'])
            ->set('answers.'.$country->id, 'South Africa')
            ->set('answers.'.$currency->id, '15000.50')
            ->set('answers.'.$percent->id, '25')
            ->set('answers.'.$consent->id, true)
            ->set('answers.'.$signature->id, 'data:image/png;base64,'.$png)
            ->call('submit')
            ->assertHasNoErrors();

        $submission = FormSubmission::query()->firstOrFail();
        $data = $submission->data;

        $this->assertSame('+27 18 000 0000', $data[$phone->id]);
        $this->assertSame(['Drilling', 'Pumps'], $data[$multi->id]);
        $this->assertSame('Yes', $data[$yesno->id]);
        $this->assertSame('South Africa', $data[$country->id]);

        // The hidden value is the master's, not the client's.
        $this->assertSame('nov-2026', $data[$hidden->id]);

        // The signature became a real stored PNG, not base64 in the data column.
        $this->assertStringStartsWith('forms/'.$form->id.'/signatures/', $data[$signature->id]);
        Storage::disk('public')->assertExists($data[$signature->id]);
    }

    public function test_out_of_range_and_off_list_answers_are_refused(): void
    {
        [$owner, $form] = $this->ownerWithForm('sad-path');

        $rating = $form->fields()->create(['type' => 'rating', 'label' => 'Rate', 'options' => ['field_key' => 'r'], 'validation_rules' => FieldTypes::storedRules('rating'), 'order' => 1]);
        $percent = $form->fields()->create(['type' => 'percentage', 'label' => 'Deposit', 'options' => ['field_key' => 'p'], 'validation_rules' => FieldTypes::storedRules('percentage'), 'order' => 2]);
        $yesno = $form->fields()->create(['type' => 'yesno', 'label' => 'VAT?', 'options' => ['field_key' => 'y'], 'validation_rules' => FieldTypes::storedRules('yesno'), 'order' => 3]);
        $multi = $form->fields()->create(['type' => 'multiselect', 'label' => 'Services', 'options' => ['field_key' => 'm', 'choices' => ['Drilling', 'Pumps']], 'validation_rules' => [], 'order' => 4]);
        $time = $form->fields()->create(['type' => 'time', 'label' => 'Opens', 'options' => ['field_key' => 't'], 'validation_rules' => FieldTypes::storedRules('time'), 'order' => 5]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$rating->id, 9)
            ->set('answers.'.$percent->id, 150)
            ->set('answers.'.$yesno->id, 'Maybe')
            ->set('answers.'.$multi->id, ['Dynamite'])
            ->set('answers.'.$time->id, 'late afternoon')
            ->call('submit')
            ->assertHasErrors([
                'answers.'.$rating->id,
                'answers.'.$percent->id,
                'answers.'.$yesno->id,
                'answers.'.$multi->id.'.*',
                'answers.'.$time->id,
            ]);

        $this->assertSame(0, FormSubmission::query()->count());
    }

    public function test_a_forged_signature_payload_is_refused(): void
    {
        [$owner, $form] = $this->ownerWithForm('sig-forge');

        $signature = $form->fields()->create(['type' => 'signature', 'label' => 'Sign', 'options' => ['field_key' => 's'], 'validation_rules' => [], 'order' => 1]);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$signature->id, 'data:text/html;base64,'.base64_encode('<script>1</script>'))
            ->call('submit')
            ->assertHasErrors(['answers.'.$signature->id]);
    }

    public function test_page_breaks_split_the_document_and_record_nothing(): void
    {
        [$owner, $form] = $this->ownerWithForm('paged');

        $one = $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'options' => ['field_key' => 'a'], 'validation_rules' => [], 'order' => 1]);
        $break = $form->fields()->create(['type' => FieldTypes::PAGE_BREAK, 'label' => 'Page break', 'options' => ['field_key' => 'b'], 'validation_rules' => [], 'order' => 2]);
        $two = $form->fields()->create(['type' => 'text', 'label' => 'Your town', 'options' => ['field_key' => 'c'], 'validation_rules' => [], 'order' => 3]);

        $component = Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertSee('Your name')
            ->assertSee('Your town')
            ->assertSee('page ', false);

        $component
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$one->id, 'Thabo')
            ->set('answers.'.$two->id, 'Mahikeng')
            ->set('answers.'.$break->id, 'should never be recorded')
            ->call('submit')
            ->assertHasNoErrors();

        $data = FormSubmission::query()->firstOrFail()->data;
        $this->assertArrayNotHasKey((string) $break->id, $data);
        $this->assertArrayNotHasKey($break->id, $data);
        $this->assertSame('Thabo', $data[$one->id]);
    }

    public function test_a_required_page_break_cannot_block_submission(): void
    {
        [$owner, $form] = $this->ownerWithForm('paged-required', false);
        $this->actingAs($owner);

        // Even if someone marks the break required in the builder, the stored
        // row must not carry `required` (it collects nothing).
        $component = Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', FieldTypes::PAGE_BREAK)
            ->set('fields.0.required', true)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $form->refresh();
        $this->assertNotContains('required', $form->fields()->first()->validation_rules ?? []);
    }
}
