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
        $method = $reflection->getMethod('getAllAssetTypes');
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
     * @param string $itemtype The class name of the item
     * @return array Array of field_key => field_label
     */
    public static function getItemTypeFields(string $itemtype): array
    {
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
            $field_key = $option['field'];
            
            // Create meaningful label
            $label = sprintf('%s (%s)', $field_name, $field_key);
            $fields[$field_key] = $label;
        }
        
        // Sort alphabetically
        asort($fields);
        
        return $fields;
    }
}