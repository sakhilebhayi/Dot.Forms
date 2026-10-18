<?php

namespace Tests\Feature;

use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\PublicFormCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The public form view caches the form for ten minutes. config/cache.php
 * sets serializable_classes to false (a security control), so a persistent
 * store (database, file, redis...) cannot hand an Eloquent model back: it
 * unserializes to __PHP_Incomplete_Class and the second view 500s. The
 * array store the suite normally runs on never serializes, which is how
 * this slipped through. These tests run on real persistent stores.
 */
class PublicFormCacheTest extends TestCase
{
    use RefreshDatabase;

    private string $fileCachePath = '';

    protected function tearDown(): void
    {
        if ($this->fileCachePath !== '') {
            File::deleteDirectory($this->fileCachePath);
        }

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function persistentStores(): array
    {
        return [
            'database store' => ['database'],
            'file store' => ['file'],
        ];
    }

    private function usePersistentStore(string $store): void
    {
        if ($store === 'file') {
            $this->fileCachePath = sys_get_temp_dir().'/forms-cache-test-'.bin2hex(random_bytes(6));
            config()->set('cache.stores.file.path', $this->fileCachePath);
            config()->set('cache.stores.file.lock_path', $this->fileCachePath);
        }

        config()->set('cache.default', $store);
        app('cache')->forgetDriver($store);
        Cache::flush();
    }

    private function publishedForm(array $overrides = []): Form
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $form = Form::query()->create(array_merge([
            'team_id' => $owner->currentTeam->id,
            'user_id' => $owner->id,
            'title' => 'Cached Form',
            'slug' => 'cached-form',
            'settings' => ['consent_required' => false],
            'is_published' => true,
        ], $overrides));

        $form->fields()->create([
            'type' => 'text',
            'label' => 'Your name',
            'placeholder' => null,
            'options' => [],
            'validation_rules' => ['required'],
            'order' => 1,
        ]);

        return $form;
    }

    private function selectQueriesOn(string $table, callable $callback): int
    {
        $count = 0;

        DB::listen(function ($query) use (&$count, $table): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "'.$table.'"')) {
                $count++;
            }
        });

        $callback();

        return $count;
    }

    #[DataProvider('persistentStores')]
    public function test_second_public_view_within_the_cache_window_does_not_500(string $store): void
    {
        $this->usePersistentStore($store);
        $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk()->assertSee('Cached Form')->assertSee('Your name');
        $this->get('/forms/cached-form')->assertOk()->assertSee('Cached Form')->assertSee('Your name');
        $this->get('/forms/cached-form')->assertOk();
    }

    #[DataProvider('persistentStores')]
    public function test_cached_views_still_skip_the_form_and_field_queries(string $store): void
    {
        $this->usePersistentStore($store);
        $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk();

        $formQueries = $this->selectQueriesOn('forms', function (): void {
            $this->get('/forms/cached-form')->assertOk()->assertSee('Your name');
        });

        $this->assertSame(0, $formQueries, 'the second view must be served from the cache, not re-query forms');

        $fieldQueries = $this->selectQueriesOn('form_fields', function (): void {
            $this->get('/forms/cached-form')->assertOk()->assertSee('Your name');
        });

        $this->assertSame(0, $fieldQueries, 'the page must render the cached fields, not re-query them');
    }

    #[DataProvider('persistentStores')]
    public function test_every_live_view_is_counted_even_when_served_from_cache(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk();
        $this->get('/forms/cached-form')->assertOk();
        $this->get('/forms/cached-form')->assertOk();

        $this->assertSame(3, $form->fresh()->views_count);
    }

    #[DataProvider('persistentStores')]
    public function test_public_submit_works_after_a_cached_mount(string $store): void
    {
        $this->usePersistentStore($store);
        config()->set('dotforms.forms.min_submit_seconds', 0);
        $form = $this->publishedForm();
        $field = $form->fields()->first();

        // Warm the cache, then mount again from it and submit.
        Livewire::test(PublicView::class, ['slug' => 'cached-form']);

        Livewire::test(PublicView::class, ['slug' => 'cached-form'])
            ->set('answers.'.$field->id, 'Ada')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(1, FormSubmission::query()->where('form_id', $form->id)->count());
    }

    #[DataProvider('persistentStores')]
    public function test_a_corrupt_or_legacy_cache_entry_is_treated_as_a_miss(string $store): void
    {
        $this->usePersistentStore($store);
        $this->publishedForm();

        // Whatever an older release (or anything else) left under the key,
        // including a serialized Eloquent model that reads back as
        // __PHP_Incomplete_Class, must fall through to the database.
        foreach (['form:public:cached-form:live', 'form:public:v2:cached-form:live'] as $key) {
            Cache::put($key, 'not-a-payload', 600);
        }

        $this->get('/forms/cached-form')->assertOk()->assertSee('Cached Form');
    }

    #[DataProvider('persistentStores')]
    public function test_editing_a_form_shows_on_the_next_view(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk()->assertSee('Cached Form');

        $form->update(['title' => 'Renamed Form']);

        $this->get('/forms/cached-form')->assertOk()->assertSee('Renamed Form')->assertDontSee('Cached Form');
    }

    #[DataProvider('persistentStores')]
    public function test_unpublishing_or_closing_a_form_takes_it_offline_immediately(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk();

        $form->update(['is_published' => false]);

        $this->get('/forms/cached-form')->assertNotFound();

        $form->update(['is_published' => true]);

        $this->get('/forms/cached-form')->assertOk();

        $form->update(['is_published' => false, 'archived_at' => now()]);

        $this->get('/forms/cached-form')->assertNotFound();
    }

    #[DataProvider('persistentStores')]
    public function test_deleting_a_form_takes_it_offline_immediately(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk();

        $form->delete();

        $this->get('/forms/cached-form')->assertNotFound();
    }

    #[DataProvider('persistentStores')]
    public function test_changing_a_slug_retires_the_old_url_and_serves_the_new_one(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk();

        $form->update(['slug' => 'moved-form']);

        $this->get('/forms/cached-form')->assertNotFound();
        $this->get('/forms/moved-form')->assertOk();
    }

    #[DataProvider('persistentStores')]
    public function test_field_changes_show_on_the_next_view(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();
        $field = $form->fields()->first();

        $this->get('/forms/cached-form')->assertOk()->assertSee('Your name');

        $field->update(['label' => 'Your full name']);
        $this->get('/forms/cached-form')->assertSee('Your full name')->assertDontSee('Your name');

        $form->fields()->create([
            'type' => 'email', 'label' => 'Work email', 'placeholder' => null,
            'options' => [], 'validation_rules' => [], 'order' => 2,
        ]);
        $this->get('/forms/cached-form')->assertSee('Work email');

        $field->delete();
        $this->get('/forms/cached-form')->assertDontSee('Your full name')->assertSee('Work email');
    }

    #[DataProvider('persistentStores')]
    public function test_a_bulk_field_delete_still_invalidates(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();

        $this->get('/forms/cached-form')->assertOk()->assertSee('Your name');

        $form->fields()->delete();
        $form->forgetPublicCache();

        $this->get('/forms/cached-form')->assertOk()->assertDontSee('Your name');
    }

    #[DataProvider('persistentStores')]
    public function test_reverting_to_a_version_without_fields_clears_the_cached_fields(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();
        $owner = User::query()->find($form->user_id);

        $this->actingAs($owner);

        $empty = $form->versions()->create([
            'version_number' => 1,
            'title' => 'Cached Form',
            'description' => null,
            'settings' => [],
            'fields_snapshot' => [],
        ]);

        $this->get('/forms/cached-form')->assertOk()->assertSee('Your name');

        // A visitor lands between the revert's form update and its bulk field
        // delete (which fires no model events) and re-warms the cache with
        // the old fields: the revert must clear that again at the end.
        Event::listen('eloquent.saved: '.Form::class, function (Form $saved): void {
            app(PublicFormCache::class)->remember($saved->slug, false, fn () => Form::withoutTeamScope()
                ->with('fields')->findOrFail($saved->id));
        });

        Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form])
            ->call('revertToVersion', $empty->id);

        $this->get('/forms/cached-form')->assertOk()->assertDontSee('Your name');
    }

    #[DataProvider('persistentStores')]
    public function test_publishing_from_the_builder_invalidates(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm(['is_published' => false, 'slug' => 'draft-form']);
        $owner = User::query()->find($form->user_id);
        $team = $owner->currentTeam;

        $this->actingAs($owner);
        // Preview is cached under its own key and must not outlive a publish.
        $this->get('/forms/draft-form?preview=1')->assertOk();
        $this->get('/forms/draft-form')->assertNotFound();

        Livewire::test(Builder::class, ['team' => $team, 'form' => $form])
            ->set('title', 'Now Live')
            ->call('publish');

        $this->get('/forms/draft-form')->assertOk()->assertSee('Now Live');
        $this->get('/forms/draft-form?preview=1')->assertOk()->assertSee('Now Live');
    }

    #[DataProvider('persistentStores')]
    public function test_reverting_to_a_version_from_the_builder_invalidates(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();
        $owner = User::query()->find($form->user_id);
        $team = $owner->currentTeam;

        $this->actingAs($owner);

        $component = Livewire::test(Builder::class, ['team' => $team, 'form' => $form])
            ->call('saveDraft') // version 1 keeps the original title
            ->set('title', 'Second Title')
            ->call('publish');

        $this->get('/forms/cached-form')->assertOk()->assertSee('Second Title');

        $first = $form->fresh()->versions()->orderBy('version_number')->first();

        $component->call('revertToVersion', $first->id);

        $this->get('/forms/cached-form')->assertOk()->assertDontSee('Second Title');
    }
}
