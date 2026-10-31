<?php

namespace App\Services;

use App\Models\FormField;
use Illuminate\Support\Collection;

/**
 * Evaluates a field's visibility against the current answers (R-30 logic v2).
 *
 * Two shapes are honoured:
 * - `options['visibility']` — the current one: ['logic' => 'all'|'any',
 *   'rules' => [['trigger_field_key','operator','value'], …]] with operators
 *   equals / not_equals / contains / empty / not_empty / greater_than / less_than.
 * - `options['visibility_rule']` — the legacy single rule, still present on forms and
 *   version snapshots saved before logic v2. It is evaluated exactly as before.
 *
 * A rule references its trigger field by a stable key (options['field_key']), not a DB id,
 * because every field is deleted and recreated on each Builder save — ids don't survive
 * that, keys do. A rule whose trigger no longer exists fails open rather than permanently
 * hiding a field the respondent can no longer make visible.
 */
class FormFieldVisibilityEvaluator
{
    public const OPERATORS = ['equals', 'not_equals', 'contains', 'empty', 'not_empty', 'greater_than', 'less_than'];

    /** The operators whose rule needs no comparison value. */
    public const VALUELESS = ['empty', 'not_empty'];

    /**
     * @param  Collection<int, FormField>  $allFields  Every field on the form (to resolve triggers by key).
     * @param  array<int, mixed>  $answers  Current answers keyed by field id.
     */
    public function isVisible(FormField $field, Collection $allFields, array $answers): bool
    {
        $visibility = $field->options['visibility'] ?? null;

        if (is_array($visibility) && ! empty($visibility['rules']) && is_array($visibility['rules'])) {
            $logic = ($visibility['logic'] ?? 'all') === 'any' ? 'any' : 'all';
            $results = [];

            foreach ($visibility['rules'] as $rule) {
                if (! is_array($rule) || empty($rule['trigger_field_key'])) {
                    continue;
                }

                $results[] = $this->ruleMatches($rule, $allFields, $answers);
            }

            if ($results === []) {
                return true;
            }

            return $logic === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
        }

        // Legacy single rule.
        $rule = $field->options['visibility_rule'] ?? null;

        if (! is_array($rule) || empty($rule['trigger_field_key'])) {
            return true;
        }

        return $this->ruleMatches($rule, $allFields, $answers);
    }

    /** @param  Collection<int, FormField>  $allFields */
    private function ruleMatches(array $rule, Collection $allFields, array $answers): bool
    {
        $operator = in_array($rule['operator'] ?? null, self::OPERATORS, true) ? $rule['operator'] : 'equals';
        $expected = (string) ($rule['value'] ?? '');

        $triggerField = $allFields->first(
            fn (FormField $candidate) => ($candidate->options['field_key'] ?? null) === $rule['trigger_field_key']
        );

        if (! $triggerField) {
            return true; // fail open: see the class doc
        }

        $raw = $answers[$triggerField->id] ?? null;
        $actual = $this->normalize($raw);
        $expectedNormalized = $this->normalize($expected);

        return match ($operator) {
            'not_equals' => $actual !== $expectedNormalized,
            'contains' => $expectedNormalized !== '' && str_contains($actual, $expectedNormalized),
            'empty' => $actual === '',
            'not_empty' => $actual !== '',
            'greater_than' => is_numeric($actual) && is_numeric($expectedNormalized) && (float) $actual > (float) $expectedNormalized,
            'less_than' => is_numeric($actual) && is_numeric($expectedNormalized) && (float) $actual < (float) $expectedNormalized,
            default => $actual === $expectedNormalized, // equals
        };
    }

    private function normalize(mixed $value): string
    {
        if (is_array($value)) {
            // Multi-choice fields submit several values.
            return strtolower(implode(',', array_map('strval', $value)));
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return strtolower(trim((string) ($value ?? '')));
    }
}
