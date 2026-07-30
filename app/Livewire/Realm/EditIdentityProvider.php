<?php

namespace App\Livewire\Realm;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Models\IdentityProviderGroupMapping;
use App\Models\IdentityProviderRoleMapping;
use App\Models\RealmIdentityProvider;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditIdentityProvider extends Component
{
    #[Locked]
    public string $providerId;

    public string $uid = '';

    public string $name = '';

    public string $issuer = '';

    public string $client_id = '';

    public string $client_secret = '';

    public string $groups_claim = 'groups';

    public bool $enabled = true;

    // "Add mapping" mini-form.
    public string $new_external_group = '';

    public string $new_committee_dn = '';

    public string $new_role_cn = '';

    public ?int $deleteMappingId = null;

    public string $deleteMappingLabel = '';

    // "Add group mapping" mini-form.
    public string $new_group_external_group = '';

    public string $new_group_dn = '';

    public ?int $deleteGroupMappingId = null;

    public string $deleteGroupMappingLabel = '';

    public function mount(Community $realm, RealmIdentityProvider $provider): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();

        abort_if($provider->realm !== $this->uid, 404);

        $this->providerId = $provider->id;
        $this->name = $provider->name;
        $this->issuer = $provider->issuer;
        $this->client_id = $provider->client_id;
        $this->groups_claim = $provider->groups_claim;
        $this->enabled = $provider->enabled;
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'issuer' => 'required|url|max:255',
            'client_id' => 'required|string|max:255',
            // Left blank on mount (see mount()) so the stored secret's length
            // never leaks through the password field's dot count - nullable
            // here means "leave the current secret unchanged".
            'client_secret' => 'nullable|string|max:255',
            'groups_claim' => 'required|string|max:255',
            'enabled' => 'boolean',
        ];
    }

    private function provider(): RealmIdentityProvider
    {
        return RealmIdentityProvider::where('realm', $this->uid)->findOrFail($this->providerId);
    }

    public function render()
    {
        $committees = Committee::fromCommunity($this->uid)->recursive()->get();
        $roles = collect();

        if (! empty($this->new_committee_dn)) {
            $committee = Committee::findOrFail($this->new_committee_dn);
            $roles = $committee->roles()->get();
        }

        $mappingRows = $this->provider()->roleMappings()->orderBy('external_group')->get()
            ->map(function (IdentityProviderRoleMapping $mapping): array {
                $committee = Committee::find($mapping->committee_dn);

                return [
                    'mapping' => $mapping,
                    'committee' => $committee,
                    'role' => $committee ? Role::find('cn='.$mapping->role_cn.','.$mapping->committee_dn) : null,
                ];
            });

        $groups = Group::fromCommunity($this->uid)->get();

        $groupMappingRows = $this->provider()->groupMappings()->orderBy('external_group')->get()
            ->map(fn (IdentityProviderGroupMapping $mapping): array => [
                'mapping' => $mapping,
                'group' => Group::find($mapping->group_dn),
            ]);

        return view('livewire.realm.edit-identity-provider', [
            'mappingRows' => $mappingRows,
            'committees' => $committees,
            'roles' => $roles,
            'groups' => $groups,
            'groupMappingRows' => $groupMappingRows,
        ])->title(__('identity_providers.edit_title'));
    }

    public function save()
    {
        $this->validate();

        $this->provider()->update(array_filter([
            'name' => $this->name,
            'issuer' => rtrim($this->issuer, '/'),
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret !== '' ? $this->client_secret : null,
            'groups_claim' => $this->groups_claim,
            'enabled' => $this->enabled,
        ], fn ($value) => $value !== null));

        Flux::toast(variant: 'success', text: __('identity_providers.edit_success'));

        return to_route('realms.identity-providers', ['realm' => $this->uid]);
    }

    public function addMapping(): void
    {
        $this->validate([
            'new_external_group' => 'required|string|max:255',
            'new_committee_dn' => 'required|string',
            'new_role_cn' => 'required|string',
        ]);

        $this->provider()->roleMappings()->create([
            'external_group' => $this->new_external_group,
            'committee_dn' => $this->new_committee_dn,
            'role_cn' => $this->new_role_cn,
        ]);

        $this->reset('new_external_group', 'new_committee_dn', 'new_role_cn');

        Flux::toast(variant: 'success', text: __('identity_providers.mapping_added_success'));
    }

    public function deleteMappingPrepare(int $mappingId): void
    {
        $mapping = $this->provider()->roleMappings()->findOrFail($mappingId);
        $this->deleteMappingId = $mapping->id;
        $this->deleteMappingLabel = $mapping->external_group;
        Flux::modal('delete-mapping')->show();
    }

    public function deleteMappingCommit(): void
    {
        $this->provider()->roleMappings()->findOrFail($this->deleteMappingId)->delete();

        Flux::toast(variant: 'success', text: __('identity_providers.mapping_deleted_success'));
        $this->closeDeleteMapping();
    }

    public function closeDeleteMapping(): void
    {
        Flux::modal('delete-mapping')->close();
        unset($this->deleteMappingId, $this->deleteMappingLabel);
    }

    public function addGroupMapping(): void
    {
        $this->validate([
            'new_group_external_group' => 'required|string|max:255',
            'new_group_dn' => 'required|string',
        ]);

        $this->provider()->groupMappings()->create([
            'external_group' => $this->new_group_external_group,
            'group_dn' => $this->new_group_dn,
        ]);

        $this->reset('new_group_external_group', 'new_group_dn');

        Flux::toast(variant: 'success', text: __('identity_providers.group_mapping_added_success'));
    }

    public function deleteGroupMappingPrepare(int $mappingId): void
    {
        $mapping = $this->provider()->groupMappings()->findOrFail($mappingId);
        $this->deleteGroupMappingId = $mapping->id;
        $this->deleteGroupMappingLabel = $mapping->external_group;
        Flux::modal('delete-group-mapping')->show();
    }

    public function deleteGroupMappingCommit(): void
    {
        $this->provider()->groupMappings()->findOrFail($this->deleteGroupMappingId)->delete();

        Flux::toast(variant: 'success', text: __('identity_providers.group_mapping_deleted_success'));
        $this->closeDeleteGroupMapping();
    }

    public function closeDeleteGroupMapping(): void
    {
        Flux::modal('delete-group-mapping')->close();
        unset($this->deleteGroupMappingId, $this->deleteGroupMappingLabel);
    }
}
