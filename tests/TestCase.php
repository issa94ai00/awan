<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Static caches outlive the database each test rolls back; an id cached
        // by one test is a foreign key to nothing in the next.
        \App\Models\CostCenter::forgetWarehouseCache();
    }
}
