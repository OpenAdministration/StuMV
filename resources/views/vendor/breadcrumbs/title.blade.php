{{-- Renders a breadcrumb title. Items flagged `truncate` are ellipsed at
     ~20 chars via CSS, with the full name shown on hover - unless it's the
     only breadcrumb item (e.g. just the community name on the community
     dashboard), where there's no sibling crowding the bar to truncate for. --}}
@if(!empty($breadcrumb->truncate) && empty($onlyItem))
    <span class="inline-block max-w-[20ch] truncate align-bottom" title="{{ $breadcrumb->title }}">{{ $breadcrumb->title }}</span>
@else
    {{ $breadcrumb->title }}
@endif
