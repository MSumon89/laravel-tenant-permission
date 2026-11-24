<?php

use BuildWithSmk\TenantPermission\PermissionRegistrar;
use BuildWithSmk\TenantPermission\Tests\Fixtures\OfficeBranchResolver as Office;
use BuildWithSmk\TenantPermission\Tests\Fixtures\Patient;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->registrar = app(PermissionRegistrar::class);

    foreach ([Office::MAIN, Office::GULSHAN, Office::GULSHAN, Office::BANANI] as $i => $office) {
        DB::table('patients')->insert(['office_id' => $office, 'name' => "Patient {$i}"]);
    }
});

it('shows only the selected branch', function () {
    $this->registrar->setAccessibleBranchIds([Office::GULSHAN])->setCurrentBranchId(Office::GULSHAN);

    expect(Patient::query()->pluck('office_id')->unique()->values()->all())->toBe([Office::GULSHAN])
        ->and(Patient::query()->count())->toBe(2);
});

it('shows every accessible branch, and nothing more, in the All branches view', function () {
    $this->registrar->setAccessibleBranchIds([Office::HQ, Office::MAIN, Office::GULSHAN])->setCurrentBranchId(0);

    expect(Patient::query()->count())->toBe(3)
        ->and(Patient::query()->forBranch(Office::MAIN)->count())->toBe(1)
        ->and(Patient::query()->forBranch(Office::BANANI)->count())->toBe(0)
        ->and(Patient::query()->withoutBranchScope()->count())->toBe(4);
});

it('shows nothing to a user with no accessible branch', function () {
    $this->registrar->setAccessibleBranchIds([])->setCurrentBranchId(0);

    expect(Patient::query()->count())->toBe(0);
});

it('leaves console queries unfiltered without a branch context', function () {
    expect(Patient::query()->count())->toBe(4);
});

it('returns no rows in an HTTP request without a branch context', function () {
    (fn () => $this->isRunningInConsole = false)->call(app());

    expect(Patient::query()->count())->toBe(0);

    config()->set('permission.branches.strict', false);
    expect(Patient::query()->count())->toBe(4);
});

it('does not filter when branches are disabled', function () {
    config()->set('permission.branches.enabled', false);
    $this->registrar->setAccessibleBranchIds([Office::GULSHAN])->setCurrentBranchId(Office::GULSHAN);

    expect(Patient::query()->count())->toBe(4);
});

it('fills the branch on create when one branch is selected', function () {
    $this->registrar->setCurrentBranchId(Office::BANANI);
    expect(Patient::query()->create(['name' => 'New'])->office_id)->toBe(Office::BANANI);

    $this->registrar->setAccessibleBranchIds([Office::MAIN, Office::BANANI])->setCurrentBranchId(0);
    expect(Patient::query()->create(['name' => 'Picked', 'office_id' => Office::MAIN])->office_id)->toBe(Office::MAIN);
});
