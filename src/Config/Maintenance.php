<?php 
namespace Daycry\Maintenance\Config;

use CodeIgniter\Config\BaseConfig;

class Maintenance extends BaseConfig
{

    //--------------------------------------------------------------------
    // maintenance mode file path
    //--------------------------------------------------------------------
    // 
    //
    public $filePath = WRITEPATH . 'maintenance/';
    public $fileName = 'down';
}
