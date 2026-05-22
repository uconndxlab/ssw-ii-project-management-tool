@foreach($agreement->deliverables as $deliverable)
    @include('agreements.partials.deliverable-row', ['agreement' => $agreement, 'deliverable' => $deliverable])
@endforeach
@if($agreement->deliverables->isEmpty())
<tr>
    <td colspan="7" class="text-muted text-center py-3">No deliverables defined yet</td>
</tr>
@endif
