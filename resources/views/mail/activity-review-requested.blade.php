<x-mail::message>
# Hi {{ $firstName }},

{{ $requester }} logged an activity and asked you to review its certification evidence.

<x-mail::panel>
<span class="detail-title">{{ $type }}</span><br>
<span class="detail-meta">{{ $date }}</span>

<span class="label">Agreements</span><br>
{{ $agreements }}

@if($mine !== '')
<span class="label">You supervise</span><br>
{{ $mine }}

@if($others !== '')
<span class="label">Other candidates</span><br>
{{ $others }}
@endif
@else
<span class="label">Candidates</span><br>
{{ $others }}
@endif
</x-mail::panel>

<x-mail::button :url="$url">
Review activity
</x-mail::button>

<p class="muted">You are receiving this because you supervise a tagged candidate, or {{ $requester }} asked you directly. Button not working? Paste this link into your browser: {{ $url }}</p>
</x-mail::message>
