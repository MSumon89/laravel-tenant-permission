<?php

use BuildWithSmk\TenantPermission\Tests\BranchTestCase;
use BuildWithSmk\TenantPermission\Tests\TestCase;
use BuildWithSmk\TenantPermission\Tests\UiTestCase;

uses(TestCase::class)->in('Feature/Core');
uses(UiTestCase::class)->in('Feature/Ui');
uses(BranchTestCase::class)->in('Feature/Branches');
