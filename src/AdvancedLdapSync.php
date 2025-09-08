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
use Glpi\Application\View\TemplateRenderer;

/**
 * Main class for Advanced LDAP Sync functionality
 */
class AdvancedLdapSync extends CommonGLPI
{
    static $rightname = 'config';

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
        if ($item instanceof AuthLDAP) {
            switch ($tabnum) {
                case 1:
                    self::showAdvancedSyncForm($item);
                    break;
            }
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
            return false;
        }

        // Get all available asset types in GLPI
        $asset_types = self::getAllAssetTypes();

        // Separate native and generic assets for dropdown with separators and icons
        $available_assets = [];
        $assets_icons = [];
        
        // Add native assets with separator
        $native_assets = [];
        $generic_assets = [];
        
        foreach ($asset_types as $itemtype => $info) {
            if ($info['type'] === 'native') {
                $native_assets[$itemtype] = $info['name'];
                $assets_icons[$itemtype] = $info['icon'];
            } else {
                $generic_assets[$itemtype] = $info['name'];
                $assets_icons[$itemtype] = $info['icon'];
            }
        }
        
        // Build dropdown array with separators
        if (!empty($native_assets)) {
            $available_assets['native_separator'] = '--- ' . __('Native Assets') . ' ---';
            foreach ($native_assets as $itemtype => $name) {
                $available_assets[$itemtype] = $name;
            }
        }
        
        if (!empty($generic_assets)) {
            $available_assets['generic_separator'] = '--- ' . __('Generic Assets') . ' ---';
            foreach ($generic_assets as $itemtype => $name) {
                $available_assets[$itemtype] = $name;
            }
        }

        // Prepare current configuration (empty for now, will be implemented later)
        $current_config = [
            'is_active' => 0,
            'asset_types' => []
        ];

        // Check if user can edit
        $can_edit = $authldap->can($ID, UPDATE);

        // Use TemplateRenderer to display the form
        TemplateRenderer::getInstance()->display('@advancedldap/ldap_sync.html.twig', [
            'authldap' => $authldap,
            'available_assets' => $available_assets,
            'current_config' => $current_config,
            'can_edit' => $can_edit,
            'sync_elements' => [] // Empty for now, will be populated later
        ]);
    }

    /**
     * Get all available asset types in GLPI
     *
     * @return array
     */
    public static function getAllAssetTypes()
    {
        $asset_types = [];

        // Common GLPI native asset types
        $native_types = [
            'Computer' => ['name' => __('Computer'), 'icon' => 'ti ti-device-laptop'],
            'Monitor' => ['name' => __('Monitor'), 'icon' => 'ti ti-device-desktop'],
            'Software' => ['name' => __('Software'), 'icon' => 'ti ti-app-window'],
            'NetworkEquipment' => ['name' => __('Network equipment'), 'icon' => 'ti ti-router'],
            'Peripheral' => ['name' => __('Device'), 'icon' => 'ti ti-device-gamepad'],
            'Printer' => ['name' => __('Printer'), 'icon' => 'ti ti-printer'],
            'CartridgeItem' => ['name' => __('Cartridge'), 'icon' => 'ti ti-package'],
            'ConsumableItem' => ['name' => __('Consumable'), 'icon' => 'ti ti-box'],
            'Phone' => ['name' => __('Phone'), 'icon' => 'ti ti-phone'],
            'Rack' => ['name' => __('Rack'), 'icon' => 'ti ti-server'],
            'Enclosure' => ['name' => __('Enclosure'), 'icon' => 'ti ti-building-warehouse'],
            'PDU' => ['name' => __('PDU'), 'icon' => 'ti ti-plug'],
            'PassiveDCEquipment' => ['name' => __('Passive equipment'), 'icon' => 'ti ti-device-desktop-analytics'],
            'Unmanaged' => ['name' => __('Unmanaged device'), 'icon' => 'ti ti-question-mark'],
            'Cable' => ['name' => __('Cable'), 'icon' => 'ti ti-line'],
            'User' => ['name' => __('User'), 'icon' => 'ti ti-user'],
            'Group' => ['name' => __('Group'), 'icon' => 'ti ti-users'],
            'Entity' => ['name' => __('Entity'), 'icon' => 'ti ti-building'],
            'Location' => ['name' => __('Location'), 'icon' => 'ti ti-map-pin'],
            'Supplier' => ['name' => __('Supplier'), 'icon' => 'ti ti-truck-delivery'],
            'Contact' => ['name' => __('Contact'), 'icon' => 'ti ti-address-book'],
            'Contract' => ['name' => __('Contract'), 'icon' => 'ti ti-file-text'],
            'Document' => ['name' => __('Document'), 'icon' => 'ti ti-file'],
        ];

        // Add native asset types
        foreach ($native_types as $itemtype => $info) {
            if (class_exists($itemtype)) {
                $asset_types[$itemtype] = [
                    'name' => $info['name'],
                    'icon' => $info['icon'],
                    'type' => 'native'
                ];
            }
        }

        // Get generic asset types (AssetDefinition)
        $generic_assets = self::getGenericAssets();
        
        foreach ($generic_assets as $itemtype => $info) {
            $asset_types[$itemtype] = [
                'name' => $info['name'],
                'icon' => $info['icon'],
                'type' => 'generic'
            ];
        }

        return $asset_types;
    }

    /**
     * Get generic (non-native) asset types
     *
     * @return array Array of generic asset types with their properties
     */
    public static function getGenericAssets()
    { 
        $generic_assets = [];

        // Check if AssetDefinition class exists (GLPI 10.0+)
        if (!class_exists('Glpi\\Asset\\AssetDefinition')) {
            return $generic_assets;
        }

        try {            
            // Use DB query instead of find() to get active asset definitions
            global $DB;
            
            if (!$DB) {
                return $generic_assets;
            }
            
            $iterator = $DB->request([
                'FROM'  => 'glpi_assets_assetdefinitions',
                'WHERE' => ['is_active' => 1],
                'ORDER' => 'system_name'
            ]);

            foreach ($iterator as $data) {
                $system_name = $data['system_name'] ?? '';
                
                if (!empty($system_name)) {
                    // Use system_name as display name if 'name' field doesn't exist
                    $display_name = isset($data['name']) ? $data['name'] : $data['system_name'];
                    
                    // For generic assets, we use a generic identifier based on the asset definition ID
                    $generic_key = 'GenericAsset_' . $data['id'];
                    
                    $generic_assets[$generic_key] = [
                        'name' => $display_name,
                        'icon' => 'ti ' . $data['icon'] ?? 'ti ti-package',
                        'asset_definition_id' => $data['id'],
                        'system_name' => $system_name
                    ];
                }
            }
            
        } catch (\Exception) {
            // Silent fail - if there's an error accessing AssetDefinition, just return empty array
        }

        return $generic_assets;
    }
}