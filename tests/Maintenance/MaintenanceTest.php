<?php

namespace Tests\Maintenance;

use CodeIgniter\Test\CIUnitTestCase;

class MaintenanceTest extends CIUnitTestCase
{
    private $message = 'In maintenance';
    private $ip = '127.0.0.1';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testCommandInMaintenanceDown()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        ob_start();
        $output = command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );
        
        if (!$output) {
            $output = \ob_get_contents();
        }
        ob_end_clean();

        $this->assertFileExists( $config->filePath . $config->fileName );
    }

    public function testCommandInMaintenanceAlreadyDown()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        ob_start();
        $output = command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );
        
        if (!$output) {
            $output = \ob_get_contents();
        }
        ob_end_clean();

        $this->assertFileExists( $config->filePath . $config->fileName );
    }

    public function testCommandCheckStatusDown()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        ob_start();
        $output = command( 'mm:status' );
        
        if (!$output) {
            $output = \ob_get_contents();
        }
        ob_end_clean();

        $data = \json_decode( file_get_contents( $config->filePath . $config->fileName ), true );

        $this->assertFileExists( $config->filePath . $config->fileName );
        $this->assertEquals( $this->message, $data[ 'message'] );
        $this->assertTrue( $this->_arrays_are_similar( \explode( ' ', $this->ip ), $data[ 'allowed_ips' ] ) );
    }

    public function testCommandMaintenanceUp()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        ob_start();
        $output = command( 'mm:up' );
        
        if (!$output) {
            $output = \ob_get_contents();
        }
        ob_end_clean();

        $this->assertFileDoesNotExist( $config->filePath . $config->fileName);
    }

    public function testCommandMaintenanceAlreadyUp()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        ob_start();
        $output = command( 'mm:status' );
        
        if (!$output) {
            $output = \ob_get_contents();
        }
        ob_end_clean();

        $this->assertFileDoesNotExist( $config->filePath . $config->fileName);
    }

    public function testCommandInMaintenanceDownWithoutMessage()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        ob_start();
        $output = command( 'mm:down' );
        
        if (!$output) {
            $output = \ob_get_contents();
        }
        ob_end_clean();

        $this->assertFileExists( $config->filePath . $config->fileName );
    }

    /**
     * Determine if two associative arrays are similar
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering 
     * 
     * @param array $a
     * @param array $b
     * @return bool
     */
    private function _arrays_are_similar( $a, $b )
    {
        // if the indexes don't match, return immediately
        if( count( array_diff_assoc( $a, $b ) ) )
        {
            return false;
        }

        // we know that the indexes, but maybe not values, match.
        // compare the values between the two arrays
        foreach( $a as $k => $v )
        {
            if( $v !== $b[ $k ] )
            {
                return false;
            }
        }
        // we have identical indexes, and no unequal values
        return true;
    }
}