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

            // Use clean field name as label
            $fields[$field_key] = $field_name;
        }

        // Sort alphabetically
        asort($fields);
        return $fields;
    }
}
