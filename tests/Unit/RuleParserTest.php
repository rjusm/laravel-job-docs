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
