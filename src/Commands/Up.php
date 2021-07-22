<?php
namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Up extends BaseCommand
{
	protected $group        = 'Maintenance Mode';
	protected $name         = 'mm:up';
	protected $description  = 'Bring the application out of maintenance mode';
	protected $usage        = 'mm:up';
	protected $arguments    = [];
	protected $options 		= [];

	public function run( array $params )
	{
		$config = \Daycry\Maintenance\Libraries\Config::getConfig();

		//delete the file with json content
		@unlink( $config->filePath . $config->fileName );

		CLI::newLine( 1 );
		CLI::write( '**** Application is now live. ****', 'black', 'green' );
		CLI::newLine( 1 );
	}
}
