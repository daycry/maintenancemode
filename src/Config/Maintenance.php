<?php 
namespace Daycry\Maintenance\Config;

use CodeIgniter\Config\BaseConfig;

class MaintenanceMode extends BaseConfig
{

    //--------------------------------------------------------------------
    // maintenance mode file path
    //--------------------------------------------------------------------
    // 
    //
    public $FilePath = WRITEPATH . 'maintenance/';
    public $FileName = 'down';
}