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
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use Toolbox;

/**
 * LDAP synchronization service for production use
 *
 * This service handles the actual synchronization of LDAP data to GLPI assets.
 * Separated from LdapTestService which is only for testing and preview.
 */
class LdapSyncService
{
    private LdapConnectionInterface $ldap_connection;
    private AssetCreationService $asset_creation_service;
    private AssetTypeClassifier $asset_type_classifier;
    private ?LdapInventoryService $ldap_inventory_service = null;

    /**
     * @param LdapConnectionInterface $ldap_connection
     * @param AssetCreationService $asset_creation_service
     * @param AssetTypeClassifier $asset_type_classifier
     */
    public function __construct(
        LdapConnectionInterface $ldap_connection,
        AssetCreationService $asset_creation_service,
        AssetTypeClassifier $asset_type_classifier,
    ) {
        $this->ldap_connection = $ldap_connection;
        $this->asset_creation_service = $asset_creation_service;
        $this->asset_type_classifier = $asset_type_classifier;
    }

    /**
     * Set the LDAP inventory service (optional dependency)
     *
     * @param LdapInventoryService $service
     * @return void
     */
    public function setLdapInventoryService(LdapInventoryService $service): void
    {
        $this->ldap_inventory_service = $service;
        // Debug: LdapInventoryService has been SET - inventory workflow is AVAILABLE
    }

    /**
     * Synchronize LDAP data based on a sync filter
     *
     * @param int $syncfilter_id SyncFilter ID
     * @param int $authldap_id AuthLDAP ID
     * @return array Synchronization results
     */
    public function synchronizeFromFilter(int $syncfilter_id, int $authldap_id): array
    {
        $results = [
            'success' => false,
            'error' => null,
            'stats' => [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
            ],
            'details' => [],
        ];

        try {
            // Load and validate sync filter
            $sync_filter = new SyncFilter();
            if (!$sync_filter->getFromDB($syncfilter_id)) {
                $results['error'] = __('Sync filter not found', 'advancedldap');
                return $results;
            }

            // Validate AuthLDAP configuration
            $authldap = new AuthLDAP();
            if (!$authldap->getFromDB($authldap_id)) {
                $results['error'] = __('AuthLDAP configuration not found', 'advancedldap');
                return $results;
            }

            // Validate sync filter configuration
            $validation_error = $this->validateSyncFilter($sync_filter);
            if ($validation_error) {
                $results['error'] = $validation_error;
                return $results;
            }

            // Get LDAP entries
            $ldap_entries = $this->fetchLdapEntries(
                $authldap_id,
                $sync_filter->getField('base_dn'),
                $sync_filter->getField('ldap_filter'),
            );

            if (isset($ldap_entries['error'])) {
                $results['error'] = $ldap_entries['error'];
                return $results;
            }

            // Process each LDAP entry
            $field_mappings = $sync_filter->getFieldMappings();
            $asset_type = $sync_filter->getField('asset_type');

            // Determine synchronization method based on asset type
            $sync_method = $this->asset_type_classifier->getSyncMethod($asset_type);

            Toolbox::logDebug("LdapSyncService: Asset type '$asset_type' determined sync method: '$sync_method'");

            // Handle LDAP entries array properly (skip count and numeric indices)
            $entries = $ldap_entries['entries'];
            $entry_count = $entries['count'] ?? 0;

            for ($i = 0; $i < $entry_count; $i++) {
                if (!isset($entries[$i]) || !is_array($entries[$i])) {
                    continue;
                }

                $ldap_entry = $entries[$i];
                $results['stats']['processed']++;

                $entry_result = $this->processSingleEntry(
                    $ldap_entry,
                    $asset_type,
                    $field_mappings,
                    $sync_method,
                );

                if ($entry_result['success']) {
                    if ($entry_result['action'] === 'created') {
                        $results['stats']['created']++;
                    } else {
                        $results['stats']['updated']++;
                    }
                } else {
                    $results['stats']['errors']++;
                }

                $results['details'][] = $entry_result;
            }

            $results['success'] = true;

        } catch (Exception $e) {
            $results['error'] = sprintf(__('Synchronization error: %s', 'advancedldap'), $e->getMessage());
        }

        return $results;
    }

    /**
     * Validate sync filter configuration
     *
     * @param SyncFilter $sync_filter
     * @return string|null Error message or null if valid
     */
    private function validateSyncFilter(SyncFilter $sync_filter): ?string
    {
        if (empty($sync_filter->getField('base_dn'))) {
            return __('Base DN is required', 'advancedldap');
        }

        if (empty($sync_filter->getField('ldap_filter'))) {
            return __('LDAP filter is required', 'advancedldap');
        }

        if (empty($sync_filter->getField('asset_type'))) {
            return __('Asset type is required', 'advancedldap');
        }

        $field_mappings = $sync_filter->getFieldMappings();
        if (empty($field_mappings)) {
            return __('Field mappings are required', 'advancedldap');
        }

        // Validate asset type exists
        $asset_type = $sync_filter->getField('asset_type');
        if (!class_exists($asset_type)) {
            return sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type);
        }

        return null;
    }

    /**
     * Fetch entries from LDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @param string $base_dn Base DN
     * @param string $filter LDAP filter
     * @return array LDAP entries or error
     */
    private function fetchLdapEntries(int $authldap_id, string $base_dn, string $filter): array
    {
        return $this->ldap_connection->searchWithErrorHandling($authldap_id, $base_dn, $filter);
    }

    /**
     * Process a single LDAP entry
     *
     * @param array $ldap_entry LDAP entry data
     * @param string $asset_type Asset type class name
     * @param array $field_mappings Field mappings (GLPI field => LDAP attribute)
     * @param string $sync_method Synchronization method ('inventory' or 'traditional')
     * @return array Processing result
     */
    private function processSingleEntry(array $ldap_entry, string $asset_type, array $field_mappings, string $sync_method): array
    {
        $result = [
            'success' => false,
            'action' => null,
            'asset_id' => null,
            'asset_name' => '',
            'dn' => $ldap_entry['dn'] ?? '',
            'error' => null,
        ];

        try {
            // Extract mapped data from LDAP entry
            $asset_data = $this->extractAssetData($ldap_entry, $field_mappings);

            if (empty($asset_data)) {
                $result['error'] = __('No valid data extracted from LDAP entry', 'advancedldap');
                return $result;
            }

            $result['asset_name'] = $asset_data['name'] ?? '';

            // Route to appropriate synchronization method
            Toolbox::logDebug("LdapSyncService: Routing asset '{$result['asset_name']}' to '$sync_method' workflow");

            if ($sync_method === 'inventory') {
                Toolbox::logDebug("LdapSyncService: Processing inventoriable asset '{$result['asset_name']}' of type '$asset_type'");
                $creation_result = $this->processInventoryableAsset($asset_type, $asset_data, $ldap_entry);
            } else {
                Toolbox::logDebug("LdapSyncService: Processing traditional asset '{$result['asset_name']}' of type '$asset_type'");
                $creation_result = $this->processTraditionalAsset($asset_type, $asset_data);
            }

            $result['success'] = $creation_result['success'];
            $result['action'] = $creation_result['action'];
            $result['asset_id'] = $creation_result['asset_id'];
            $result['error'] = $creation_result['error'];

        } catch (Exception $e) {
            $result['error'] = sprintf(__('Error processing entry: %s', 'advancedldap'), $e->getMessage());
        }

        return $result;
    }

    /**
     * Extract asset data from LDAP entry using field mappings
     *
     * @param array $ldap_entry LDAP entry
     * @param array $field_mappings Field mappings (GLPI field => LDAP attribute)
     * @return array Extracted asset data
     */
    private function extractAssetData(array $ldap_entry, array $field_mappings): array
    {
        $asset_data = [];

        foreach ($field_mappings as $glpi_field => $ldap_attribute) {
            // Normalize LDAP attribute name to lowercase (PHP ldap_get_entries normalizes all keys)
            $normalized_ldap_attribute = strtolower($ldap_attribute);

            if (isset($ldap_entry[$normalized_ldap_attribute])) {
                $ldap_value = $ldap_entry[$normalized_ldap_attribute];

                // Handle LDAP array values
                if (is_array($ldap_value)) {
                    if (isset($ldap_value['count'])) {
                        unset($ldap_value['count']);
                    }
                    // Use first value for single-value fields, join for multi-value
                    $asset_data[$glpi_field] = count($ldap_value) === 1 ? $ldap_value[0] : implode(', ', $ldap_value);
                } else {
                    $asset_data[$glpi_field] = $ldap_value;
                }
            }
        }

        // Ensure we have at least a name
        if (empty($asset_data['name'])) {
            // Fallback to common LDAP naming attributes
            $name_candidates = ['cn', 'displayName', 'uid', 'sAMAccountName'];
            foreach ($name_candidates as $candidate) {
                if (isset($ldap_entry[$candidate])) {
                    $value = $ldap_entry[$candidate];
                    if (is_array($value) && isset($value[0])) {
                        $value = $value[0];
                    }
                    $asset_data['name'] = $value;
                    break;
                }
            }
        }

        return $asset_data;
    }

    /**
     * Process inventoriable asset using new inventory workflow
     *
     * @param string $asset_type Asset type class name
     * @param array $asset_data Extracted asset data
     * @param array $ldap_entry Original LDAP entry
     * @return array Processing result
     */
    private function processInventoryableAsset(string $asset_type, array $asset_data, array $ldap_entry): array
    {
        $asset_name = $asset_data['name'] ?? 'Unknown';

        // Use inventory workflow if service is available
        if ($this->ldap_inventory_service !== null) {
            Toolbox::logDebug("LdapSyncService: Using INVENTORY workflow for asset '$asset_name' (type: $asset_type)");

            // Use the full LDAP entry for inventory processing (more complete than extracted asset_data)
            return $this->ldap_inventory_service->syncInventoriableAsset(
                $ldap_entry,
                $asset_type,
                [],
            );
        }

        // Fallback to traditional method if inventory service not available
        Toolbox::logDebug("LdapSyncService: FALLBACK to TRADITIONAL workflow for asset '$asset_name' (type: $asset_type) - Inventory service not available");
        return $this->processTraditionalAsset($asset_type, $asset_data);
    }

    /**
     * Process asset using traditional GLPI workflow
     *
     * @param string $asset_type Asset type class name
     * @param array $asset_data Extracted asset data
     * @return array Processing result
     */
    private function processTraditionalAsset(string $asset_type, array $asset_data): array
    {
        $asset_name = $asset_data['name'] ?? 'Unknown';
        Toolbox::logDebug("LdapSyncService: Executing TRADITIONAL workflow for asset '$asset_name' (type: $asset_type)");

        return $this->asset_creation_service->createOrUpdateAsset(
            $asset_type,
            $asset_data,
        );
    }

}
