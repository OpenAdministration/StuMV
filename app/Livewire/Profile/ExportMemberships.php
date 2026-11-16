<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Concerns\SortsMemberships;
use App\Models\RealmBranding;
use App\Models\RealmContact;
use App\Support\CoverLetter;
use App\Support\MembershipCertificate;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ExportMemberships extends Component
{
    use SortsMemberships;

    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $currentUsername;

    /** @var array<int, string> */
    public array $selected = [];

    public bool $withCoverLetter = false;

    /** @var array<string, string> */
    public array $recipient = [];

    public string $letterBody = '';

    public string $senderUnit = '';

    public string $senderEmail = '';

    public string $senderPhone = '';

    public string $signerFunction = '';

    public string $subject = '';

    /** Where "cancel" leads: the profile or the member list this page was opened from. */
    #[Locked]
    public string $backUrl;

    public function mount(Community $realm, string $username): void
    {
        $this->authorizeExport($realm, $username);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;

        $fallback = route('profile.memberships', ['realm' => $this->realm_uid, 'username' => $username]);
        $previous = url()->previous();
        $this->backUrl = $previous !== url()->current() && str_starts_with($previous, url('/')) ? $previous : $fallback;

        // the realm settings hold the defaults a letter starts out with
        $contact = RealmContact::forRealm($this->realm_uid);
        $this->withCoverLetter = (bool) $contact?->letter_enabled;
        $this->recipient = CoverLetter::recipient($contact->letter_recipient ?? []);
        $this->senderUnit = (string) $contact?->letter_sender_unit;
        $this->senderEmail = (string) ($contact?->letter_sender_email ?? $contact?->email);
        $this->senderPhone = (string) ($contact?->letter_sender_phone ?? $contact?->phone);
        $this->signerFunction = (string) $contact?->letter_signer_function;
        $this->subject = (string) $contact?->letter_subject;
        $this->letterBody = (string) $contact?->letter_body;
        $this->selectAll();
    }

    public function selectAll(): void
    {
        $this->selected = array_map(
            fn (array $row): string => (string) $row['id'],
            MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, false),
        );
    }

    public function deselectAll(): void
    {
        $this->selected = [];
    }

    public function render()
    {
        $user = User::query()->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())->where('uid', '=', $this->currentUsername)->first() ?? abort(404);

        return view('livewire.profile.export-memberships', [
            'memberships' => $this->sortMemberships(MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, false)),
            'name' => $user->getFirstAttribute('givenName').' '.$user->getFirstAttribute('sn'),
            'hasContact' => RealmContact::forRealm($this->realm_uid) !== null,
        ])->title(__('profile.export_memberships_title'));
    }

    public function export()
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $this->authorizeExport($community, $this->currentUsername);

        $rules = [
            'selected' => ['required', 'array', 'min:1'],
            ...CoverLetter::recipientRules('recipient', ['exclude_unless:withCoverLetter,true']),
            'letterBody' => [
                'exclude_unless:withCoverLetter,true', 'required', 'string', 'max:20000',
                // the editor submits <p></p> when empty
                fn (string $attribute, mixed $value, \Closure $fail) => CoverLetter::bodyIsEmpty((string) $value)
                    ? $fail(__('validation.required', ['attribute' => __('profile.cover_letter_body')]))
                    : null,
            ],
            'senderUnit' => ['exclude_unless:withCoverLetter,true', 'nullable', 'string', 'max:100'],
            'senderEmail' => ['exclude_unless:withCoverLetter,true', 'nullable', 'email', 'max:255'],
            'senderPhone' => ['exclude_unless:withCoverLetter,true', 'nullable', 'string', 'max:50'],
            'signerFunction' => ['exclude_unless:withCoverLetter,true', 'nullable', 'string', 'max:100'],
            'subject' => ['exclude_unless:withCoverLetter,true', 'nullable', 'string', 'max:150'],
        ];

        $this->validate(
            $rules,
            [
                'selected.required' => __('profile.export_memberships_none_selected'),
                'selected.min' => __('profile.export_memberships_none_selected'),
            ],
            [
                'letterBody' => __('profile.cover_letter_body'),
                'senderUnit' => __('profile.cover_letter_sender_unit'),
                'senderEmail' => __('profile.cover_letter_sender_email'),
                'senderPhone' => __('profile.cover_letter_sender_phone'),
                'signerFunction' => __('profile.cover_letter_signer_function'),
                'subject' => __('profile.cover_letter_subject'),
            ],
        );

        $letter = null;
        if ($this->withCoverLetter) {
            $logo = RealmBranding::forRealm($this->realm_uid)?->logo_id;
            $letter = new CoverLetter(
                (string) $community->getFirstAttribute('description'),
                RealmContact::forRealm($this->realm_uid),
                CoverLetter::recipientAddress($this->recipient),
                $this->letterBody,
                $logo ? 'realm-branding/'.$logo : null,
                trim($this->senderUnit) ?: null,
                trim($this->senderEmail) ?: null,
                trim($this->senderPhone) ?: null,
                (string) auth()->user()->full_name,
                trim($this->signerFunction) ?: null,
                trim($this->subject) ?: null,
            );
        }

        return MembershipCertificate::download(
            $this->realm_uid,
            $this->currentUsername,
            $community->getFirstAttribute('description'),
            strtolower(trans('profile.memberships')).'_'.$this->currentUsername.'.pdf',
            array_map(intval(...), $this->selected),
            $letter,
        );
    }

    /** The member themselves, their realm's admins, or its moderators (member list export). */
    private function authorizeExport(Community $realm, string $username): void
    {
        $user = auth()->user();

        abort_unless(
            $user->can('manageProfile', [User::class, $realm, $username]) || $user->can('moderator', $realm),
            403,
        );
    }
}
