<?php

namespace Rjusm\LaravelJobDocs\Tests\Fixtures;

class FakeEnvelopeRequest
{
    public function rules(): array
    {
        return [
            'handler' => ['required', 'alpha_dash'],
            'with_queue' => ['required', 'boolean'],
            'payload' => ['required', 'array'],
        ];
    }
}
