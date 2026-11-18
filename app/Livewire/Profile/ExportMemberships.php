<?php

namespace App\Livewire\Profile;

use App\Enums\ExportDateFormat;
use App\Enums\LetterRecipient;
use App\Enums\LetterSender;
use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Concerns\SortsMemberships;
use App\Models\MembershipExportTemplate;
use App\Models\RealmContact;
use App\Support\CoverLetter;
use App\Support\MembershipCertificate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
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

    /** Id of the chosen template. */
    public string $template = '';

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

        $templates = $this->templates();
        if ($templates->count() === 1) {
            $this->template = (string) $templates->first()->id;
        }

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
        $user = $this->member();

        return view('livewire.profile.export-memberships', [
            'memberships' => $this->sortMemberships(MembershipCertificate::memberships($this->realm_uid, $this->currentUsername, false)),
            'name' => $user->getFirstAttribute('givenName').' '.$user->getFirstAttribute('sn'),
            'templates' => $this->templates(),
            'warnings' => $this->warnings(),
        ])->title(__('profile.export_memberships_title'));
    }

    public function export()
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $this->authorizeExport($community, $this->currentUsername);
        $templates = $this->templates();

        $this->validate(
            [
                'selected' => ['required', 'array', 'min:1'],
                'template' => [Rule::requiredIf($templates->isNotEmpty()), Rule::in($templates->modelKeys())],
            ],
            [
                'selected.required' => __('profile.export_memberships_none_selected'),
                'selected.min' => __('profile.export_memberships_none_selected'),
            ],
            ['template' => __('profile.export_template')],
        );

        $template = $templates->find($this->template);
        $letter = $template?->with_letter
            ? CoverLetter::fromTemplate($template, $community, $this->member(), auth()->user()->ldap())
            : null;

        return MembershipCertificate::download(
            $this->realm_uid,
            $this->currentUsername,
            $community->getFirstAttribute('description'),
            strtolower(trans('profile.memberships')).'_'.$this->currentUsername.'.pdf',
            array_map(intval(...), $this->selected),
            $letter,
            $template?->date_format ?? ExportDateFormat::Iso,
        );
    }

    /** @return Collection<int, MembershipExportTemplate> */
    private function templates(): Collection
    {
        return MembershipExportTemplate::forRealm($this->realm_uid)->get();
    }

    /**
     * Addresses the chosen letter would be missing.
     *
     * @return list<string>
     */
    private function warnings(): array
    {
        $template = $this->templates()->find($this->template);
        if (! $template?->with_letter) {
            return [];
        }

        $hasAddress = fn (User $user): bool => $user->getFirstAttribute('street') && $user->getFirstAttribute('l');
        $warnings = [];

        if ($template->sender === LetterSender::User) {
            if (! $hasAddress(auth()->user()->ldap())) {
                $warnings[] = __('profile.cover_letter_no_profile_address');
            }
        } elseif (RealmContact::forRealm($this->realm_uid) === null) {
            $warnings[] = __('profile.cover_letter_no_contact');
        }

        if ($template->recipient_type === LetterRecipient::Member && ! $hasAddress($this->member())) {
            $warnings[] = __('profile.cover_letter_no_member_address');
        }

        return $warnings;
    }

    private function member(): User
    {
        return User::query()->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())->where('uid', '=', $this->currentUsername)->first() ?? abort(404);
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
