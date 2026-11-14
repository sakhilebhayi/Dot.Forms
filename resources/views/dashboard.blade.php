{{-- The desk (R-50, R-51): the page a person arrives at from InfoDot. A working register of the company's forms, the selected
     master beside it, one primary action marked data-primary-action. Data comes from DashboardController; every copy
     figure is null for a person who may not read copies, and is then not drawn at all. --}}
<x-app-layout>
    @php
        $serial = fn ($form) => 'FRM-'.str_pad((string) $form->id, 3, '0', STR_PAD_LEFT);
        $company = $team->name ?? null;
        $waiting = $copies['waiting'] ?? null;
        $mine = $copies['assignedCount'] ?? null;
        $personalOrUnlinked = ! $team || $team->personal_team || blank($team->infodot_team_uuid);
        $plate = fn ($form) => $form->archived_at ? ['closed', 'CLOSED', 'closed'] : ($form->is_published ? ['live', 'IN USE', 'in use'] : ['draft', 'UNSET', 'unset']);
        $rowTarget = function ($form) use ($team, $showStage, $canViewCopies, $canEdit) {
            if (! $team) { return null; }
            return $showStage ? route('dashboard', ['form' => $form->id]) : route('teams.forms', $team);
        };
    @endphp

    <div class="cb-desk">
        {{-- Arrival: the original logo, a headline that states the current state, the one filled action. --}}
        <section class="cb-desk__arrive" aria-label="Where the book stands">
            <picture class="cb-desk__logo">
                <source srcset="{{ asset('images/dot_forms-light.png') }}" media="(prefers-color-scheme: dark)">
                <img src="{{ asset('images/dot_forms.png') }}" alt="Dot.Forms" width="72" height="72">
            </picture>

            <div class="cb-desk__say">
                @if (! $team)
                    <h1 class="cb-desk__h1">You are not in a company yet.</h1>
                    <p class="cb-desk__sub">Add one to start a book.</p>
                @elseif ($totalForms === 0)
                    <h1 class="cb-desk__h1">The book is empty.</h1>
                    <p class="cb-desk__sub">Welcome, <b>{{ $firstName }}</b>.
                        Dot.Forms is where <b>{{ $company }}</b> sets the forms it sends out and keeps every response as a numbered copy.</p>
                @elseif ($canViewCopies)
                    <h1 class="cb-desk__h1">
                        @if ($waiting > 0)
                            {{ $waiting }} new {{ Str::plural('copy', $waiting) }} {{ $waiting === 1 ? 'is' : 'are' }} waiting.
                        @else
                            Nothing is waiting.
                        @endif
                    </h1>
                    <p class="cb-desk__sub">
                        @if ($mine > 0)
                            <b>{{ $mine }}</b> {{ $mine === 1 ? 'is' : 'are' }} assigned to you.
                        @endif
                        {{ $published }} {{ Str::plural('form', $published) }} in use, and {{ $copiesThisWeek }} {{ Str::plural('copy', $copiesThisWeek) }} came in over the last 7 days.
                    </p>
                @else
                    <h1 class="cb-desk__h1">{{ $totalForms }} {{ Str::plural('form', $totalForms) }} in the book.</h1>
                    <p class="cb-desk__sub">{{ $published }} in use. Welcome, <b>{{ $firstName }}</b>.</p>
                @endif
            </div>

            <div class="cb-desk__act">
                @if ($team && $canCreate)
                    <form method="POST" action="{{ route('teams.forms.store', $team) }}">
                        @csrf
                        <button type="submit" class="cb-btn cb-btn--primary cb-desk__go" data-primary-action>Set a new form</button>
                    </form>
                    <p class="cb-desk__also">
                        <a href="{{ route('teams.forms.ai-builder', $team) }}">Set with AI</a>
                        <a href="{{ route('teams.forms.templates', $team) }}">Master templates</a>
                        <a href="{{ route('teams.forms', $team) }}">Open the book</a>
                    </p>
                @elseif ($team)
                    <a href="{{ route('teams.forms', $team) }}" class="cb-btn cb-btn--primary cb-desk__go" data-primary-action>Open the book</a>
                @else
                    <a href="{{ route('teams.create') }}" class="cb-btn cb-btn--primary cb-desk__go" data-primary-action>Add your company</a>
                @endif

                {{-- R-51: the spine's Dot Apps switcher replaces this way back (it has the InfoDot tile and "See all platforms").
                     A personal or unlinked workspace gets no platform list from the switcher, so only there it stays. --}}
                @if ($personalOrUnlinked)
                    <nav class="cb-land__back cb-desk__back" aria-label="Dot ecosystem">
                        <a href="{{ $infodotUrl }}/home">InfoDot</a>
                        <a href="{{ $infodotUrl }}/platforms">Platforms</a>
                    </nav>
                @endif
            </div>

            @if ($team && ! $canCreate)
                <p class="cb-desk__role">
                    You can read the forms {{ $canViewCopies ? 'and copies ' : '' }}in this book. Setting a form needs an editor role; {{ $team->owner->name ?? 'the company owner' }} can give you one.
                </p>
            @elseif (! $team)
                <p class="cb-desk__role">Dot.Forms is where a company sets the forms it sends out and keeps every response as a numbered copy.</p>
            @endif
        </section>

        @if ($team && $forms->isEmpty())
            <section class="cb-desk__blank" data-empty-state aria-labelledby="cb-empty-t">
                <div class="cb-desk__blank-art">
                    <x-dashboard.carbon-stack />
                </div>
                <div>
                    <h2 id="cb-empty-t">Nothing is set yet</h2>
                    <p>
                        @if ($canCreate)
                            The first form you set shows here as a master sheet. Each response to it is filed under it as a numbered copy.
                        @else
                            When a form is set in this book it shows here as a master sheet, with each response filed under it as a numbered copy.
                        @endif
                    </p>
                </div>
            </section>
        @elseif ($team)
            <div class="cb-desk__grid">
                <div class="cb-desk__col">
                    <section aria-labelledby="cb-reg-t">
                        <div class="cb-desk__head">
                            <h2 id="cb-reg-t">The book</h2>
                            <a href="{{ route('teams.forms', $team) }}">The whole book</a>
                        </div>
                        <table class="cb-desk__reg" data-sortable>
                            <thead>
                                <tr>
                                    <th scope="col" class="cb-hide-sm" aria-sort="none"><button type="button" data-sort="serial">Serial</button></th>
                                    <th scope="col" aria-sort="none"><button type="button" data-sort="title">Form</button></th>
                                    @if ($canViewCopies)
                                        <th scope="col" class="cb-r" aria-sort="none"><button type="button" data-sort="new">New</button></th>
                                        <th scope="col" class="cb-r cb-hide-sm" aria-sort="none"><button type="button" data-sort="copies">Copies</button></th>
                                        <th scope="col" class="cb-hide-sm" aria-sort="none"><button type="button" data-sort="last">Last copy</button></th>
                                    @else
                                        <th scope="col" class="cb-hide-sm" aria-sort="none"><button type="button" data-sort="last">Changed</button></th>
                                    @endif
                                    <th scope="col" class="cb-hide-sm">State</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($forms as $form)
                                    @php
                                        [$pc, $pl, $pw] = $plate($form);
                                        $isSel = $showStage && $form->id === $selected->id;
                                        $last = $canViewCopies ? $form->submissions_max_submitted_at : null;
                                        $lastAt = $last ? \Illuminate\Support\Carbon::parse($last) : null;
                                    @endphp
                                    <tr @class(['is-selected' => $isSel])
                                        data-serial="{{ $form->id }}" data-title="{{ Str::lower($form->title) }}"
                                        @if ($canViewCopies) data-new="{{ $form->new_submissions_count ?? 0 }}" data-copies="{{ $form->submissions_count }}" @endif
                                        data-last="{{ $canViewCopies ? ($lastAt?->timestamp ?? 0) : $form->updated_at->timestamp }}">
                                        <td class="cb-serial cb-hide-sm">{{ $serial($form) }}</td>
                                        <td class="cb-desk__name">
                                            <a href="{{ $rowTarget($form) }}" @if ($isSel) aria-current="true" @endif>{{ $form->title }}</a>
                                            <small>
                                                <span class="cb-sm-only cb-mono">{{ $serial($form) }} · </span>{{ $form->fields_count }} {{ Str::plural('field', $form->fields_count) }}<span class="cb-sm-only"> · @if ($canViewCopies){{ $form->submissions_count }} {{ Str::plural('copy', $form->submissions_count) }} · @endif{{ $pw }}</span>
                                            </small>
                                        </td>
                                        @if ($canViewCopies)
                                            <td @class(['cb-r', 'cb-n', 'cb-zero' => ! $form->new_submissions_count])>{{ $form->new_submissions_count }}</td>
                                            <td @class(['cb-r', 'cb-hide-sm', 'cb-zero' => ! $form->submissions_count])>{{ $form->submissions_count }}</td>
                                            <td class="cb-hide-sm">{{ $lastAt ? $lastAt->diffForHumans(short: true) : 'None yet' }}</td>
                                        @else
                                            <td class="cb-hide-sm">{{ $form->updated_at->diffForHumans(short: true) }}</td>
                                        @endif
                                        <td class="cb-hide-sm"><span class="cb-state cb-state--{{ $pc }}">{{ $pl }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="cb-desk__note">
                            @if ($showStage)
                                Choosing a row puts that form's master on the sheet beside it.
                            @else
                                The whole book is where forms are opened.
                            @endif
                        </p>
                    </section>

                    @if ($canViewCopies && $mine > 0)
                        <section aria-labelledby="cb-mine-t">
                            <div class="cb-desk__head">
                                <h2 id="cb-mine-t">Assigned to you</h2>
                                <span>{{ $mine > $copies['assigned']->count() ? $copies['assigned']->count().' of '.$mine.', longest waiting first' : 'Longest waiting first' }}</span>
                            </div>
                            <ol class="cb-desk__list">
                                @foreach ($copies['assigned'] as $copy)
                                    <li>
                                        <span class="cb-mono cb-desk__cs">FRM-{{ str_pad((string) $copy->form_id, 3, '0', STR_PAD_LEFT) }}/{{ $copy->id }}</span>
                                        <span class="cb-desk__main">
                                            <a href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $copy->form_id]) }}">{{ $copy->form->title ?? 'A form' }}</a>
                                            <small>Lodged {{ ($copy->submitted_at ?? $copy->created_at)->diffForHumans(short: true) }}</small>
                                        </span>
                                        <span @class(['cb-desk__st', 'is-new' => $copy->status === 'new'])>{{ mb_strtoupper(\App\Models\FormSubmission::STATUS_LABELS[$copy->status] ?? $copy->status) }}</span>
                                        @if ($copy->decision === 'approved')
                                            <span class="cb-stamp cb-stamp--ok">APPROVED</span>
                                        @elseif ($copy->decision === 'rejected')
                                            <span class="cb-stamp">REJECTED</span>
                                        @else
                                            <span></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif
                </div>

                <div class="cb-desk__col">
                    @if ($showStage)
                        <aside class="cb-desk__stage" aria-labelledby="cb-stage-t">
                            <div class="cb-desk__head">
                                <h2 id="cb-stage-t">{{ $selected->id === $forms->first()->id ? 'The master '.$company.' last worked on' : 'The master you picked' }}</h2>
                            </div>
                            <div class="cb-desk__sheet">
                                <div class="cb-desk__sheet-head">
                                    <b>{{ $selected->title }}</b>
                                    <span class="cb-serial">{{ $serial($selected) }}</span>
                                </div>
                                <ul class="cb-desk__fields">
                                    @forelse ($selectedFields as $field)
                                        <li>
                                            <span class="cb-desk__no">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                            <span>{{ $field->label }}</span>
                                            <span class="cb-desk__ftype">{{ $field->type }}</span>
                                        </li>
                                    @empty
                                        <li class="cb-desk__more">No fields on this master yet.</li>
                                    @endforelse
                                    @if ($selected->fields_count > $selectedFields->count() && $selectedFields->count() > 0)
                                        <li class="cb-desk__more">and {{ $selected->fields_count - $selectedFields->count() }} more {{ Str::plural('field', $selected->fields_count - $selectedFields->count()) }}</li>
                                    @endif
                                </ul>

                                @php
                                    $viewed = (int) $selected->views_count;
                                    $started = (int) $selected->starts_count;
                                    $lodged = (int) $selected->submissions_count;
                                    $top = max(1, $viewed, $started, $canViewCopies ? $lodged : 0);
                                    $rate = $started > 0 && $canViewCopies ? min(100, (int) round($lodged / $started * 100)) : null;
                                    $funnel = [['Viewed', $viewed]];
                                    $funnel[] = ['Started', $started];
                                    if ($canViewCopies) { $funnel[] = ['Lodged', $lodged]; }
                                @endphp
                                <dl class="cb-desk__facts" data-funnel>
                                    @foreach ($funnel as [$flabel, $fval])
                                        <dt>{{ $flabel }}</dt>
                                        <dd>
                                            <span class="cb-desk__v" data-countup>{{ $fval }}</span>
                                            <svg class="cb-desk__bar" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                                <rect class="cb-desk__bar-track" x="0" y="0" width="100" height="8"/>
                                                <rect class="cb-desk__bar-fill" x="0" y="0" width="{{ $fval > 0 ? max(2, round($fval / $top * 100, 1)) : 0 }}" height="8"/>
                                            </svg>
                                        </dd>
                                    @endforeach
                                </dl>
                                @if ($rate !== null)
                                    <p class="cb-desk__note">{{ $rate }}% of the people who started lodged a copy. A start is a first answer, counted only from when the tally shipped. <a href="{{ route('dashboard.analytics') }}">All tallies</a></p>
                                @else
                                    <p class="cb-desk__note">A start is a first answer, counted only from when the tally shipped. <a href="{{ route('dashboard.analytics') }}">All tallies</a></p>
                                @endif

                                @if ($canViewCopies)
                                    <div class="cb-desk__chart-t">
                                        <h3>Copies per day</h3>
                                        @if ($copies['perDayTotal'] > 0)
                                            <span>{{ $copies['perDayTotal'] }} in {{ \App\Http\Controllers\DashboardController::CHART_DAYS }} days</span>
                                        @endif
                                    </div>
                                    @if ($copies['perDayTotal'] > 0)
                                        @include('dashboard._copies-per-day', ['series' => $copies['perDay'], 'formId' => $selected->id])
                                    @else
                                        <div class="cb-desk__nocopies">
                                            <x-dashboard.carbon-stack size="sm" />
                                            <p>{{ $selected->submissions_count > 0 ? 'No copies in the last '.\App\Http\Controllers\DashboardController::CHART_DAYS.' days.' : 'No copies yet.' }}</p>
                                        </div>
                                    @endif
                                @endif

                                <div class="cb-desk__stage-act">
                                    @if ($canEdit)
                                        <a class="cb-btn" href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $selected]) }}">Open the master</a>
                                    @endif
                                    @if ($canViewCopies)
                                        <a class="cb-btn" href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $selected]) }}">Copies</a>
                                    @endif
                                    @if ($selected->is_published && ! $selected->archived_at)
                                        <a class="cb-btn cb-btn--quiet" href="{{ route('forms.public', $selected->slug) }}">Public form</a>
                                    @endif
                                </div>
                            </div>
                        </aside>
                    @endif

                    @if ($canViewCopies && array_sum($copies['byStatus']) > 0)
                        <section aria-labelledby="cb-state-t">
                            <div class="cb-desk__head"><h2 id="cb-state-t">Every copy in the book, by state</h2></div>
                            <dl class="cb-desk__ledger">
                                @foreach (\App\Models\FormSubmission::STATUS_LABELS as $key => $label)
                                    <dt>{{ $label }}</dt><dd data-countup>{{ $copies['byStatus'][$key] }}</dd>
                                @endforeach
                                <dt class="is-sub">Decided: approved</dt><dd class="is-sub">{{ $copies['decided']['approved'] }}</dd>
                                <dt class="is-sub">Decided: rejected</dt><dd class="is-sub">{{ $copies['decided']['rejected'] }}</dd>
                            </dl>
                        </section>
                    @endif
                </div>
            </div>

            <p class="cb-desk__foot" data-book-tally>
                <span data-tally="forms">{{ $totalForms }}</span> {{ Str::plural('form', $totalForms) }} in the book:
                <span data-tally="in-use">{{ $published }}</span> in use, {{ $unset }} unset, {{ $closed }} closed.
                @if ($canViewCopies)
                    <span data-tally="copies-week">{{ $copiesThisWeek }}</span> {{ Str::plural('copy', $copiesThisWeek) }} in the last 7 days.
                    Counted from this company's own copies; nothing is estimated.
                @endif
            </p>
        @endif
    </div>
</x-app-layout>
