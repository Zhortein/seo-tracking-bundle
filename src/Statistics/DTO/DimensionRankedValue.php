<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DTO;

final readonly class DimensionRankedValue
{
    public string $label;
    public string $type;

    public function __construct(
        public string|int|float|bool $value,
        public int $count,
    ) {
        $this->label = self::label($value);
        $this->type = match (true) {
            is_string($value) => 'string',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            default => 'boolean',
        };
    }

    private static function label(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_float($value)) {
            $encoded = json_encode($value, JSON_PRESERVE_ZERO_FRACTION);

            return false === $encoded ? (string) $value : $encoded;
        }

        return (string) $value;
    }
}
