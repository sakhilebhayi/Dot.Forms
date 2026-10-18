<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Ten-minute cache of the form a public (or preview) URL resolves to.
 *
 * It stores plain data, never a model: config/cache.php sets
 * serializable_classes to false (a security control: a poisoned cache entry
 * must not be able to instantiate arbitrary classes), so a persistent store
 * hands any cached object back as __PHP_Incomplete_Class. The payload is the
 * form's and its fields' raw attribute arrays (strings, ints and nulls as
 * they sit in the database), and the models are rebuilt from them on read.
 *
 * The key is namespaced (v2) so entries written by the earlier release, which
 * cached the model itself, are never read back. An entry that is not shaped
 * like a payload is treated as a miss.
 *
 * Every write path that can change what a visitor sees calls forget(): the
 * Form and FormField model events do it for saves and deletes, and the code
 * that deletes fields in bulk (which fires no model events) calls
 * Form::forgetPublicCache() itself.
 */
class PublicFormCache
{
    private const TTL_MINUTES = 10;

    private const KEY_PREFIX = 'form:public:v2:';

    /** Slugs longer than this are hashed so the key stays under the 255-character key limit. */
    private const MAX_RAW_SLUG_LENGTH = 100;

    private static int $deferDepth = 0;

    /** @var array<string, true> */
    private static array $deferredSlugs = [];

    public static function key(string $slug, bool $preview): string
    {
        $part = strlen($slug) > self::MAX_RAW_SLUG_LENGTH ? 'sha256:'.hash('sha256', $slug) : $slug;

        return self::KEY_PREFIX.$part.':'.($preview ? 'preview' : 'live');
    }

    /**
     * Run $callback with invalidation held back: forget() calls made inside
     * are collected and applied once, after the callback (and so after any
     * database transaction it wraps) has finished. Used by multi-write saves
     * such as the builder's, which would otherwise forget the same keys once
     * per field.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function deferring(callable $callback): mixed
    {
        self::$deferDepth++;

        try {
            return $callback();
        } finally {
            self::$deferDepth--;

            if (self::$deferDepth === 0) {
                $slugs = array_keys(self::$deferredSlugs);
                self::$deferredSlugs = [];

                self::forget(...$slugs);
            }
        }
    }

    /**
     * @param  callable(): Form  $resolve  Loads the form (with its fields) from the database on a miss.
     */
    public function remember(string $slug, bool $preview, callable $resolve): Form
    {
        $key = self::key($slug, $preview);

        // The cache is an optimisation: a store that is down or misconfigured
        // must never take the public page with it.
        try {
            $payload = Cache::get($key);
        } catch (Throwable $e) {
            report($e);
            $payload = null;
        }

        if ($this->isPayload($payload, $slug)) {
            return $this->hydrate($payload);
        }

        $form = $resolve();

        try {
            Cache::put($key, $this->dehydrate($form), now()->addMinutes(self::TTL_MINUTES));
        } catch (Throwable $e) {
            report($e);
        }

        return $form;
    }

    public static function forget(?string ...$slugs): void
    {
        foreach (array_unique(array_filter($slugs, fn (?string $slug) => $slug !== null && $slug !== '')) as $slug) {
            if (self::$deferDepth > 0) {
                self::$deferredSlugs[$slug] = true;

                continue;
            }

            try {
                Cache::forget(self::key($slug, false));
                Cache::forget(self::key($slug, true));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @return array{form: array<string, mixed>, fields: list<array<string, mixed>>}
     */
    private function dehydrate(Form $form): array
    {
        $fields = [];

        foreach ($form->fields as $field) {
            $fields[] = $field->getAttributes();
        }

        return ['form' => $form->getAttributes(), 'fields' => $fields];
    }

    /**
     * @param  array{form: array<string, mixed>, fields: list<array<string, mixed>>}  $payload
     */
    private function hydrate(array $payload): Form
    {
        $form = (new Form)->newFromBuilder($payload['form']);

        $form->setRelation('fields', new EloquentCollection(
            array_map(fn (array $attributes) => (new FormField)->newFromBuilder($attributes), $payload['fields'])
        ));

        return $form;
    }

    private function isPayload(mixed $payload, string $slug): bool
    {
        if (! is_array($payload)
            || ! is_array($payload['form'] ?? null)
            || ! isset($payload['form']['id'])
            || ($payload['form']['slug'] ?? null) !== $slug
            || ! is_array($payload['fields'] ?? null)) {
            return false;
        }

        foreach ($payload['fields'] as $field) {
            if (! is_array($field) || ! isset($field['id'])) {
                return false;
            }
        }

        return true;
    }
}
