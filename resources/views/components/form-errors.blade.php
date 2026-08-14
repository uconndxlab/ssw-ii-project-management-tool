@if ($errors->any())
    <div {{ $attributes->class(['alert', 'alert-danger']) }}>
        <strong>Please fix the highlighted fields.</strong>
    </div>
@endif
