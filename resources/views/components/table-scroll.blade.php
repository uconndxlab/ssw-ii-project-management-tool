<div class="app-table-scroll-frame">
    <div {{ $attributes->merge(['class' => 'table-responsive', 'tabindex' => '0', 'role' => 'region', 'aria-label' => 'Table results']) }}>
        {{ $slot }}
    </div>
</div>
