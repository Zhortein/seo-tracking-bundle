<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Ip;

final readonly class IpAnonymizer implements IpAnonymizerInterface
{
    public function __construct(
        private int $ipv4Prefix = 24,
        private int $ipv6Prefix = 64,
    ) {
        if ($this->ipv4Prefix < 0 || $this->ipv4Prefix > 32) {
            throw new \InvalidArgumentException('The IPv4 anonymization prefix must be between 0 and 32.');
        }

        if ($this->ipv6Prefix < 0 || $this->ipv6Prefix > 128) {
            throw new \InvalidArgumentException('The IPv6 anonymization prefix must be between 0 and 128.');
        }
    }

    public function anonymize(?string $address): ?string
    {
        if (null === $address || '' === trim($address)) {
            return null;
        }

        $address = trim($address);
        $packed = @inet_pton($address);
        if (false === $packed) {
            return null;
        }

        $prefix = 4 === strlen($packed) ? $this->ipv4Prefix : $this->ipv6Prefix;
        $anonymized = inet_ntop($this->mask($packed, $prefix));

        return false === $anonymized ? null : $anonymized;
    }

    private function mask(string $packedAddress, int $prefixLength): string
    {
        $anonymized = '';
        $length = strlen($packedAddress);

        for ($index = 0; $index < $length; ++$index) {
            $byte = ord($packedAddress[$index]);
            $remainingBits = $prefixLength - ($index * 8);

            if ($remainingBits >= 8) {
                $anonymized .= chr($byte);
            } elseif ($remainingBits <= 0) {
                $anonymized .= "\0";
            } else {
                $anonymized .= pack('C', $byte & (0xFF << (8 - $remainingBits)));
            }
        }

        return $anonymized;
    }
}
