<x-mail::message>
{{ __('You have been invited to join the :team team on Dot.Forms.', ['team' => $invitation->team->name]) }}

{{ __('Dot.Forms signs in through your InfoDot account. If you do not have one yet, you can create it from the sign-in page; then come back to this message and accept the invitation.') }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('If you did not expect to receive an invitation to this team, you may discard this email.') }}
</x-mail::message>
