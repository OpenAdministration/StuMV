<?php

namespace App\Listeners;

use App\Ldap\Committee;
use App\Ldap\Group;
use App\Ldap\Role;
use App\Ldap\User;
use LightSaml\ClaimTypes;
use LightSaml\Model\Assertion\Attribute;

class SamlAssertionAttributesListener
{
    public function __construct()
    {
    }

    public function handle($event): void
    {
        /** @var User $ldapUser */
        $ldapUser = \Auth::user()->ldap();
        $userDn = $ldapUser->getDn();

        $roles = Role::query()->where('uniqueMember', $userDn)->get();

        $committeeDns = $roles->map(function ($item){
           return $item->getParentDn();
        })->reject(function (string $dn){
            return !str_contains($dn, 'ou=Committees');
        })->toArray();

        //Issue: returns all Committees from all realms, SAML has no easy more segmented solution
        $committeeQuery = Committee::query();
        foreach ($committeeDns as $committeeDn){
            $committeeQuery->orWhere('dn', '=', $committeeDn);
        }
        $committees = $committeeQuery->get()->map(function ($item){
           return $item->getFirstAttribute('description');
        });

        $groupsQuery = Group::query()->orWhere('uniqueMember', '=', $userDn);
        foreach ($roles as $role){
            $groupsQuery->orWhere('uniqueMember', '=', $role->getDn());
        }
        $groups = $groupsQuery->get();

        $groupDns = $groups->map(function ($item){
            return $item->getDn();
        })->reject(function (string $dn){
            // throw out the roles. only memberships and permissions inside
            return str_contains($dn, 'ou=Committees');
        })->toArray();

        //$committees = Committee::query()->where('dn', "=", $committeeDns, 'or')->get();

        $event->attribute_statement
            ->addAttribute(new Attribute(ClaimTypes::PPID, $ldapUser->getObjectGuid()))
            ->addAttribute(new Attribute('ppid', $ldapUser->getObjectGuid()))

            ->addAttribute(new Attribute(ClaimTypes::NAME, $ldapUser->getFirstAttribute('uid')))
            ->addAttribute(new Attribute('uid', $ldapUser->getFirstAttribute('uid')))

            //->setAttribute(new Attribute(ClaimTypes::COMMON_NAME, $ldapUser->getFirstAttribute('cn')))
            ->addAttribute(new Attribute('cn', $ldapUser->getFirstAttribute('cn')))

            ->addAttribute(new Attribute('mail', $ldapUser->getFirstAttribute('mail')))

            ->addAttribute(new Attribute(ClaimTypes::GROUP, $groupDns))
            ->addAttribute(new Attribute('group', $groupDns))

            ->addAttribute(new Attribute('committees', $committees))
        ;
    }
}
