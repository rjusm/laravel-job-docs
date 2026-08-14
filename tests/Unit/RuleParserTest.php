<?php

use Illuminate\Validation\Rule;
use Rjusm\LaravelJobDocs\Generators\RuleParser;

beforeEach(function () {
    $this->parser = new RuleParser();
});

it('parses a pipe-delimited rule string', function () {
    $result = $this->parser->parseField('amount', 'required|numeric|max:11000');

    expect($result['required'])->toBeTrue();
    expect($result['schema']['type'])->toBe('number');
    expect($result['schema']['maximum'])->toBe(11000);
    expect($result['example'])->toBe(1);
});

it('parses an array of plain rule strings', function () {
    $result = $this->parser->parseField('status', ['required', 'in:ACTIVE,DISABLED']);

    expect($result['required'])->toBeTrue();
    expect($result['schema']['enum'])->toBe(['ACTIVE', 'DISABLED']);
    expect($result['example'])->toBe('ACTIVE');
});

it('parses an array containing a Rule object', function () {
    $result = $this->parser->parseField('action', ['required', Rule::in(['block', 'unblock'])]);

    expect($result['required'])->toBeTrue();
    expect($result['schema']['enum'])->toBe(['block', 'unblock']);
    expect($result['example'])->toBe('block');
});

it('marks nullable fields and keeps a string default type', function () {
    $result = $this->parser->parseField('note', 'nullable|string|max:20');

    expect($result['required'])->toBeFalse();
    expect($result['schema']['nullable'])->toBeTrue();
    expect($result['schema']['type'])->toBe('string');
    expect($result['schema']['maxLength'])->toBe(20);
});

it('does not crash on unknown tokens and records them as a hint', function () {
    $result = $this->parser->parseField('weird', 'required|some_custom_rule');

    expect($result['schema']['type'])->toBe('string');
    expect($result['schema']['description'])->toContain('some_custom_rule');
});

it('builds a full fieldset schema and example', function () {
    $result = $this->parser->parseFieldset([
        'session_id' => 'required|alpha_dash',
        'amount' => 'required|numeric|max:11000',
    ]);

    expect($result['schema']['type'])->toBe('object');
    expect($result['schema']['required'])->toBe(['session_id', 'amount']);
    expect($result['example'])->toHaveKeys(['session_id', 'amount']);
});

it('strips quotes from a date_format argument like Laravel\'s own parser does', function () {
    // Real-world case: 'doc_datetime' => 'required|date_format:"Y-m-d"' in
    // several jobs. Laravel's ValidationRuleParser parses the parameter via
    // str_getcsv(), so the actual format Laravel validates against is
    // Y-m-d — WITHOUT quotes. Generating an example with the quotes taken
    // literally produced a value like '"2026-08-14"' that fails Laravel's
    // own validation.
    $result = $this->parser->parseField('doc_datetime', 'required|date_format:"Y-m-d"');

    expect($result['example'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($result['example'])->not->toContain('"');
});

it('does not split a regex pattern containing a literal pipe', function () {
    // Laravel's docs explicitly warn regex needs array form (or to be last)
    // in a pipe-string precisely because the pattern itself may contain '|'.
    $result = $this->parser->parseField('code', ['required', 'regex:/^[a-z|A-Z]+$/']);

    expect($result['schema']['pattern'])->toBe('/^[a-z|A-Z]+$/');
    expect($result['required'])->toBeTrue();

    // Same check in single pipe-delimited string form, with a rule after it.
    $result2 = $this->parser->parseField('code2', 'required|regex:/^[a-z|A-Z]+$/');
    expect($result2['schema']['pattern'])->toBe('/^[a-z|A-Z]+$/');
});

it('nests dot-notation field names into a real nested object instead of a literal dotted key', function () {
    $result = $this->parser->parseFieldset([
        'sender_receiver_info.account_number' => 'required_without:sender_receiver_info.card_number',
        'sender_receiver_info.sender_name' => 'nullable|string',
        'sender_receiver_info.sender_address' => 'nullable|string',
    ]);

    expect($result['example'])->toHaveKey('sender_receiver_info');
    // toHaveKey() itself resolves dots as a nested path, so assert directly
    // against array_keys() that no literal dotted key was left flat.
    expect(array_keys($result['example']))->not->toContain('sender_receiver_info.sender_name');
    expect($result['example']['sender_receiver_info'])->toHaveKeys(['account_number', 'sender_name', 'sender_address']);

    $schema = $result['schema']['properties']['sender_receiver_info'];
    expect($schema['type'])->toBe('object');
    expect($schema['properties'])->toHaveKeys(['account_number', 'sender_name', 'sender_address']);
});

it('nests a single level of wildcard array validation (items.*.field)', function () {
    $result = $this->parser->parseFieldset([
        'fin_app_parameters' => 'required|array|min:1',
        'fin_app_parameters.*.specification_id' => 'required|alpha_dash',
        'fin_app_parameters.*.value' => 'required',
    ]);

    $schema = $result['schema']['properties']['fin_app_parameters'];
    expect($schema['type'])->toBe('array');
    expect($schema['items']['type'])->toBe('object');
    expect($schema['items']['properties'])->toHaveKeys(['specification_id', 'value']);

    $example = $result['example']['fin_app_parameters'];
    expect($example)->toBeArray()->toHaveCount(1);
    expect($example[0])->toHaveKeys(['specification_id', 'value']);
});

it('nests arbitrarily deep wildcard arrays (documents.*.attachments.*.name)', function () {
    $result = $this->parser->parseFieldset([
        'documents' => 'required|array|min:1',
        'documents.*.name' => 'required|string',
        'documents.*.attachments' => 'required|array|min:1',
        'documents.*.attachments.*.name' => 'required|string',
        'documents.*.attachments.*.type_id' => 'required|alpha_dash',
    ]);

    $documents = $result['schema']['properties']['documents'];
    expect($documents['type'])->toBe('array');
    expect($documents['items']['properties'])->toHaveKeys(['name', 'attachments']);

    $attachments = $documents['items']['properties']['attachments'];
    expect($attachments['type'])->toBe('array');
    expect($attachments['items']['properties'])->toHaveKeys(['name', 'type_id']);

    $example = $result['example']['documents'][0];
    expect($example['attachments'][0])->toHaveKeys(['name', 'type_id']);
});
