<?php

namespace Rjusm\LaravelJobDocs\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Rjusm\LaravelJobDocs\Generators\OpenApiGenerator;

class GenerateJobDocsCommand extends Command
{
    protected $signature = 'job-docs:generate';

    protected $description = 'Generate the OpenAPI spec and UI catalog from the configured job map';

    public function handle(OpenApiGenerator $generator): int
    {
        $outputPath = rtrim((string) config('job-docs.output_path', storage_path('app/job-docs')), '/');
        File::ensureDirectoryExists($outputPath);

        $spec = $generator->generate();
        $catalog = $generator->catalog();

        File::put(
            $outputPath.'/openapi.json',
            json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        File::put(
            $outputPath.'/catalog.json',
            json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $exampleCount = 0;
        foreach ($catalog as $group2Map) {
            $exampleCount += count($group2Map);
        }

        $this->info("Generated OpenAPI spec with {$exampleCount} job examples.");
        $this->line("  openapi.json -> {$outputPath}/openapi.json");
        $this->line("  catalog.json -> {$outputPath}/catalog.json");

        return self::SUCCESS;
    }
}
