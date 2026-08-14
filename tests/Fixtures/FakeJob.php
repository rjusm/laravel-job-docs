<?php

namespace Rjusm\LaravelJobDocs\Tests\Fixtures;

class FakeJob
{
    public static function rules(): array
    {
        return ['session_id' => 'required|alpha_dash'];
    }
}
