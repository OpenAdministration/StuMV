<?php

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Livewire\Settings\Domain\ListDomains;
use App\Livewire\Settings\Domain\NewDomain;
use App\Livewire\Settings\EditRealm;
use App\Livewire\Settings\EditRealmBranding;
use App\Livewire\Settings\MembershipExport\EditMembershipExportTemplate;
use App\Livewire\Settings\MembershipExport\ListMembershipExportTemplates;
use App\Models\ActivityLog;
use App\Models\MembershipExportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('editing the realm logs the changed name and contact fields on the realm', function (): void {
    $community = newCommunity();
    $before = $community->getFirstAttribute('description');
    actingAsAdmin($community);

    Livewire::test(EditRealm::class, ['realm' => $community])
        ->set('name', 'Studierendenrat')
        ->set('city', 'Jena')
        ->call('save')
        ->assertHasNoErrors();
    Livewire::test(EditRealm::class, ['realm' => Community::findByUid($community->getShortCode())])->call('save');

    $log = ActivityLog::sole();
    expect($log->action)->toBe('realm.updated')
        ->and($log->realm)->toBe($community->getShortCode())
        ->and($log->subject_type)->toBe(Community::class)
        ->and($log->details)->toBe([
            'name' => ['old' => $before, 'new' => 'Studierendenrat'],
            'city' => ['old' => null, 'new' => 'Jena'],
        ]);
});

test('the login notice is logged when first saved and when changed', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    $component = Livewire::test(EditRealmBranding::class, ['realm' => $community])
        ->set('login_notice_enabled', true)
        ->set('login_notice', '<p>Hallo</p>')
        ->call('saveLoginNotice');
    $component->set('login_notice', '<p>Servus</p>')->call('saveLoginNotice');

    $logs = ActivityLog::orderBy('id')->get();
    // switching the notice on saves right away
    expect($logs->pluck('action')->all())->toBe(['realm_branding.created', 'realm_branding.updated', 'realm_branding.updated'])
        ->and($logs[0]->details['login_notice_enabled'])->toBeTrue()
        ->and($logs[1]->details)->toBe(['login_notice' => ['old' => null, 'new' => '<p>Hallo</p>']])
        ->and($logs[2]->details)->toBe(['login_notice' => ['old' => '<p>Hallo</p>', 'new' => '<p>Servus</p>']]);
});

test('adding and removing a domain is logged with the domain as name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $dc = 'dom'.bin2hex(random_bytes(3)).'.de';
    actingAsAdmin($community);

    Livewire::test(NewDomain::class, ['realm' => $community])
        ->set('dc', $dc)
        ->call('save')
        ->assertHasNoErrors();
    $domain = Domain::findBy('dc', $dc);
    TestLdap::track($domain);
    Livewire::test(ListDomains::class, ['realm' => $community])
        ->call('deletePrepare', $dc)
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['domain.created', 'domain.deleted'])
        ->and($logs->pluck('subject_label')->unique()->all())->toBe([$dc])
        ->and($logs->pluck('subject_id')->unique())->toHaveCount(1)
        ->and($logs[1]->details)->toBe(['dn' => $domain->getDn()]);
});

test('export templates are logged when created, changed and deleted', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    Livewire::test(EditMembershipExportTemplate::class, ['realm' => $community])
        ->set('name', 'Nachweis')
        ->call('save')
        ->assertHasNoErrors();
    $template = MembershipExportTemplate::where('realm', $uid)->sole();
    Livewire::test(EditMembershipExportTemplate::class, ['realm' => $community, 'template' => $template])
        ->set('subject', 'Tätigkeitsnachweis')
        ->call('save')
        ->assertHasNoErrors();
    Livewire::test(ListMembershipExportTemplates::class, ['realm' => $community])
        ->call('deletePrepare', $template->id)
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['membership_export_template.created', 'membership_export_template.updated', 'membership_export_template.deleted'])
        ->and($logs->pluck('subject_label')->unique()->all())->toBe(['Nachweis'])
        ->and($logs[0]->details)->toHaveKey('date_format')->not->toHaveKey('id')
        ->and($logs[1]->details)->toBe(['subject' => ['old' => null, 'new' => 'Tätigkeitsnachweis']])
        ->and($logs[2]->details['subject'])->toBe('Tätigkeitsnachweis');
});
