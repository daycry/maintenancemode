<?php

namespace Tests\Maintenance;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Filters\CITestStreamFilter;

class CommandsTest extends CIUnitTestCase
{
    private $message = 'In maintenance';
    private $ip = '127.0.0.1';

    /**
     * @var resource
     */
    private $streamFilter;

    /**
     * @var BaseConfig
     */
    private BaseConfig $config;
    
    protected function setUp(): void
    {
        parent::setUp();

        CITestStreamFilter::$buffer = '';
        $this->streamFilter = stream_filter_append(STDOUT, 'CITestStreamFilter');

        $this->config = new \Daycry\Maintenance\Config\Maintenance();
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testCommandInMaintenanceDown()
    {
        command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );

        $data = \json_decode( file_get_contents( $this->config->filePath . $this->config->fileName ), true );

        $this->assertFileExists( $this->config->filePath . $this->config->fileName );
        $this->assertSame( $this->message, $data['message'] );
        $this->assertTrue( $this->_arrays_are_similar( \explode( ' ', $this->ip ), $data[ 'allowed_ips' ] ) );
    }

    public function testCommandInMaintenanceAlreadyDown()
    {
        command( 'mm:down -message "'. $this->message .'" -ip ' . $this->ip );

        $data = \json_decode( file_get_contents( $this->config->filePath . $this->config->fileName ), true );

        $this->assertFileExists( $this->config->filePath . $this->config->fileName );
        $this->assertSame( $this->message, $data['message'] );
        $this->assertTrue( $this->_arrays_are_similar( \explode( ' ', $this->ip ), $data[ 'allowed_ips' ] ) );
    }

    public function testCommandCheckStatusDown()
    {
        command( 'mm:status' );

        $data = \json_decode( file_get_contents( $this->config->filePath . $this->config->fileName ), true );

        $this->assertFileExists( $this->config->filePath . $this->config->fileName );
        $this->assertEquals( $this->message, $data[ 'message'] );
        $this->assertTrue( $this->_arrays_are_similar( \explode( ' ', $this->ip ), $data[ 'allowed_ips' ] ) );
    }

    public function testCommandMaintenanceUp()
    {
        command( 'mm:up' );

        $this->assertFileDoesNotExist( $this->config->filePath . $this->config->fileName);
    }

    public function testCommandMaintenanceAlreadyUp()
    {
        $output = command( 'mm:status' );

        $this->assertFileDoesNotExist( $this->config->filePath . $this->config->fileName);
    }

    public function testCommandInMaintenanceDownWithoutMessage()
    {
        command( 'mm:down' );
        
        $this->assertFileExists( $this->config->filePath . $this->config->fileName );
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

    public static function tearDownAfterClass(): void
    {
        $config = new \Daycry\Maintenance\Config\Maintenance();
        unlink($config->filePath . $config->fileName);
    }

    protected function tearDown(): void
    {
        stream_filter_remove($this->streamFilter);
    }
}