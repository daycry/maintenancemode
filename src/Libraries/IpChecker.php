<?php

namespace Daycry\Maintenance\Libraries;

use RuntimeException;

/**
 * IP address / CIDR matcher with bounded LRU-like cache.
 *
 * Replaces the legacy {@see IpUtils} static class. This one is instantiable
 * (so it can be injected and mocked), keeps an internal cache that is capped
 * to avoid unbounded growth in long-running CLI workers, and exposes a
 * reusable {@see validateCidr()} helper for command-side input validation.
 */
class IpChecker
{
    private const MAX_CACHE_ENTRIES = 256;

    /**
     * @var array<string, bool>
     */
    private array $cache = [];

    /**
     * Returns true if $requestIp matches any entry in $ips. Each entry is
     * either a literal IP address or a CIDR range. IPv4 and IPv6 are both
     * supported; the request IP determines which matcher is used.
     *
     * @param array<int, string>|string $ips
     */
    public function checkIp(string $requestIp, array|string $ips): bool
    {
        if (! is_array($ips)) {
            $ips = [$ips];
        }

        $isV6 = substr_count($requestIp, ':') > 1;

        foreach ($ips as $ip) {
            $matched = $isV6 ? $this->checkIp6($requestIp, (string) $ip) : $this->checkIp4($requestIp, (string) $ip);
            if ($matched) {
                return true;
            }
        }

        return false;
    }

    public function checkIp4(string $requestIp, string $ip): bool
    {
        $cacheKey = '4|' . $requestIp . '|' . $ip;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if (! filter_var($requestIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->remember($cacheKey, false);
        }

        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);

            if ($netmask === '0') {
                return $this->remember($cacheKey, (bool) filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
            }

            if (! ctype_digit($netmask)) {
                return $this->remember($cacheKey, false);
            }

            $prefix = (int) $netmask;
            if ($prefix < 0 || $prefix > 32) {
                return $this->remember($cacheKey, false);
            }
        } else {
            $address = $ip;
            $prefix  = 32;
        }

        $addressLong = ip2long($address);
        if ($addressLong === false) {
            return $this->remember($cacheKey, false);
        }

        $requestLong = ip2long($requestIp);
        $match       = substr_compare(
            sprintf('%032b', $requestLong),
            sprintf('%032b', $addressLong),
            0,
            $prefix,
        ) === 0;

        return $this->remember($cacheKey, $match);
    }

    public function checkIp6(string $requestIp, string $ip): bool
    {
        $cacheKey = '6|' . $requestIp . '|' . $ip;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        if (! ((extension_loaded('sockets') && defined('AF_INET6')) || @inet_pton('::1'))) {
            throw new RuntimeException('Unable to check IPv6: PHP is compiled without IPv6 support.');
        }

        if (str_contains($ip, '/')) {
            [$address, $netmask] = explode('/', $ip, 2);

            if ($netmask === '0') {
                return $this->remember($cacheKey, (bool) @inet_pton($address));
            }

            if (! ctype_digit($netmask)) {
                return $this->remember($cacheKey, false);
            }

            $prefix = (int) $netmask;
            if ($prefix < 1 || $prefix > 128) {
                return $this->remember($cacheKey, false);
            }
        } else {
            $address = $ip;
            $prefix  = 128;
        }

        $bytesAddr = @inet_pton($address);
        $bytesTest = @inet_pton($requestIp);

        if ($bytesAddr === false || $bytesTest === false) {
            return $this->remember($cacheKey, false);
        }

        $shortsAddr = unpack('n*', $bytesAddr);
        $shortsTest = unpack('n*', $bytesTest);

        if (! $shortsAddr || ! $shortsTest) {
            return $this->remember($cacheKey, false);
        }

        for ($i = 1, $ceil = (int) ceil($prefix / 16); $i <= $ceil; $i++) {
            $left = $prefix - 16 * ($i - 1);
            $left = $left <= 16 ? $left : 16;
            $mask = ~(0xFFFF >> $left) & 0xFFFF;

            if (($shortsAddr[$i] & $mask) !== ($shortsTest[$i] & $mask)) {
                return $this->remember($cacheKey, false);
            }
        }

        return $this->remember($cacheKey, true);
    }

    /**
     * Validate a single IP or CIDR string and return whether it is well-formed.
     * Useful from CLI commands to reject bad input before storage.
     */
    public function validateCidr(string $entry): bool
    {
        if (! str_contains($entry, '/')) {
            return (bool) filter_var($entry, FILTER_VALIDATE_IP);
        }

        [$address, $netmask] = explode('/', $entry, 2);
        if (! ctype_digit($netmask)) {
            return false;
        }
        $prefix = (int) $netmask;

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $prefix >= 0 && $prefix <= 32;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $prefix >= 0 && $prefix <= 128;
        }

        return false;
    }

    private function remember(string $key, bool $value): bool
    {
        if (count($this->cache) >= self::MAX_CACHE_ENTRIES) {
            // Drop the oldest entry; PHP arrays preserve insertion order.
            array_shift($this->cache);
        }

        return $this->cache[$key] = $value;
    }
}
