{{-- Kept on one line (not truncated) so the responsive collapse in
     tailwind.blade.php can measure natural widths and detect overflow -
     without this, text would just wrap onto multiple lines instead of
     overflowing, and nothing would ever need to collapse. --}}
<span class="whitespace-nowrap">{{ $breadcrumb->title }}</span>
