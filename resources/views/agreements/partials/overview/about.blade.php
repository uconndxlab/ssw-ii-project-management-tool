<x-record-section title="About">
    @if(blank($agreement->abstract) && $agreement->attachments->isEmpty())
        <p class="text-muted small mb-0">No summary or files added.</p>
    @else
        @if(filled($agreement->abstract))
            <p class="app-abstract mb-2" id="agreement-abstract" data-overview-abstract>{{ $agreement->abstract }}</p>
            <button type="button" class="btn btn-link app-link p-0 mb-3" aria-expanded="false" aria-controls="agreement-abstract" data-abstract-toggle hidden>Read more</button>
        @endif
        <div class="d-grid gap-2">
            @forelse($agreement->attachments as $attachment)
                <a href="{{ $attachment->download_url }}" target="_blank" rel="noopener" class="app-link small text-break"><i class="bi bi-download me-1" aria-hidden="true"></i>{{ $attachment->filename }}</a>
            @empty
                <p class="text-muted small mb-0">No files attached.</p>
            @endforelse
        </div>
    @endif
</x-record-section>
