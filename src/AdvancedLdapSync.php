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
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap;

use CommonGLPI;
use AuthLDAP;
use Config;
use Glpi\Application\View\TemplateRenderer;

/**
 * Main class for Advanced LDAP Sync functionality
 */
class AdvancedLdapSync extends CommonGLPI
{
    public static $rightname = 'config';

    /**
     * Get tab name for AuthLDAP item
     *
     * @param CommonGLPI $item         Item for which tab is displayed
     * @param int        $withtemplate Template mode
     * @return array
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $item->can($item->getID(), READ)) {
            return [
                1 => self::createTabEntry(__('Items to synchronize', 'advancedldap'), 0, $item::class, "ti ti-adjustments-alt"),
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
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $tabnum == 1) {
            self::showAdvancedSyncForm($item);
        }
        return true;
    }

    /**
     * Show advanced sync configuration form
     *
     * @param AuthLDAP $authldap AuthLDAP instance
     * @return void
     */
    public static function showAdvancedSyncForm(AuthLDAP $authldap)
    {
        $ID = $authldap->getField('id');

        if (!$authldap->can($ID, READ)) {
            return;
        }

        $available_assets = self::buildAssetDropdown();

        $current_config = [
            'show_inactive_generic_assets' => self::getConfigValue('show_inactive_generic_assets', 0),
            'ldap_connection_filter' => self::getConfigValue('ldap_connection_filter', ''),
            'ldap_base_dn' => self::getConfigValue('ldap_base_dn', ''),
        ];

        // Check if we need to run a test
        $test_results = null;
        if (isset($_GET['test_ldap']) && $_GET['test_ldap'] == '1') {
            $test_base_dn = $_GET['test_base_dn'] ?? '';
            $test_filter = $_GET['test_filter'] ?? '';
            $test_asset_type = $_GET['test_asset_type'] ?? '';
            $test_asset_field = $_GET['test_asset_field'] ?? '';
            
            if (!empty($test_base_dn) && !empty($test_filter)) {
                $test_results = LdapTester::testLdapFilter(
                    $ID,
                    $test_base_dn,
                    $test_filter,
                    $test_asset_type,
                    $test_asset_field
                );
                
                // Repopulate form fields with test values
                $current_config['ldap_base_dn'] = $test_base_dn;
                $current_config['ldap_connection_filter'] = $test_filter;
            }
        }

        TemplateRenderer::getInstance()->display('@advancedldap/ldap_sync.html.twig', [
            'authldap' => $authldap,
            'available_assets' => $available_assets,
            'current_config' => $current_config,
            'can_edit' => $authldap->can($ID, UPDATE),
            'test_results' => $test_results,
        ]);
    }

    /**
     * Build dropdown array with asset types separated by categories
     *
     * @return array
     */
    private static function buildAssetDropdown()
    {
        $asset_types = self::getAllAssetTypes();
        $available_assets = [];

        $native_assets = array_filter($asset_types, fn($info) => $info['type'] === 'native');
        $generic_assets = array_filter($asset_types, fn($info) => $info['type'] === 'generic');

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
     * Get all available asset types in GLPI
     *
     * @return array
     */
    private static function getAllAssetTypes()
    {
        global $CFG_GLPI;
        $asset_types = [];

        // Collect native asset types from GLPI configuration
        $native_itemtypes = array_unique(array_merge(
            $CFG_GLPI['asset_types'] ?? [],
            $CFG_GLPI['inventory_types'] ?? [],
            $CFG_GLPI['state_types'] ?? [],
        ));

        // Get generic assets info to avoid duplicates
        $show_inactive = self::getConfigValue('show_inactive_generic_assets', 0);
        $generic_assets_info = self::getGenericAssets($show_inactive);
        $generic_names = array_column($generic_assets_info, 'name');

        // Process native assets
        foreach ($native_itemtypes as $itemtype) {
            // Check if class exists and has getTypeName method
            if (class_exists($itemtype) && method_exists($itemtype, 'getTypeName')) {
                try {
                    $display_name = $itemtype::getTypeName(1);

                    // Skip if this is actually a generic asset to avoid duplicates
                    if (in_array($display_name, $generic_names)) {
                        continue;
                    }

                    $asset_types[$itemtype] = [
                        'name' => $display_name,
                        'type' => 'native',
                    ];
                } catch (\Exception) {
                    // Skip faulty classes
                    continue;
                }
            }
        }

        // Add generic assets
        foreach ($generic_assets_info as $itemtype => $info) {
            $asset_types[$itemtype] = [
                'name' => $info['name'],
                'type' => 'generic',
            ];
        }

        return $asset_types;
    }


    /**
     * Get generic asset types from AssetDefinition
     *
     * @param int $show_inactive Whether to include inactive assets
     * @return array Array of generic asset types
     */
    private static function getGenericAssets($show_inactive = 0)
    {
        // Check if AssetDefinition class exists (GLPI 10.0+)
        if (!class_exists('Glpi\\Asset\\AssetDefinition')) {
            return [];
        }

        try {
            global $DB;

            // Query asset definitions table with optional filter for active assets only
            $iterator = $DB->request([
                'FROM'  => 'glpi_assets_assetdefinitions',
                'WHERE' => $show_inactive ? [] : ['is_active' => 1], // Filter by active status if requested
                'ORDER' => 'system_name',
            ]);

            $generic_assets = [];
            foreach ($iterator as $data) {
                // Only process assets with a valid system name
                if (!empty($data['system_name'])) {
                    // Build asset entry with fallback for display name
                    $generic_assets['GenericAsset_' . $data['id']] = [
                        'name'                  => $data['label'] ?? $data['name'] ?? $data['system_name'], // Priority: label > name > system_name
                        'asset_definition_id'   => $data['id'],
                        'system_name'           => $data['system_name'],
                    ];
                }
            }

            return $generic_assets;
        } catch (\Exception) {
            // Return empty array on any database or processing error
            return [];
        }
    }

    /**
     * Update plugin configuration
     *
     * @param array $config Configuration values to update
     * @return bool
     */
    public static function updateConfig($config)
    {
        return Config::setConfigurationValues('plugin:Advancedldap', $config);
    }

    /**
     * Get plugin configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public static function getConfigValue($key, $default = null)
    {
        return Config::getConfigurationValue('plugin:Advancedldap', $key, $default);
    }
}
