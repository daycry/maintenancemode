# Propuesta de Mejora: Testabilidad del Config Secret Bypass

## Problema Identificado

Las líneas 42-50 del `Maintenance Controller` no se ejecutan en los tests porque:

```php
// Líneas 42-50 en src/Controllers/Maintenance.php
if ($config->allowSecretBypass && !empty($config->secretBypassKey)) {
    $request = Services::request();
    if ($request->getGet('maintenance_secret') === $config->secretBypassKey) {
        if ($config->enableLogging) {
            log_message('info', 'Maintenance mode bypassed via secret key from IP: ' . $request->getIPAddress());
        }
        return true;
    }
}
```

**Causa**: El controller crea una nueva instancia de configuración en línea 21:
```php
$config = new \Daycry\Maintenance\Config\Maintenance();
```

Esto impide que los tests puedan inyectar configuración personalizada.

## Solución Recomendada

### Opción 1: Inyección de Dependencias (Recomendada)

Modificar el método `check()` para aceptar configuración opcional:

```php
public static function check($config = null)
{
    // if request is from CLI
    if (is_cli() && ENVIRONMENT !== 'testing') {
        return true;
    }

    $config = $config ?? new \Daycry\Maintenance\Config\Maintenance();
    $storage = new MaintenanceStorage($config);
    
    // ... resto del código
}
```

**Beneficios**:
- Mantiene compatibilidad hacia atrás
- Permite inyección en tests
- No rompe el uso existente

### Opción 2: Factory Method

Crear un método factory para obtener la configuración:

```php
protected static function getConfig()
{
    return new \Daycry\Maintenance\Config\Maintenance();
}

public static function check()
{
    // ... 
    $config = static::getConfig();
    // ...
}
```

**Beneficios**:
- Permite sobrescribir en tests
- Patrón más testeable

### Opción 3: Service Locator

Usar el sistema de servicios de CodeIgniter:

```php
$config = Services::config('Maintenance');
```

## Tests Propuestos Después de la Mejora

```php
public function testConfigSecretBypassWithInjection(): void
{
    // Crear configuración personalizada
    $config = new \Daycry\Maintenance\Config\Maintenance();
    $config->allowSecretBypass = true;
    $config->secretBypassKey = 'test-config-secret';
    
    // Activar mantenimiento
    $this->storage->save([
        'time' => time(),
        'message' => 'Config secret test',
        'allowed_ips' => []
    ]);
    
    // Probar bypass con config secret
    $_GET['maintenance_secret'] = 'test-config-secret';
    $_SERVER['REMOTE_ADDR'] = '192.168.1.200';
    
    $result = Maintenance::check($config);  // ← Inyección de config
    $this->assertTrue($result);
}

public function testConfigSecretPriorityOverDataSecret(): void
{
    $config = new \Daycry\Maintenance\Config\Maintenance();
    $config->allowSecretBypass = true;
    $config->secretBypassKey = 'config-secret';
    
    $this->storage->save([
        'time' => time(),
        'message' => 'Priority test',
        'secret_bypass' => true,
        'secret_key' => 'data-secret',
        'allowed_ips' => []
    ]);
    
    // Con config secret debería ganar sobre data secret
    $_GET['maintenance_secret'] = 'config-secret';
    
    $result = Maintenance::check($config);
    $this->assertTrue($result);
    
    // Verificar que NO usó data secret
    // (esto requeriría logging o algún mecanismo de tracking)
}
```

## Cobertura de Código Actual

**Antes de la mejora**:
- ✅ Líneas 53-60: Data secret bypass (100% cobertura)
- ✅ Líneas 65-72: IP bypass (100% cobertura)  
- ✅ Líneas 74-84: Cookie bypass (parcial cobertura)
- ❌ Líneas 42-50: Config secret bypass (0% cobertura)

**Después de la mejora**:
- ✅ Todas las líneas tendrían cobertura completa
- ✅ Se podrían probar prioridades de bypass
- ✅ Se podrían probar configuraciones de logging

## Implementación Inmediata

Para implementar esta mejora de forma no disruptiva:

1. Modificar `src/Controllers/Maintenance.php` línea 14:
```php
public static function check($config = null)
```

2. Modificar línea 21:
```php
$config = $config ?? new \Daycry\Maintenance\Config\Maintenance();
```

3. Actualizar tests para usar inyección de configuración

4. Mantener compatibilidad: todos los usos existentes siguen funcionando

## Estado Actual de Tests

Los tests documentan que la funcionalidad existe pero no se puede probar completamente:

- `testConfigSecretBypassLogicExists()`: Documenta la lógica
- `testConfigSecretBypassCodeCoverage()`: Explica por qué no se ejecuta
- `testCheckWithMultipleSecretMethods()`: Documenta el orden de prioridad

Esta documentación sirve como especificación para cuando se implemente la mejora.
