<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
    }

    //
}
