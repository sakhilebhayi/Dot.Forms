<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\VideoEmbed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** R-31: content elements (heading, text, image, video, divider) and the living master. */
class ContentElementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_embed_parsing_accepts_only_youtube_and_vimeo(): void
    {
        $this->assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'], VideoEmbed::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'], VideoEmbed::parse('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'], VideoEmbed::parse('https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0'));
        $this->assertSame(['provider' => 'vimeo', 'id' => '123456789'], VideoEmbed::parse('https://vimeo.com/123456789'));
        $this->assertNull(VideoEmbed::parse('https://evil.example/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(VideoEmbed::parse('https://youtube.com.evil.example/watch?v=x'));
        $this->assertNull(VideoEmbed::parse('javascript:alert(1)'));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', VideoEmbed::src(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']));
    }

    public function test_the_builder_saves_content_elements_and_refuses_foreign_video_hosts(): void
    {
        Storage::fake('public');
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Content',
            'slug' => 'content-form', 'settings' => [], 'is_published' => false,
        ]);

        $this->actingAs($owner);

        $component = Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', 'heading')
            ->set('fields.0.label', 'About your business')
            ->call('addField', 'text_block')
            ->set('fields.1.body', "First paragraph.\n\nSecond paragraph.")
            ->call('addField', 'image')
            ->set('fields.2.label', 'Our workshop')
            ->set('fields.2.helper_text', 'The floor in Mahikeng')
            ->set('elementImages.2', UploadedFile::fake()->image('floor.jpg', 900, 600))
            ->call('addField', 'video')
            ->set('fields.3.video_url', 'https://youtu.be/dQw4w9WgXcQ')
            ->call('addField', 'divider')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $form->refresh();
        $fields = $form->fields()->orderBy('order')->get();

        $this->assertSame("First paragraph.\n\nSecond paragraph.", $fields[1]->options['body']);
        $this->assertNotEmpty($fields[2]->options['image_path']);
        Storage::disk('public')->assertExists($fields[2]->options['image_path']);
        $this->assertSame(['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ'], $fields[3]->options['video']);

        // None of them collects, so none may carry `required`.
        foreach ($fields as $field) {
            $this->assertNotContains('required', $field->validation_rules ?? []);
        }

        // A foreign host is refused at save with a plain reason.
        $component
            ->set('fields.3.video_url', 'https://evil.example/v.mp4')
            ->call('saveDraft')
            ->assertHasErrors(['fields.3.video_url']);
    }

    public function test_the_document_renders_content_unboxed_and_numbers_only_questions(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Story Form',
            'slug' => 'story-form', 'settings' => [], 'is_published' => true,
        ]);

        $form->fields()->create(['type' => 'heading', 'label' => 'Welcome to the yard', 'options' => ['field_key' => 'h'], 'validation_rules' => [], 'order' => 1]);
        $form->fields()->create(['type' => 'text_block', 'label' => 'Text block', 'options' => ['field_key' => 't', 'body' => 'We buy in bulk and repack.'], 'validation_rules' => [], 'order' => 2]);
        $form->fields()->create(['type' => 'video', 'label' => 'Watch first', 'options' => ['field_key' => 'v', 'video' => ['provider' => 'youtube', 'id' => 'dQw4w9WgXcQ']], 'validation_rules' => [], 'order' => 3]);
        $one = $form->fields()->create(['type' => 'text', 'label' => 'Your name', 'options' => ['field_key' => 'q1'], 'validation_rules' => [], 'order' => 4]);
        $two = $form->fields()->create(['type' => 'text', 'label' => 'Your town', 'options' => ['field_key' => 'q2'], 'validation_rules' => [], 'order' => 5]);

        $component = Livewire::test(PublicView::class, ['slug' => $form->slug])
            ->assertSee('Welcome to the yard')
            ->assertSee('We buy in bulk and repack.')
            ->assertSeeHtml('youtube-nocookie.com/embed/dQw4w9WgXcQ')
            // the two questions are 01 and 02 — content elements take no number
            ->assertSeeHtml('>01<')
            ->assertSeeHtml('>02<')
            ->assertDontSeeHtml('>03<');

        // Content elements record nothing even if a client submits values for them.
        $component
            ->set('startedAt', now()->subSeconds(5)->timestamp)
            ->set('answers.'.$one->id, 'Naledi')
            ->set('answers.'.$two->id, 'Mahikeng')
            ->call('submit')
            ->assertHasNoErrors();

        $data = FormSubmission::query()->firstOrFail()->data;
        $this->assertCount(2, array_filter($data, fn ($v) => $v !== null));
    }

    public function test_the_living_master_selects_and_inserts_in_place(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $form = Form::query()->create([
            'team_id' => $owner->currentTeam->id, 'user_id' => $owner->id, 'title' => 'Canvas',
            'slug' => 'canvas-form', 'settings' => [], 'is_published' => false,
        ]);

        $this->actingAs($owner);

        $component = Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('addField', 'text')
            ->set('fields.0.label', 'First')
            ->call('addField', 'text')
            ->set('fields.1.label', 'Last')
            ->call('insertFieldAt', 1);

        // The inserted element sits between the two, selected, as a short text.
        $this->assertCount(3, $component->get('fields'));
        $this->assertSame('First', $component->get('fields.0.label'));
        $this->assertSame('text', $component->get('fields.1.type'));
        $this->assertSame('Last', $component->get('fields.2.label'));
        $this->assertSame(1, $component->get('editingFieldIndex'));

        $component->call('selectField', 2);
        $this->assertSame(2, $component->get('editingFieldIndex'));

        $component->call('deselectField');
        $this->assertNull($component->get('editingFieldIndex'));
    }
}
