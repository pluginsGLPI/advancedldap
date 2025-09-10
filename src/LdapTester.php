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

use AuthLDAP;
use GlpiPlugin\Advancedldap\AssetFieldManager;

/**
 * LDAP Filter Tester
 */
class LdapTester
{
    /**
     * Test LDAP filter and return results
     *
     * @param int    $authldap_id      AuthLDAP ID
     * @param string $base_dn          Base DN for search
     * @param string $filter           LDAP filter
     * @param string $asset_type       Asset type (Computer, Printer, etc.)
     * @param string $asset_field      Asset field to synchronize
     * @return array
     */
    public static function testLdapFilter($authldap_id, $base_dn, $filter, $asset_type, $asset_field)
    {
        $results = [
            'error' => null,
            'config' => [
                'authldap_name' => '',
                'base_dn' => $base_dn,
                'filter' => $filter,
                'asset_type' => $asset_type,
                'asset_field' => $asset_field
            ],
            'entries' => []
        ];

        try {
            // Load AuthLDAP configuration
            $authldap = new AuthLDAP();
            if (!$authldap->getFromDB($authldap_id)) {
                $results['error'] = __('AuthLDAP configuration not found', 'advancedldap');
                return $results;
            }

            $results['config']['authldap_name'] = $authldap->getField('name');

            // Get human-readable field name if asset_type and asset_field are provided
            if (!empty($asset_type) && !empty($asset_field)) {
                $fields = AssetFieldManager::getItemTypeFields($asset_type);
                $results['config']['asset_field'] = $fields[$asset_field] ?? $asset_field;
            }

            // Validate required parameters
            if (empty($base_dn)) {
                $results['error'] = __('Base DN is required', 'advancedldap');
                return $results;
            }

            if (empty($filter)) {
                $results['error'] = __('LDAP filter is required', 'advancedldap');
                return $results;
            }

            if (empty($asset_type)) {
                $results['error'] = __('Asset type is required', 'advancedldap');
                return $results;
            }

            // Connect to LDAP
            $ldap_connection = $authldap->connect();
            if (!$ldap_connection) {
                $results['error'] = __('Cannot connect to LDAP server', 'advancedldap');
                return $results;
            }

            // Perform LDAP search
            $search = ldap_search($ldap_connection, $base_dn, $filter);
            if (!$search) {
                $results['error'] = sprintf(
                    __('LDAP search failed: %s', 'advancedldap'),
                    ldap_error($ldap_connection)
                );
                return $results;
            }

            $entries = ldap_get_entries($ldap_connection, $search);
            
            // Process each entry
            for ($i = 0; $i < $entries['count']; $i++) {
                $entry = $entries[$i];
                $processed_entry = [
                    'dn' => $entry['dn'],
                    'attributes' => [],
                    'glpi_impact' => []
                ];

                // Extract relevant attributes
                foreach ($entry as $attr => $values) {
                    if (is_numeric($attr) || $attr === 'count' || $attr === 'dn') {
                        continue;
                    }
                    
                    // Clean up attribute values
                    if (is_array($values) && isset($values['count'])) {
                        unset($values['count']);
                        if (count($values) === 1) {
                            $processed_entry['attributes'][$attr] = $values[0];
                        } else {
                            $processed_entry['attributes'][$attr] = $values;
                        }
                    } else {
                        $processed_entry['attributes'][$attr] = $values;
                    }
                }

                // Analyze GLPI impact
                $processed_entry['glpi_impact'] = self::analyzeGlpiImpact(
                    $asset_type,
                    $asset_field,
                    $processed_entry['attributes']
                );

                $results['entries'][] = $processed_entry;
            }

            ldap_close($ldap_connection);

        } catch (\Exception $e) {
            $results['error'] = sprintf(__('Error: %s', 'advancedldap'), $e->getMessage());
        }

        return $results;
    }

    /**
     * Analyze what would happen in GLPI
     *
     * @param string $asset_type      Asset type
     * @param string $asset_field     Asset field  
     * @param array  $ldap_attributes LDAP attributes
     * @return array
     */
    private static function analyzeGlpiImpact($asset_type, $asset_field, $ldap_attributes)
    {
        $impact = [
            'exists' => false,
            'message' => ''
        ];

        try {
            // Get the asset name from common LDAP attributes
            $asset_name = $ldap_attributes['cn'] ?? $ldap_attributes['name'] ?? null;
            
            if (!$asset_name) {
                $impact['message'] = __('No name found in LDAP attributes (cn or name)', 'advancedldap');
                return $impact;
            }

            // Check if asset class exists
            if (!class_exists($asset_type)) {
                $impact['message'] = sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type);
                return $impact;
            }

            // Try to find existing asset by name
            global $DB;
            $asset_table = getTableForItemType($asset_type);
            
            if (!$asset_table) {
                $impact['message'] = sprintf(__('No table found for asset type %s', 'advancedldap'), $asset_type);
                return $impact;
            }

            $iterator = $DB->request([
                'FROM'  => $asset_table,
                'WHERE' => ['name' => $asset_name],
                'LIMIT' => 1
            ]);

            if (count($iterator) > 0) {
                $impact['exists'] = true;
                $impact['message'] = sprintf(
                    __('Asset "%s" exists, field "%s" will be updated', 'advancedldap'),
                    $asset_name,
                    $asset_field
                );
            } else {
                $impact['exists'] = false;
                $impact['message'] = sprintf(
                    __('Asset "%s" will be created with field "%s"', 'advancedldap'),
                    $asset_name,
                    $asset_field
                );
            }

        } catch (\Exception $e) {
            $impact['message'] = sprintf(__('Analysis error: %s', 'advancedldap'), $e->getMessage());
        }

        return $impact;
    }
}