<button {{ $attributes->merge(['type' => 'button', 'style' => 'padding: 12px 18px; background: var(--red); color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .15s, box-shadow .15s; font-family: var(--dot-font-sans);', 'onmouseover' => "this.style.background='#B71C1C'", 'onmouseout' => "this.style.background='var(--red)'"]) }}>
    {{ $slot }}
</button>
