<button {{ $attributes->merge(['type' => 'submit', 'style' => 'padding: 12px 18px; background: #f1c62e; color: #221b12; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: background .15s, box-shadow .15s; font-family: var(--dot-font-sans);', 'onmouseover' => "this.style.background='#f5d364'", 'onmouseout' => "this.style.background='#f1c62e'"]) }}>
    {{ $slot }}
</button>
