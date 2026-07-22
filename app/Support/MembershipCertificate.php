<?php

namespace App\Support;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Ldap\User;
use App\Models\RoleMembership;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generates the "Tätigkeitsbescheinigung" (membership activity certificate)
 * PDF - shared by a user exporting their own certificate (Profile\Memberships)
 * and an admin exporting one on a member's behalf (Realm\ListMembers).
 */
class MembershipCertificate
{
    /**
     * @return array<int, array{role: Role, from: mixed, until: mixed, decided: mixed, comment: mixed}>
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
                'role' => Role::findOrFail('cn='.$row->role_cn.','.$row->committee_dn),
                'from' => $row->from,
                'until' => $row->until,
                'decided' => $row->decided,
                'comment' => $row->comment,
            ])
            ->all();
    }

    public static function pdf(string $realmUid, string $username, ?string $communityName = null): DomPdf
    {
        $user = User::query()->in(Community::findOrFailByUid($realmUid)->peopleDn())->where('uid', '=', $username)->first() ?? abort(404);

        return Pdf::loadView('pdfs.memberships', [
            'fullName' => $user->getFirstAttribute('cn'),
            'community' => $communityName,
            'memberships' => self::memberships($realmUid, $username, false),
        ]);
    }

    public static function download(string $realmUid, string $username, ?string $communityName, string $filename): StreamedResponse
    {
        $pdf = self::pdf($realmUid, $username, $communityName);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->stream();
        }, $filename);
    }
}
