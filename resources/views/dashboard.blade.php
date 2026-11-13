{{-- The desk (R-50): the page a person arrives at from InfoDot. One primary action, marked
     data-primary-action; everything else is secondary. Data comes from DashboardController. --}}
<x-app-layout>
    @php
        $serial = fn ($form) => 'FRM-'.str_pad((string) $form->id, 3, '0', STR_PAD_LEFT);
        $company = $team->name ?? null;
    @endphp

    <div class="cb-land">
        <div class="cb-land__top">
            <picture>
                <source srcset="{{ asset('images/dot_forms-light.png') }}" media="(prefers-color-scheme: dark)">
                <img class="cb-land__logo" src="{{ asset('images/dot_forms.png') }}" alt="Dot.Forms" width="96" height="96">
            </picture>
            <nav class="cb-land__back" aria-label="Dot ecosystem">
                <a href="{{ $infodotUrl }}/home">InfoDot</a>
                <a href="{{ $infodotUrl }}/platforms">Platforms</a>
            </nav>
        </div>

        <div class="cb-land__open">
            <div class="cb-land__say">
                <h1 class="cb-land__h1">Set a form once. Number every copy.</h1>
                <p class="cb-land__lead">
                    Welcome, <b>{{ $firstName }}</b>.
                    @if ($company)
                        Dot.Forms is where <b>{{ $company }}</b> sets the forms it sends out and keeps every response as a numbered copy.
                    @else
                        Dot.Forms is where a company sets the forms it sends out and keeps every response as a numbered copy.
                    @endif
                </p>

                @if ($team && $canCreate)
                    <form method="POST" action="{{ route('teams.forms.store', $team) }}" class="cb-land__act">
                        @csrf
                        <button type="submit" class="cb-btn cb-btn--primary cb-land__go" data-primary-action>Set a new form</button>
                    </form>
                    <p class="cb-land__also">
                        <a href="{{ route('teams.forms.ai-builder', $team) }}">Set with AI</a>
                        <a href="{{ route('teams.forms.templates', $team) }}">Master templates</a>
                        <a href="{{ route('teams.forms', $team) }}">Open the book</a>
                    </p>
                @elseif ($team)
                    <div class="cb-land__act">
                        <a href="{{ route('teams.forms', $team) }}" class="cb-btn cb-btn--primary cb-land__go" data-primary-action>Open the book</a>
                    </div>
                    <p class="cb-land__role">
                        You can read the forms and copies in this book. Setting a form needs an editor role; {{ $team->owner->name ?? 'the company owner' }} can give you one.
                    </p>
                @else
                    <div class="cb-land__act">
                        <a href="{{ route('teams.create') }}" class="cb-btn cb-btn--primary cb-land__go" data-primary-action>Add your company</a>
                    </div>
                    <p class="cb-land__role">You are not in a company yet. Add one to start a book.</p>
                @endif
            </div>

            <div class="cb-land__sheet">
                @if ($latest)
                    <h2 class="cb-land__h2">The master {{ $company }} last worked on</h2>
                    <div class="cb-master">
                        <div class="cb-master__head">
                            <b>{{ $latest->title }}</b>
                            <span class="cb-serial">{{ $serial($latest) }}</span>
                        </div>
                        @forelse ($latestFields as $field)
                            <div class="cb-fieldbox cb-land__field">
                                <span class="cb-fieldbox__no">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="cb-land__flabel">{{ $field->label }}</span>
                                <span class="cb-land__ftype">{{ $field->type }}</span>
                                <span class="cb-land__line" aria-hidden="true"></span>
                            </div>
                        @empty
                            <p class="cb-land__blank">No fields on this master yet.</p>
                        @endforelse
                        @if ($latest->fields_count > $latestFields->count() && $latestFields->count() > 0)
                            <p class="cb-land__more">and {{ $latest->fields_count - $latestFields->count() }} more {{ Str::plural('field', $latest->fields_count - $latestFields->count()) }}</p>
                        @endif
                        <div class="cb-land__foot">
                            @if ($latest->archived_at)
                                <span class="cb-state cb-state--closed">CLOSED</span>
                            @elseif ($latest->is_published)
                                <span class="cb-state cb-state--live">IN USE</span>
                            @else
                                <span class="cb-state cb-state--draft">UNSET</span>
                            @endif
                            <span class="cb-land__count">{{ $latest->submissions_count }} {{ Str::plural('copy', $latest->submissions_count) }}</span>
                            @if ($canEdit)
                                <a href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $latest]) }}">Open the master</a>
                            @endif
                            @if ($canViewCopies)
                                <a href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $latest]) }}">Copies</a>
                            @endif
                        </div>
                    </div>
                @else
                    <h2 class="cb-land__h2">The book is empty</h2>
                    <div class="cb-master cb-land__unset" data-empty-state>
                        <div class="cb-master__head">
                            <b>Nothing is set yet</b>
                        </div>
                        <p class="cb-land__blank">
                            @if ($canCreate)
                                The first form you set shows here as a master sheet. Each response to it is filed under it as a numbered copy.
                            @else
                                When a form is set in this book it shows here as a master sheet, with each response filed under it as a numbered copy.
                            @endif
                        </p>
                        <span class="cb-land__line" aria-hidden="true"></span>
                        <span class="cb-land__line" aria-hidden="true"></span>
                        <span class="cb-land__line" aria-hidden="true"></span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($forms->count() > 0)
        <section class="cb-sec">
            <div class="cb-sec__head">
                <h2>Recent masters</h2>
                <p class="cb-land__sum" data-book-tally>
                    <span data-tally="forms">{{ $totalForms }}</span> {{ Str::plural('form', $totalForms) }} in the book,
                    <span data-tally="in-use">{{ $published }}</span> in use,
                    <span data-tally="copies-week">{{ $copiesThisWeek }}</span> {{ Str::plural('copy', $copiesThisWeek) }} in the last 7 days
                </p>
                <a href="{{ route('teams.forms', $team) }}">The whole book</a>
            </div>

            <div class="cb-register">
                @foreach ($forms as $form)
                    <div class="cb-bookrow">
                        <span class="cb-serial">{{ $serial($form) }}</span>
                        <span>
                            <b>{{ $form->title }}</b><br>
                            <span class="cb-dim">{{ $form->fields_count }} {{ Str::plural('field', $form->fields_count) }}</span>
                        </span>
                        <span class="cb-n">{{ $form->submissions_count }} {{ Str::plural('copy', $form->submissions_count) }}</span>
                        <span class="cb-dim cb-when">{{ $form->updated_at->diffForHumans(short: true) }}</span>
                        @if ($form->archived_at)
                            <span class="cb-state cb-state--closed">CLOSED</span>
                        @elseif ($form->is_published)
                            <span class="cb-state cb-state--live">IN USE</span>
                        @else
                            <span class="cb-state cb-state--draft">UNSET</span>
                        @endif
                    </div>
                    @if ($canEdit || $canViewCopies)
                        <div class="cb-land__rowacts">
                            @if ($canEdit)
                                <a class="cb-btn cb-btn--quiet cb-btn--sm" href="{{ route('teams.forms.builder', ['team' => $team, 'form' => $form]) }}">Open the master</a>
                            @endif
                            @if ($canViewCopies)
                                <a class="cb-btn cb-btn--quiet cb-btn--sm" href="{{ route('teams.forms.submissions', ['team' => $team, 'form' => $form]) }}">Copies</a>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</x-app-layout>
