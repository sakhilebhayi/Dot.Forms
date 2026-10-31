<?php

namespace App\Exports;

use App\Models\Form;
use App\Support\FieldTypes;
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

        return array_merge(['Submission ID', 'Submitted At'], $labels);
    }

    public function array(): array
    {
        $rows = [];

        $submissions = $this->form
            ->submissions()
            ->latest('submitted_at')
            ->get();

        foreach ($submissions as $submission) {
            $row = [
                $submission->id,
                optional($submission->submitted_at)?->toDateTimeString(),
            ];

            foreach ($this->form->fields->filter(fn ($f) => FieldTypes::collectsAnswer($f->type))->whereIn('id', $this->fieldIds) as $field) {
                $value = $submission->data[$field->id] ?? null;

                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }

                if (is_array($value)) {
                    $value = implode(', ', array_map('strval', $value));
                }

                $row[] = (string) ($value ?? '');
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
