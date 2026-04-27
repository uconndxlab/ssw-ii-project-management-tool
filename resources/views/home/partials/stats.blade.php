<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Stats Snapshot</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($stats as $key => $value)
                <div class="col-sm-6 col-md-3">
                    <div class="p-3 bg-light rounded text-center">
                        <div class="h3 fw-bold text-primary mb-0">{{ number_format($value) }}</div>
                        <small class="text-muted">{{ str_replace('_', ' ', ucfirst($key)) }}</small>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
