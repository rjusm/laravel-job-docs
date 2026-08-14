<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable
    |--------------------------------------------------------------------------
    */
    'enabled' => env('JOB_DOCS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    | URI prefix under which the docs UI and openapi.json are served, and the
    | middleware guarding it (e.g. add your IP-allowlist middleware here).
    */
    'route' => env('JOB_DOCS_ROUTE', 'docs'),
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Generation Mode
    |--------------------------------------------------------------------------
    | 'live'   - rebuild the spec/catalog on every request.
    | 'cached' - serve the artifact written by `php artisan job-docs:generate`.
    */
    'mode' => env('JOB_DOCS_MODE', 'live'),
    'output_path' => storage_path('app/job-docs'),

    /*
    |--------------------------------------------------------------------------
    | Job Map Source
    |--------------------------------------------------------------------------
    | A class implementing Rjusm\LaravelJobDocs\Contracts\JobMapProvider, or
    | any callable ([Class::class, 'method'] / Closure) returning the same
    | two-level [group1][group2] => ['class' => ..., 'validation_rule' => ...,
    | 'meta' => [...]] shape.
    */
    'map_provider' => null,

    /*
    |--------------------------------------------------------------------------
    | Request Envelope
    |--------------------------------------------------------------------------
    | Optional class-string (static or instance rules()) documenting the outer
    | request envelope, and the field names used to inject group1/group2 into
    | the generated example. Leave the *_field entries null to skip that step.
    */
    'envelope_rules' => null,
    'envelope_group_field' => null,
    'payload_field' => null,
    'payload_group_field' => null,

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    | Real HTTP endpoints documented in the generated OpenAPI spec.
    */
    'endpoints' => [
        // ['method' => 'POST', 'path' => '/api/exchange', 'label' => 'Exchange'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Example Masking
    |--------------------------------------------------------------------------
    | Optional callable applied to every generated example payload before it
    | is persisted/rendered, e.g. [App\Services\DataMasker::class, 'mask'].
    */
    'example_masker' => null,

    'tag' => 'Job Docs',

    'info' => [
        'title' => env('APP_NAME', 'API'),
        'version' => '1.0.0',
    ],
];
