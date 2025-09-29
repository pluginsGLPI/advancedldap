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

use AuthLDAP;
use Exception;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;

/**
 * LDAP filter testing service
 */
class LdapTestService
{
    private LdapConnectionInterface $ldap_connection;
    private DatabaseInterface $database;
    private AssetFieldProviderInterface $asset_field_provider;

    /**
     * @param LdapConnectionInterface $ldap_connection
     * @param DatabaseInterface $database
     * @param AssetFieldProviderInterface $asset_field_provider
     */
    public function __construct(
        LdapConnectionInterface $ldap_connection,
        DatabaseInterface $database,
        AssetFieldProviderInterface $asset_field_provider,
    ) {
        $this->ldap_connection = $ldap_connection;
        $this->database = $database;
        $this->asset_field_provider = $asset_field_provider;
    }

    /**
     * Test LDAP filter and return results
     *
     * @param int    $authldap_id      AuthLDAP ID
     * @param string $base_dn          Base DN for search
     * @param string $filter           LDAP filter
     * @param string $asset_type       Asset type (Computer, Printer, etc.)
     * @param string $asset_field      Asset field to synchronize (legacy)
     * @param array  $field_mappings   Field mappings array (new system)
     * @return array<string, mixed> Test results
     */
    public function testLdapFilter(
        int $authldap_id,
        string $base_dn,
        string $filter,
        string $asset_type,
        string $asset_field = '',
        array $field_mappings = [],
    ): array {
        $results = [
            'error' => null,
            'config' => [
                'authldap_name' => '',
                'base_dn' => $base_dn,
                'filter' => $filter,
                'asset_type' => $asset_type,
                'asset_field' => $asset_field,
                'field_mappings' => $field_mappings,
            ],
            'entries' => [],
        ];

        try {
            // Load AuthLDAP configuration
            $authldap = new AuthLDAP();
            if (!$authldap->getFromDB($authldap_id)) {
                $results['error'] = __('AuthLDAP configuration not found', 'advancedldap');
                return $results;
            }

            $results['config']['authldap_name'] = $authldap->getField('name');

            // Get human-readable field names
            if (!empty($asset_type)) {
                $fields = $this->asset_field_provider->getItemTypeFields($asset_type);

                // Handle new field mappings system
                if (!empty($field_mappings)) {
                    $readable_mappings = [];
                    foreach ($field_mappings as $glpi_field => $ldap_attribute) {
                        $readable_mappings[$glpi_field] = $fields[$glpi_field] ?? $glpi_field;
                    }
                    $results['config']['field_mappings_readable'] = $readable_mappings;
                }

                // Legacy support for single asset_field
                if (!empty($asset_field)) {
                    $results['config']['asset_field'] = $fields[$asset_field] ?? $asset_field;
                }
            }

            $validation_error = $this->validateParameters($base_dn, $filter, $asset_type);
            if ($validation_error) {
                $results['error'] = $validation_error;
                return $results;
            }

            $ldap_entries = $this->performLdapSearch($authldap_id, $base_dn, $filter);
            if (isset($ldap_entries['error'])) {
                $results['error'] = $ldap_entries['error'];
                return $results;
            }

            $results['entries'] = $this->processLdapEntries(
                $ldap_entries['entries'],
                $filter,
                $asset_type,
                $asset_field,
                $field_mappings,
            );

        } catch (Exception $e) {
            $results['error'] = sprintf(__('Error: %s', 'advancedldap'), $e->getMessage());
        }

        return $results;
    }


    /**
     * Validate required parameters
     *
     * @param string $base_dn Base DN
     * @param string $filter LDAP filter
     * @param string $asset_type Asset type
     * @return string|null Error message or null if valid
     */
    private function validateParameters(string $base_dn, string $filter, string $asset_type): ?string
    {
        if (empty($base_dn)) {
            return __('Base DN is required', 'advancedldap');
        }

        if (empty($filter)) {
            return __('LDAP filter is required', 'advancedldap');
        }

        if (empty($asset_type)) {
            return __('Asset type is required', 'advancedldap');
        }

        return null;
    }

    /**
     * Perform LDAP search
     *
     * @param int $authldap_id AuthLDAP ID
     * @param string $base_dn Base DN
     * @param string $filter LDAP filter
     * @return array<string, mixed> Search results or error
     */
    private function performLdapSearch(int $authldap_id, string $base_dn, string $filter): array
    {
        $connection = $this->ldap_connection->connect($authldap_id);
        if (!$connection) {
            return ['error' => __('Cannot connect to LDAP server', 'advancedldap')];
        }

        $search = $this->ldap_connection->search($connection, $base_dn, $filter);
        if (!$search) {
            $error = sprintf(
                __('LDAP search failed: %s', 'advancedldap'),
                $this->ldap_connection->getError($connection),
            );
            $this->ldap_connection->close($connection);
            return ['error' => $error];
        }

        $entries = $this->ldap_connection->getEntries($connection, $search);
        $this->ldap_connection->close($connection);

        return ['entries' => $entries];
    }

    /**
     * Process LDAP entries for display
     *
     * @param array $entries Raw LDAP entries
     * @param string $filter LDAP filter
     * @param string $asset_type Asset type
     * @param string $asset_field Asset field (legacy)
     * @param array $field_mappings Field mappings array (new system)
     * @return array<int, array> Processed entries
     */
    private function processLdapEntries(
        array $entries,
        string $filter,
        string $asset_type,
        string $asset_field,
        array $field_mappings = [],
    ): array {
        $processed_entries = [];
        $target_attribute = $this->extractAttributeFromFilter($filter);

        for ($i = 0; $i < $entries['count']; $i++) {
            $entry = $entries[$i];
            $processed_entry = [
                'dn' => $entry['dn'],
                'attributes' => [],
                'glpi_impact' => [],
            ];

            // Determine attributes to show
            $attributes_to_show = ['cn', 'name'];
            if ($target_attribute) {
                $attributes_to_show[] = $target_attribute;
            }

            // Add attributes from field mappings
            if (!empty($field_mappings)) {
                foreach ($field_mappings as $glpi_field => $ldap_attribute) {
                    $attributes_to_show[] = $ldap_attribute;
                }
            }

            $attributes_to_show = array_unique($attributes_to_show);

            // Process attributes
            foreach ($entry as $attr => $values) {
                if (is_numeric($attr) || $attr === 'count' || $attr === 'dn') {
                    continue;
                }

                if (!in_array($attr, $attributes_to_show)) {
                    continue;
                }

                // Clean up attribute values
                if (is_array($values) && isset($values['count'])) {
                    unset($values['count']);
                    $processed_entry['attributes'][$attr] = count($values) === 1 ? $values[0] : $values;
                } else {
                    $processed_entry['attributes'][$attr] = $values;
                }

                // Mark target attribute
                if ($attr === $target_attribute) {
                    $processed_entry['target_attribute'] = $attr;
                }
            }

            // Analyze GLPI impact
            $processed_entry['glpi_impact'] = $this->analyzeGlpiImpact(
                $asset_type,
                $asset_field,
                $processed_entry['attributes'],
                $field_mappings,
            );

            $processed_entries[] = $processed_entry;
        }

        return $processed_entries;
    }

    /**
     * Analyze what would happen in GLPI
     *
     * @param string $asset_type Asset type
     * @param string $asset_field Asset field (legacy)
     * @param array $ldap_attributes LDAP attributes
     * @param array $field_mappings Field mappings array (new system)
     * @return array<string, mixed> Impact analysis
     */
    private function analyzeGlpiImpact(string $asset_type, string $asset_field, array $ldap_attributes, array $field_mappings = []): array
    {
        $impact = [
            'exists' => false,
            'message' => '',
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

            // Get asset table
            $asset_table = $this->database->getTableForItemType($asset_type);
            if (!$asset_table) {
                $impact['message'] = sprintf(__('No table found for asset type %s', 'advancedldap'), $asset_type);
                return $impact;
            }

            // Check if asset exists
            $iterator = $this->database->request([
                'FROM'  => $asset_table,
                'WHERE' => ['name' => $asset_name],
                'LIMIT' => 1,
            ]);

            if (count($iterator) > 0) {
                $impact['exists'] = true;

                // Build message with all fields that will be synchronized
                $fields_to_sync = $this->buildFieldsSyncMessage($field_mappings, $asset_field);
                $impact['message'] = sprintf(
                    __('Asset "%s" exists, fields will be updated: %s', 'advancedldap'),
                    $asset_name,
                    $fields_to_sync,
                );
            } else {
                $impact['exists'] = false;

                // Build message with all fields that will be created
                $fields_to_sync = $this->buildFieldsSyncMessage($field_mappings, $asset_field);
                $impact['message'] = sprintf(
                    __('Asset "%s" will be created with fields: %s', 'advancedldap'),
                    $asset_name,
                    $fields_to_sync,
                );
            }

        } catch (Exception $e) {
            $impact['message'] = sprintf(__('Analysis error: %s', 'advancedldap'), $e->getMessage());
        }

        return $impact;
    }

    /**
     * Build human-readable message for fields synchronization
     *
     * @param array $field_mappings Field mappings array
     * @param string $asset_field Legacy single field
     * @return string Human-readable fields list
     */
    private function buildFieldsSyncMessage(array $field_mappings, string $asset_field): string
    {
        if (!empty($field_mappings)) {
            // Use new field mappings system
            $field_names = [];
            foreach ($field_mappings as $glpi_field => $ldap_attribute) {
                // Get human-readable GLPI field name
                $readable_name = $this->getHumanReadableFieldName($glpi_field);
                $field_names[] = sprintf('%s ← %s', $readable_name, $ldap_attribute);
            }
            return implode(', ', $field_names);
        } elseif (!empty($asset_field)) {
            // Legacy single field
            $readable_name = $this->getHumanReadableFieldName($asset_field);
            return $readable_name;
        } else {
            return __('No fields configured', 'advancedldap');
        }
    }

    /**
     * Get human-readable field name
     *
     * @param string $field_name Technical field name
     * @return string Human-readable name
     */
    private function getHumanReadableFieldName(string $field_name): string
    {
        $field_translations = [
            'name' => __('Name'),
            'serial' => __('Serial number'),
            'comment' => __('Comments'),
            'locations_id' => __('Location'),
            'otherserial' => __('Inventory number'),
            'contact' => __('Contact'),
            'contact_num' => __('Contact number'),
            'users_id_tech' => __('Technician in charge'),
            'groups_id_tech' => __('Group in charge'),
            'model' => __('Model'),
            'brand' => __('Brand'),
            'uuid' => __('UUID'),
        ];

        return $field_translations[$field_name] ?? $field_name;
    }

    /**
     * Extract the target attribute from LDAP filter
     *
     * @param string $filter LDAP filter
     * @return string|null The attribute being searched for
     */
    private function extractAttributeFromFilter(string $filter): ?string
    {
        // Look for pattern like (attributeName=*) or (attributeName=value)
        // Skip objectClass as it's structural
        if (preg_match_all('/\(([a-zA-Z][a-zA-Z0-9]*)\s*=/', $filter, $matches)) {
            foreach ($matches[1] as $attr) {
                if (strtolower($attr) !== 'objectclass') {
                    return $attr;
                }
            }
        }
        return null;
    }
}
