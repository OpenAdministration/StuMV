<?php

namespace App\Models\Concerns;

use App\Support\ActivityLogger;
use Illuminate\Support\Arr;

/**
 * Activity log entries for a database row: created and deleted with all its fields,
 * updated with only the fields that changed.
 */
trait RecordsActions
{
    /** Prefix of the logged actions, e.g. "domain" for domain.created. */
    abstract protected function activityLogName(): string;

    protected function activityLogRealm(): ?string
    {
        return $this->getAttribute('realm');
    }

    protected function activityLogLabel(): ?string
    {
        return $this->getAttribute('name');
    }

    /**
     * Call after the change; "saved" logs created or updated, whichever happened.
     * Other events, e.g. "revoked", log only $details.
     *
     * @param  array<string, mixed>  $details  added to the fields
     * @param  ?array<string, mixed>  $before  activityLogSnapshot() from before, when saved more than once
     */
    public function recordAction(string $event, array $details = [], ?array $before = null): void
    {
        if ($event === 'saved') {
            $event = $this->wasRecentlyCreated ? 'created' : 'updated';
        }

        $fields = match ($event) {
            'created', 'deleted' => $this->activityLogSnapshot(),
            'updated' => $before === null ? $this->changedFieldsForActivityLog() : ActivityLogger::diff($before, $this->activityLogSnapshot()),
            default => [],
        };
        if ($event === 'updated' && $fields === [] && $details === []) {
            return;
        }

        resolve(ActivityLogger::class)->record(
            "{$this->activityLogName()}.{$event}",
            $this->activityLogRealm(),
            $this,
            [...$fields, ...$details],
            $this->activityLogLabel(),
        );
    }

    /** @return list<string> */
    private function fieldsNotLogged(): array
    {
        return array_values(array_filter([$this->getKeyName(), $this->getCreatedAtColumn(), $this->getUpdatedAtColumn(), ...$this->getHidden()]));
    }

    /** @return array<string, mixed> */
    public function activityLogSnapshot(): array
    {
        return Arr::except($this->attributesToArray(), $this->fieldsNotLogged());
    }

    /** @return array<string, array{old: mixed, new: mixed}> */
    private function changedFieldsForActivityLog(): array
    {
        $previous = $this->getPrevious();
        $changed = [];
        // raw database values, cast like the model's own attributes
        $cast = fn (string $key, mixed $value): mixed => $value !== null && $this->hasCast($key) ? $this->castAttribute($key, $value) : $value;
        foreach (Arr::except($this->getChanges(), $this->fieldsNotLogged()) as $key => $new) {
            $changed[$key] = ['old' => $cast($key, $previous[$key] ?? null), 'new' => $cast($key, $new)];
        }

        return $changed;
    }
}
