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

use Glpi\Application\View\TemplateRenderer;

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
            
            $field_id = $option['id'];
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
    
    /**
     * Render asset dropdown with AJAX update capability
     * 
     * @param string $name Name of the dropdown
     * @param array $params Parameters for the dropdown
     * @return string HTML output
     */
    public static function showAssetDropdown(string $name, array $params = []): string
    {
        $default_params = [
            'value' => 0,
            'display_emptychoice' => true,
            'rand' => mt_rand(),
            'width' => '200px'
        ];
        
        $params = array_merge($default_params, $params);
        $rand = $params['rand'];
        
        // Get available itemtypes
        $itemtypes = self::getAvailableItemTypes();
        
        $out = "<select name='$name' id='dropdown_{$name}_{$rand}' class='form-select'>";
        
        if ($params['display_emptychoice']) {
            $out .= "<option value='0'>" . Dropdown::EMPTY_VALUE . "</option>";
        }
        
        foreach ($itemtypes as $itemtype => $label) {
            $selected = ($params['value'] == $itemtype) ? 'selected' : '';
            $out .= "<option value='$itemtype' $selected>$label</option>";
        }
        
        $out .= "</select>";
        
        return $out;
    }
    
    /**
     * Generate AJAX update code for field dropdown
     * 
     * @param string $asset_dropdown_id ID of the asset dropdown
     * @param string $field_dropdown_id ID of the field dropdown to update
     * @param string $field_dropdown_name Name of the field dropdown
     * @return string JavaScript code for AJAX update
     */
    public static function getAjaxUpdateCode(
        string $asset_dropdown_id, 
        string $field_dropdown_id, 
        string $field_dropdown_name
    ): string {
        global $CFG_GLPI;
        
        $rand = mt_rand();
        $params = [
            'name' => $field_dropdown_name,
            'rand' => $rand,
            'itemtype' => '__VALUE__'
        ];
        
        return Ajax::updateItemOnSelectEvent(
            $asset_dropdown_id,
            $field_dropdown_id,
            $CFG_GLPI['root_doc'] . '/plugins/advancedldap/ajax/getAssetFields.php',
            $params,
            false
        );
    }
}