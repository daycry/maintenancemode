<?php
namespace Daycry\Maintenance\Libraries;

class Config
{
    /**
     * Checks if an IPv4 or IPv6 address is contained in the list of given IPs or subnets.
     *
     * @param string       $requestIp IP to check
     * @param string|array $ips       List of IPs or subnets (can be a string if only a single one)
     *
     * @return bool Whether the IP is valid
     */
    public static function getConfig()
    {
        $config = config( 'Maintenance' );

        if( empty( $config ) )
        {    
            $config=new \Daycry\Maintenance\Config\Maintenance();
        }

        return $config;
    }
}