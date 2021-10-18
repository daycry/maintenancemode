<?php
use CodeIgniter\CLI\CLI;

if( !isset( $code ) )
{
    $code = '';
}

if( !isset( $message ) )
{
    $message = '';
}

CLI::error( 'ERROR: ' . $code );
CLI::write( $message );
CLI::newLine();