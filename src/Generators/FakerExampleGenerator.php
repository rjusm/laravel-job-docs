<?php

namespace Rjusm\LaravelJobDocs\Generators;

use Faker\Factory;
use Faker\Generator;

class FakerExampleGenerator
{
    protected Generator $faker;

    /**
     * Field-name substrings (checked against the lowercased field name, in this
     * order) used when nothing more specific (enum/type/format) already
     * determined the example value.
     *
     * @var list<string>
     */
    protected const FIELD_NAME_GUESSES = [
        'email', 'phone', 'fio', 'fullname', 'name', 'address', 'card', 'pan',
        'cvv', 'cvc', 'account', 'inn', 'iban', 'session', 'uuid', 'token',
        'hash', 'amount', 'sum', 'code', 'status', 'message', 'url', 'id',
    ];

    public function __construct()
    {
        $this->faker = Factory::create();
    }

    public function generate(
        string $field,
        string $type,
        ?string $format,
        ?array $enum,
        int|float|null $minimum,
        int|float|null $maximum,
    ): mixed {
        // Reseed deterministically per field so repeated generations stay stable.
        $this->faker->seed(crc32($field.'|'.$type.'|'.$format));

        if ($enum !== null && $enum !== []) {
            return $this->faker->randomElement($enum);
        }

        return match (true) {
            $type === 'boolean' => $this->faker->boolean(),
            $type === 'integer' => $this->faker->numberBetween(
                (int) ($minimum ?? 1),
                (int) ($maximum ?? (($minimum ?? 1) + 1000))
            ),
            $type === 'number' => $this->faker->randomFloat(
                2,
                $minimum ?? 1,
                $maximum ?? (($minimum ?? 1) + 1000)
            ),
            $type === 'object' => [],
            $format === 'email' => $this->faker->safeEmail(),
            $format === 'date' => $this->faker->date('Y-m-d'),
            default => $this->guessStringByFieldName($field),
        };
    }

    protected function guessStringByFieldName(string $field): string
    {
        $lower = strtolower($field);

        foreach (self::FIELD_NAME_GUESSES as $needle) {
            if (str_contains($lower, $needle)) {
                return (string) $this->resolveGuess($needle);
            }
        }

        return $this->faker->word();
    }

    protected function resolveGuess(string $needle): string|float
    {
        $f = $this->faker;

        return match ($needle) {
            'email' => $f->safeEmail(),
            'phone' => $f->numerify('#########'),
            'fio', 'fullname' => $f->name(),
            'name' => $f->firstName(),
            'address' => $f->address(),
            'card', 'pan' => $f->numerify('################'),
            'cvv', 'cvc' => $f->numerify('###'),
            'account' => $f->numerify('####################'),
            'inn' => $f->numerify('#########'),
            'iban' => $f->numerify('TJ##############################'),
            'session', 'uuid' => $f->uuid(),
            'token', 'hash' => $f->sha256(),
            'amount', 'sum' => (string) $f->randomFloat(2, 1, 10000),
            'code' => $f->numerify('####'),
            'status' => $f->randomElement(['ACTIVE', 'PENDING', 'DONE']),
            'message' => $f->sentence(4),
            'url' => $f->url(),
            'id' => (string) $f->numberBetween(1, 100000),
            default => $f->word(),
        };
    }
}
