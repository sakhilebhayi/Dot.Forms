<?php

namespace App\Exports;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Support\FieldTypes;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FormSubmissionsExport implements FromArray, WithHeadings
{
    public function __construct(
        protected Form $form,
        protected array $fieldIds
    ) {}

    public function headings(): array
    {
        $labels = $this->form->fields
            ->filter(fn ($field) => FieldTypes::collectsAnswer($field->type))
            ->whereIn('id', $this->fieldIds)
            ->pluck('label')
            ->values()
            ->all();

        return array_merge(['Submission ID', 'Submitted At', 'Status', 'Held By', 'Decision', 'Decided By', 'Decided At'], $labels);
    }

    public function array(): array
    {
        $rows = [];

        /** @var Collection<int, FormSubmission> $submissions */
        $submissions = $this->form
            ->submissions()
            ->with('assignee', 'decider')
            ->latest('submitted_at')
            ->get();

        foreach ($submissions as $submission) {
            $row = [
                $submission->id,
                optional($submission->submitted_at)?->toDateTimeString(),
                FormSubmission::STATUS_LABELS[$submission->status] ?? $submission->status,
                $submission->assignee->name ?? '',
                $submission->decision ? strtoupper($submission->decision) : '',
                $submission->decider->name ?? '',
                $submission->decided_at?->toDateTimeString() ?? '',
            ];

            foreach ($this->form->fields->filter(fn ($f) => FieldTypes::collectsAnswer($f->type))->whereIn('id', $this->fieldIds) as $field) {
                $value = $submission->data[$field->id] ?? null;

                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                if (is_array($value)) {
                    $value = array_is_list($value)
                        ? implode(', ', array_map('strval', $value))
                        : collect($value)->map(fn ($v, $k) => $k.': '.$v)->implode('; ');
                }

                $row[] = (string) ($value ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
