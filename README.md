# Maintenance Mode [![](https://github.com/daycry/maintenancemode/workflows/PHP%20Tests/badge.svg)](https://github.com/daycry/maintenancemode/actions?query=workflow%3A%22PHP+Tests%22)

for Codeigniter 4

## Installation via composer

Use the package with composer install

	> composer composer require daycry/maintenancemode

## Manual installation

Download this repo and then enable it by editing **app/Config/Autoload.php** and adding the **Daycry\Maintenance**
namespace to the **$psr4** array. For example, if you copied it into **app/ThirdParty**:

```php
$psr4 = [
    'Config'      => APPPATH . 'Config',
    APP_NAMESPACE => APPPATH,
    'App'         => APPPATH,
    'Daycry\Maintenance' => APPPATH .'ThirdParty/maintenancemode/src',
];
```

## Configuration

Run command:

	> php spark mm:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/Maintenance.php`.

Create new event in **app/Config/Events.php**

```php
Events::on( 'pre_system', 'Daycry\Maintenance\Controllers\Maintenance::check' );
```


## Usage

```php
php spark mm:down
php spark mm:status
php spark mm:up
```

## How Run Tests

```php
cd vendor\daycry\maintenancemode\
composer install
vendor\bin\phpunit

```