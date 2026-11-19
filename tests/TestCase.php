<?php

namespace Tests;

use App\Services\Import\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeHostResolver;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but a test database. Tests reset the
     * database, which must never hit the data of a running installation.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
            $this->fail('Tests must run against a database whose name ends in "_test".');
        }

        $this->app->instance(HostResolver::class, new FakeHostResolver);
    }

    //
}
