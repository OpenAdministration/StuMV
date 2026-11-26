<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\RealmIdentityProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Models\Model as LdapModel;

/** Records what a person or the system changed, see App\Models\ActivityLog. */
class ActivityLogger
{
    /** Keys whose values never reach the log, at any depth. */
    private const string SENSITIVE_KEYS = '/password|secret|token/i';

    /**
     * @param  ?string  $realm  the subject's realm, null only for actions outside any realm
     * @param  array<string, mixed>  $details
     * @param  User|RealmIdentityProvider|null  $actor  acting instead of the logged in account
     */
    public function record(
        string $action,
        ?string $realm,
        Model|LdapModel|null $subject = null,
        array $details = [],
        ?string $label = null,
        User|RealmIdentityProvider|null $actor = null,
    ): ActivityLog {
        $actor ??= Auth::user();

        return ActivityLog::create([
            'realm' => $realm,
            'actor_type' => match (true) {
                $actor instanceof User => 'user',
                $actor instanceof RealmIdentityProvider => 'identity_provider',
                default => null,
            },
            'actor_id' => match (true) {
                $actor instanceof User => $actor->uid,
                $actor instanceof RealmIdentityProvider => $actor->id,
                default => null,
            },
            'action' => $action,
            'subject_type' => match (true) {
                $subject instanceof Model => $subject->getMorphClass(),
                $subject instanceof LdapModel => $subject::class,
                default => null,
            },
            'subject_id' => $this->subjectId($subject),
            'subject_label' => $label ?? $this->ldapLabel($subject),
            'details' => $details === [] ? null : $this->redact($details),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /**
     * Logs an LDAP entry such as a committee, role or group, by default with its DN and description.
     *
     * @param  ?array<string, mixed>  $details
     */
    public function recordEntry(string $action, string $realm, LdapModel $entry, ?array $details = null): void
    {
        $this->record($action, $realm, $entry, $details ?? [
            'dn' => $entry->getDn(),
            'description' => $entry->getFirstAttribute('description'),
        ]);
    }

    /**
     * @param  list<string>  $groupDns  the groups the role was linked to
     */
    public function recordRoleDeletion(string $realm, LdapModel $role, array $groupDns): void
    {
        $this->recordEntry('role.deleted', $realm, $role, [
            'dn' => $role->getDn(),
            'description' => $role->getFirstAttribute('description'),
            'groups' => $groupDns,
        ]);
    }

    /**
     * Logs a changed description, nothing when it stayed the same.
     */
    public function recordDescriptionChange(string $action, string $realm, LdapModel $entry, ?string $before): void
    {
        $details = self::diff(['description' => $before], ['description' => $entry->getFirstAttribute('description')]);
        if ($details !== []) {
            $this->record($action, $realm, $entry, $details);
        }
    }

    /**
     * The fields that differ, as old and new value.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[$key] = ['old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    private function subjectId(Model|LdapModel|null $subject): ?string
    {
        if ($subject instanceof Model) {
            return (string) $subject->getKey();
        }

        if (! $subject instanceof LdapModel) {
            return null;
        }

        // a freshly created entry gets its entryUUID from the server
        $guid = $subject->getConvertedGuid() ?? ($subject->exists ? $subject->fresh()?->getConvertedGuid() : null);

        return $guid ?? $subject->getDn();
    }

    private function ldapLabel(Model|LdapModel|null $subject): ?string
    {
        if (! $subject instanceof LdapModel) {
            return null;
        }

        return $subject->getFirstAttribute('description')
            ?? $subject->getFirstAttribute('cn')
            ?? $subject->getFirstAttribute('ou')
            ?? $subject->getDn();
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEYS, $key)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
