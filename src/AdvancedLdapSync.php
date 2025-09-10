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
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.fr.html
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap;

use AuthLDAP;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Advancedldap\Container\ServiceContainer;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use GlpiPlugin\Advancedldap\Contracts\ConfigurationInterface;
use GlpiPlugin\Advancedldap\Services\AssetFieldService;
use GlpiPlugin\Advancedldap\Services\LdapTestService;

/**
 * Advanced LDAP synchronization functionality
 */
class AdvancedLdapSync extends CommonGLPI
{
    public static $rightname = 'config';

    private ServiceContainer $container;
    private AssetFieldProviderInterface $assetFieldProvider;
    private ConfigurationInterface $configuration;
    private LdapTestService $ldapTestService;

    /**
     * @param ServiceContainer|null $container Optional service container
     */
    public function __construct(?ServiceContainer $container = null)
    {
        parent::__construct();

        $this->container = $container ?? ServiceContainer::getInstance();
        $this->assetFieldProvider = $this->container->get(AssetFieldProviderInterface::class);
        $this->configuration = $this->container->get(ConfigurationInterface::class);
        $this->ldapTestService = $this->container->get(LdapTestService::class);
    }

    /**
     * Get tab name for AuthLDAP item
     *
     * @param CommonGLPI $item         Item for which tab is displayed
     * @param int        $withtemplate Template mode
     * @return array<int, string>|string Tab names or empty string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $item->can($item->getID(), READ)) {
            return [
                1 => self::createTabEntry(
                    __('Items to synchronize', 'advancedldap'),
                    0,
                    $item::class,
                    "ti ti-adjustments-alt",
                ),
            ];
        }
        return '';
    }

    /**
     * Display tab content for AuthLDAP item
     *
     * @param CommonGLPI $item      Item for which tab is displayed
     * @param int        $tabnum    Tab number
     * @param int        $withtemplate Template mode
     * @return bool Success status
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof AuthLDAP && $tabnum === 1) {
            $instance = new self();
            $instance->showAdvancedSyncForm($item);
        }
        return true;
    }

    /**
     * Show advanced sync configuration form
     *
     * @param AuthLDAP $authldap AuthLDAP instance
     * @return void
     */
    public function showAdvancedSyncForm(AuthLDAP $authldap): void
    {
        $id = $authldap->getField('id');

        if (!$authldap->can($id, READ)) {
            return;
        }

        $available_assets = $this->buildAssetDropdown();
        $current_config = $this->getCurrentConfiguration();
        $test_results = $this->handleTestRequest($id, $current_config);

        TemplateRenderer::getInstance()->display('@advancedldap/ldap_sync.html.twig', [
            'authldap' => $authldap,
            'available_assets' => $available_assets,
            'current_config' => $current_config,
            'can_edit' => $authldap->can($id, UPDATE),
            'test_results' => $test_results,
        ]);
    }

    /**
     * @return array<string, string> Asset types grouped by category
     */
    private function buildAssetDropdown(): array
    {
        $available_assets = [];

        // Separate assets by type
        $assetFieldService = $this->assetFieldProvider;
        $all_asset_types = $assetFieldService instanceof AssetFieldService ? $assetFieldService->getAllAssetTypes() : [];

        $native_assets = array_filter($all_asset_types, fn($info) => $info['type'] === 'native');
        $generic_assets = array_filter($all_asset_types, fn($info) => $info['type'] === 'generic');

        if (!empty($native_assets)) {
            $available_assets['native_separator'] = '--- ' . __('Native Assets') . ' ---';
            foreach ($native_assets as $itemtype => $info) {
                $available_assets[$itemtype] = $info['name'];
            }
        }

        if (!empty($generic_assets)) {
            $available_assets['generic_separator'] = '--- ' . __('Generic Assets') . ' ---';
            foreach ($generic_assets as $itemtype => $info) {
                $available_assets[$itemtype] = $info['name'];
            }
        }

        return $available_assets;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCurrentConfiguration(): array
    {
        return [
            'show_inactive_generic_assets' => $this->configuration->get('show_inactive_generic_assets', 0),
            'ldap_connection_filter' => $this->configuration->get('ldap_connection_filter', ''),
            'ldap_base_dn' => $this->configuration->get('ldap_base_dn', ''),
        ];
    }

    /**
     * @param int $authldap_id
     * @param array $current_config
     * @return array<string, mixed>|null
     */
    private function handleTestRequest(int $authldap_id, array &$current_config): ?array
    {
        if (!isset($_GET['test_ldap']) || $_GET['test_ldap'] !== '1') {
            return null;
        }

        $test_base_dn = $_GET['test_base_dn'] ?? '';
        $test_filter = $_GET['test_filter'] ?? '';
        $test_asset_type = $_GET['test_asset_type'] ?? '';
        $test_asset_field = $_GET['test_asset_field'] ?? '';

        if (empty($test_base_dn) || empty($test_filter)) {
            return null;
        }

        $test_results = $this->ldapTestService->testLdapFilter(
            $authldap_id,
            $test_base_dn,
            $test_filter,
            $test_asset_type,
            $test_asset_field,
        );

        $current_config['ldap_base_dn'] = $test_base_dn;
        $current_config['ldap_connection_filter'] = $test_filter;

        return $test_results;
    }

    /**
     * Update plugin configuration
     *
     * @param array<string, mixed> $config Configuration values to update
     * @return bool Success status
     */
    public function updateConfig(array $config): bool
    {
        return $this->configuration->set($config);
    }

    /**
     * Get plugin configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function getConfigValue(string $key, $default = null)
    {
        return $this->configuration->get($key, $default);
    }
}
