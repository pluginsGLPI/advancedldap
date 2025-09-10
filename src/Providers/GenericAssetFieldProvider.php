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

namespace GlpiPlugin\Advancedldap\Providers;

use Exception;
use Glpi\Asset\AssetDefinition;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;

/**
 * Field provider for generic assets
 */
class GenericAssetFieldProvider implements AssetFieldProviderInterface
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
     * Get fields for a generic asset by itemtype
     *
     * @param string $itemtype Generic asset itemtype (format: GenericAsset_ID)
     * @return array<string, string> Array of field_key => field_label
     */
    public function getItemTypeFields(string $itemtype): array
    {
        // Extract the asset definition ID from the itemtype string
        $asset_definition_id = (int) str_replace('GenericAsset_', '', $itemtype);
        return $this->getGenericAssetFields($asset_definition_id);
    }

    /**
     * Get fields for a generic asset by definition ID
     *
     * @param int $asset_definition_id The AssetDefinition ID
     * @return array<string, string> Array of field_key => field_label
     */
    private function getGenericAssetFields(int $asset_definition_id): array
    {
        try {
            // Load asset definition from database
            $definition = new AssetDefinition();
            if (!$definition->getFromDB($asset_definition_id)) {
                return [];
            }

            // Get all fields (native + custom)
            $all_fields = $definition->getAllFields();

            // Load field visibility configuration
            $fields_display = $definition->getDecodedFieldsField();

            // Build lookup map for field options
            $field_options_map = [];
            foreach ($fields_display as $field_config) {
                $field_options_map[$field_config['key']] = $field_config['field_options'] ?? [];
            }

            $fields = [];
            $skip_fields = ['id', 'date_mod', 'date_creation', 'assets_assetdefinitions_id'];

            foreach ($all_fields as $field_key => $field_info) {
                // Skip fields not in display configuration (disabled)
                if (!isset($field_options_map[$field_key])) {
                    continue;
                }

                // Skip fields explicitly marked as hidden
                if (isset($field_options_map[$field_key]['hidden']) && $field_options_map[$field_key]['hidden'] === true) {
                    continue;
                }

                // Skip technical fields
                if (in_array($field_key, $skip_fields)) {
                    continue;
                }

                $field_label = $field_info['text'];

                // Mark custom fields with indicator
                if (str_starts_with($field_key, 'custom_')) {
                    $field_label .= ' (Custom)';
                }

                // Store clean field label
                $fields[$field_key] = $field_label;
            }

            // Sort alphabetically
            asort($fields);
            return $fields;

        } catch (Exception $e) {
            error_log("Error getting generic asset fields for definition ID $asset_definition_id: " . $e->getMessage());
            return [];
        }
    }
}
