<?php

namespace Rjusm\LaravelJobDocs\Contracts;

interface JobMapProvider
{
    /**
     * Two-level group map describing every job the host application can dispatch.
     *
     * @return array<string, array<string, array{class: class-string, validation_rule: array, meta?: array}>>
     */
    public function map(): array;
}
