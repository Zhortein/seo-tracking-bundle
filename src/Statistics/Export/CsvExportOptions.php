<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Export;

final readonly class CsvExportOptions
{
    public function __construct(
        public string $delimiter = ',',
        public string $enclosure = '"',
        public string $lineEnding = "\n",
        public bool $includeUtf8Bom = false,
    ) {
        if (1 !== strlen($delimiter) || str_contains("\r\n\0", $delimiter)) {
            throw new \InvalidArgumentException('The CSV delimiter must be one byte and cannot be a line break or NUL.');
        }

        if (1 !== strlen($enclosure) || str_contains("\r\n\0", $enclosure)) {
            throw new \InvalidArgumentException('The CSV enclosure must be one byte and cannot be a line break or NUL.');
        }

        if ($delimiter === $enclosure) {
            throw new \InvalidArgumentException('The CSV delimiter and enclosure must be different.');
        }

        if (!in_array($lineEnding, ["\n", "\r\n"], true)) {
            throw new \InvalidArgumentException('The CSV line ending must be LF or CRLF.');
        }
    }
}
