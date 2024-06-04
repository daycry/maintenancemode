<?php
namespace Daycry\Maintenance\Controllers;

use CodeIgniter\Controller;
use Config\Services;
use Daycry\Maintenance\Libraries\IpUtils;
use Daycry\Maintenance\Exceptions\ServiceUnavailableException;

class Maintenance extends Controller
{
    private $config;

    public function __construct(){}

    public static function check()
    {
        // if request is from CLI
        if(is_cli() && ENVIRONMENT !== 'testing') return true;

        helper( 'setting' );
        $donwFilePath = setting('Maintenance.filePath') . setting('Maintenance.fileName');

        // if donw file does not exist app should keep running
        if( !file_exists( $donwFilePath ) )
        {
            return true;
        }

        // get all json data from donw file
        $data = json_decode( file_get_contents( $donwFilePath ) );

        // if request ip was entered in allowed_ips
        // the app should continue running
        $lib = new IpUtils();
        if( $lib->checkIp( Services::request()->getIPAddress(), $data->allowed_ips ) )
        {
            return true;
        }

        // if user's browser has been used the cookie pass
        // the app should continue running
        helper( 'cookie' );
        $cookieName = get_cookie( $data->cookie_name );

        if( $cookieName == $data->cookie_name )
        {
            // @codeCoverageIgnoreStart
            return true;
            // @codeCoverageIgnoreEnd
        }

        throw ServiceUnavailableException::forServerDow( $data->message );
    }
}
