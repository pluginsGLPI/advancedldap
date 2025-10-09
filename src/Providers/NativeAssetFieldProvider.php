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
    public function getAvailableItemtypes(): array
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
    public function getItemtypeFields(string $itemtype): array
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

            return $this->formatSearchOptionsAsFields($search_options, $itemtype);
        } catch (Exception $e) {
            Toolbox::logDebug("Advanced LDAP - Error getting native fields for $itemtype: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format search options into field dropdown format
     *
     * @param array<int|string, mixed> $search_options Search options from searchOptions() method
     * @param string $itemtype The itemtype being processed
     * @return array<string, string> Formatted fields array
     */
    private function formatSearchOptionsAsFields(array $search_options, string $itemtype): array
    {
        $fields = [];
        $skip_fields = ['id', 'date_mod', 'date_creation'];

        // Get the main table for this specific itemtype
        $main_table = getTableForItemType($itemtype);


        // Separate main table fields from related table fields for proper priority
        $main_table_fields = [];
        $related_table_fields = [];

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

            if ($table === $main_table) {
                $main_table_fields[] = [
                    'key' => $field_key,
                    'name' => $field_name,
                    'table' => $table,
                ];
            } else {
                $related_table_fields[] = [
                    'key' => $field_key,
                    'name' => $field_name,
                    'table' => $table,
                ];
            }
        }

        // Track field names to detect label duplicates
        $name_counts = [];

        // Count all field names first
        foreach (array_merge($main_table_fields, $related_table_fields) as $field_info) {
            $name = $field_info['name'];
            $name_counts[$name] = ($name_counts[$name] ?? 0) + 1;
        }

        // Process main table fields first (they get priority)
        foreach ($main_table_fields as $field_info) {
            $field_key = $field_info['key'];
            $field_name = $field_info['name'];

            // If this name appears multiple times, qualify the main table field as well
            if ($name_counts[$field_name] > 1) {
                $table_display = $this->getTableDisplayName($field_info['table']);
                $qualified_name = $field_name . ' (' . $table_display . ')';
                $fields[$field_key] = $qualified_name;
            } else {
                $fields[$field_key] = $field_name;
            }
        }

        // Process related table fields
        foreach ($related_table_fields as $field_info) {
            $field_key = $field_info['key'];
            $field_name = $field_info['name'];
            $table = $field_info['table'];

            if (isset($fields[$field_key])) {
                // Collision with main table field key - skip the related field to avoid key duplicates
                continue;
            } elseif ($name_counts[$field_name] > 1) {
                // If this name appears multiple times, qualify it
                $table_display = $this->getTableDisplayName($table);
                $qualified_name = $field_name . ' (' . $table_display . ')';
                $fields[$field_key] = $qualified_name;
            } else {
                $fields[$field_key] = $field_name;
            }
        }

        // Sort alphabetically
        asort($fields);
        return $fields;
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
