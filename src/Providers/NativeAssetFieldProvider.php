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

namespace GlpiPlugin\Advancedldap\Providers;

use Exception;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use Toolbox;

/**
 * Field provider for native GLPI assets
 */
class NativeAssetFieldProvider implements AssetFieldProviderInterface
{
    /**
     * Get available itemtypes for assets (not implemented for individual providers)
     *
     * @return array<string, string> Array of itemtype => label
     */
    public function getAvailableItemTypes(): array
    {
        // This method is implemented at the service level
        return [];
    }

    /**
     * Get fields for a native GLPI itemtype
     *
     * @param string $itemtype Class name (e.g. 'Computer')
     * @return array<string, string> Array of field_key => field_label
     */
    public function getItemTypeFields(string $itemtype): array
    {
        // Validate standard GLPI itemtype classes
        if (!class_exists($itemtype)) {
            return [];
        }

        // Ensure the class inherits from CommonDBTM
        if (!is_subclass_of($itemtype, 'CommonDBTM')) {
            return [];
        }

        // Extract fields from GLPI search options
        try {
            $item = new $itemtype();
            $search_options = $item->searchOptions();
            return $this->formatSearchOptionsAsFields($search_options);
        } catch (Exception $e) {
            Toolbox::logDebug("Advanced LDAP - Error getting native fields for $itemtype: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format search options into field dropdown format
     *
     * @param array $search_options Search options from searchOptions() method
     * @return array<string, string> Formatted fields array
     */
    private function formatSearchOptionsAsFields(array $search_options): array
    {
        $fields = [];
        $skip_fields = ['id', 'date_mod', 'date_creation'];

        foreach ($search_options as $option) {
            // Skip non-field options
            if (!isset($option['field']) || empty($option['field'])) {
                continue;
            }

            // Skip technical fields
            if (in_array($option['field'], $skip_fields)) {
                continue;
            }

            $field_name = $option['name'];
            $field_key = $option['field'];
            $table = $option['table'] ?? 'unknown';

            // Create unique key to avoid field collisions between tables
            $unique_key = $field_key;
            if (isset($fields[$field_key])) {
                // For collisions, prefer main table fields or create qualified keys
                if ($this->isMainTableField($table)) {
                    // Keep the main table field with original key
                    $unique_key = $field_key;
                } else {
                    // Create qualified key for non-main table fields
                    $table_display = $this->getTableDisplayName($table);
                    $table_key = strtolower(str_replace([' ', '-'], '_', $table_display));
                    $unique_key = $field_key . '_' . $table_key;
                    $field_name = $field_name . ' (' . $table_display . ')';
                }
            }

            // Use clean field name as label
            $fields[$unique_key] = $field_name;
        }

        // Sort alphabetically
        asort($fields);
        return $fields;
    }

    /**
     * Check if field belongs to the main asset table
     *
     * @param string $table Table name
     * @return bool
     */
    private function isMainTableField(string $table): bool
    {
        global $CFG_GLPI;

        try {
            // Get all asset types from GLPI configuration
            $asset_types = $CFG_GLPI['asset_types'] ?? [];
            $inventory_types = $CFG_GLPI['inventory_types'] ?? [];
            $state_types = $CFG_GLPI['state_types'] ?? [];

            // Convert itemtypes to table names
            $main_tables = [];
            foreach (array_merge($asset_types, $inventory_types, $state_types) as $itemtype) {
                if (class_exists($itemtype)) {
                    $main_tables[] = getTableForItemType($itemtype);
                }
            }

            // Add generic assets if they exist
            if (class_exists('Glpi\\Asset\\AssetDefinition')) {
                // Generic assets follow pattern glpi_assets_assets_{id}
                // For now, we'll consider any glpi_assets_* as main tables
                if (str_starts_with($table, 'glpi_assets_')) {
                    return true;
                }
            }

            return in_array($table, $main_tables);

        } catch (Exception $e) {
            Toolbox::logDebug("Advanced LDAP - Error checking main table field for $table: " . $e->getMessage());

            // Fallback to original hardcoded list
            $fallback_tables = [
                'glpi_computers', 'glpi_monitors', 'glpi_printers', 'glpi_peripherals',
                'glpi_phones', 'glpi_networkequipments', 'glpi_softwares',
            ];

            return in_array($table, $fallback_tables);
        }
    }

    /**
     * Get display name for table using GLPI metadata
     *
     * @param string $table Full table name
     * @return string
     */
    private function getTableDisplayName(string $table): string
    {
        try {
            // Use GLPI's native function to get itemtype from table
            $itemtype = getItemTypeForTable($table);
            if ($itemtype && class_exists($itemtype)) {
                return $itemtype::getTypeName(1);
            }
        } catch (Exception $e) {
            Toolbox::logDebug("Advanced LDAP - Error getting display name for table $table: " . $e->getMessage());
        }

        // Fallback: clean table name
        $name = str_replace('glpi_', '', $table);
        $name = str_replace('_', ' ', $name);
        return ucwords($name);
    }
}
