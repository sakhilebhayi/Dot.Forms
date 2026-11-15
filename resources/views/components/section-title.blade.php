<div style="display: flex; justify-content: space-between; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin: 0; font-family: var(--dot-font-sans);">{{ $title }}</h3>

        <p style="margin-top: 6px; font-size: 13px; color: #6B7280;">
            {{ $description }}
        </p>
    </div>

    <div>
        {{ $aside ?? '' }}
    </div>
</div>
