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

namespace GlpiPlugin\Advancedldap\Services;

use Config;
use GlpiPlugin\Advancedldap\Contracts\ConfigurationInterface;
use Toolbox;

/**
 * GLPI configuration wrapper
 */
class GlpiConfigurationService implements ConfigurationInterface
{
    private const PLUGIN_NAMESPACE = 'plugin:Advancedldap';

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null)
    {
        return Config::getConfigurationValue(self::PLUGIN_NAMESPACE, $key, $default);
    }

    /**
     * Set configuration values
     *
     * @param array $values Configuration values to set
     * @return bool Success status
     */
    public function set(array $values): bool
    {
        try {
            Config::setConfigurationValues(self::PLUGIN_NAMESPACE, $values);
            return true;
        } catch (\Exception $e) {
            Toolbox::logDebug("Advanced LDAP - Configuration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get GLPI global configuration
     *
     * @param string $key Configuration key
     * @return mixed Configuration value
     */
    public function getGlpiConfig(string $key)
    {
        global $CFG_GLPI;
        return $CFG_GLPI[$key] ?? null;
    }
}
