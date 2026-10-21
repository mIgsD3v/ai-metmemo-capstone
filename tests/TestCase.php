<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without real asset tags, so tests pass whether or not `npm run dev` is running
        // (or a build exists).
        $this->withoutVite();
    }
}
