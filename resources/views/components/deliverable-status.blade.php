@props([
    'status' => null,
])

@if($status)
    @php $chip = \App\Support\AgreementOverview::statusChip($status); @endphp
    <span {{ $attributes->class(['app-status', 'app-status--'.$chip['tone']]) }}>{{ $chip['label'] }}</span>
@endif
