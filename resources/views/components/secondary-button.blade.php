<button {{ $attributes->merge(['type' => 'button', 'style' => 'padding: 12px 18px; background: white; color: #374151; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: background .15s, border-color .15s; font-family: var(--dot-font-sans);', 'onmouseover' => "this.style.background='#F9FAFB'; this.style.borderColor='#D1D5DB'", 'onmouseout' => "this.style.background='white'; this.style.borderColor='#E5E7EB'"]) }}>
    {{ $slot }}
</button>
