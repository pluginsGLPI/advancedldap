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
     * Get available itemtypes for assets (inspired by AdvancedLdapSync::getAllAssetTypes)
     * 
     * @return array Array of itemtype => label
     */
    public static function getAvailableItemTypes(): array
    {
        // Use reflection to access the private getAllAssetTypes method from AdvancedLdapSync
        $reflection = new ReflectionClass('AdvancedLdapSync');
        $method     = $reflection->getMethod('getAllAssetTypes');
        $method->setAccessible(true);
        
        $asset_types_full = $method->invoke(null);
        
        // Convert from ['itemtype' => ['name' => ..., 'type' => ...]] 
        // to ['itemtype' => 'name'] format
        $itemtypes = [];
        foreach ($asset_types_full as $itemtype => $info) {
            $itemtypes[$itemtype] = $info['name'];
        }
        
        // Sort by label
        asort($itemtypes);
        
        return $itemtypes;
    }
    
    /**
     * Get fields for a specific itemtype
     * 
     * @param string $itemtype The class name of the item or generic asset identifier
     * @return array Array of field_key => field_label
     */
    public static function getItemTypeFields(string $itemtype): array
    {
        // Check if it's a generic asset (format: GenericAsset_ID)
        if (str_starts_with($itemtype, 'GenericAsset_')) {
            $asset_definition_id = (int)str_replace('GenericAsset_', '', $itemtype);
            return self::getGenericAssetFields($asset_definition_id);
        }
        
        // Handle regular itemtypes
        if (!class_exists($itemtype)) {
            return [];
        }
        
        if (!is_subclass_of($itemtype, 'CommonDBTM')) {
            return [];
        }
        
        try {
            $item = new $itemtype();
            $search_options = $item->searchOptions();
            
            return self::formatSearchOptionsAsFields($search_options);
        } catch (Exception) {
            return [];
        }
    }
    
    /**
     * Format search options into field dropdown format
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
            // 1. Get the asset definition from glpi_assets_assetdefinitions
            $definition = new AssetDefinition();
            if (!$definition->getFromDB($asset_definition_id)) {
                return [];
            }
            
            // 2. Get all available fields (core + custom)
            $all_fields = $definition->getAllFields();
            
            // 3. Get fields display configuration from JSON fields_display
            $fields_display = $definition->getDecodedFieldsField();
            
            // Create a map of field_key => field_options for quick lookup
            $field_options_map = [];
            foreach ($fields_display as $field_config) {
                $field_options_map[$field_config['key']] = $field_config['field_options'] ?? [];
            }
            
            $fields = [];
            foreach ($all_fields as $field_key => $field_info) {
                // 4. Skip fields that are marked as hidden (field_options.hidden = true)
                if (isset($field_options_map[$field_key]['hidden']) && $field_options_map[$field_key]['hidden'] === true) {
                    continue;
                }
                
                // Skip technical fields
                $skip_fields = ['id', 'date_mod', 'date_creation', 'assets_assetdefinitions_id'];
                if (in_array($field_key, $skip_fields)) {
                    continue;
                }
                
                $field_label = $field_info['text'];
                
                // Add indicator for custom fields (stored in custom_fields JSON)
                if (str_starts_with($field_key, 'custom_')) {
                    $field_label .= ' (Custom)';
                }
                
                // Use clean field label
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