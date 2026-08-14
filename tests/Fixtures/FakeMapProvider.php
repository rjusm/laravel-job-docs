<?php

namespace Rjusm\LaravelJobDocs\Tests\Fixtures;

use Illuminate\Validation\Rule;
use Rjusm\LaravelJobDocs\Contracts\JobMapProvider;

class FakeMapProvider implements JobMapProvider
{
    public function map(): array
    {
        return [
            'processing' => [
                'DEFAULT' => [
                    'class' => FakeJob::class,
                    'queue' => 'default1',
                    'validation_rule' => [
                        'session_id' => 'required|alpha_dash',
                        'amount' => 'required|numeric|max:11000',
                    ],
                ],
            ],
            'card_lock_unlock' => [
                'BPC_VISA' => [
                    'class' => FakeJob::class,
                    'queue' => 'visa_request',
                    'validation_rule' => [
                        'session_id' => ['required', 'alpha_dash'],
                        'status' => ['required', 'in:ACTIVE,DISABLED'],
                    ],
                ],
                'BPC_MTM' => [
                    'class' => FakeJob::class,
                    'queue' => 'mtm_request',
                    'validation_rule' => [
                        'session_id' => 'required|alpha_dash',
                        'action' => ['required', Rule::in(['block', 'unblock'])],
                    ],
                ],
            ],
        ];
    }
}
