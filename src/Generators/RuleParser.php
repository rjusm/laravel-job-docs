<?php

namespace Rjusm\LaravelJobDocs\Generators;

class RuleParser
{
    public function __construct(protected ?FakerExampleGenerator $faker = null) {}

    /**
     * Parse a full Laravel `rules()` array into an OpenAPI object schema plus an example payload.
     *
     * Field names using Laravel's dot notation for nested arrays (`parent.child`)
     * and wildcard array validation (`items.*.name`, arbitrarily deep, e.g.
     * `documents.*.attachments.*.name`) are expanded into real nested
     * object/array schemas instead of being kept as literal dotted keys.
     *
     * @param  array<string, mixed>  $rules
     * @return array{schema: array, example: array}
     */
    public function parseFieldset(array $rules, bool $useFaker = false): array
    {
        $root = $this->newContainerNode();

        foreach ($rules as $field => $fieldRules) {
            $field = (string) $field;
            $segments = explode('.', $field);

            $leafFieldName = end($segments);
            if ($leafFieldName === '*') {
                $leafFieldName = $segments[count($segments) - 2] ?? $field;
            }

            $leaf = $this->parseField($leafFieldName, $fieldRules, $useFaker);

            $this->insertIntoTree($root, $segments, $leaf);
        }

        return [
            'schema' => $this->nodeToSchema($root),
            'example' => $this->nodeToExample($root),
        ];
    }

    /**
     * @return array{__type: string, __props: array, __required: list<string>, __items: ?array}
     */
    private function newContainerNode(): array
    {
        return ['__type' => 'object', '__props' => [], '__required' => [], '__items' => null];
    }

    /**
     * @param  list<string>  $segments
     * @param  array{schema: array, required: bool, example: mixed}  $leaf
     */
    private function insertIntoTree(array &$node, array $segments, array $leaf): void
    {
        $segment = array_shift($segments);
        $isLast = $segments === [];

        if ($segment === '*') {
            $node['__type'] = 'array';

            if ($isLast) {
                if (($node['__items']['__type'] ?? null) !== 'object' && ($node['__items']['__type'] ?? null) !== 'array') {
                    $node['__items'] = ['__type' => 'leaf'] + $leaf;
                }

                return;
            }

            if (! isset($node['__items']) || ($node['__items']['__type'] ?? null) === 'leaf') {
                $node['__items'] = $this->newContainerNode();
            }

            $this->insertIntoTree($node['__items'], $segments, $leaf);

            return;
        }

        $node['__type'] = 'object';

        if ($isLast) {
            if (! isset($node['__props'][$segment]) || $node['__props'][$segment]['__type'] === 'leaf') {
                $node['__props'][$segment] = ['__type' => 'leaf'] + $leaf;
            }

            if ($leaf['required'] && ! in_array($segment, $node['__required'], true)) {
                $node['__required'][] = $segment;
            }

            return;
        }

        // A field like "documents" => "required|array" (its own top-level rule)
        // may be processed before/after "documents.*.name" — whichever arrives
        // first creates a leaf placeholder, and once the dotted/wildcard rules
        // reveal the real shape, that placeholder is upgraded into a container.
        if (! isset($node['__props'][$segment]) || $node['__props'][$segment]['__type'] === 'leaf') {
            $node['__props'][$segment] = $this->newContainerNode();
        }

        $this->insertIntoTree($node['__props'][$segment], $segments, $leaf);
    }

    private function nodeToSchema(array $node): array
    {
        if ($node['__type'] === 'leaf') {
            return $node['schema'];
        }

        if ($node['__type'] === 'array') {
            return [
                'type' => 'array',
                'items' => $node['__items'] !== null ? $this->nodeToSchema($node['__items']) : ['type' => 'string'],
            ];
        }

        $properties = [];
        foreach ($node['__props'] as $name => $child) {
            $properties[$name] = $this->nodeToSchema($child);
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($node['__required'] !== []) {
            $schema['required'] = $node['__required'];
        }

        return $schema;
    }

    private function nodeToExample(array $node): mixed
    {
        if ($node['__type'] === 'leaf') {
            return $node['example'];
        }

        if ($node['__type'] === 'array') {
            return $node['__items'] !== null ? [$this->nodeToExample($node['__items'])] : [];
        }

        $example = [];
        foreach ($node['__props'] as $name => $child) {
            $example[$name] = $this->nodeToExample($child);
        }

        return $example;
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
        $dateFormat = null;
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
                    $format = 'date';
                    break;
                case 'date_format':
                    $format = 'date';
                    // Laravel's date_format rule takes a PHP date() token string
                    // (e.g. "ymdHis"), so it can be fed straight to date().
                    $dateFormat = $args;
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
                    // Laravel's digits rule means "an integer-like string of
                    // exactly N digits" — never a decimal. Only refines an
                    // already-numeric field down to integer; it does NOT make an
                    // otherwise-string field (e.g. a 20-digit account number,
                    // which must stay a string to avoid int/float precision
                    // loss) numeric on its own.
                    if ($type === 'number') {
                        $type = 'integer';
                    }
                    $len = (int) $args;
                    $minLength = $maxLength = $len;
                    $pattern = '^\\d+$';
                    break;
                case 'digits_between':
                    if ($type === 'number') {
                        $type = 'integer';
                    }
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

        // digits/digits_between always set minLength/maxLength (a digit *count*),
        // regardless of type. For a numeric/integer field that's the only signal
        // we have for a realistic example range, since max/min/between/size on a
        // numeric type set $minimum/$maximum directly instead and never touch
        // these — so there's no ambiguity to resolve here.
        if (($type === 'integer' || $type === 'number') && $minimum === null && $maximum === null && ($minLength !== null || $maxLength !== null)) {
            $lowerDigits = $minLength ?? 1;
            $upperDigits = $maxLength ?? max($lowerDigits, 1);
            $minimum = $lowerDigits <= 0 ? 0 : (10 ** ($lowerDigits - 1));
            $maximum = (10 ** max($upperDigits, 1)) - 1;
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
            'example' => $this->exampleFor($field, $type, $format, $dateFormat, $pattern, $enum, $minimum, $maximum, $maxLength, $minLength, $useFaker),
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

            foreach ($this->splitPipeAware($entry) as $token) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Splits a rule string on `|`, except inside a regex/not_regex pattern —
     * Laravel's own docs warn those can't safely coexist with more pipes in
     * pipe-string form precisely because the pattern itself may contain `|`
     * (e.g. `regex:/^[a-z|A-Z]+$/`). Once a `regex:`/`not_regex:` marker is
     * found, everything from there to the end of the string is kept as one
     * token instead of being split further.
     *
     * @return list<string>
     */
    private function splitPipeAware(string $entry): array
    {
        if (preg_match('/(?:^|\|)(regex:|not_regex:)/', $entry, $match, PREG_OFFSET_CAPTURE)) {
            $markerStart = $match[1][1];
            $before = rtrim(substr($entry, 0, $markerStart), '|');
            $regexToken = trim(substr($entry, $markerStart));

            $tokens = array_values(array_filter(array_map('trim', explode('|', $before)), fn ($v) => $v !== ''));
            $tokens[] = $regexToken;

            return $tokens;
        }

        return array_values(array_filter(array_map('trim', explode('|', $entry)), fn ($v) => $v !== ''));
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
        ?string $dateFormat,
        ?string $pattern,
        ?array $enum,
        int|float|null $minimum,
        int|float|null $maximum,
        ?int $maxLength,
        ?int $minLength,
        bool $useFaker,
    ): mixed {
        if ($useFaker && $this->faker !== null) {
            return $this->faker->generate($field, $type, $format, $dateFormat, $enum, $minimum, $maximum);
        }

        if ($enum !== null && $enum !== []) {
            return array_values($enum)[0];
        }

        $digitsOnly = $pattern === '^\\d+$';

        return match (true) {
            $type === 'boolean' => true,
            $type === 'integer' => $minimum ?? 1,
            $type === 'number' => $minimum ?? 1,
            $type === 'object' => [],
            $format === 'email' => 'example@example.com',
            $format === 'date' => date($dateFormat ?? 'Y-m-d'),
            $digitsOnly && $maxLength !== null => substr(str_repeat('1234567890', (int) ceil($maxLength / 10)), 0, max($maxLength, $minLength ?? 0)),
            $maxLength !== null => str_pad('', min($maxLength, max($minLength ?? 0, 3)), 'x'),
            default => 'example_'.$field,
        };
    }
}
