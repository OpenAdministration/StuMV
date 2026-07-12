<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Rules\UniqueCommittee;
use LdapRecord\Models\Attributes\DistinguishedName;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class NewCommittee extends Component
{
    #[Locked]
    public string $realm_uid;

    public string $parent_dn = '';

    #[Validate]
    public string $ou = '';

    #[Validate('required|min:3')]
    public string $description = '';

    public array $roles = ['member'];

    private array $defaultRoles = [
        'head' => [
            'cn' => 'leitung',
            'description' => 'Leitung',
        ],
        'deputy-head' => [
            'cn' => 'leitung-stelli',
            'description' => 'Stellvertretende Leitung',
        ],
        'member' => [
            'cn' => 'mitglied',
            'description' => 'Mitglied',
        ],
        'active' => [
            'cn' => 'aktiv',
            'description' => 'Aktiv',
        ],
        'student-member' => [
            'cn' => 'mitglied-stud',
            'description' => 'Studentisches Mitglied',
        ],
    ];

    public function mount(Community $uid)
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function rules(): array
    {
        return [
            'ou' => [
                'required',
                'regex:/^[a-z0-9-]*$/',
                new UniqueCommittee($this->realm_uid),
            ],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'ou' => __('Short Committee Name'),
            'description' => __('Full Committee Name'),
        ];
    }

    protected function messages(): array
    {
        return [
            'ou.regex' => __('committees.new_ou_regex_error'),
        ];
    }

    public function render()
    {
        $parentsLdap = Committee::fromCommunity($this->realm_uid)
            ->whereNotEquals('ou', 'Committees') // remove parent Folder from Results;
            ->get();

        $parents = [];
        foreach ($parentsLdap as $parent) {
            $dn = DistinguishedName::make($parent->getDn());
            $pathFromDn = $dn->assoc();
            $pathDescription = '';
            foreach (array_reverse($pathFromDn['ou']) as $key => $ou) {
                if ($key < 3) {
                    continue;
                }
                $c = Committee::findByNameOrFail($this->realm_uid, $ou);
                $pathDescription .= $c->getFirstAttribute('description').' → ';
            }
            $pathDescription = rtrim($pathDescription, ' → ');

            $parents[$parent->getDn()] = [
                'description' => $pathDescription,
            ];
        }

        return view('livewire.committee.new-committee', [
            'select_parents' => $parents,
            'defaultRoles' => $this->defaultRoles,
        ])->title(__('committees.new_title'));
    }

    public function save()
    {

        $this->validate();

        $dn = Committee::dnFrom($this->realm_uid, $this->ou, parentDn: $this->parent_dn);
        $c = new Committee([
            'ou' => $this->ou,
            'description' => $this->description,
        ]);
        $c->setDn($dn);
        $c->save();

        foreach ($this->roles as $role) {
            $roleConfig = $this->defaultRoles[$role];
            $r = new Role([
                'cn' => $roleConfig['cn'],
                'description' => $roleConfig['description'],
                'uniqueMember' => '',
            ]);
            $r->inside($c);
            $r->save();
        }

        return response()->redirectToRoute('committees.roles', [
            'uid' => $this->realm_uid,
            'ou' => $this->ou,
        ]);
    }
}
