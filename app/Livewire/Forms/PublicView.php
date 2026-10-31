<?php

namespace App\Livewire\Forms;

use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Notifications\NewFormSubmissionNotification;
use App\Services\FormFieldVisibilityEvaluator;
use App\Services\FormSubmissionIntegrationDispatcher;
use App\Services\PublicFormCache;
use App\Support\FieldTypes;
use App\Support\Formula;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.document')]
class PublicView extends Component
{
    use WithFileUploads;

    public Form $form;

    public array $answers = [];

    public array $uploads = [];

    #[Validate('nullable|string|max:0')]
    public string $website = '';

    public int $startedAt = 0;

    public bool $submitted = false;

    public bool $consentAccepted = false;

    public ?int $submittedQuizScore = null;

    public ?int $submittedQuizMax = null;

    /** Set server-side at submit; printed on the respondent's receipt. */
    public ?string $receiptAt = null;

    public function mount(string $slug): void
    {
        $preview = request()->boolean('preview');

        $this->form = app(PublicFormCache::class)->remember($slug, $preview, function () use ($preview, $slug) {
            // Public/preview lookups must resolve by slug regardless of the
            // viewer's own current team (an authenticated team member may be
            // viewing another team's published form, or previewing while a
            // different team is "current"), so bypass Form's team scope here.
            $query = Form::withoutTeamScope()->where('slug', $slug)->whereNull('archived_at');

            if (! $preview) {
                $query->where('is_published', true);
            }

            return $query->with(['fields' => fn ($fields) => $fields->orderBy('order')])->firstOrFail();
        });

        if ($preview) {
            abort_unless(Auth::check(), 403);
            // A cached preview can outlive its team (a cascade delete fires
            // no events); a form with no team is gone.
            $team = $this->form->team;
            abort_if($team === null, 404);

            Gate::authorize('canEditForm', $team);
        }

        $this->startedAt = now()->timestamp;

        if (! $preview) {
            $this->form->increment('views_count');
        }

        foreach ($this->form->fields as $field) {
            $this->answers[$field->id] = null;
        }
    }

    /**
     * Whether $field should currently be shown to the respondent, per its
     * visibility_rule (if any) evaluated against the answers given so far.
     * Called from the view for every field, and reused in submit()/rules()
     * so a hidden field's answer is neither validated nor recorded.
     */
    public function isFieldVisible(FormField $field): bool
    {
        return app(FormFieldVisibilityEvaluator::class)
            ->isVisible($field, $this->form->fields, $this->answers);
    }

    public function submit(): void
    {
        $limiterKey = 'form-submit:'.$this->form->id.':'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, 10)) {
            $this->addError('website', 'Too many submission attempts. Please try again later.');

            return;
        }

        $this->validate($this->rules());

        if ($this->website !== '') {
            $this->addError('website', 'Spam detected.');

            return;
        }

        $minSeconds = (int) config('dotforms.forms.min_submit_seconds', 2);

        if ((now()->timestamp - $this->startedAt) < $minSeconds) {
            $this->addError('website', 'Please wait a moment before submitting.');

            return;
        }

        if (($this->form->settings['consent_required'] ?? false) && ! $this->consentAccepted) {
            $this->addError('consentAccepted', 'Consent is required to submit this form.');

            return;
        }

        $submissionData = $this->answers;

        foreach ($this->form->fields as $field) {
            if (! $this->isFieldVisible($field)) {
                // A field hidden by conditional logic was never actually
                // shown to the respondent -- don't record whatever stale
                // value it happened to hold (e.g. from before its trigger
                // was answered).
                unset($submissionData[$field->id]);

                continue;
            }

            if (! FieldTypes::collectsAnswer($field->type)) {
                unset($submissionData[$field->id]);

                continue;
            }

            if ($field->type === 'hidden') {
                // The respondent never sees or edits this: the master's set
                // value is recorded, not whatever the client sent.
                $submissionData[$field->id] = $field->options['default_value'] ?? null;

                continue;
            }

            if ($field->type === 'signature' && filled($submissionData[$field->id] ?? null)) {
                $submissionData[$field->id] = $this->storeSignature((string) $submissionData[$field->id]);

                continue;
            }

            if ($field->type === 'file' && isset($this->uploads[$field->id])) {
                $submissionData[$field->id] = $this->uploads[$field->id]->store(
                    'forms/'.$this->form->id,
                    config('dotforms.forms.upload_disk', 'public')
                );
            }
        }

        // Calculated fields (R-31): evaluated server-side from the other answers,
        // keyed by field_key — never from anything the client sent for them.
        $byKey = [];
        foreach ($this->form->fields as $field) {
            if (FieldTypes::collectsAnswer($field->type) && $field->type !== 'calculated') {
                $byKey[(string) ($field->options['field_key'] ?? '')] = $submissionData[$field->id] ?? null;
            }
        }
        foreach ($this->form->fields as $field) {
            if ($field->type === 'calculated') {
                $submissionData[$field->id] = $this->isFieldVisible($field)
                    ? Formula::evaluate($field->options['formula'] ?? null, $byKey)
                    : null;
            }
        }

        $submission = FormSubmission::query()->create([
            'form_id' => $this->form->id,
            'user_id' => Auth::id(),
            'data' => $submissionData,
            'submitted_at' => now(),
            'completion_seconds' => max(0, now()->timestamp - $this->startedAt),
            'quiz_score' => $this->quizScore($submissionData)[0],
            'quiz_max_score' => $this->quizScore($submissionData)[1],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        [$this->submittedQuizScore, $this->submittedQuizMax] = $this->quizScore($submissionData);

        RateLimiter::hit($limiterKey, 60);

        $this->form->loadMissing('user', 'fields');

        if ($this->form->user) {
            $this->form->user->notify(new NewFormSubmissionNotification($this->form, $submission));
        }

        app(FormSubmissionIntegrationDispatcher::class)->dispatch($this->form, $submission);

        $this->submitted = true;
        $this->receiptAt = $submission->submitted_at->format('j M Y, H:i');
        $this->reset('uploads', 'website', 'consentAccepted');
    }

    protected function rules(): array
    {
        $rules = [
            'website' => ['nullable', 'string', 'max:0'],
            'consentAccepted' => ['boolean'],
        ];

        foreach ($this->form->fields as $field) {
            // A field hidden by conditional logic can't be required --
            // the respondent has no way to fill in something they can't
            // see.
            if (! $this->isFieldVisible($field)) {
                if ($field->type === 'file') {
                    $rules['uploads.'.$field->id] = ['nullable'];
                } else {
                    $rules['answers.'.$field->id] = ['nullable'];
                }

                continue;
            }

            if (! FieldTypes::collectsAnswer($field->type)) {
                continue;
            }

            if ($field->type === 'file') {
                $required = in_array('required', $field->validation_rules ?? [], true);
                $rules['uploads.'.$field->id] = [$required ? 'required' : 'nullable', 'file', 'max:10240'];

                continue;
            }

            // Every other type's rules live in the one registry the builder
            // stores from, so the two can never drift apart (R-30 Phase 2).
            foreach (FieldTypes::publicRules($field) as $key => $fieldRules) {
                $rules[$key] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * The drawn signature arrives as a validated PNG data URL; it is decoded and
     * kept as a real image file beside the form's uploads, and the stored path is
     * what the submission records — never megabytes of base64 in the data column.
     */
    protected function storeSignature(string $dataUrl): ?string
    {
        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false || $binary === '' || substr($binary, 1, 3) !== 'PNG') {
            return null;
        }

        $path = 'forms/'.$this->form->id.'/signatures/'.Str::uuid().'.png';

        Storage::disk(config('dotforms.forms.upload_disk', 'public'))->put($path, $binary);

        return $path;
    }

    /**
     * @return array{0:int|null,1:int|null}
     */
    protected function quizScore(array $submissionData): array
    {
        if (! ($this->form->settings['quiz_enabled'] ?? false)) {
            return [null, null];
        }

        $answerKey = $this->form->settings['quiz_answer_key'] ?? [];

        if (! is_array($answerKey) || $answerKey === []) {
            return [0, 0];
        }

        $score = 0;
        $max = count($answerKey);

        foreach ($answerKey as $fieldId => $expected) {
            $actual = $submissionData[(int) $fieldId] ?? null;

            if ((string) $actual === (string) $expected) {
                $score++;
            }
        }

        return [$score, $max];
    }

    #[Layout('components.layouts.document')]
    public function render()
    {
        return view('livewire.forms.public-view');
    }
}
