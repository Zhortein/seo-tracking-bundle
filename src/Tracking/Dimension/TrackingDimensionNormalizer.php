<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Dimension;

final readonly class TrackingDimensionNormalizer
{
    public const MAX_DIMENSIONS = 20;
    public const MAX_KEY_LENGTH = 64;
    public const MAX_STRING_LENGTH = 255;
    public const MAX_ENCODED_BYTES = 4096;

    /**
     * @return array<string, string|int|float|bool>|null
     */
    public function normalize(mixed $value): ?array
    {
        if (null === $value || [] === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('The "dimensions" field must be an object.');
        }

        if (count($value) > self::MAX_DIMENSIONS) {
            throw new \InvalidArgumentException(sprintf('The "dimensions" field cannot contain more than %d entries.', self::MAX_DIMENSIONS));
        }

        /** @var array<string, string|int|float|bool> $normalized */
        $normalized = [];
        foreach ($value as $key => $dimension) {
            if (!is_string($key) || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/D', $key)) {
                throw new \InvalidArgumentException(sprintf(
                    'Dimension keys must start with an ASCII letter and contain at most %d letters, digits, dots, underscores or hyphens.',
                    self::MAX_KEY_LENGTH,
                ));
            }

            if (is_string($dimension)) {
                if ('' === trim($dimension)) {
                    throw new \InvalidArgumentException(sprintf('Dimension "%s" cannot be empty.', $key));
                }
                if (mb_strlen($dimension) > self::MAX_STRING_LENGTH) {
                    throw new \InvalidArgumentException(sprintf('Dimension "%s" is too long.', $key));
                }
                $normalized[$key] = $dimension;
                continue;
            }

            if (is_float($dimension)) {
                if (!is_finite($dimension)) {
                    throw new \InvalidArgumentException(sprintf('Dimension "%s" must be a finite number.', $key));
                }
                $normalized[$key] = $dimension;
                continue;
            }

            if (is_int($dimension) || is_bool($dimension)) {
                $normalized[$key] = $dimension;
                continue;
            }

            throw new \InvalidArgumentException(sprintf('Dimension "%s" must be a string, integer, finite float or boolean.', $key));
        }

        ksort($normalized);
        $encoded = json_encode($normalized, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if (strlen($encoded) > self::MAX_ENCODED_BYTES) {
            throw new \InvalidArgumentException(sprintf('The encoded "dimensions" field cannot exceed %d bytes.', self::MAX_ENCODED_BYTES));
        }

        return $normalized;
    }
}
