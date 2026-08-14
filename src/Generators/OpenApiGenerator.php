<?php

namespace Rjusm\LaravelJobDocs\Generators;

use Closure;
use Illuminate\Contracts\Container\Container;
use ReflectionMethod;
use Rjusm\LaravelJobDocs\Contracts\JobMapProvider;

class OpenApiGenerator
{
    public function __construct(
        protected Container $container,
        protected RuleParser $ruleParser,
    ) {}

    /**
     * Build a full OpenAPI 3.0 document: one real path per configured endpoint,
     * with every group1/group2 job documented as a named request example.
     */
    public function generate(): array
    {
        $useFaker = (bool) config('job-docs.use_faker', false);
        $map = $this->resolveMap();
        $endpoints = (array) config('job-docs.endpoints', []);
        $envelopeSchema = $this->buildEnvelopeSchema($useFaker);
        $envelopeExample = $this->buildEnvelopeExample($useFaker);

        $paths = [];
        foreach ($endpoints as $endpoint) {
            $method = strtolower($endpoint['method'] ?? 'post');
            $path = $endpoint['path'] ?? '/';
            $label = $endpoint['label'] ?? $path;

            $paths[$path][$method] = $this->buildOperation($label, $envelopeSchema, $envelopeExample, $map, $useFaker);
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('job-docs.info.title', config('app.name', 'API')),
                'version' => config('job-docs.info.version', '1.0.0'),
            ],
            'servers' => [
                ['url' => rtrim((string) config('app.url', ''), '/')],
            ],
            'paths' => $paths,
        ];
    }

    /**
     * Build a friendlier group1 -> group2 -> {class, meta, rules, schema, example} structure
     * for the browsable UI, where a flat OpenAPI examples list would be unwieldy.
     */
    public function catalog(): array
    {
        $useFaker = (bool) config('job-docs.use_faker', false);
        $envelopeExample = $this->buildEnvelopeExample($useFaker);
        $catalog = [];

        foreach ($this->resolveMap() as $group1 => $group2Map) {
            foreach ($group2Map as $group2 => $entry) {
                $rules = is_array($entry['validation_rule'] ?? null) ? $entry['validation_rule'] : [];
                $parsed = $this->ruleParser->parseFieldset($rules, $useFaker);
                $jobExample = $this->maskExample($parsed['example']);

                $catalog[$group1][$group2] = [
                    'class' => $entry['class'] ?? null,
                    'meta' => $entry['meta'] ?? array_diff_key($entry, array_flip(['class', 'validation_rule', 'meta'])),
                    'rules' => $rules,
                    'schema' => $parsed['schema'],
                    'example' => $jobExample,
                    'request' => $this->mergeEnvelopeAndPayload($envelopeExample, $jobExample, (string) $group1, (string) $group2),
                ];
            }
        }

        return $catalog;
    }

    protected function buildOperation(string $label, array $envelopeSchema, array $envelopeExample, array $map, bool $useFaker = false): array
    {
        $examples = [];

        foreach ($map as $group1 => $group2Map) {
            foreach ($group2Map as $group2 => $entry) {
                $rules = is_array($entry['validation_rule'] ?? null) ? $entry['validation_rule'] : [];
                $parsed = $this->ruleParser->parseFieldset($rules, $useFaker);
                $jobExample = $this->maskExample($parsed['example']);

                $exampleKey = $this->slug($group1.'__'.$group2);

                $examples[$exampleKey] = [
                    'summary' => "{$group1} / {$group2}",
                    'value' => $this->mergeEnvelopeAndPayload($envelopeExample, $jobExample, (string) $group1, (string) $group2),
                ];
            }
        }

        $content = ['schema' => $envelopeSchema];
        if ($examples !== []) {
            $content['examples'] = $examples;
        }

        return [
            'summary' => $label,
            'operationId' => $this->slug($label),
            'tags' => [(string) config('job-docs.tag', 'Job Docs')],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => $content,
                ],
            ],
            'responses' => [
                '200' => ['description' => 'Successful response'],
            ],
        ];
    }

    protected function mergeEnvelopeAndPayload(array $envelopeExample, array $jobExample, string $group1, string $group2): array
    {
        $value = $envelopeExample;

        $groupField = config('job-docs.envelope_group_field');
        if ($groupField !== null) {
            $value[$groupField] = $group1;
        }

        $payload = $jobExample;
        $payloadGroupField = config('job-docs.payload_group_field');
        if ($payloadGroupField !== null) {
            $payload = [$payloadGroupField => $group2] + $payload;
        }

        $payloadField = config('job-docs.payload_field');
        if ($payloadField !== null) {
            $value[$payloadField] = $payload;
        } else {
            $value = $payload + $value;
        }

        return $value;
    }

    protected function buildEnvelopeSchema(bool $useFaker = false): array
    {
        $rules = $this->resolveRules(config('job-docs.envelope_rules'));

        return $this->ruleParser->parseFieldset($rules, $useFaker)['schema'];
    }

    protected function buildEnvelopeExample(bool $useFaker = false): array
    {
        $rules = $this->resolveRules(config('job-docs.envelope_rules'));

        return $this->ruleParser->parseFieldset($rules, $useFaker)['example'];
    }

    /**
     * @return array<string, array<string, array{class: class-string, validation_rule: array, meta?: array}>>
     */
    protected function resolveMap(): array
    {
        $provider = config('job-docs.map_provider');

        if ($provider === null) {
            return [];
        }

        if (is_array($provider) || $provider instanceof Closure) {
            return (array) $this->container->call($this->resolveCallable($provider));
        }

        if (is_string($provider) && class_exists($provider)) {
            $instance = $this->container->make($provider);

            if ($instance instanceof JobMapProvider) {
                return $instance->map();
            }

            if (method_exists($instance, 'map')) {
                return (array) $instance->map();
            }
        }

        return [];
    }

    /**
     * Container::call() only auto-resolves a class from `[ClassString, 'method']`
     * when the method is static; for instance methods it would otherwise attempt
     * (and fail) a static call. Resolve the instance ourselves in that case.
     */
    protected function resolveCallable(mixed $callable): mixed
    {
        if (! is_array($callable) || count($callable) !== 2 || ! is_string($callable[0]) || ! class_exists($callable[0])) {
            return $callable;
        }

        [$class, $method] = $callable;

        try {
            if ((new ReflectionMethod($class, $method))->isStatic()) {
                return $callable;
            }
        } catch (\ReflectionException) {
            return $callable;
        }

        return [$this->container->make($class), $method];
    }

    protected function resolveRules(mixed $source): array
    {
        if ($source === null) {
            return [];
        }

        if (is_array($source) || $source instanceof Closure) {
            return (array) $this->container->call($this->resolveCallable($source));
        }

        if (is_string($source) && class_exists($source)) {
            try {
                if (is_callable([$source, 'rules'])) {
                    $reflection = new ReflectionMethod($source, 'rules');
                    if ($reflection->isStatic()) {
                        return (array) $source::rules();
                    }
                }

                $instance = $this->container->make($source);

                return (array) $instance->rules();
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    protected function maskExample(array $example): array
    {
        if (! config('job-docs.mask_examples', true)) {
            return $example;
        }

        $masker = config('job-docs.example_masker');

        if ($masker !== null && is_callable($masker)) {
            $masked = call_user_func($masker, $example);

            if (is_array($masked)) {
                return $masked;
            }
        }

        return $example;
    }

    protected function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_]+/', '_', $value);

        return trim($slug ?? $value, '_');
    }
}
