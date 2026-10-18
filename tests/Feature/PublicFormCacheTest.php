<?php

namespace Tests\Feature;

use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Livewire\Forms\Builder;
use App\Livewire\Forms\PublicView;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\Team;
use App\Models\User;
use App\Services\PublicFormCache;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
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

    /**
     * @return array{0: User, 1: Team, 2: Form}
     */
    private function ownerWithSecondTeamForm(): array
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Agency', 'personal_team' => false]);

        $form = Form::query()->create([
            'team_id' => $team->id,
            'user_id' => $owner->id,
            'title' => 'Agency Form',
            'slug' => 'agency-form',
            'settings' => [],
            'is_published' => true,
        ]);

        $form->fields()->create([
            'type' => 'text', 'label' => 'Agency field', 'placeholder' => null,
            'options' => [], 'validation_rules' => [], 'order' => 1,
        ]);

        return [$owner, $team, $form];
    }

    #[DataProvider('persistentStores')]
    public function test_deleting_a_team_takes_its_public_and_preview_forms_offline(string $store): void
    {
        $this->usePersistentStore($store);
        [$owner, $team] = $this->ownerWithSecondTeamForm();

        $this->get('/forms/agency-form')->assertOk()->assertSee('Agency Form');
        $this->actingAs($owner)->get('/forms/agency-form?preview=1')->assertOk();

        app(DeleteTeam::class)->delete($team);

        $this->get('/forms/agency-form')->assertNotFound();
        $this->actingAs($owner->fresh())->get('/forms/agency-form?preview=1')->assertNotFound();
    }

    #[DataProvider('persistentStores')]
    public function test_deleting_a_user_takes_the_forms_they_created_offline(string $store): void
    {
        $this->usePersistentStore($store);
        [$owner, $team, $form] = $this->ownerWithSecondTeamForm();

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'editor']);
        $form->update(['user_id' => $member->id]);

        $this->get('/forms/agency-form')->assertOk();
        $this->actingAs($owner)->get('/forms/agency-form?preview=1')->assertOk();

        app(DeleteUser::class)->delete($member);

        $this->assertNull(Form::withoutTeamScope()->find($form->id));
        $this->get('/forms/agency-form')->assertNotFound();
        $this->actingAs($owner->fresh())->get('/forms/agency-form?preview=1')->assertNotFound();
    }

    #[DataProvider('persistentStores')]
    public function test_a_cached_preview_whose_team_is_gone_is_a_404_not_a_500(string $store): void
    {
        $this->usePersistentStore($store);
        [$owner, $team] = $this->ownerWithSecondTeamForm();

        $this->actingAs($owner)->get('/forms/agency-form?preview=1')->assertOk();

        // Remove the team behind the cache's back (a cascade fires no events).
        DB::table('teams')->where('id', $team->id)->delete();
        $this->assertSame(0, Form::withoutTeamScope()->where('slug', 'agency-form')->count());

        $this->actingAs($owner->fresh())->get('/forms/agency-form?preview=1')->assertNotFound();
    }

    public function test_the_page_still_renders_when_the_cache_store_is_unavailable(): void
    {
        $this->usePersistentStore('database');
        $form = $this->publishedForm();

        Schema::drop('cache');

        $this->get('/forms/cached-form')->assertOk()->assertSee('Cached Form');
        $this->get('/forms/cached-form')->assertOk();

        // And a save does not blow up on the failed invalidation either.
        $form->update(['title' => 'Edited while cache is down']);

        $this->get('/forms/cached-form')->assertOk()->assertSee('Edited while cache is down');
    }

    #[DataProvider('persistentStores')]
    public function test_a_payload_with_a_malformed_field_entry_is_a_miss(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();

        Cache::put(PublicFormCache::key('cached-form', false), [
            'form' => $form->getAttributes(),
            'fields' => ['not-an-array', ['no_id' => true]],
        ], 600);

        $this->get('/forms/cached-form')->assertOk()->assertSee('Your name');
    }

    #[DataProvider('persistentStores')]
    public function test_a_payload_for_a_different_slug_is_never_served(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();
        $this->publishedForm(['title' => 'Other Form', 'slug' => 'other-form']);

        Cache::put(PublicFormCache::key('other-form', false), [
            'form' => $form->getAttributes(),
            'fields' => [],
        ], 600);

        $this->get('/forms/other-form')->assertOk()->assertSee('Other Form');
    }

    #[DataProvider('persistentStores')]
    public function test_very_long_slugs_keep_the_cache_key_short_and_distinct(string $store): void
    {
        $this->usePersistentStore($store);
        $a = str_repeat('a', 300);
        $b = str_repeat('a', 299).'b';

        foreach ([true, false] as $preview) {
            $this->assertLessThanOrEqual(200, strlen(PublicFormCache::key($a, $preview)));
        }
        $this->assertNotSame(PublicFormCache::key($a, false), PublicFormCache::key($b, false));
        $this->assertNotSame(PublicFormCache::key($a, false), PublicFormCache::key($a, true));

        $this->publishedForm(['slug' => $a]);
        $this->get('/forms/'.$a)->assertOk();
        $this->get('/forms/'.$a)->assertOk()->assertSee('Cached Form');
    }

    private function builderFor(Form $form): Testable
    {
        $owner = User::query()->find($form->user_id);
        $this->actingAs($owner);

        return Livewire::test(Builder::class, ['team' => $owner->currentTeam, 'form' => $form]);
    }

    #[DataProvider('persistentStores')]
    public function test_autosaving_a_many_field_form_issues_a_bounded_number_of_cache_operations(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();
        foreach (range(2, 30) as $order) {
            $form->fields()->create([
                'type' => 'text', 'label' => 'Field '.$order, 'placeholder' => null,
                'options' => [], 'validation_rules' => [], 'order' => $order,
            ]);
        }

        $builder = $this->builderFor($form);
        $this->get('/forms/cached-form')->assertOk();

        $forgets = 0;
        Event::listen([KeyForgotten::class, KeyForgetFailed::class], function () use (&$forgets): void {
            $forgets++;
        });
        $cacheQueries = 0;
        DB::listen(function ($query) use (&$cacheQueries): void {
            if (str_contains($query->sql, '"cache"')) {
                $cacheQueries++;
            }
        });

        $builder->call('saveDraft')->assertHasNoErrors();

        // Once per key (live + preview), not once per saved field.
        $this->assertLessThanOrEqual(4, $forgets, 'autosave must forget the cached form once, not once per field');

        if ($store === 'database') {
            $this->assertLessThanOrEqual(10, $cacheQueries);
        }

        $this->assertSame(30, $form->fields()->count());
    }

    #[DataProvider('persistentStores')]
    public function test_a_visitor_reading_mid_save_cannot_leave_a_partial_form_cached(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm(['is_published' => true]);
        $builder = $this->builderFor($form);

        foreach (range(2, 6) as $order) {
            $builder->call('addField', 'text');
        }

        // A visitor arrives after every field insert and caches whatever it sees.
        Event::listen('eloquent.saved: '.FormField::class, function (): void {
            app(PublicFormCache::class)->remember('cached-form', false, fn () => Form::withoutTeamScope()
                ->with('fields')->where('slug', 'cached-form')->firstOrFail());
        });

        $builder->call('publish');

        $seen = Form::withoutTeamScope()->where('slug', 'cached-form')->firstOrFail()->fields()->count();
        $this->assertGreaterThan(1, $seen);

        $payload = Cache::get(PublicFormCache::key('cached-form', false));
        $this->assertTrue($payload === null || count($payload['fields']) === $seen, 'a stale partial form was left in the cache');
    }

    #[DataProvider('persistentStores')]
    public function test_a_failure_mid_save_rolls_back_the_whole_builder_save(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();
        $builder = $this->builderFor($form);
        $builder->call('addField', 'text')->call('addField', 'text');

        $created = 0;
        Event::listen('eloquent.creating: '.FormField::class, function () use (&$created): void {
            if (++$created === 2) {
                throw new \RuntimeException('boom');
            }
        });

        try {
            $builder->set('title', 'Half saved')->call('saveDraft');
        } catch (\RuntimeException) {
            // expected
        }

        $form = $form->fresh();
        $this->assertSame('Cached Form', $form->title);
        $this->assertSame(1, $form->fields()->count());
    }

    #[DataProvider('persistentStores')]
    public function test_a_failure_mid_revert_rolls_back_the_whole_revert(string $store): void
    {
        $this->usePersistentStore($store);
        $form = $this->publishedForm();
        $builder = $this->builderFor($form);

        $version = $form->versions()->create([
            'version_number' => 1,
            'title' => 'Old title',
            'description' => null,
            'settings' => [],
            'fields_snapshot' => [
                ['type' => 'text', 'label' => 'A'],
                ['type' => 'text', 'label' => 'B'],
            ],
        ]);

        $created = 0;
        Event::listen('eloquent.creating: '.FormField::class, function () use (&$created): void {
            if (++$created === 2) {
                throw new \RuntimeException('boom');
            }
        });

        try {
            $builder->call('revertToVersion', $version->id);
        } catch (\RuntimeException) {
            // expected
        }

        $form = $form->fresh();
        $this->assertSame('Cached Form', $form->title);
        $this->assertSame(['Your name'], $form->fields()->pluck('label')->all());
    }
}
