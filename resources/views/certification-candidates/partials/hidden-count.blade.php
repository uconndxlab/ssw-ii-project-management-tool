@if($count > 0)
    <div class="mt-1"><x-restricted-count :count="$count" :kind="$kind" /></div>
@endif
