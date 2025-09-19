<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by the advancedldap plugin team.
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap;

use GlpiPlugin\Advancedldap\Container\ServiceContainer;

/**
 * Plugin initialization helper
 */
class Bootstrap
{
    private static ?ServiceContainer $container = null;

    /**
     * Initialize the plugin services
     *
     * @return ServiceContainer Service container instance
     */
    public static function initialize(): ServiceContainer
    {
        if (self::$container === null) {
            self::$container = ServiceContainer::getInstance();
        }

        return self::$container;
    }

    /**
     * Get service container instance
     *
     * @return ServiceContainer Service container instance
     */
    public static function getContainer(): ServiceContainer
    {
        return self::initialize();
    }

    /**
     * Create an AdvancedLdapSync instance with proper dependencies
     *
     * @return AdvancedLdapSync Configured instance
     */
    public static function createAdvancedLdapSync(): AdvancedLdapSync
    {
        $container = self::getContainer();
        return new AdvancedLdapSync($container);
    }

    /**
     * Reset services (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$container = null;
        ServiceContainer::reset();
    }
}
