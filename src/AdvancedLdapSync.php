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
use GlpiPlugin\Advancedldap\Services\AssetFieldService;
use GlpiPlugin\Advancedldap\Services\LdapTestService;

/**
 * Advanced LDAP synchronization functionality
 */
class AdvancedLdapSync extends CommonGLPI
{
    public static $rightname = 'config';

    private ServiceContainer $container;
    private AssetFieldProviderInterface $asset_field_provider;
    private LdapTestService $ldap_test_service;

    /**
     * @param ServiceContainer|null $container Optional service container
     */
    public function __construct(?ServiceContainer $container = null)
    {
        parent::__construct();

        $this->container = $container ?? ServiceContainer::getInstance();
        $this->asset_field_provider = $this->container->get(AssetFieldProviderInterface::class);
        $this->ldap_test_service = $this->container->get(LdapTestService::class);
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
        if ($item instanceof \AuthLDAP && $item->can($item->getID(), \READ)) {
            return self::createTabEntry(
                __('Items to synchronize', 'advancedldap'),
                0,
                $item::class,
                "ti ti-adjustments-alt",
            );
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
        if ($item instanceof \AuthLDAP) {
            $instance = new self();
            $instance->showAdvancedSyncForm($item);
        }
        return true;
    }

    /**
     * Show advanced sync configuration form
     *
     * @param \AuthLDAP $authldap AuthLDAP instance
     * @return void
     */
    public function showAdvancedSyncForm(\AuthLDAP $authldap): void
    {
        $id = $authldap->getField('id');

        if (!$authldap->can($id, \READ)) {
            return;
        }

        $available_assets = $this->buildAssetDropdown();
        $current_config = $this->getCurrentConfiguration($id);
        $test_results = $this->handleTestRequest($id, $current_config);

        TemplateRenderer::getInstance()->display('@advancedldap/ldap_sync.html.twig', [
            'authldap' => $authldap,
            'available_assets' => $available_assets,
            'current_config' => $current_config,
            'can_edit' => $authldap->can($id, \UPDATE),
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
        $asset_field_service = $this->asset_field_provider;
        $all_asset_types = $asset_field_service instanceof AssetFieldService ? $asset_field_service->getAllAssetTypes() : [];

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
     * Get current configuration including sync filter data for this AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array<string, mixed>
     */
    private function getCurrentConfiguration(int $authldap_id): array
    {
        $config = [
            'syncfilter_id' => '',
            'filter_name' => '',
            'ldap_connection_filter' => '',
            'ldap_base_dn' => '',
            'asset_type' => '',
            'asset_field' => '',
            'is_active' => 1,
        ];

        // Try to get existing sync filter for this AuthLDAP
        try {
            $sync_filter_service = $this->container->get(\GlpiPlugin\Advancedldap\Services\SyncFilterService::class);
            $filters = $sync_filter_service->getSyncFiltersForAuthLdap($authldap_id);

            if (!empty($filters)) {
                // Use the first active filter found
                $filter = reset($filters);
                $field_mappings = $filter['field_mappings'] ?? [];
                $asset_field = !empty($field_mappings) ? array_key_first($field_mappings) : '';

                $config = array_merge($config, [
                    'syncfilter_id' => $filter['id'] ?? '',
                    'filter_name' => $filter['name'] ?? '',
                    'ldap_connection_filter' => $filter['ldap_filter'] ?? '',
                    'ldap_base_dn' => $filter['base_dn'] ?? '',
                    'asset_type' => $filter['asset_type'] ?? '',
                    'asset_field' => $asset_field,
                    'is_active' => $filter['is_active'] ?? 1,
                ]);
            }
        } catch (\Exception) {
            // If no filter exists or error occurs, keep defaults
        }

        return $config;
    }

    /**
     * Get sync filters for this AuthLDAP instance
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array
     */
    public function getSyncFiltersForAuthLdap(int $authldap_id): array
    {
        $sync_filter_service = $this->container->get(\GlpiPlugin\Advancedldap\Services\SyncFilterService::class);
        return $sync_filter_service->getSyncFiltersForAuthLdap($authldap_id);
    }

    /**
     * Get all available sync filters
     *
     * @return array
     */
    public function getAvailableSyncFilters(): array
    {
        $sync_filter_service = $this->container->get(\GlpiPlugin\Advancedldap\Services\SyncFilterService::class);
        return $sync_filter_service->getAvailableSyncFilters();
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

        $test_results = $this->ldap_test_service->testLdapFilter(
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


}
