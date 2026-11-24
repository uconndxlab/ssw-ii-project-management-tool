<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Stats Snapshot</h2>
    </div>
    <div class="card-body">
        <dl class="app-record-metrics">
            @foreach($stats as $key => $value)
                <div>
                    <dt>{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                    <dd>{{ number_format($value) }}</dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
