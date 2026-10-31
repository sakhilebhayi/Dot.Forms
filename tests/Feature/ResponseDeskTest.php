<?php

namespace Tests\Feature;

use App\Livewire\Forms\PublicView;
use App\Livewire\Forms\Submissions;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R-30 Phase 4: a copy is worked — status, holder, internal note, filters. */
class ResponseDeskTest extends TestCase
{
    use RefreshDatabase;

    private function formWithSubmissions(User $owner, int $count = 2): Form
    {
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Desk Form',
            'slug' => 'desk-form-'.$count.'-'.uniqid(),
            'settings' => [],
            'is_published' => true,
        ]);

        foreach (range(1, $count) as $i) {
            FormSubmission::query()->create([
                'form_id' => $form->id,
                'data' => ['answer' => 'copy '.$i],
                'submitted_at' => now()->subMinutes($i),
            ]);
        }

        return $form;
    }

    public function test_status_assignee_and_note_are_worked_and_recorded(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->formWithSubmissions($owner);
        $submission = $form->submissions()->first();

        $this->actingAs($owner);

        $component = Livewire::test(Submissions::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('setStatus', $submission->id, 'in_review')
            ->call('assign', $submission->id, $owner->id);

        $submission->refresh();
        $this->assertSame('in_review', $submission->status);
        $this->assertSame($owner->id, $submission->assigned_to);

        $component
            ->call('viewSubmission', $submission->id)
            ->set('noteDraft', 'Called the client, awaiting the slip.')
            ->call('saveNote');

        $this->assertSame('Called the client, awaiting the slip.', $submission->fresh()->internal_note);

        // An unknown status and an outsider assignee are both refused quietly.
        $stranger = User::factory()->withPersonalTeam()->create();
        $component
            ->call('setStatus', $submission->id, 'burned')
            ->call('assign', $submission->id, $stranger->id);

        $submission->refresh();
        $this->assertSame('in_review', $submission->status);
        $this->assertSame($owner->id, $submission->assigned_to);
    }

    public function test_the_desk_filters_by_status(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->formWithSubmissions($owner, 3);
        [$a, $b, $c] = $form->submissions()->orderBy('id')->get();

        $a->update(['status' => 'closed']);

        $this->actingAs($owner);

        Livewire::test(Submissions::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->set('statusFilter', 'closed')
            ->assertSee('copy 1')
            ->assertDontSee('copy 2')
            ->set('statusFilter', 'new')
            ->assertDontSee('copy 1')
            ->assertSee('copy 2')
            ->assertSee('copy 3');
    }

    public function test_the_internal_note_never_reaches_the_public_document(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = $this->formWithSubmissions($owner, 1);
        $form->submissions()->first()->update(['internal_note' => 'VERY-PRIVATE-NOTE']);

        Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertDontSee('VERY-PRIVATE-NOTE');
    }
}
