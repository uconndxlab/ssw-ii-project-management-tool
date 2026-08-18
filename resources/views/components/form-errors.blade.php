@if ($errors->any())
    <div {{ $attributes->class(['alert', 'alert-danger']) }}>
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach (collect($errors->all())->unique() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
