<?php

/**
 * ---------------------------------------------------------------------
 *
 * Advanced LDAP Plugin for GLPI
 *
 * @copyright 2024
 * @license   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap;

use ReflectionClass;
use Exception;
use Glpi\Asset\AssetDefinition;

/**
 * Asset Field Manager
 *
 * Manages the retrieval and formatting of asset fields for dropdown population
 */
class AssetFieldManager
{
    /**
     * Get available itemtypes for assets
     *
     * @return array Array of itemtype => label
     */
    public static function getAvailableItemTypes(): array
    {
        // Access AdvancedLdapSync::getAllAssetTypes() via reflection
        $reflection = new ReflectionClass('AdvancedLdapSync');
        $method     = $reflection->getMethod('getAllAssetTypes');
        $method->setAccessible(true);

        $asset_types_full = $method->invoke(null);

        // Extract only the names from the full asset types data
        $itemtypes = [];
        foreach ($asset_types_full as $itemtype => $info) {
            $itemtypes[$itemtype] = $info['name'];
        }

        // Sort alphabetically by display name
        asort($itemtypes);

        return $itemtypes;
    }

    /**
     * Get fields for a specific itemtype or generic asset
     *
     * @param string $itemtype Class name (e.g. 'Computer') or generic asset ID (e.g. 'GenericAsset_123')
     * @return array Array of field_key => field_label
     */
    public static function getItemTypeFields(string $itemtype): array
    {
        // Handle generic assets (format: GenericAsset_ID)
        if (str_starts_with($itemtype, 'GenericAsset_')) {
            // Extract the asset definition ID from the itemtype string
            $asset_definition_id = (int) str_replace('GenericAsset_', '', $itemtype);
            return self::getGenericAssetFields($asset_definition_id);
        }

        // Validate standard GLPI itemtype classes
        // Check if the class exists in the system
        if (!class_exists($itemtype)) {
            return [];
        }

        // Ensure the class inherits from CommonDBTM (GLPI base class for database objects)
        if (!is_subclass_of($itemtype, 'CommonDBTM')) {
            return [];
        }

        // Extract fields from GLPI search options
        try {
            // Instantiate the itemtype class
            $item = new $itemtype();
            // Get all search options which contain field definitions
            $search_options = $item->searchOptions();

            // Format search options into a usable field array
            return self::formatSearchOptionsAsFields($search_options);
        } catch (Exception) {
            // Return empty array if instantiation or method call fails
            return [];
        }
    }

    /**
     * Format search options into field dropdown format
     *
     * Note: searchOptions() returns ALL available fields for an itemtype, including:
     * - Fields from main table and related tables (manufacturer, model, location, etc.)
     * - Fields only visible in specific tabs or with certain user rights
     * - Calculated/virtual fields used for search and reporting
     * This explains why more fields appear in dropdown than in the standard UI interface.
     *
     * @param array $search_options Search options from searchOptions() method
     * @return array Formatted fields array
     */
    private static function formatSearchOptionsAsFields(array $search_options): array
    {
        $fields = [];

        foreach ($search_options as $option) {
            // Skip non-field options
            if (!isset($option['field']) || empty($option['field'])) {
                continue;
            }

            // Skip technical fields
            $skip_fields = ['id', 'date_mod', 'date_creation'];
            if (in_array($option['field'], $skip_fields)) {
                continue;
            }

            $field_name = $option['name'];
            $field_key  = $option['field'];

            // Use clean field name as label
            $fields[$field_key] = $field_name;
        }

        // Sort alphabetically
        asort($fields);

        return $fields;
    }

    /**
     * Get fields for a generic asset by definition ID
     *
     * @param int $asset_definition_id The AssetDefinition ID
     * @return array Array of field_key => field_label
     */
    private static function getGenericAssetFields(int $asset_definition_id): array
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
                $skip_fields = ['id', 'date_mod', 'date_creation', 'assets_assetdefinitions_id'];
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
