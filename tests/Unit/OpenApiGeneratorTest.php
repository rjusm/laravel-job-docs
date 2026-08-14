<?php

use Rjusm\LaravelJobDocs\Generators\OpenApiGenerator;
use Rjusm\LaravelJobDocs\Tests\Fixtures\FakeEnvelopeRequest;
use Rjusm\LaravelJobDocs\Tests\Fixtures\FakeMapProvider;

beforeEach(function () {
    config()->set('job-docs.map_provider', [FakeMapProvider::class, 'map']);
    config()->set('job-docs.endpoints', [
        ['method' => 'POST', 'path' => '/api/v2/exchange', 'label' => 'Exchange v2'],
    ]);

    $this->generator = app(OpenApiGenerator::class);
});

it('builds a catalog grouped by group1 and group2 with 3 leaf jobs', function () {
    $catalog = $this->generator->catalog();

    expect($catalog)->toHaveKeys(['processing', 'card_lock_unlock']);
    expect($catalog['card_lock_unlock'])->toHaveKeys(['BPC_VISA', 'BPC_MTM']);

    $leafCount = 0;
    foreach ($catalog as $group2Map) {
        $leafCount += count($group2Map);
    }
    expect($leafCount)->toBe(3);

    expect($catalog['processing']['DEFAULT']['meta'])->toBe(['queue' => 'default1']);
});

it('generates a valid OpenAPI document with one path and named examples per job', function () {
    $spec = $this->generator->generate();

    expect($spec['openapi'])->toBe('3.0.3');
    expect($spec['paths'])->toHaveKey('/api/v2/exchange');

    $operation = $spec['paths']['/api/v2/exchange']['post'];
    $examples = $operation['requestBody']['content']['application/json']['examples'];

    expect($examples)->toHaveCount(3);
    expect($examples)->toHaveKey('processing__DEFAULT');
});

it('merges the envelope with group1/group2 into each example value', function () {
    config()->set('job-docs.envelope_rules', FakeEnvelopeRequest::class);
    config()->set('job-docs.envelope_group_field', 'handler');
    config()->set('job-docs.payload_field', 'payload');
    config()->set('job-docs.payload_group_field', 'gateway');

    $generator = app(OpenApiGenerator::class);
    $spec = $generator->generate();

    $examples = $spec['paths']['/api/v2/exchange']['post']['requestBody']['content']['application/json']['examples'];
    $value = $examples['processing__DEFAULT']['value'];

    expect($value['handler'])->toBe('processing');
    expect($value['payload']['gateway'])->toBe('DEFAULT');
    expect($value['payload'])->toHaveKey('session_id');
});

it('applies the configured example masker to generated payloads', function () {
    config()->set('job-docs.example_masker', function (array $data) {
        $data['session_id'] = '***masked***';

        return $data;
    });

    $generator = app(OpenApiGenerator::class);
    $catalog = $generator->catalog();

    expect($catalog['processing']['DEFAULT']['example']['session_id'])->toBe('***masked***');
});

it('skips masking entirely when mask_examples is disabled, even with a masker configured', function () {
    config()->set('job-docs.example_masker', function (array $data) {
        $data['session_id'] = '***masked***';

        return $data;
    });
    config()->set('job-docs.mask_examples', false);

    $generator = app(OpenApiGenerator::class);
    $catalog = $generator->catalog();

    expect($catalog['processing']['DEFAULT']['example']['session_id'])->toBe('example_session_id');
});

it('generates faker-backed examples when use_faker is enabled', function () {
    config()->set('job-docs.use_faker', true);

    $generator = app(OpenApiGenerator::class);
    $catalog = $generator->catalog();

    expect($catalog['processing']['DEFAULT']['example']['session_id'])->toBeString();
    expect($catalog['processing']['DEFAULT']['example']['session_id'])->not->toBe('example_session_id');
});

it('includes a merged full request example per catalog entry', function () {
    config()->set('job-docs.envelope_rules', FakeEnvelopeRequest::class);
    config()->set('job-docs.envelope_group_field', 'handler');
    config()->set('job-docs.payload_field', 'payload');
    config()->set('job-docs.payload_group_field', 'gateway');

    $generator = app(OpenApiGenerator::class);
    $catalog = $generator->catalog();

    $request = $catalog['card_lock_unlock']['BPC_VISA']['request'];

    expect($request['handler'])->toBe('card_lock_unlock');
    expect($request['payload']['gateway'])->toBe('BPC_VISA');
    expect($request['payload'])->toHaveKey('session_id');
});
