<?php namespace Daycry\Maintenance\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Status extends BaseCommand
{
	protected $group        = 'Maintenance Mode';
	protected $name         = 'mm:status';
	protected $description  = 'Display the maintenance mode status';
	protected $usage        = 'mm:status';
	protected $arguments    = [];
	protected $options 		= [];

	public function run(array $params)
	{
		helper('setting');
		if( file_exists( setting('Maintenance.filePath') . setting('Maintenance.fileName') ) )
        {
			$data = json_decode( file_get_contents( setting('Maintenance.filePath') . setting('Maintenance.fileName') ) );
			
			CLI::newLine( 1 );
			CLI::error( 'Application is already DOWN.' );
			CLI::newLine( 1 );
			
			// echo keys and values in table
			// without allowed_ips
			$thead = [ "key", "value" ];

			$tbody = array();

			foreach( $data as $key => $value )
            {	
				switch( $key )
				{
					case "allowed_ips":
						break;
					case "time":
						$tbody[] = [ $key, date( 'Y-m-d H:i:s', $value ) ];
						break;
					default:
						$tbody[] = [ $key, $value ];
				}
			}

			CLI::table( $tbody, $thead );

			//
			// echo allowed_ips in table
			//
			$thead = [ "allowed ips" ];

			$tbody = array();

			foreach( $data->allowed_ips as $ip )
            {
				$tbody[] = [ $ip ];
			}

			CLI::table( $tbody, $thead );
			
			CLI::newLine( 1 );
			
		}else{
			CLI::newLine( 1 );
			CLI::write( '**** Application is already live. ****', 'green' );
			CLI::newLine( 1 );
		}
	}
}
