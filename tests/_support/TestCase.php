<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Support;

use CodeIgniter\Config\Factories;
use CodeIgniter\Settings\Config\Settings as SettingsConfig;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Security;
use Config\Services;

/**
 * @internal
 */
abstract class TestCase extends CIUnitTestCase
{
    protected function setUp(): void
    {
        $this->resetServices();

        parent::setUp();

        // Use Array Settings Handler
        /** @var SettingsConfig $configSettings */
        $configSettings           = config('Settings');
        $configSettings->handlers = ['array'];
        $settings                 = new Settings($configSettings);
        Services::injectMock('settings', $settings);

        // Load helpers that should be autoloaded
        helper(['setting']);

        // Set Config\Security::$csrfProtection to 'session'
        /** @var Security $config */
        $config                 = config('Security');
        $config->csrfProtection = 'session';
        Factories::injectMock('config', 'Security', $config);
    }
}
