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
    private LdapConnectionInterface $ldapConnection;
    private DatabaseInterface $database;
    private AssetFieldProviderInterface $assetFieldProvider;

    /**
     * @param LdapConnectionInterface $ldapConnection
     * @param DatabaseInterface $database
     * @param AssetFieldProviderInterface $assetFieldProvider
     */
    public function __construct(
        LdapConnectionInterface $ldapConnection,
        DatabaseInterface $database,
        AssetFieldProviderInterface $assetFieldProvider,
    ) {
        $this->ldapConnection = $ldapConnection;
        $this->database = $database;
        $this->assetFieldProvider = $assetFieldProvider;
    }

    /**
     * Test LDAP filter and return results
     *
     * @param int    $authldap_id      AuthLDAP ID
     * @param string $base_dn          Base DN for search
     * @param string $filter           LDAP filter
     * @param string $asset_type       Asset type (Computer, Printer, etc.)
     * @param string $asset_field      Asset field to synchronize
     * @return array<string, mixed> Test results
     */
    public function testLdapFilter(
        int $authldap_id,
        string $base_dn,
        string $filter,
        string $asset_type,
        string $asset_field,
    ): array {
        $results = [
            'error' => null,
            'config' => [
                'authldap_name' => '',
                'base_dn' => $base_dn,
                'filter' => $filter,
                'asset_type' => $asset_type,
                'asset_field' => $asset_field,
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

            // Get human-readable field name if asset_type and asset_field are provided
            if (!empty($asset_type) && !empty($asset_field)) {
                $fields = $this->assetFieldProvider->getItemTypeFields($asset_type);
                $results['config']['asset_field'] = $fields[$asset_field] ?? $asset_field;
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
        $connection = $this->ldapConnection->connect($authldap_id);
        if (!$connection) {
            return ['error' => __('Cannot connect to LDAP server', 'advancedldap')];
        }

        $search = $this->ldapConnection->search($connection, $base_dn, $filter);
        if (!$search) {
            $error = sprintf(
                __('LDAP search failed: %s', 'advancedldap'),
                $this->ldapConnection->getError($connection),
            );
            $this->ldapConnection->close($connection);
            return ['error' => $error];
        }

        $entries = $this->ldapConnection->getEntries($connection, $search);
        $this->ldapConnection->close($connection);

        return ['entries' => $entries];
    }

    /**
     * Process LDAP entries for display
     *
     * @param array $entries Raw LDAP entries
     * @param string $filter LDAP filter
     * @param string $asset_type Asset type
     * @param string $asset_field Asset field
     * @return array<int, array> Processed entries
     */
    private function processLdapEntries(
        array $entries,
        string $filter,
        string $asset_type,
        string $asset_field,
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
            );

            $processed_entries[] = $processed_entry;
        }

        return $processed_entries;
    }

    /**
     * Analyze what would happen in GLPI
     *
     * @param string $asset_type Asset type
     * @param string $asset_field Asset field
     * @param array $ldap_attributes LDAP attributes
     * @return array<string, mixed> Impact analysis
     */
    private function analyzeGlpiImpact(string $asset_type, string $asset_field, array $ldap_attributes): array
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
                $impact['message'] = sprintf(
                    __('Asset "%s" exists, field "%s" will be updated', 'advancedldap'),
                    $asset_name,
                    $asset_field,
                );
            } else {
                $impact['exists'] = false;
                $impact['message'] = sprintf(
                    __('Asset "%s" will be created with field "%s"', 'advancedldap'),
                    $asset_name,
                    $asset_field,
                );
            }

        } catch (Exception $e) {
            $impact['message'] = sprintf(__('Analysis error: %s', 'advancedldap'), $e->getMessage());
        }

        return $impact;
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
