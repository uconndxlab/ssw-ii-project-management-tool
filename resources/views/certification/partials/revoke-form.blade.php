{{-- Revocation of one live award; shared by the progress page and the award record. --}}
@php $submitted = old('lifecycle_form') === 'revoke-'.$award->id; @endphp
<details class="border rounded mt-2" @if($submitted) open @endif>
    <summary class="px-3 py-2 fw-medium text-danger"><i class="bi bi-x-octagon" aria-hidden="true"></i> Revoke award…</summary>
    <form class="p-3 border-top" method="POST" action="{{ route('certificate-enrollments.revoke', ['certificateEnrollment' => $enrollment, 'award' => $award, 'agreement_id' => request()->integer('agreement_id') ?: null]) }}">
        @csrf
        <input type="hidden" name="lifecycle_form" value="revoke-{{ $award->id }}">
        <p class="small mb-3">Revokes the cycle {{ $award->cycle }} award{{ $award->expires_at ? ' (valid until '.$award->expires_at->format('M j, Y').')' : '' }}. Evidence, confirmations and history stay. Endorsement and award close for this enrollment afterward.</p>
        <x-form-field label="Reason" :for="'revoke-reason-'.$award->id" :name="$submitted ? 'reason' : null" required>
            <textarea class="form-control @if($submitted && $errors->has('reason')) is-invalid @endif" rows="3" name="reason" id="revoke-reason-{{ $award->id }}" required>{{ $submitted ? old('reason') : '' }}</textarea>
        </x-form-field>
        <button type="submit" class="btn btn-sm btn-danger">Revoke award</button>
    </form>
</details>
