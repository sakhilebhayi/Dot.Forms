@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['style' => 'width: 100%; height: 44px; background: white; border: 1px solid #E5E7EB; border-radius: 10px; padding: 0 14px; font-size: 16px; color: #221b12; outline: none; transition: border-color .15s, box-shadow .15s; font-family: var(--dot-font-sans); margin-top: 6px;', 'onfocus' => "this.style.borderColor='#f1c62e'; this.style.boxShadow='0 0 0 3px rgba(241,198,46,0.22)'", 'onblur' => "this.style.borderColor='#E5E7EB'; this.style.boxShadow='none'"]) !!}}
