<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $_ENV['APP_BASE_PATH'] = dirname(__DIR__);

        return parent::createApplication();
    }
}
