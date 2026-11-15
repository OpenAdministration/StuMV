<?php

namespace App\Support;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Ldap\User;
use App\Models\RoleMembership;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates the "Tätigkeitsbescheinigung" (membership activity certificate)
 * PDF - shared by a user exporting their own certificate (Profile\Memberships)
 * and an admin exporting one on a member's behalf (Realm\ListMembers).
 */
class MembershipCertificate
{
    /**
     * @return array<int, array{id: int, role: Role, from: mixed, until: mixed, decided: mixed, comment: mixed}>
     */
    public static function memberships(string $realmUid, string $username, bool $onlyActive): array
    {
        // committee_dn is a bare string column (no realm reference of its
        // own) - filter by which realm's Committees branch it falls under
        // in addition to username, since the same username can now
        // independently exist in more than one realm.
        $query = RoleMembership::where('username', $username)
            ->where('committee_dn', 'like', '%'.Committee::dnRootResolved($realmUid));
        if ($onlyActive) {
            $query->whereNull('until');
        }

        return $query->get()
            ->map(fn (RoleMembership $row): array => [
                'id' => $row->id,
                'role' => Role::findOrFail('cn='.$row->role_cn.','.$row->committee_dn),
                'from' => $row->from,
                'until' => $row->until,
                'decided' => $row->decided,
                'comment' => $row->comment,
            ])
            ->all();
    }

    /**
     * Raw bytes of the certificate PDF, limited to the given membership ids
     * when there are any - ids of other users' memberships are ignored.
     *
     * @param  array<int, int>|null  $ids
     */
    public static function pdf(string $realmUid, string $username, ?string $communityName = null, ?array $ids = null): string
    {
        $user = User::query()->in(Community::findOrFailByUid($realmUid)->peopleDn())->where('uid', '=', $username)->first() ?? abort(404);

        return (new MembershipCertificatePdf)->render(
            __('profile.activities'),
            (string) $user->getFirstAttribute('cn'),
            $communityName,
            array_values(array_filter(
                self::memberships($realmUid, $username, false),
                fn (array $row): bool => $ids === null || in_array($row['id'], $ids, true),
            )),
        );
    }

    /**
     * @param  array<int, int>|null  $ids
     */
    public static function download(string $realmUid, string $username, ?string $communityName, string $filename, ?array $ids = null): StreamedResponse
    {
        $pdf = self::pdf($realmUid, $username, $communityName, $ids);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
