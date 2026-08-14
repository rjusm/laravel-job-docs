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
