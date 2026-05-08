<?php

namespace Tests\Maintenance;

use Daycry\Maintenance\Libraries\IpChecker;
use Tests\Support\TestCase;

/**
 * @internal
 *
 * Sprint 4 coverage gap: the legacy IpUtils class was wholly @codeCoverageIgnore.
 * IpChecker is now testable; this suite locks down the IPv4/IPv6/CIDR behaviour
 * we rely on for bypass.
 */
final class IpCheckerTest extends TestCase
{
    private IpChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new IpChecker();
    }

    // ========== IPv4 ==========

    public function testIpv4ExactMatch(): void
    {
        $this->assertTrue($this->checker->checkIp('10.0.0.5', '10.0.0.5'));
        $this->assertFalse($this->checker->checkIp('10.0.0.5', '10.0.0.6'));
    }

    public function testIpv4Cidr24(): void
    {
        $this->assertTrue($this->checker->checkIp('10.0.0.5', '10.0.0.0/24'));
        $this->assertTrue($this->checker->checkIp('10.0.0.255', '10.0.0.0/24'));
        $this->assertFalse($this->checker->checkIp('10.0.1.5', '10.0.0.0/24'));
    }

    public function testIpv4Cidr8(): void
    {
        $this->assertTrue($this->checker->checkIp('10.255.255.5', '10.0.0.0/8'));
        $this->assertFalse($this->checker->checkIp('11.0.0.5', '10.0.0.0/8'));
    }

    public function testIpv4Cidr0MatchesAnyIpv4(): void
    {
        $this->assertTrue($this->checker->checkIp('1.2.3.4', '0.0.0.0/0'));
        $this->assertTrue($this->checker->checkIp('255.255.255.255', '0.0.0.0/0'));
    }

    public function testIpv4Cidr32IsExact(): void
    {
        $this->assertTrue($this->checker->checkIp('10.0.0.5', '10.0.0.5/32'));
        $this->assertFalse($this->checker->checkIp('10.0.0.6', '10.0.0.5/32'));
    }

    public function testIpv4WithMultipleEntries(): void
    {
        $allowed = ['127.0.0.1', '10.0.0.0/8', '192.168.1.5'];
        $this->assertTrue($this->checker->checkIp('127.0.0.1', $allowed));
        $this->assertTrue($this->checker->checkIp('10.5.5.5', $allowed));
        $this->assertTrue($this->checker->checkIp('192.168.1.5', $allowed));
        $this->assertFalse($this->checker->checkIp('192.168.1.6', $allowed));
    }

    public function testIpv4InvalidRequestIp(): void
    {
        $this->assertFalse($this->checker->checkIp('not.an.ip', '10.0.0.0/24'));
        $this->assertFalse($this->checker->checkIp('999.999.999.999', '10.0.0.0/24'));
    }

    public function testIpv4OutOfRangePrefixIsRejected(): void
    {
        $this->assertFalse($this->checker->checkIp('10.0.0.5', '10.0.0.0/40'));
        $this->assertFalse($this->checker->checkIp('10.0.0.5', '10.0.0.0/-1'));
    }

    public function testIpv4MalformedAddressInRange(): void
    {
        $this->assertFalse($this->checker->checkIp('10.0.0.5', '999.999.999.999/24'));
    }

    // ========== IPv6 ==========

    public function testIpv6ExactMatch(): void
    {
        $this->assertTrue($this->checker->checkIp('2001:db8::1', '2001:db8::1'));
        $this->assertFalse($this->checker->checkIp('2001:db8::1', '2001:db8::2'));
    }

    public function testIpv6CidrMatch(): void
    {
        $this->assertTrue($this->checker->checkIp('2001:db8::ff', '2001:db8::/32'));
        $this->assertTrue($this->checker->checkIp('2001:db8:ffff::1', '2001:db8::/32'));
        $this->assertFalse($this->checker->checkIp('2001:db9::1', '2001:db8::/32'));
    }

    public function testIpv6Cidr0MatchesAnyIpv6(): void
    {
        $this->assertTrue($this->checker->checkIp('2001:db8::1', '::/0'));
        $this->assertTrue($this->checker->checkIp('::1', '::/0'));
    }

    public function testIpv6OutOfRangePrefixIsRejected(): void
    {
        $this->assertFalse($this->checker->checkIp('2001:db8::1', '2001:db8::/200'));
    }

    // ========== CACHE BEHAVIOUR ==========

    public function testCacheReturnsConsistentResult(): void
    {
        // Same query twice should give same answer; the second call hits cache.
        $first  = $this->checker->checkIp('10.0.0.5', '10.0.0.0/24');
        $second = $this->checker->checkIp('10.0.0.5', '10.0.0.0/24');

        $this->assertTrue($first);
        $this->assertSame($first, $second);
    }

    // ========== validateCidr() ==========

    public function testValidateCidrAcceptsValidEntries(): void
    {
        $this->assertTrue($this->checker->validateCidr('127.0.0.1'));
        $this->assertTrue($this->checker->validateCidr('10.0.0.0/24'));
        $this->assertTrue($this->checker->validateCidr('10.0.0.0/0'));
        $this->assertTrue($this->checker->validateCidr('10.0.0.0/32'));
        $this->assertTrue($this->checker->validateCidr('2001:db8::1'));
        $this->assertTrue($this->checker->validateCidr('2001:db8::/64'));
        $this->assertTrue($this->checker->validateCidr('::/0'));
    }

    public function testValidateCidrRejectsBadEntries(): void
    {
        $this->assertFalse($this->checker->validateCidr(''));
        $this->assertFalse($this->checker->validateCidr('not-an-ip'));
        $this->assertFalse($this->checker->validateCidr('10.0.0.0/40'));
        $this->assertFalse($this->checker->validateCidr('10.0.0.0/-1'));
        $this->assertFalse($this->checker->validateCidr('10.0.0.0/abc'));
        $this->assertFalse($this->checker->validateCidr('2001:db8::/200'));
        $this->assertFalse($this->checker->validateCidr('999.999.999.999/24'));
    }
}
