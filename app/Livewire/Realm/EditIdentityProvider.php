<?php

namespace App\Livewire\Realm;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Livewire\Concerns\ParsesExtraAuthorizeParams;
use App\Models\RealmIdentityProvider;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditIdentityProvider extends Component
{
    use ParsesExtraAuthorizeParams;

    #[Locked]
    public int $providerId;

    public string $uid = '';

    public string $name = '';

    public string $issuer = '';

    public string $client_id = '';

    public string $client_secret = '';

    public string $groups_claim = 'groups';

    public string $extra_authorize_params_input = '';

    public bool $enabled = true;

    // "Add mapping" mini-form.
    public string $new_external_group = '';

    public string $new_committee_dn = '';

    public string $new_role_cn = '';

    public ?int $deleteMappingId = null;

    public string $deleteMappingLabel = '';

    public function mount(Community $realm, RealmIdentityProvider $provider): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();

        abort_if($provider->realm !== $this->uid, 404);

        $this->providerId = $provider->id;
        $this->name = $provider->name;
        $this->issuer = $provider->issuer;
        $this->client_id = $provider->client_id;
        $this->client_secret = $provider->client_secret;
        $this->groups_claim = $provider->groups_claim;
        $this->extra_authorize_params_input = $this->formatExtraAuthorizeParams($provider->extra_authorize_params);
        $this->enabled = $provider->enabled;
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'issuer' => 'required|url|max:255',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string|max:255',
            'groups_claim' => 'required|string|max:255',
            'extra_authorize_params_input' => ['nullable', 'string', $this->validateExtraAuthorizeParamsLine(...)],
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

        return view('livewire.realm.edit-identity-provider', [
            'mappings' => $this->provider()->roleMappings()->orderBy('external_group')->get(),
            'committees' => $committees,
            'roles' => $roles,
        ])->title(__('identity_providers.edit_title'));
    }

    public function save()
    {
        $this->validate();

        $this->provider()->update([
            'name' => $this->name,
            'issuer' => rtrim($this->issuer, '/'),
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'groups_claim' => $this->groups_claim,
            'extra_authorize_params' => $this->parseExtraAuthorizeParams($this->extra_authorize_params_input),
            'enabled' => $this->enabled,
        ]);

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
}
