<?php

namespace App\Support;

use App\Models\FormField;

/**
 * The one registry of what a field can be (R-30 Phase 2). The builder's tray, the save-time
 * whitelist and stored rules, the public form's rendering and validation, and the AI builder
 * all read from here — a type exists only when every one of those can honour it. A type that
 * cannot store and validate a real answer does not belong in this list (brief §10).
 */
class FieldTypes
{
    /** Structural: starts a new page on the published form; collects nothing. */
    public const PAGE_BREAK = 'page_break';

    /** Content elements (R-31): they render on the document but never collect an answer. */
    public const DISPLAY_TYPES = ['heading', 'text_block', 'image', 'video', 'divider'];

    /** Every type the builder may save, in tray order within their groups. */
    public const ALL = [
        // text and contact
        'text', 'textarea', 'email', 'phone', 'url',
        // numbers
        'number', 'currency', 'percentage', 'rating', 'scale',
        // choices
        'select', 'multiselect', 'radio', 'yesno', 'checkbox', 'consent', 'matrix', 'ranking',
        // date and time
        'date', 'time', 'datetime',
        // attachments
        'file', 'signature',
        // composites and computation
        'address', 'calculated',
        // content (collects nothing; tells the story between questions)
        'heading', 'text_block', 'image', 'video', 'divider',
        // other
        'country', 'hidden', self::PAGE_BREAK,
    ];

    /** Types whose options come from the builder's comma-separated list. */
    public const CHOICE_TYPES = ['select', 'multiselect', 'radio', 'matrix', 'ranking'];

    /** @return array<string, array<int, string>> tray groups, label => types */
    public static function groups(): array
    {
        return [
            'Text & contact' => ['text', 'textarea', 'email', 'phone', 'url'],
            'Numbers' => ['number', 'currency', 'percentage', 'rating', 'scale'],
            'Choices' => ['select', 'multiselect', 'radio', 'yesno', 'checkbox', 'consent', 'matrix', 'ranking'],
            'Date & time' => ['date', 'time', 'datetime'],
            'Attachments' => ['file', 'signature'],
            'Business' => ['address', 'calculated'],
            'Content' => ['heading', 'text_block', 'image', 'video', 'divider'],
            'Structure' => ['country', 'hidden', self::PAGE_BREAK],
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            'text' => 'Short text',
            'textarea' => 'Long text',
            'email' => 'Email',
            'phone' => 'Phone number',
            'url' => 'Website',
            'number' => 'Number',
            'currency' => 'Currency (R)',
            'percentage' => 'Percentage',
            'rating' => 'Rating (1–5)',
            'scale' => 'Scale (0–10)',
            'select' => 'Dropdown',
            'multiselect' => 'Multiple choice',
            'radio' => 'Single choice',
            'yesno' => 'Yes / No',
            'checkbox' => 'Checkbox',
            'consent' => 'Consent',
            'matrix' => 'Matrix (rows × choices)',
            'ranking' => 'Ranking',
            'address' => 'Address',
            'calculated' => 'Calculated value',
            'date' => 'Date',
            'time' => 'Time',
            'datetime' => 'Date and time',
            'file' => 'File upload',
            'signature' => 'Signature',
            'country' => 'Country',
            'hidden' => 'Hidden value',
            'heading' => 'Section heading',
            'text_block' => 'Text block',
            'image' => 'Image',
            'video' => 'Video embed',
            'divider' => 'Divider',
            self::PAGE_BREAK => 'Page break',
            default => ucfirst($type),
        };
    }

    /** The small mono mark on the tray button. */
    public static function mark(string $type): string
    {
        return match ($type) {
            'text' => 'Aa', 'textarea' => '¶', 'email' => '@', 'phone' => '✆', 'url' => '://',
            'number' => '#', 'currency' => 'R', 'percentage' => '%', 'rating' => '★', 'scale' => '10',
            'select' => '▾', 'multiselect' => '☑☑', 'radio' => '◉', 'yesno' => 'Y/N',
            'checkbox' => '☑', 'consent' => '§', 'matrix' => '▦', 'ranking' => '1·2', 'address' => '⌂', 'calculated' => '∑',
            'date' => '31', 'time' => '⏱', 'datetime' => '31⏱',
            'file' => '↥', 'signature' => '✎',
            'country' => 'ZA', 'hidden' => '·', self::PAGE_BREAK => '⤵',
            'heading' => 'H2', 'text_block' => 'Tx', 'image' => '▣', 'video' => '▶', 'divider' => '—',
            default => '·',
        };
    }

    public static function collectsAnswer(string $type): bool
    {
        return $type !== self::PAGE_BREAK && ! in_array($type, self::DISPLAY_TYPES, true);
    }

    /** Content element (R-31): rendered unboxed and unnumbered on the document. */
    public static function isDisplay(string $type): bool
    {
        return in_array($type, self::DISPLAY_TYPES, true);
    }

    public static function usesChoices(string $type): bool
    {
        return in_array($type, self::CHOICE_TYPES, true);
    }

    /**
     * The rules stored on the field row at save time (type-intrinsic; `required`
     * is added separately from the builder's checkbox).
     *
     * @return array<int, string>
     */
    public static function storedRules(string $type): array
    {
        return match ($type) {
            'email' => ['email'],
            'url' => ['url'],
            'phone' => ['regex:/^[0-9+()\\-\\s]{6,20}$/'],
            'number' => ['numeric'],
            'currency' => ['numeric', 'min:0'],
            'percentage' => ['numeric', 'min:0', 'max:100'],
            'rating' => ['integer', 'min:1', 'max:5'],
            'scale' => ['integer', 'min:0', 'max:10'],
            'date' => ['date'],
            'time' => ['date_format:H:i'],
            'datetime' => ['date'],
            'yesno' => ['in:Yes,No'],
            default => [],
        };
    }

    /**
     * The public form's validation for one visible field. Returns rules keyed the way
     * PublicView binds them ('answers.{id}', plus 'answers.{id}.*' for arrays).
     *
     * @return array<string, array<int, string>>
     */
    public static function publicRules(FormField $field): array
    {
        $id = (int) $field->id;
        $required = in_array('required', $field->validation_rules ?? [], true);
        $base = $required ? ['required'] : ['nullable'];
        $choices = array_map('strval', $field->options['choices'] ?? []);
        $choiceRule = 'in:'.implode(',', str_replace(',', '', $choices));

        $rows = array_map('strval', $field->options['rows'] ?? []);

        return match ($field->type) {
            'matrix' => array_merge(
                ['answers.'.$id => $required ? ['required', 'array', 'size:'.max(1, count($rows))] : ['nullable', 'array']],
                ['answers.'.$id.'.*' => $choices === [] ? ['string', 'max:255'] : [$choiceRule]],
            ),
            'ranking' => [
                'answers.'.$id => $required ? ['required', 'array', 'size:'.max(1, count($choices))] : ['nullable', 'array'],
                'answers.'.$id.'.*' => array_merge(['distinct'], $choices === [] ? ['string', 'max:255'] : [$choiceRule]),
            ],
            'address' => [
                'answers.'.$id => array_merge($base, ['array']),
                'answers.'.$id.'.street' => [$required ? 'required' : 'nullable', 'string', 'max:255'],
                'answers.'.$id.'.city' => [$required ? 'required' : 'nullable', 'string', 'max:255'],
                'answers.'.$id.'.province' => ['nullable', 'string', 'max:255'],
                'answers.'.$id.'.code' => ['nullable', 'string', 'max:20'],
            ],
            'calculated' => [], // computed server-side at submit; never user input
            'multiselect' => [
                'answers.'.$id => array_merge($base, ['array']),
                'answers.'.$id.'.*' => $choices === [] ? ['string', 'max:255'] : [$choiceRule],
            ],
            'select', 'radio' => [
                'answers.'.$id => array_merge($base, $choices === [] ? ['string', 'max:255'] : [$choiceRule]),
            ],
            'country' => [
                'answers.'.$id => array_merge($base, ['in:'.implode(',', self::COUNTRIES)]),
            ],
            'consent' => [
                'answers.'.$id => $required ? ['accepted'] : ['boolean'],
            ],
            'checkbox' => [
                'answers.'.$id => array_merge($base, ['boolean']),
            ],
            'signature' => [
                // A drawn signature arrives as a PNG data URL; ~200 KB of base64 is a
                // generous hand-drawn mark and a safe upper bound for the shared host.
                'answers.'.$id => array_merge($base, ['string', 'starts_with:data:image/png;base64,', 'max:280000']),
            ],
            'hidden' => [
                'answers.'.$id => ['nullable', 'string', 'max:1000'],
            ],
            'textarea' => [
                'answers.'.$id => array_merge($base, ['string', 'max:10000']),
            ],
            'text' => [
                'answers.'.$id => array_merge($base, ['string', 'max:1000']),
            ],
            default => [
                'answers.'.$id => array_merge($base, self::storedRules($field->type)),
            ],
        };
    }

    /** UN member states plus common dependencies, South Africa's neighbours first. */
    public const COUNTRIES = [
        'South Africa', 'Botswana', 'Eswatini', 'Lesotho', 'Mozambique', 'Namibia', 'Zimbabwe',
        'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda',
        'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain',
        'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia',
        'Bosnia and Herzegovina', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi',
        'Cabo Verde', 'Cambodia', 'Cameroon', 'Canada', 'Central African Republic', 'Chad',
        'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica', 'Croatia', 'Cuba',
        'Cyprus', 'Czechia', 'Democratic Republic of the Congo', 'Denmark', 'Djibouti',
        'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea',
        'Eritrea', 'Estonia', 'Ethiopia', 'Fiji', 'Finland', 'France', 'Gabon', 'Gambia',
        'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea',
        'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India',
        'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy', 'Ivory Coast', 'Jamaica',
        'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan', 'Laos',
        'Latvia', 'Lebanon', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg',
        'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands',
        'Mauritania', 'Mauritius', 'Mexico', 'Micronesia', 'Moldova', 'Monaco', 'Mongolia',
        'Montenegro', 'Morocco', 'Myanmar', 'Nauru', 'Nepal', 'Netherlands', 'New Zealand',
        'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway', 'Oman',
        'Pakistan', 'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru',
        'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda',
        'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa',
        'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia',
        'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands',
        'Somalia', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname',
        'Sweden', 'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand',
        'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey',
        'Turkmenistan', 'Tuvalu', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom',
        'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela',
        'Vietnam', 'Yemen', 'Zambia', 'Other',
    ];
}
