<?php

namespace Daycry\Maintenance;

class MaintenanceTest extends TestCase
{
    private $message = 'In maintenance';
    private $ip = '127.0.0.1';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testInMaintenance()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );
        
        $this->assertFileExists( 
            $config->filePath . $config->fileName, 
            "given filename doesn't exists"
        ); 
    }

    public function testCheckStatus()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        command( 'mm:status' );
        
        $this->assertFileExists( $config->filePath . $config->fileName, "given filename doesn't exists" );

        $data = \json_decode( file_get_contents( $config->filePath . $config->fileName ), true );

        $this->assertEquals( $this->message, $data[ 'message'] );

        $this->assertTrue( $this->_arrays_are_similar( \explode( ' ', $this->ip ), $data[ 'allowed_ips' ] ) );
    }

    public function testOutMaintenance()
    {
        $config = config( 'Daycry\Maintenance\Config\Maintenance' );

        command( 'mm:up' );
        
        $this->assertFileNotExists( $config->filePath . $config->fileName, "given filename does exists" ); 
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