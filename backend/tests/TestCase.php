<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear Spatie Permission in-memory cache before each test.
        // Without this, role/permission data from a previous test (RefreshDatabase)
        // can bleed into the next test, causing spurious 403 errors.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
