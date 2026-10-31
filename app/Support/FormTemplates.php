<?php

namespace App\Support;

/**
 * The built-in master templates (R-30 Phase 3, brief §19): genuinely usable South African
 * business forms, not placeholders. Each field carries a symbolic `key` local to its
 * template; FormTemplateService remaps keys to fresh uuids when a template is used, so
 * visibility rules survive instantiation. A template may only use registry types.
 */
class FormTemplates
{
    /** @return array<string, array<string, mixed>> keyed by template slug */
    public static function all(): array
    {
        return [
            'leave-application' => [
                'name' => 'Leave application',
                'group' => 'Human resources',
                'description' => 'Leave type, dates and the supporting note when it is sick leave.',
                'settings' => ['confirmation_message' => 'Your leave application is in — you will hear back once it has been decided.'],
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Employee full name', 'required' => true],
                    ['key' => 'number', 'type' => 'text', 'label' => 'Employee number', 'placeholder' => 'As on your payslip'],
                    ['key' => 'type', 'type' => 'select', 'label' => 'Leave type', 'required' => true, 'choices' => ['Annual', 'Sick', 'Family responsibility', 'Maternity / parental', 'Unpaid']],
                    ['key' => 'from', 'type' => 'date', 'label' => 'First day of leave', 'required' => true],
                    ['key' => 'to', 'type' => 'date', 'label' => 'Last day of leave', 'required' => true],
                    ['key' => 'reason', 'type' => 'textarea', 'label' => 'Reason', 'helper' => 'A sentence is enough for annual leave.'],
                    ['key' => 'note', 'type' => 'file', 'label' => 'Doctor\'s note', 'helper' => 'Required for more than two consecutive sick days.',
                        'visibility' => ['logic' => 'all', 'rules' => [['trigger_field_key' => 'type', 'operator' => 'equals', 'value' => 'Sick']]]],
                ],
            ],

            'job-application' => [
                'name' => 'Job application',
                'group' => 'Human resources',
                'description' => 'Candidate details, the role, CV upload and POPIA consent, over two pages.',
                'settings' => ['consent_required' => false],
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Full name', 'required' => true],
                    ['key' => 'email', 'type' => 'email', 'label' => 'Email address', 'required' => true],
                    ['key' => 'phone', 'type' => 'phone', 'label' => 'Phone number', 'required' => true],
                    ['key' => 'country', 'type' => 'country', 'label' => 'Country of residence', 'required' => true],
                    ['key' => 'brk', 'type' => 'page_break', 'label' => 'Page break'],
                    ['key' => 'role', 'type' => 'text', 'label' => 'Position applied for', 'required' => true],
                    ['key' => 'experience', 'type' => 'scale', 'label' => 'Years of relevant experience (0–10+)'],
                    ['key' => 'cv', 'type' => 'file', 'label' => 'CV (PDF)', 'required' => true],
                    ['key' => 'letter', 'type' => 'textarea', 'label' => 'Short motivation'],
                    ['key' => 'consent', 'type' => 'consent', 'label' => 'I consent to this information being processed for recruitment (POPIA)', 'required' => true],
                ],
            ],

            'employee-onboarding' => [
                'name' => 'Employee onboarding',
                'group' => 'Human resources',
                'description' => 'What payroll and IT need on day one, with banking details on their own page.',
                'settings' => [],
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Full name', 'required' => true],
                    ['key' => 'id', 'type' => 'text', 'label' => 'ID / passport number', 'required' => true],
                    ['key' => 'start', 'type' => 'date', 'label' => 'Start date', 'required' => true],
                    ['key' => 'email', 'type' => 'email', 'label' => 'Personal email', 'required' => true],
                    ['key' => 'phone', 'type' => 'phone', 'label' => 'Phone number', 'required' => true],
                    ['key' => 'brk', 'type' => 'page_break', 'label' => 'Page break'],
                    ['key' => 'tax', 'type' => 'text', 'label' => 'SARS tax number'],
                    ['key' => 'bank', 'type' => 'select', 'label' => 'Bank', 'choices' => ['Absa', 'Capitec', 'FNB', 'Nedbank', 'Standard Bank', 'TymeBank', 'Other']],
                    ['key' => 'account', 'type' => 'text', 'label' => 'Account number'],
                    ['key' => 'shirt', 'type' => 'select', 'label' => 'Shirt size', 'choices' => ['S', 'M', 'L', 'XL', 'XXL']],
                    ['key' => 'consent', 'type' => 'consent', 'label' => 'I consent to this information being processed for employment administration', 'required' => true],
                ],
            ],

            'exit-interview' => [
                'name' => 'Exit interview',
                'group' => 'Human resources',
                'description' => 'Why people leave, what they would change, and whether they would return.',
                'settings' => [],
                'fields' => [
                    ['key' => 'role', 'type' => 'text', 'label' => 'Your role', 'required' => true],
                    ['key' => 'tenure', 'type' => 'select', 'label' => 'Time with the company', 'choices' => ['Under 1 year', '1–3 years', '3–5 years', 'Over 5 years']],
                    ['key' => 'reason', 'type' => 'multiselect', 'label' => 'Main reasons for leaving', 'choices' => ['Pay', 'Growth', 'Management', 'Workload', 'Relocation', 'Career change', 'Other']],
                    ['key' => 'rating', 'type' => 'rating', 'label' => 'Your overall experience here'],
                    ['key' => 'return', 'type' => 'yesno', 'label' => 'Would you work here again?'],
                    ['key' => 'why', 'type' => 'textarea', 'label' => 'What should change?',
                        'visibility' => ['logic' => 'any', 'rules' => [
                            ['trigger_field_key' => 'rating', 'operator' => 'less_than', 'value' => '4'],
                            ['trigger_field_key' => 'return', 'operator' => 'equals', 'value' => 'No'],
                        ]]],
                ],
            ],

            'training-evaluation' => [
                'name' => 'Training evaluation',
                'group' => 'Human resources',
                'description' => 'Was the course worth the day: content, trainer, usefulness.',
                'settings' => [],
                'fields' => [
                    ['key' => 'course', 'type' => 'text', 'label' => 'Course attended', 'required' => true],
                    ['key' => 'date', 'type' => 'date', 'label' => 'Date attended'],
                    ['key' => 'content', 'type' => 'rating', 'label' => 'Content quality', 'required' => true],
                    ['key' => 'trainer', 'type' => 'rating', 'label' => 'Trainer', 'required' => true],
                    ['key' => 'useful', 'type' => 'scale', 'label' => 'How useful will this be in your work?'],
                    ['key' => 'again', 'type' => 'yesno', 'label' => 'Should we run it again?'],
                    ['key' => 'comments', 'type' => 'textarea', 'label' => 'Comments'],
                ],
            ],

            'expense-claim' => [
                'name' => 'Expense claim',
                'group' => 'Finance',
                'description' => 'One expense per claim, with the slip attached and VAT split out.',
                'settings' => [],
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Claimant', 'required' => true],
                    ['key' => 'date', 'type' => 'date', 'label' => 'Date of expense', 'required' => true],
                    ['key' => 'category', 'type' => 'select', 'label' => 'Category', 'required' => true, 'choices' => ['Travel', 'Fuel', 'Accommodation', 'Meals', 'Stationery', 'Airtime / data', 'Other']],
                    ['key' => 'amount', 'type' => 'currency', 'label' => 'Amount (incl. VAT)', 'required' => true],
                    ['key' => 'vat', 'type' => 'yesno', 'label' => 'Is VAT shown on the slip?'],
                    ['key' => 'slip', 'type' => 'file', 'label' => 'Slip or invoice', 'required' => true],
                    ['key' => 'notes', 'type' => 'textarea', 'label' => 'What was it for?'],
                ],
            ],

            'purchase-request' => [
                'name' => 'Purchase request',
                'group' => 'Finance',
                'description' => 'What is needed, from whom, for how much — with a motivation above R10 000.',
                'settings' => [],
                'fields' => [
                    ['key' => 'requester', 'type' => 'text', 'label' => 'Requested by', 'required' => true],
                    ['key' => 'item', 'type' => 'text', 'label' => 'Item or service', 'required' => true],
                    ['key' => 'supplier', 'type' => 'text', 'label' => 'Preferred supplier'],
                    ['key' => 'amount', 'type' => 'currency', 'label' => 'Estimated amount', 'required' => true],
                    ['key' => 'needed', 'type' => 'date', 'label' => 'Needed by'],
                    ['key' => 'motivation', 'type' => 'textarea', 'label' => 'Motivation', 'helper' => 'Required for amounts over R10 000.',
                        'visibility' => ['logic' => 'all', 'rules' => [['trigger_field_key' => 'amount', 'operator' => 'greater_than', 'value' => '10000']]]],
                    ['key' => 'quote', 'type' => 'file', 'label' => 'Quotation'],
                ],
            ],

            'incident-report' => [
                'name' => 'Incident report',
                'group' => 'Operations',
                'description' => 'What happened, where, injuries and photos — injury details only appear when needed.',
                'settings' => [],
                'fields' => [
                    ['key' => 'reporter', 'type' => 'text', 'label' => 'Reported by', 'required' => true],
                    ['key' => 'when', 'type' => 'datetime', 'label' => 'When it happened', 'required' => true],
                    ['key' => 'where', 'type' => 'text', 'label' => 'Location / site', 'required' => true],
                    ['key' => 'severity', 'type' => 'select', 'label' => 'Severity', 'required' => true, 'choices' => ['Near miss', 'Minor', 'Serious', 'Critical']],
                    ['key' => 'injuries', 'type' => 'yesno', 'label' => 'Was anyone injured?', 'required' => true],
                    ['key' => 'injured', 'type' => 'textarea', 'label' => 'Who was injured, and how?', 'required' => true,
                        'visibility' => ['logic' => 'all', 'rules' => [['trigger_field_key' => 'injuries', 'operator' => 'equals', 'value' => 'Yes']]]],
                    ['key' => 'description', 'type' => 'textarea', 'label' => 'What happened?', 'required' => true],
                    ['key' => 'photo', 'type' => 'file', 'label' => 'Photo of the scene'],
                ],
            ],

            'site-inspection' => [
                'name' => 'Site inspection checklist',
                'group' => 'Operations',
                'description' => 'A walk-through with scores, findings and the inspector\'s signature.',
                'settings' => [],
                'fields' => [
                    ['key' => 'site', 'type' => 'text', 'label' => 'Site', 'required' => true],
                    ['key' => 'inspector', 'type' => 'text', 'label' => 'Inspector', 'required' => true],
                    ['key' => 'date', 'type' => 'date', 'label' => 'Inspection date', 'required' => true],
                    ['key' => 'housekeeping', 'type' => 'rating', 'label' => 'Housekeeping', 'required' => true],
                    ['key' => 'ppe', 'type' => 'rating', 'label' => 'PPE compliance', 'required' => true],
                    ['key' => 'equipment', 'type' => 'rating', 'label' => 'Equipment condition', 'required' => true],
                    ['key' => 'brk', 'type' => 'page_break', 'label' => 'Page break'],
                    ['key' => 'findings', 'type' => 'textarea', 'label' => 'Findings and actions'],
                    ['key' => 'photos', 'type' => 'file', 'label' => 'Photos'],
                    ['key' => 'sign', 'type' => 'signature', 'label' => 'Inspector\'s signature', 'required' => true],
                ],
            ],

            'maintenance-request' => [
                'name' => 'Maintenance request',
                'group' => 'Operations',
                'description' => 'What is broken, where, and how urgently it is needed back.',
                'settings' => [],
                'fields' => [
                    ['key' => 'requester', 'type' => 'text', 'label' => 'Reported by', 'required' => true],
                    ['key' => 'location', 'type' => 'text', 'label' => 'Location', 'required' => true],
                    ['key' => 'asset', 'type' => 'text', 'label' => 'Equipment / asset'],
                    ['key' => 'urgency', 'type' => 'select', 'label' => 'Urgency', 'required' => true, 'choices' => ['Can wait', 'This week', 'Today', 'Production is stopped']],
                    ['key' => 'problem', 'type' => 'textarea', 'label' => 'Describe the problem', 'required' => true],
                    ['key' => 'photo', 'type' => 'file', 'label' => 'Photo'],
                ],
            ],

            'equipment-inspection' => [
                'name' => 'Equipment inspection',
                'group' => 'Operations',
                'description' => 'Pre-use check: pass/fail items and what was found.',
                'settings' => [],
                'fields' => [
                    ['key' => 'equipment', 'type' => 'text', 'label' => 'Equipment / plant number', 'required' => true],
                    ['key' => 'operator', 'type' => 'text', 'label' => 'Operator', 'required' => true],
                    ['key' => 'date', 'type' => 'date', 'label' => 'Date', 'required' => true],
                    ['key' => 'safe', 'type' => 'yesno', 'label' => 'Safe to operate?', 'required' => true],
                    ['key' => 'defects', 'type' => 'textarea', 'label' => 'Defects found', 'required' => true,
                        'visibility' => ['logic' => 'all', 'rules' => [['trigger_field_key' => 'safe', 'operator' => 'equals', 'value' => 'No']]]],
                    ['key' => 'hours', 'type' => 'number', 'label' => 'Hour / km reading'],
                    ['key' => 'sign', 'type' => 'signature', 'label' => 'Operator\'s signature'],
                ],
            ],

            'supplier-onboarding' => [
                'name' => 'Supplier onboarding',
                'group' => 'Business',
                'description' => 'Company details, CIPC registration and banking documents, over two pages.',
                'settings' => [],
                'fields' => [
                    ['key' => 'company', 'type' => 'text', 'label' => 'Registered company name', 'required' => true],
                    ['key' => 'trading', 'type' => 'text', 'label' => 'Trading name'],
                    ['key' => 'reg', 'type' => 'text', 'label' => 'CIPC registration number', 'placeholder' => '2019/123456/07'],
                    ['key' => 'vat', 'type' => 'text', 'label' => 'VAT number'],
                    ['key' => 'contact', 'type' => 'text', 'label' => 'Contact person', 'required' => true],
                    ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                    ['key' => 'phone', 'type' => 'phone', 'label' => 'Phone', 'required' => true],
                    ['key' => 'brk', 'type' => 'page_break', 'label' => 'Page break'],
                    ['key' => 'bee', 'type' => 'select', 'label' => 'B-BBEE level', 'choices' => ['1', '2', '3', '4', '5', '6', '7', '8', 'Not rated']],
                    ['key' => 'regdoc', 'type' => 'file', 'label' => 'CIPC registration document'],
                    ['key' => 'bankletter', 'type' => 'file', 'label' => 'Bank confirmation letter'],
                    ['key' => 'taxclear', 'type' => 'file', 'label' => 'SARS tax clearance'],
                ],
            ],

            'customer-enquiry' => [
                'name' => 'Customer enquiry',
                'group' => 'Business',
                'description' => 'A short way in: who they are, what they need, how to reach them.',
                'settings' => [],
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Your name', 'required' => true],
                    ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                    ['key' => 'phone', 'type' => 'phone', 'label' => 'Phone'],
                    ['key' => 'about', 'type' => 'select', 'label' => 'What is this about?', 'choices' => ['A quote', 'An order', 'Support', 'Something else']],
                    ['key' => 'message', 'type' => 'textarea', 'label' => 'How can we help?', 'required' => true],
                ],
            ],

            'client-satisfaction' => [
                'name' => 'Client satisfaction survey',
                'group' => 'Business',
                'description' => 'Rating, likelihood to recommend, and the follow-up question only unhappy clients see.',
                'settings' => [],
                'fields' => [
                    ['key' => 'service', 'type' => 'rating', 'label' => 'How was the service?', 'required' => true],
                    ['key' => 'recommend', 'type' => 'scale', 'label' => 'How likely are you to recommend us? (0–10)', 'required' => true],
                    ['key' => 'gone-wrong', 'type' => 'textarea', 'label' => 'What went wrong?',
                        'visibility' => ['logic' => 'any', 'rules' => [
                            ['trigger_field_key' => 'service', 'operator' => 'less_than', 'value' => '4'],
                            ['trigger_field_key' => 'recommend', 'operator' => 'less_than', 'value' => '7'],
                        ]]],
                    ['key' => 'best', 'type' => 'textarea', 'label' => 'What did we do well?'],
                    ['key' => 'contact', 'type' => 'yesno', 'label' => 'May we contact you about your feedback?'],
                ],
            ],

            'quotation-request' => [
                'name' => 'Quotation request',
                'group' => 'Business',
                'description' => 'What the client needs priced, by when, and where to send it.',
                'settings' => [],
                'fields' => [
                    ['key' => 'company', 'type' => 'text', 'label' => 'Company / name', 'required' => true],
                    ['key' => 'email', 'type' => 'email', 'label' => 'Email for the quote', 'required' => true],
                    ['key' => 'phone', 'type' => 'phone', 'label' => 'Phone'],
                    ['key' => 'needs', 'type' => 'textarea', 'label' => 'What do you need quoted?', 'required' => true],
                    ['key' => 'budget', 'type' => 'currency', 'label' => 'Budget, if you have one'],
                    ['key' => 'by', 'type' => 'date', 'label' => 'Needed by'],
                ],
            ],

            'event-registration' => [
                'name' => 'Event registration',
                'group' => 'Business',
                'description' => 'Who is coming, dietary needs, and a seat count that stops at the limit you set.',
                'settings' => [],
                'fields' => [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Full name', 'required' => true],
                    ['key' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                    ['key' => 'seats', 'type' => 'number', 'label' => 'Seats needed', 'required' => true],
                    ['key' => 'diet', 'type' => 'multiselect', 'label' => 'Dietary requirements', 'choices' => ['None', 'Vegetarian', 'Vegan', 'Halaal', 'Kosher', 'Gluten-free']],
                    ['key' => 'notes', 'type' => 'textarea', 'label' => 'Anything we should know?'],
                ],
            ],
        ];
    }
}
