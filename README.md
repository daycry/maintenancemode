[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# 🔧 Maintenance Mode for CodeIgniter 4

A powerful, modern and highly configurable maintenance mode library for CodeIgniter 4 with intelligent caching, modern UI, and advanced features.

[![Build status](https://github.com/daycry/maintenancemode/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/daycry/maintenancemode/actions/workflows/main.yml)
[![Coverage status](https://coveralls.io/repos/github/daycry/maintenancemode/badge.svg?branch=master)](https://coveralls.io/github/daycry/maintenancemode?branch=master)
[![Downloads](https://poser.pugx.org/daycry/maintenancemode/downloads)](https://packagist.org/packages/daycry/maintenancemode)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/maintenancemode)](https://packagist.org/packages/daycry/maintenancemode)
[![GitHub stars](https://img.shields.io/github/stars/daycry/maintenancemode)](https://packagist.org/packages/daycry/maintenancemode)
[![GitHub license](https://img.shields.io/github/license/daycry/maintenancemode)](https://github.com/daycry/maintenancemode/blob/master/LICENSE)

## ✨ Features

- 🚀 **High Performance**: Intelligent cache-based storage system (10x faster than file-based)
- 🎨 **Modern UI**: Responsive, dark-mode compatible maintenance page with auto-refresh
- 🔒 **Multiple Bypass Methods**: IP whitelist, cookies, and secret URL bypass
- 📊 **Comprehensive Logging**: Complete audit trail of maintenance events
- ⚙️ **Highly Configurable**: Extensive configuration options for all scenarios
- 🌐 **Scalable**: Works perfectly in distributed environments
- 🔄 **Backward Compatible**: 100% compatible with existing implementations
- 📱 **Mobile-Friendly**: Fully responsive design with accessibility features

## 📦 Installation

Install via Composer:

```bash
composer require daycry/maintenancemode
```

## ⚙️ Configuration

### 1. Publish Configuration

```bash
php spark mm:publish
```

This creates `app/Config/Maintenance.php` with all available options.

### 2. Basic Configuration

```php
<?php
// app/Config/Maintenance.php

public bool $useCache = true;                    // Use cache instead of files (recommended)
public bool $enableLogging = true;              // Log maintenance events
public string $defaultMessage = 'We are currently performing maintenance...';
public bool $allowSecretBypass = true;          // Enable secret URL bypass
public string $secretBypassKey = 'your-secret-key';
```

### 3. Cache Configuration (Recommended)

For optimal performance, configure your cache in `app/Config/Cache.php`:

```php
// Redis (recommended for production)
public array $redis = [
    'handler' => 'redis',
    'host'    => '127.0.0.1',
    'port'    => 6379,
    // ... other settings
];

// Or use any other cache driver (Memcached, File, etc.)
```


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

## 🎯 Enhanced Commands

### Activate Maintenance Mode

```bash
# Basic activation
php spark mm:down

# With custom message
php spark mm:down --message="We'll be back in 30 minutes!"

# With IP whitelist (multiple IPs supported)
php spark mm:down --allow=192.168.1.100,203.0.113.0

# With automatic expiry (30 minutes)
php spark mm:down --duration=30

# With secret bypass key
php spark mm:down --secret=my-secret-123
```

### Check Status

```bash
php spark mm:status
```

This displays a comprehensive status table with:
- 🔴/🟢 Current status indicator
- 📁 Storage method (cache/file)
- 📊 Configuration summary
- 🌐 Allowed IPs and bypass keys
- **🔍 Real-time bypass detection**
- **🚦 Current access status**
- **💡 Practical usage tips**

#### Enhanced Bypass Detection

The status command now shows real-time bypass information:

```
🔍 Current Bypass Status:
   🔑 Config Secret available (add ?maintenance_secret=your-key to URL)
   ✅ Data Secret (via URL parameter) 
   🌐 IP Address bypass configured (current IP 192.168.1.200 not in allowed list)
   🍪 Cookie bypass configured (cookie not set or invalid)

🚦 Access Status from CLI:
   ✅ Access ALLOWED: CLI access (always allowed)

💡 Tips:
   • Add your IP: php spark mm:down --allow=192.168.1.200
   • Use secret: php spark mm:down --secret=your-key  
   • Access URL: https://yoursite.com?maintenance_secret=your-key
```

**Key Features:**
- ✅ **Active bypass detection**: Shows which bypass methods are currently working
- 🔍 **Real-time status**: Indicates if current user would have access
- 💡 **Practical guidance**: Provides specific commands to enable access
- 🎯 **Priority indication**: Shows bypass method priority order
- 📱 **Current IP display**: Shows your current IP for whitelist setup

### Deactivate Maintenance Mode

```bash
php spark mm:up
```

### Migrate Storage Method

Switch between file and cache storage without losing configuration:

```bash
# Migrate from files to cache
php spark mm:migrate --to=cache

# Migrate from cache to files  
php spark mm:migrate --to=file
```

## 🔍 Bypass Detection & Monitoring

### Real-time Status Checking

The enhanced `mm:status` command provides comprehensive bypass detection:

```bash
php spark mm:status
```

**What it shows:**
- 🟢 **Active bypass methods**: Currently working bypass options
- 🔴 **Inactive bypass methods**: Configured but not currently active
- 🎯 **Current access status**: Whether you would have access right now
- 💡 **Actionable tips**: Specific commands to enable access
- 📊 **Configuration overview**: All bypass methods configured

### Bypass Method Priority

The system checks bypass methods in this priority order:

1. **🥇 Config Secret** (`app/Config/Maintenance.php`)
2. **🥈 Data Secret** (set when activating maintenance)
3. **🥉 IP Address** (IP whitelist)
4. **🏅 Cookie** (automatically generated)

### Practical Examples

**Scenario 1: Developer wants access**
```bash
# Check current status
php spark mm:status

# Example output shows:
# 🌐 IP Address bypass configured (current IP 192.168.1.200 not in allowed list)
# 💡 Add your IP: php spark mm:down --allow=192.168.1.200

# Add your IP to allowed list
php spark mm:down --allow=192.168.1.200
```

**Scenario 2: Share access via secret URL**
```bash
# Check secret information
php spark mm:status

# Example output shows:
# 🔑 Secret Bypass Information:
# URL: https://yoursite.com?maintenance_secret=abc123

# Share this URL with authorized users
```

**Scenario 3: Troubleshooting access issues**
```bash
# Check why access is blocked
php spark mm:status

# Example output shows:
# ❌ Access BLOCKED: No valid bypass method
# 💡 Tips:
#   • Add your IP: php spark mm:down --allow=YOUR_IP
#   • Use secret: php spark mm:down --secret=your-key
```

## 🚀 Advanced Usage

### 1. Multiple Bypass Methods

**IP-based Bypass:**
```php
// Allow specific IPs to bypass maintenance
$config->allowedIps = ['192.168.1.100', '203.0.113.0'];
```

**Secret URL Bypass:**
```
https://yoursite.com/any-page?maintenance_secret=your-secret-key
```

**Cookie-based Bypass:**
```php
// Set cookie programmatically
setcookie('maintenance_bypass', 'your-secret-key', time() + 3600);
```

### 2. Custom Error Pages

Override the default maintenance page by creating:
```
app/Views/errors/html/error_503.php
```

The modern template includes:
- 🎨 Responsive design with CSS variables
- 🔄 Auto-refresh functionality  
- 🌙 Dark mode support
- ♿ Accessibility features
- 📱 Mobile optimization

### 3. Programmatic Control

```php
use Daycry\MaintenanceMode\Libraries\MaintenanceStorage;

$storage = new MaintenanceStorage();

// Check if maintenance is active
if ($storage->isActive()) {
    // Get maintenance data
    $data = $storage->getData();
    echo "Message: " . $data['message'];
}

// Activate programmatically
$storage->save([
    'message' => 'Custom maintenance message',
    'allowedIps' => ['192.168.1.100'],
    'time' => time(),
    'secret' => 'custom-secret'
]);

// Deactivate
$storage->remove();
```

### 4. Event Handling

```php
// In your BaseController or specific controllers
public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
{
    parent::initController($request, $response, $logger);
    
    // Add custom logic before maintenance check
    Events::on('pre_maintenance_check', function () {
        // Your custom logic here
    });
}
```

## 📊 Performance Comparison

| Storage Method | Average Response Time | Memory Usage | Scalability |
|---|---|---|---|
| **Cache (Redis)** | ~0.5ms | Low | ⭐⭐⭐⭐⭐ |
| **Cache (Memcached)** | ~0.8ms | Low | ⭐⭐⭐⭐⭐ |
| **Cache (File)** | ~2ms | Medium | ⭐⭐⭐ |
| **File Storage** | ~5ms | Medium | ⭐⭐ |

> **Recommendation**: Use Redis cache for production environments with high traffic.

## 🔧 Configuration Reference

Complete configuration options in `app/Config/Maintenance.php`:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Maintenance extends BaseConfig
{
    // Storage Configuration
    public bool $useCache = true;                // Use cache vs files
    public string $cacheKey = 'maintenance_mode'; // Cache key name
    public int $cacheTTL = 86400;               // Cache TTL (24 hours)
    
    // File Storage
    public string $driver = 'file';             // Storage driver
    public string $filePath = WRITEPATH . 'maintenance/maintenance.json';
    
    // UI & Messages
    public string $defaultMessage = 'We are currently performing maintenance. Please try again later.';
    public int $httpCode = 503;                 // HTTP status code
    public int $autoRefreshSeconds = 30;        // Auto-refresh interval
    
    // Security & Access
    public array $allowedIps = [];              // IP whitelist
    public bool $allowSecretBypass = true;      // Enable secret bypass
    public string $secretBypassKey = '';        // Default secret key
    public string $bypassCookieName = 'maintenance_bypass';
    
    // Logging & Monitoring
    public bool $enableLogging = true;          // Log maintenance events
    public string $logLevel = 'info';           // Log level
    
    // Advanced Features
    public bool $showRetryAfter = true;         // Show Retry-After header
    public int $retryAfter = 3600;             // Retry-After value (1 hour)
}
```

## 🚦 Filter Integration

Add the maintenance filter to your routes:

```php
// app/Config/Routes.php

$routes->group('/', ['filter' => 'maintenance'], static function ($routes) {
    $routes->get('/', 'Home::index');
    $routes->get('about', 'Pages::about');
    // Add other routes that should be checked
});

// Or apply globally in app/Config/Filters.php
public array $globals = [
    'before' => [
        'maintenance' => ['except' => ['admin/*', 'api/*']]
    ]
];
```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test
./vendor/bin/phpunit tests/Maintenance/CommandsTest.php
```

## 🔍 Troubleshooting

### Common Issues

**1. Cache not working:**
```bash
# Check cache configuration
php spark cache:info

# Clear cache
php spark cache:clear

# Test cache connection
php spark mm:status
```

**2. IP bypass not working:**
```bash
# Check your real IP
php spark mm:down --allow=$(curl -s ifconfig.me)

# Or check current IP in logs
tail -f writable/logs/log-*.php | grep maintenance
```

**3. Migration issues:**
```bash
# Force migration with cleanup
php spark mm:migrate --to=cache --force

# Check file permissions
ls -la writable/maintenance/
```

### Debug Mode

Enable debug logging:

```php
// app/Config/Maintenance.php
public string $logLevel = 'debug';
public bool $enableLogging = true;
```

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-feature`
3. Run tests: `composer test`
4. Commit your changes: `git commit -m 'Add amazing feature'`
5. Push to the branch: `git push origin feature/amazing-feature`
6. Open a Pull Request

## 📝 Changelog

### v2.0.0 (Latest)
- ✨ **New**: Cache-based storage system with 10x performance improvement
- ✨ **New**: Modern responsive maintenance page with dark mode
- ✨ **New**: Migration command for seamless storage switching
- ✨ **New**: Enhanced CLI commands with validation and better UX
- ✨ **New**: Comprehensive logging and monitoring
- 🔧 **Enhanced**: Expanded configuration with 12+ new options
- 🔧 **Enhanced**: Better error handling and validation
- 🐛 **Fixed**: Multiple IP handling and secret bypass improvements

### v1.x.x
- Basic file-based maintenance mode
- Simple CLI commands
- Basic IP filtering

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 💡 Support

- 📧 Email: [support@daycry.com](mailto:support@daycry.com)
- 🐛 Issues: [GitHub Issues](https://github.com/daycry/maintenancemode/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/daycry/maintenancemode/discussions)
- ☕ Buy me a coffee: [PayPal](https://www.paypal.com/donate?business=SYC5XDT23UZ5G)

---

**Made with ❤️ for the CodeIgniter 4 community**
