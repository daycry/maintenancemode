<?php

namespace Daycry\Maintenance\Libraries;

/**
 * @deprecated since v3.0 — use {@see IpChecker} (instantiable, testable, bounded cache).
 *             This class remains as a thin BC wrapper and will be removed in a future major.
 */
class IpUtils
{
    private static ?IpChecker $checker = null;

    public function __construct()
    {
    }

    /**
     * @param array<int, string>|string $ips
     */
    public static function checkIp(string $requestIp, array|string $ips): bool
    {
        return self::checker()->checkIp($requestIp, $ips);
    }

    public static function checkIp4(string $requestIp, string $ip): bool
    {
        return self::checker()->checkIp4($requestIp, $ip);
    }

    public static function checkIp6(string $requestIp, string $ip): bool
    {
        return self::checker()->checkIp6($requestIp, $ip);
    }

    private static function checker(): IpChecker
    {
        return self::$checker ??= new IpChecker();
    }
}
