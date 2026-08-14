<?php

namespace Rjusm\LaravelJobDocs\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Rjusm\LaravelJobDocs\LaravelJobDocsServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelJobDocsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('job-docs.map_provider', null);
        $app['config']->set('job-docs.endpoints', []);
        $app['config']->set('job-docs.envelope_rules', null);
        $app['config']->set('job-docs.envelope_group_field', null);
        $app['config']->set('job-docs.payload_field', null);
        $app['config']->set('job-docs.payload_group_field', null);
    }
}
