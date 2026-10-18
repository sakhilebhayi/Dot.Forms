<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

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

    public static function key(string $slug, bool $preview): string
    {
        return self::KEY_PREFIX.$slug.':'.($preview ? 'preview' : 'live');
    }

    /**
     * @param  callable(): Form  $resolve  Loads the form (with its fields) from the database on a miss.
     */
    public function remember(string $slug, bool $preview, callable $resolve): Form
    {
        $key = self::key($slug, $preview);

        $payload = Cache::get($key);

        if ($this->isPayload($payload)) {
            return $this->hydrate($payload);
        }

        $form = $resolve();

        Cache::put($key, $this->dehydrate($form), now()->addMinutes(self::TTL_MINUTES));

        return $form;
    }

    public static function forget(?string ...$slugs): void
    {
        foreach (array_unique(array_filter($slugs, fn (?string $slug) => $slug !== null && $slug !== '')) as $slug) {
            Cache::forget(self::key($slug, false));
            Cache::forget(self::key($slug, true));
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

    private function isPayload(mixed $payload): bool
    {
        return is_array($payload)
            && is_array($payload['form'] ?? null)
            && isset($payload['form']['id'])
            && is_array($payload['fields'] ?? null);
    }
}
