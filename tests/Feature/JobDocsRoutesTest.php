<?php

use Rjusm\LaravelJobDocs\Tests\Fixtures\FakeMapProvider;

it('serves a valid openapi.json from the configured route', function () {
    config()->set('job-docs.map_provider', [FakeMapProvider::class, 'map']);
    config()->set('job-docs.endpoints', [
        ['method' => 'POST', 'path' => '/api/v2/exchange', 'label' => 'Exchange v2'],
    ]);

    $response = $this->getJson('/docs/openapi.json');

    $response->assertOk();
    $response->assertJsonPath('openapi', '3.0.3');
    $response->assertJsonStructure(['paths' => ['/api/v2/exchange' => ['post']]]);
});

it('renders the docs UI page', function () {
    config()->set('job-docs.map_provider', [FakeMapProvider::class, 'map']);

    $response = $this->get('/docs');

    $response->assertOk();
    $response->assertSee('openapi.json', false);
});

it('rejects requests without credentials when basic auth is enabled', function () {
    config()->set('job-docs.basic_auth.enabled', true);
    config()->set('job-docs.basic_auth.username', 'admin');
    config()->set('job-docs.basic_auth.password', 'secret');

    $response = $this->get('/docs');

    $response->assertStatus(401);
    $response->assertHeader('WWW-Authenticate', 'Basic realm="Job Docs"');
});

it('rejects wrong credentials when basic auth is enabled', function () {
    config()->set('job-docs.basic_auth.enabled', true);
    config()->set('job-docs.basic_auth.username', 'admin');
    config()->set('job-docs.basic_auth.password', 'secret');

    $response = $this->withServerVariables([
        'PHP_AUTH_USER' => 'admin',
        'PHP_AUTH_PW' => 'wrong-password',
    ])->get('/docs');

    $response->assertStatus(401);
});

it('allows requests with correct basic auth credentials', function () {
    config()->set('job-docs.map_provider', [FakeMapProvider::class, 'map']);
    config()->set('job-docs.basic_auth.enabled', true);
    config()->set('job-docs.basic_auth.username', 'admin');
    config()->set('job-docs.basic_auth.password', 'secret');

    $response = $this->withServerVariables([
        'PHP_AUTH_USER' => 'admin',
        'PHP_AUTH_PW' => 'secret',
    ])->get('/docs');

    $response->assertOk();
});

it('does not require credentials when basic auth is disabled (default)', function () {
    config()->set('job-docs.map_provider', [FakeMapProvider::class, 'map']);

    $response = $this->get('/docs');

    $response->assertOk();
});
