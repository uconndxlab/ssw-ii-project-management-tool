@if ($errors->any())
    <div {{ $attributes->class(['alert', 'alert-danger']) }}>
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->getMessages() as $name => $messages)
                @foreach (array_unique($messages) as $message)
                    <li data-error-name="{{ $name }}">{{ $message }}</li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
