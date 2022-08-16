<?php
namespace Daycry\Maintenance\Libraries;

class Config
{
    public static function getConfig()
    {
        $config = config( 'Maintenance' );

        if( empty( $config ) )
        {   
            // @codeCoverageIgnoreStart
            $config = new \Daycry\Maintenance\Config\Maintenance();
            // @codeCoverageIgnoreEnd
        }

        return $config;
    }
}