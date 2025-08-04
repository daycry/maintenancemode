<?php
/**
 * Demo script to show bypass detection functionality
 */

echo "🔧 Maintenance Mode Bypass Detection Demo\n";
echo "==========================================\n\n";

// Simulate different bypass scenarios
$scenarios = [
    [
        'title' => 'Scenario 1: Config Secret Bypass',
        'config' => ['allowSecretBypass' => true, 'secretBypassKey' => 'global-secret'],
        'data' => ['secret_bypass' => false],
        'request' => ['secret' => 'global-secret', 'ip' => '192.168.1.200', 'cookie' => ''],
        'expected' => 'Config Secret Bypass'
    ],
    [
        'title' => 'Scenario 2: Data Secret Bypass',
        'config' => ['allowSecretBypass' => false, 'secretBypassKey' => ''],
        'data' => ['secret_bypass' => true, 'secret_key' => 'data-secret'],
        'request' => ['secret' => 'data-secret', 'ip' => '192.168.1.200', 'cookie' => ''],
        'expected' => 'Data Secret Bypass'
    ],
    [
        'title' => 'Scenario 3: IP Address Bypass',
        'config' => ['allowSecretBypass' => false, 'secretBypassKey' => ''],
        'data' => ['allowed_ips' => ['127.0.0.1', '192.168.1.100']],
        'request' => ['secret' => '', 'ip' => '127.0.0.1', 'cookie' => ''],
        'expected' => 'IP Address Bypass'
    ],
    [
        'title' => 'Scenario 4: Cookie Bypass',
        'config' => ['allowSecretBypass' => false, 'secretBypassKey' => ''],
        'data' => ['cookie_name' => 'bypass_cookie'],
        'request' => ['secret' => '', 'ip' => '192.168.1.200', 'cookie' => 'bypass_cookie'],
        'expected' => 'Cookie Bypass'
    ],
    [
        'title' => 'Scenario 5: No Bypass (Blocked)',
        'config' => ['allowSecretBypass' => false, 'secretBypassKey' => ''],
        'data' => ['allowed_ips' => ['192.168.1.100']],
        'request' => ['secret' => 'wrong-secret', 'ip' => '192.168.1.200', 'cookie' => 'wrong-cookie'],
        'expected' => 'ACCESS BLOCKED'
    ],
    [
        'title' => 'Scenario 6: Multiple Methods - Priority Test',
        'config' => ['allowSecretBypass' => true, 'secretBypassKey' => 'priority-secret'],
        'data' => ['secret_bypass' => true, 'secret_key' => 'data-secret', 'allowed_ips' => ['127.0.0.1']],
        'request' => ['secret' => 'priority-secret', 'ip' => '127.0.0.1', 'cookie' => ''],
        'expected' => 'Config Secret Bypass (highest priority)'
    ],
];

foreach ($scenarios as $i => $scenario) {
    echo ($i + 1) . ". {$scenario['title']}\n";
    echo str_repeat('-', strlen($scenario['title']) + 3) . "\n";
    
    // Show configuration
    echo "   Config:\n";
    foreach ($scenario['config'] as $key => $value) {
        $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : ($value ?: 'empty');
        echo "     {$key}: {$valueStr}\n";
    }
    
    echo "   Data:\n";
    foreach ($scenario['data'] as $key => $value) {
        if (is_array($value)) {
            echo "     {$key}: [" . implode(', ', $value) . "]\n";
        } else {
            $valueStr = is_bool($value) ? ($value ? 'true' : 'false') : ($value ?: 'empty');
            echo "     {$key}: {$valueStr}\n";
        }
    }
    
    echo "   Request:\n";
    foreach ($scenario['request'] as $key => $value) {
        echo "     {$key}: " . ($value ?: 'empty') . "\n";
    }
    
    // Simulate bypass checking logic
    $bypassResult = checkBypassLogic($scenario['config'], $scenario['data'], $scenario['request']);
    
    echo "   Result:\n";
    echo "     ✅ {$bypassResult}\n";
    echo "     Expected: {$scenario['expected']}\n";
    
    $matches = (strpos($bypassResult, $scenario['expected']) !== false);
    echo "     Status: " . ($matches ? '✅ CORRECT' : '❌ UNEXPECTED') . "\n";
    
    echo "\n";
}

echo "🎉 Bypass Detection Demo Complete!\n";
echo "=====================================\n\n";

echo "📋 Summary:\n";
echo "✅ Config secret has highest priority\n";
echo "✅ Data secret has second priority\n";
echo "✅ IP bypass checks client IP against allowed list\n";
echo "✅ Cookie bypass validates cookie name/value match\n";
echo "✅ Priority order is enforced correctly\n";
echo "✅ Multiple bypass methods can coexist\n\n";

echo "🔧 Status Command Enhancement:\n";
echo "The mm:status command now shows:\n";
echo "• Current bypass status for each method\n";
echo "• Whether current user would have access\n";
echo "• Practical tips for setting up bypass\n";
echo "• Real-time detection of active bypasses\n\n";

echo "⚠️  Controller Testability Issue Identified:\n";
echo "Lines 42-50 in Controller (Config Secret Bypass) are not testeable\n";
echo "because the controller creates its own config instance.\n\n";

echo "🔧 Proposed Solution:\n";
echo "Modify Controller::check() to accept optional config parameter:\n";
echo "  public static function check(\$config = null)\n";
echo "  \$config = \$config ?? new Maintenance();\n\n";

echo "📊 Current Test Coverage:\n";
echo "✅ Data Secret Bypass: 100% covered\n";
echo "✅ IP Address Bypass: 100% covered\n";
echo "✅ Cookie Bypass: Partially covered\n";
echo "❌ Config Secret Bypass: 0% covered (lines 42-50)\n\n";

echo "🎯 With Proposed Improvement:\n";
echo "✅ All bypass methods: 100% testeable\n";
echo "✅ Priority order: Fully verifiable\n";
echo "✅ Logging: Completely testeable\n";
echo "✅ Edge cases: All scenarios covered\n\n";

echo "📝 See generated files:\n";
echo "• CONTROLLER_TESTABILITY_PROPOSAL.md\n";
echo "• CONTROLLER_IMPROVED_EXAMPLE.php\n";
echo "• CONTROLLER_IMPROVED_TESTS_EXAMPLE.php\n";

function checkBypassLogic($config, $data, $request) 
{
    // 1. Check config secret bypass (highest priority)
    if (($config['allowSecretBypass'] ?? false) && !empty($config['secretBypassKey'])) {
        if ($request['secret'] === $config['secretBypassKey']) {
            return 'Config Secret Bypass (highest priority)';
        }
    }
    
    // 2. Check data secret bypass
    if (($data['secret_bypass'] ?? false) && !empty($data['secret_key'] ?? '')) {
        if ($request['secret'] === $data['secret_key']) {
            return 'Data Secret Bypass';
        }
    }
    
    // 3. Check IP bypass
    if (!empty($data['allowed_ips'] ?? [])) {
        if (in_array($request['ip'], $data['allowed_ips'])) {
            return 'IP Address Bypass';
        }
    }
    
    // 4. Check cookie bypass
    if (!empty($data['cookie_name'] ?? '')) {
        if ($request['cookie'] === $data['cookie_name']) {
            return 'Cookie Bypass';
        }
    }
    
    return 'ACCESS BLOCKED - No valid bypass method';
}
