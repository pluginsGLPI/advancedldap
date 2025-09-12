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

namespace GlpiPlugin\Advancedldap\Services;

use Exception;
use Glpi\Asset\AssetDefinition;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use GlpiPlugin\Advancedldap\Contracts\ConfigurationInterface;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Factories\AssetFieldProviderFactory;
use Toolbox;

/**
 * Asset field management service
 */
class AssetFieldService implements AssetFieldProviderInterface
{
    private ConfigurationInterface $configuration;
    private DatabaseInterface $database;
    private AssetFieldProviderFactory $factory;

    /**
     * @param ConfigurationInterface $configuration
     * @param DatabaseInterface $database
     * @param AssetFieldProviderFactory $factory
     */
    public function __construct(
        ConfigurationInterface $configuration,
        DatabaseInterface $database,
        AssetFieldProviderFactory $factory,
    ) {
        $this->configuration = $configuration;
        $this->database = $database;
        $this->factory = $factory;
    }

    /**
     * Get available itemtypes for assets
     *
     * @return array<string, string> Array of itemtype => label
     */
    public function getAvailableItemTypes(): array
    {
        $asset_types = $this->getAllAssetTypes();
        $itemtypes = [];

        foreach ($asset_types as $itemtype => $info) {
            $itemtypes[$itemtype] = $info['name'];
        }

        asort($itemtypes);
        return $itemtypes;
    }

    /**
     * Get fields for a specific itemtype
     *
     * @param string $itemtype The itemtype to get fields for
     * @return array<string, string> Array of field_key => field_label
     */
    public function getItemTypeFields(string $itemtype): array
    {
        $provider = $this->factory->createProvider($itemtype);
        return $provider->getItemTypeFields($itemtype);
    }

    /**
     * Get all available asset types in GLPI
     *
     * @return array<string, array> Asset types with metadata
     */
    public function getAllAssetTypes(): array
    {
        $asset_types = [];

        // Get native assets
        $native_itemtypes = $this->getNativeItemTypes();
        $generic_assets_info = $this->getGenericAssets();
        $generic_names = array_column($generic_assets_info, 'name');

        // Process native assets
        foreach ($native_itemtypes as $itemtype) {
            if (!class_exists($itemtype) || !method_exists($itemtype, 'getTypeName')) {
                continue;
            }

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
            } catch (Exception $e) {
                Toolbox::logDebug("Advanced LDAP - Error getting fields for itemtype $itemtype: " . $e->getMessage());
                continue;
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
     * Get native GLPI item types
     *
     * @return array<string> Native item types
     */
    private function getNativeItemTypes(): array
    {
        $asset_types = $this->configuration->getGlpiConfig('asset_types') ?? [];
        $inventory_types = $this->configuration->getGlpiConfig('inventory_types') ?? [];
        $state_types = $this->configuration->getGlpiConfig('state_types') ?? [];

        return array_unique(array_merge($asset_types, $inventory_types, $state_types));
    }

    /**
     * Get generic asset types from AssetDefinition
     *
     * @return array<string, array> Array of generic asset types
     */
    private function getGenericAssets(): array
    {
        if (!class_exists('Glpi\\Asset\\AssetDefinition')) {
            return [];
        }

        try {
            $show_inactive = $this->configuration->get('show_inactive_generic_assets', 0);

            $iterator = $this->database->request([
                'FROM'  => 'glpi_assets_assetdefinitions',
                'WHERE' => $show_inactive ? [] : ['is_active' => 1],
                'ORDER' => 'system_name',
            ]);

            $generic_assets = [];
            foreach ($iterator as $data) {
                if (!empty($data['system_name'])) {
                    $generic_assets['GenericAsset_' . $data['id']] = [
                        'name'                  => $data['label'] ?? $data['name'] ?? $data['system_name'],
                        'asset_definition_id'   => $data['id'],
                        'system_name'           => $data['system_name'],
                    ];
                }
            }

            return $generic_assets;
        } catch (Exception $e) {
            Toolbox::logDebug("Advanced LDAP - Error getting itemtype fields: " . $e->getMessage());
            return [];
        }
    }
}
