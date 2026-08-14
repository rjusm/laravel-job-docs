<?php

namespace Rjusm\LaravelJobDocs\Generators;

class RuleParser
{
    public function __construct(protected ?FakerExampleGenerator $faker = null) {}

    /**
     * Parse a full Laravel `rules()` array into an OpenAPI object schema plus an example payload.
     *
     * @param  array<string, mixed>  $rules
     * @return array{schema: array, example: array}
     */
    public function parseFieldset(array $rules, bool $useFaker = false): array
    {
        $properties = [];
        $required = [];
        $example = [];

        foreach ($rules as $field => $fieldRules) {
            $field = (string) $field;
            $parsed = $this->parseField($field, $fieldRules, $useFaker);

            $properties[$field] = $parsed['schema'];
            $example[$field] = $parsed['example'];

            if ($parsed['required']) {
                $required[] = $field;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return ['schema' => $schema, 'example' => $example];
    }

    /**
     * Parse a single field's rules (string, array of strings, or array containing Rule objects)
     * into an OpenAPI schema fragment plus an example value.
     *
     * @return array{schema: array, required: bool, example: mixed}
     */
    public function parseField(string $field, mixed $fieldRules, bool $useFaker = false): array
    {
        $tokens = $this->normalizeToTokens($fieldRules);

        $required = false;
        $nullable = false;
        $type = 'string';
        $format = null;
        $enum = null;
        $pattern = null;
        $minLength = null;
        $maxLength = null;
        $minimum = null;
        $maximum = null;
        $unknownHints = [];

        foreach ($tokens as $token) {
            [$name, $args] = $this->splitToken($token);

            switch ($name) {
                case 'required':
                    $required = true;
                    break;
                case 'nullable':
                    $nullable = true;
                    break;
                case 'sometimes':
                case 'confirmed':
                    // Documentation-neutral: doesn't change the field's own shape.
                    break;
                case 'boolean':
                case 'bool':
                    $type = 'boolean';
                    break;
                case 'integer':
                case 'int':
                    $type = 'integer';
                    break;
                case 'numeric':
                    if ($type !== 'integer') {
                        $type = 'number';
                    }
                    break;
                case 'array':
                    $type = 'object';
                    break;
                case 'email':
                    $format = 'email';
                    break;
                case 'date':
                case 'date_format':
                    $format = 'date';
                    break;
                case 'string':
                case 'alpha_dash':
                case 'alpha_num':
                case 'alpha':
                    // Already the default type; nothing to change.
                    break;
                case 'in':
                    $enum = array_values(array_filter(
                        array_map(fn ($v) => $this->unquoteRuleValue($v), explode(',', (string) $args)),
                        fn ($v) => $v !== ''
                    ));
                    break;
                case 'digits':
                    $len = (int) $args;
                    $minLength = $maxLength = $len;
                    $pattern = '^\\d+$';
                    break;
                case 'digits_between':
                    [$min, $max] = array_pad(explode(',', (string) $args), 2, null);
                    $minLength = $min !== null ? (int) $min : $minLength;
                    $maxLength = $max !== null ? (int) $max : $maxLength;
                    $pattern = '^\\d+$';
                    break;
                case 'max':
                    if ($type === 'integer' || $type === 'number') {
                        $maximum = $this->numeric($args);
                    } else {
                        $maxLength = (int) $args;
                    }
                    break;
                case 'min':
                    if ($type === 'integer' || $type === 'number') {
                        $minimum = $this->numeric($args);
                    } else {
                        $minLength = (int) $args;
                    }
                    break;
                case 'size':
                    if ($type === 'integer' || $type === 'number') {
                        $minimum = $maximum = $this->numeric($args);
                    } else {
                        $minLength = $maxLength = (int) $args;
                    }
                    break;
                case 'between':
                    [$min, $max] = array_pad(explode(',', (string) $args), 2, null);
                    if ($type === 'integer' || $type === 'number') {
                        $minimum = $min !== null ? $this->numeric($min) : $minimum;
                        $maximum = $max !== null ? $this->numeric($max) : $maximum;
                    } else {
                        $minLength = $min !== null ? (int) $min : $minLength;
                        $maxLength = $max !== null ? (int) $max : $maxLength;
                    }
                    break;
                case 'regex':
                    $pattern = $args;
                    break;
                default:
                    $unknownHints[] = $token;
            }
        }

        $schema = ['type' => $type];

        if ($nullable) {
            $schema['nullable'] = true;
        }
        if ($format !== null) {
            $schema['format'] = $format;
        }
        if ($enum !== null && $enum !== []) {
            $schema['enum'] = array_values($enum);
        }
        if ($pattern !== null) {
            $schema['pattern'] = $pattern;
        }
        if ($minLength !== null) {
            $schema['minLength'] = $minLength;
        }
        if ($maxLength !== null) {
            $schema['maxLength'] = $maxLength;
        }
        if ($minimum !== null) {
            $schema['minimum'] = $minimum;
        }
        if ($maximum !== null) {
            $schema['maximum'] = $maximum;
        }
        if ($unknownHints !== []) {
            $schema['description'] = 'Additional rules: '.implode(', ', $unknownHints);
        }

        return [
            'schema' => $schema,
            'required' => $required,
            'example' => $this->exampleFor($field, $type, $format, $enum, $minimum, $maximum, $maxLength, $minLength, $useFaker),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeToTokens(mixed $fieldRules): array
    {
        $entries = is_array($fieldRules) ? $fieldRules : [$fieldRules];

        $tokens = [];
        foreach ($entries as $entry) {
            $entry = is_string($entry) ? $entry : $this->stringifyRule($entry);

            foreach (explode('|', $entry) as $token) {
                $token = trim($token);
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        return $tokens;
    }

    private function stringifyRule(mixed $rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        if (is_object($rule) && method_exists($rule, '__toString')) {
            return (string) $rule;
        }

        return '';
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function splitToken(string $token): array
    {
        if (! str_contains($token, ':')) {
            return [$token, null];
        }

        [$name, $args] = explode(':', $token, 2);

        return [$name, $args];
    }

    /**
     * Laravel's Rule::in()/Rule::notIn() stringify each value wrapped in double quotes
     * (escaping literal quotes as ""), to survive commas inside values. Plain
     * `in:a,b,c` rule strings have no quoting at all, so this is a no-op for those.
     */
    private function unquoteRuleValue(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = str_replace('""', '"', substr($value, 1, -1));
        }

        return $value;
    }

    private function numeric(?string $value): int|float
    {
        if ($value === null) {
            return 0;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    private function exampleFor(
        string $field,
        string $type,
        ?string $format,
        ?array $enum,
        int|float|null $minimum,
        int|float|null $maximum,
        ?int $maxLength,
        ?int $minLength,
        bool $useFaker,
    ): mixed {
        if ($useFaker && $this->faker !== null) {
            return $this->faker->generate($field, $type, $format, $enum, $minimum, $maximum);
        }

        if ($enum !== null && $enum !== []) {
            return array_values($enum)[0];
        }

        return match (true) {
            $type === 'boolean' => true,
            $type === 'integer' => $minimum ?? 1,
            $type === 'number' => $minimum ?? 1,
            $type === 'object' => [],
            $format === 'email' => 'example@example.com',
            $format === 'date' => date('Y-m-d'),
            $maxLength !== null => str_pad('', min($maxLength, max($minLength ?? 0, 3)), 'x'),
            default => 'example_'.$field,
        };
    }
}
