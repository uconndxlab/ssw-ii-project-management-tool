<x-mail::message>
# Hi {{ $firstName }},

We received a request to reset the password for your {{ config('app.name') }} account.

<x-mail::button :url="$url">
Reset password
</x-mail::button>

This link expires in {{ $expires }} minutes. If you didn't ask for a reset, you can ignore this email and your password will stay the same.

<p class="muted">Button not working? Paste this link into your browser: {{ $url }}</p>
</x-mail::message>
