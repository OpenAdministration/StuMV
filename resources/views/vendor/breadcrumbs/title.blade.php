{{-- Renders a breadcrumb title. Items flagged `truncate` (committee long names)
     are ellipsed at ~20 chars via CSS, with the full name shown on hover. --}}
@if(!empty($breadcrumb->truncate))
    <span class="inline-block max-w-[20ch] truncate align-bottom" title="{{ $breadcrumb->title }}">{{ $breadcrumb->title }}</span>
@else
    {{ $breadcrumb->title }}
@endif
