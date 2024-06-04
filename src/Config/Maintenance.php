<?php

namespace Daycry\Maintenance\Config;

use CodeIgniter\Config\BaseConfig;

class Maintenance extends BaseConfig
{
    //--------------------------------------------------------------------
    // maintenance mode file path
    //--------------------------------------------------------------------
    public string $filePath = WRITEPATH . 'maintenance/';
    public string $fileName = 'down';
}
