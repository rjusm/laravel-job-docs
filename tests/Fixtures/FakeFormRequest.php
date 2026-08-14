<?php

namespace Rjusm\LaravelJobDocs\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class FakeFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'handler' => ['required', 'alpha_dash'],
            'with_queue' => ['required', 'boolean'],
            'payload' => ['required', 'array'],
            'hash' => ['required', 'alpha_num'],
            'datetime' => ['required', 'date_format:ymdHis'],
        ];
    }
}
