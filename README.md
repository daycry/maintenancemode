[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# Maintenance Mode for Codeigniter 4


[![Build Status](https://github.com/daycry/maintenancemode/workflows/PHP%20Tests/badge.svg)](https://github.com/daycry/maintenancemode/actions?query=workflow%3A%22PHP+Tests%22)
[![Coverage Status](https://coveralls.io/repos/github/daycry/maintenancemode/badge.svg?branch=master)](https://coveralls.io/github/daycry/maintenancemode?branch=master)
[![Downloads](https://poser.pugx.org/daycry/maintenancemode/downloads)](https://packagist.org/packages/daycry/maintenancemode)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/maintenancemode)](https://packagist.org/packages/daycry/maintenancemode)
[![GitHub stars](https://img.shields.io/github/stars/daycry/maintenancemode)](https://packagist.org/packages/daycry/maintenancemode)
[![GitHub license](https://img.shields.io/github/license/daycry/maintenancemode)](https://github.com/daycry/maintenancemode/blob/master/LICENSE)

## Installation via composer

Use the package with composer install

	> composer require daycry/maintenancemode

## Configuration

Run command:

	> php spark mm:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/Maintenance.php`.


## Commands Available

```php
php spark mm:down
php spark mm:status
php spark mm:up
```

## Use it

#### Method 1 (Recommended)
Create new event in **app/Config/Events.php**

```php
Events::on( 'pre_system', 'Daycry\Maintenance\Controllers\Maintenance::check' );
```

#### Method 2

edit application/Config/Filters.php and
add the new line in $aliases array:

```php
public $aliases = [
    'maintenance' => \Daycry\Maintenance\Filters\Maintenance::class,
    ...
]
```
and add "maintenance" in $globals['before'] array:
```php
public $globals = [
    'before' => [
        'maintenance',
        ...
    ],
    'after'  => [
        ...
    ],
];
```
