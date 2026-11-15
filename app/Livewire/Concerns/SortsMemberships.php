<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Url;

/** Sortable role/committee/from/until columns of the membership tables. */
trait SortsMemberships
{
    #[Url]
    public string $sortField = 'from';

    #[Url]
    public string $sortDirection = 'desc';

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['role', 'committee', 'from', 'until'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    /**
     * @param  array<int, array{role: mixed, from: mixed, until: mixed}>  $memberships
     * @return array<int, array{role: mixed, from: mixed, until: mixed}>
     */
    protected function sortMemberships(array $memberships): array
    {
        $key = match ($this->sortField) {
            'role' => fn (array $row): string => mb_strtolower((string) $row['role']->getFirstAttribute('description')),
            'committee' => fn (array $row): string => mb_strtolower((string) $row['role']->committee()->getFirstAttribute('description')),
            // an open-ended membership runs until today, so it sorts after every ended one
            'until' => fn (array $row): string => $row['until'] != '' ? Date::parse($row['until'])->format('Y-m-d') : '9999-12-31',
            default => fn (array $row): string => Date::parse($row['from'])->format('Y-m-d'),
        };

        return collect($memberships)->sortBy($key, SORT_NATURAL, $this->sortDirection === 'desc')->values()->all();
    }
}
