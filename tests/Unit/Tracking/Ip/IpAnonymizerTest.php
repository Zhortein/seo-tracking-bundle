<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\Ip;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Tracking\Ip\IpAnonymizer;

final class IpAnonymizerTest extends TestCase
{
    #[DataProvider('addresses')]
    public function testItAnonymizesIpv4AndIpv6(?string $address, ?string $expected): void
    {
        self::assertSame($expected, (new IpAnonymizer())->anonymize($address));
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function addresses(): iterable
    {
        yield 'IPv4 /24' => ['203.0.113.87', '203.0.113.0'];
        yield 'IPv6 /64' => ['2001:db8:1234:5678:90ab:cdef:1234:5678', '2001:db8:1234:5678::'];
        yield 'compressed IPv6' => ['2001:db8::1', '2001:db8::'];
        yield 'missing address' => [null, null];
        yield 'invalid address' => ['not-an-ip', null];
    }

    public function testPrefixesAreConfigurable(): void
    {
        $anonymizer = new IpAnonymizer(16, 48);

        self::assertSame('203.0.0.0', $anonymizer->anonymize('203.0.113.87'));
        self::assertSame('2001:db8:1234::', $anonymizer->anonymize('2001:db8:1234:5678::1'));
    }
}
