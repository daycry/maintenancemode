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
		//delete the file with json content
		@unlink( setting('Maintenance.filePath') . setting('Maintenance.fileName') );

		CLI::newLine( 1 );
		CLI::write( '**** Application is now live. ****', 'black', 'green' );
		CLI::newLine( 1 );
	}
}
