# laravel-job-docs

Generate browsable OpenAPI documentation — importable straight into Postman — for Laravel APIs that dispatch **jobs** rather than one controller action per route. Built for the common "single generic endpoint + a `handler`/`gateway`-style dispatch map" pattern, where validation rules live on the job classes (`public static function rules(): array`) instead of `FormRequest`s.

It does **not** scan your routes. It reads whatever "job map" your app already has — any two-level `[group1][group2] => ['class' => Job::class, 'validation_rule' => [...], 'meta' => [...]]` array — and turns it into:

- an **OpenAPI 3.0** document (`GET /docs/openapi.json`) with one named request example per job, ready to import into Postman;
- a self-contained **browsable UI** (`GET /docs`) — search + tree of every job, its validation rules, and an example payload, no external assets required.

## Install

```bash
composer require rjusm/laravel-job-docs
```

The service provider auto-registers. Publish the config to wire it up:

```bash
php artisan vendor:publish --tag=job-docs-config
```

## Configure

Point `map_provider` at whatever already produces your job map — a class implementing `Rjusm\LaravelJobDocs\Contracts\JobMapProvider`, or simply a callable:

```php
// config/job-docs.php
'map_provider' => [\App\Services\Queue\Exchange\QueueExchangeServices::class, 'map'],

'endpoints' => [
    ['method' => 'POST', 'path' => '/api/v2/exchange', 'label' => 'Exchange v2'],
],

// Optional: document the outer request envelope and where group1/group2
// belong inside the generated example.
'envelope_rules' => \App\Http\Requests\Exchange\ExchangeStoreRequest::class,
'envelope_group_field' => 'handler',
'payload_field' => 'payload',
'payload_group_field' => 'gateway',

// Optional HTTP Basic Auth in front of the docs UI and openapi.json,
// independent of whatever else is in `middleware` (IP allowlists, etc.).
'basic_auth' => [
    'enabled' => env('JOB_DOCS_BASIC_AUTH_ENABLED', false),
    'username' => env('JOB_DOCS_BASIC_AUTH_USERNAME'),
    'password' => env('JOB_DOCS_BASIC_AUTH_PASSWORD'),
],

// Optional: scrub generated examples before they're rendered/cached.
// mask_examples is the single on/off switch for this.
'mask_examples' => true,
'example_masker' => [\App\Services\Common\Helpers\DataMasker::class, 'mask'],

// Realistic example values via fakerphp/faker (seeded per field, so repeated
// generations stay stable) instead of plain "example_field" placeholders.
'use_faker' => true,

// Human-readable names for the two grouping levels, used by the UI's
// "group by" selector (which also auto-detects any `meta` keys, e.g. "queue").
'group1_label' => 'Handler',
'group2_label' => 'Gateway',

// Lets the UI send a real HTTP request (editable example body) to one of the
// configured endpoints and show the response, like Swagger UI's "Try it out".
'allow_try_it' => true,
```

## Generate

```bash
php artisan job-docs:generate
```

Writes `openapi.json` and `catalog.json` to `config('job-docs.output_path')` (default `storage/app/job-docs`). Set `'mode' => 'cached'` to serve these files instead of regenerating on every request (recommended for production); leave `'mode' => 'live'` (default) to always rebuild on request.

## View

Visit `/docs` (configurable via `job-docs.route`) for the UI, or import `/docs/openapi.json` directly into Postman (Import → Link).

## License

MIT.
