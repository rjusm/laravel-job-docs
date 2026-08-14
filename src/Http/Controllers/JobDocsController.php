<?php

namespace Rjusm\LaravelJobDocs\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Rjusm\LaravelJobDocs\Generators\OpenApiGenerator;

class JobDocsController
{
    public function __construct(protected OpenApiGenerator $generator) {}

    public function index(): View
    {
        return view('job-docs::index', [
            'catalog' => $this->catalog(),
            'openapiUrl' => route('job-docs.openapi'),
        ]);
    }

    public function openapi(): JsonResponse
    {
        return response()->json($this->spec());
    }

    protected function isCached(): bool
    {
        return config('job-docs.mode', 'live') === 'cached';
    }

    protected function outputPath(): string
    {
        return rtrim((string) config('job-docs.output_path'), '/');
    }

    protected function spec(): array
    {
        if ($this->isCached()) {
            $path = $this->outputPath().'/openapi.json';
            if (File::exists($path)) {
                return json_decode(File::get($path), true) ?? [];
            }
        }

        return $this->generator->generate();
    }

    protected function catalog(): array
    {
        if ($this->isCached()) {
            $path = $this->outputPath().'/catalog.json';
            if (File::exists($path)) {
                return json_decode(File::get($path), true) ?? [];
            }
        }

        return $this->generator->catalog();
    }
}
