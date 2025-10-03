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

use Glpi\Inventory\Inventory;
use Glpi\Inventory\Request;
use Safe\Exceptions\JsonException;
use Toolbox;

use function Safe\json_decode;
use function Safe\json_encode;

/**
 * Service for orchestrating LDAP inventory synchronization
 *
 * This service handles the complete workflow for inventoriable assets:
 * 1. Convert LDAP data to Inventory JSON format
 * 2. Send to native GLPI Inventory.php for processing
 * 3. Handle validation and error management
 */
class LdapInventoryService
{
    private LdapToInventoryConverter $converter;

    public function __construct(LdapToInventoryConverter $converter)
    {
        $this->converter = $converter;
    }

    /**
     * Synchronize inventoriable asset from LDAP data
     *
     * @param array<string, mixed> $ldapData LDAP attributes
     * @param string $itemtype GLPI itemtype (Computer, NetworkEquipment, etc.)
     * @param array<string, string> $fieldMappings Field mappings from sync filter (GLPI field => LDAP attribute)
     * @return array<string, mixed> Result with success status and details
     */
    public function syncInventoriableAsset(array $ldapData, string $itemtype, array $fieldMappings = []): array
    {
        $assetName = $ldapData['name'][0] ?? $ldapData['cn'][0] ?? 'Unknown';
        Toolbox::logDebug("LdapInventoryService: Starting inventory sync for asset '$assetName' (type: $itemtype)");

        try {
            $inventoryData = $this->converter->convertToInventoryFormat($ldapData, $itemtype, $fieldMappings);

            Toolbox::logDebug("LdapInventoryService: Inventory data for '$assetName': " . json_encode($inventoryData, JSON_PRETTY_PRINT));

            // Convert array to object for GLPI schema validation
            try {
                $inventoryObject = json_decode(json_encode($inventoryData));
            } catch (JsonException $e) {
                Toolbox::logDebug("LdapInventoryService: Failed to encode/decode inventory data for '$assetName': " . $e->getMessage());
                return [
                    'success' => false,
                    'action' => 'inventory',
                    'asset_id' => null,
                    'error' => $e->getMessage(),
                    'message' => 'Failed to convert inventory data to object',
                ];
            }

            // Create inventory without auto-processing to avoid file storage issues
            $inventory = new Inventory(null, Inventory::FULL_MODE, Request::JSON_MODE);

            if (!$inventory->setData($inventoryObject, Request::JSON_MODE)) {
                $errors = $inventory->getErrors();
                Toolbox::logDebug("LdapInventoryService: FAILED to set inventory data for '$assetName': " . implode(', ', $errors));
                return [
                    'success' => false,
                    'action' => 'inventory',
                    'asset_id' => null,
                    'error' => implode(', ', $errors),
                    'message' => 'Failed to set inventory data',
                ];
            }

            $inventory->doInventory();

            if ($inventory->inError()) {
                $errors = $inventory->getErrors();
                Toolbox::logDebug("LdapInventoryService: Inventory processing FAILED for '$assetName': " . implode(', ', $errors));
                return [
                    'success' => false,
                    'action' => 'inventory',
                    'asset_id' => null,
                    'error' => implode(', ', $errors),
                    'message' => 'Inventory validation or processing failed',
                ];
            }

            // Extract result information from the processed inventory
            $item = $inventory->getItem();
            $assetId = $item->getID();

            Toolbox::logDebug("LdapInventoryService: Inventory item class: " . get_class($item) . ", ID: $assetId");

            // Check if inventory failed silently (ID = -1 or 0 means failure)
            if ($assetId <= 0) {
                // Provide specific minimum requirements based on itemtype
                $requirements = $this->getMinimumFieldRequirements($itemtype);
                $errorMsg = sprintf(
                    __('Inventory system could not create/update asset. Insufficient field mappings. For %s, you need at least: %s', 'advancedldap'),
                    basename(str_replace('\\', '/', $itemtype)),
                    $requirements
                );
                Toolbox::logDebug("LdapInventoryService: FAILED - Asset '$assetName' not created (ID: $assetId). Insufficient data. Required: $requirements");
                return [
                    'success' => false,
                    'action' => 'inventory',
                    'asset_id' => null,
                    'error' => $errorMsg,
                    'message' => 'Insufficient field mappings for inventory creation',
                ];
            }

            // Determine if this was a creation or update based on MainAsset status
            $mainAsset = $inventory->getMainAsset();
            // PHPStan: getMainAsset() always returns an object (never null) after successful doInventory()
            $isNew = $mainAsset->isNew();
            $action = $isNew ? 'created' : 'updated';

            Toolbox::logDebug("LdapInventoryService: MainAsset isNew: " . ($isNew ? 'true' : 'false') . ", action: $action");
            Toolbox::logDebug("LdapInventoryService: SUCCESS - Asset '$assetName' $action via inventory system (ID: $assetId)");

            return [
                'success' => true,
                'action' => $action,
                'asset_id' => $assetId,
                'error' => null,
                'message' => 'Asset synchronized via inventory system',
            ];

        } catch (\InvalidArgumentException $e) {
            Toolbox::logDebug("LdapInventoryService: Invalid argument for '$assetName': " . $e->getMessage());
            return [
                'success' => false,
                'action' => 'inventory',
                'asset_id' => null,
                'error' => $e->getMessage(),
                'message' => 'Invalid itemtype or configuration',
            ];

        } catch (\Exception $e) {
            Toolbox::logDebug("LdapInventoryService: Unexpected error for '$assetName': " . $e->getMessage());

            return [
                'success' => false,
                'action' => 'inventory',
                'asset_id' => null,
                'error' => $e->getMessage(),
                'message' => 'Unexpected error during inventory processing',
            ];
        }
    }

    /**
     * Check if the service can handle the given itemtype
     *
     * @param string $itemtype GLPI itemtype
     * @return bool
     */
    public function canHandleItemtype(string $itemtype): bool
    {
        return in_array($itemtype, $this->converter->getSupportedItemtypes());
    }

    /**
     * Get supported itemtypes for inventory processing
     *
     * @return array<string> List of supported itemtypes
     */
    public function getSupportedItemtypes(): array
    {
        return $this->converter->getSupportedItemtypes();
    }

    /**
     * Get minimum field requirements for a given itemtype
     *
     * @param string $itemtype GLPI itemtype
     * @return string Human-readable requirements
     */
    private function getMinimumFieldRequirements(string $itemtype): string
    {
        // Based on GLPI inventory schema and practical requirements
        switch ($itemtype) {
            case \Computer::class:
                return __('Name (required for identification)', 'advancedldap');

            case \NetworkEquipment::class:
                return __('Name + Serial Number OR MAC Address (required for unique identification)', 'advancedldap');

            case \Printer::class:
                return __('Name (required)', 'advancedldap');

            case \Phone::class:
                return __('Name + Serial Number (recommended for unique identification)', 'advancedldap');

            default:
                return __('Name + unique identifier (Serial Number, MAC Address, etc.)', 'advancedldap');
        }
    }

    /**
     * Validate LDAP data before processing (optional pre-check)
     *
     * @param array<string, mixed> $ldapData LDAP attributes
     * @param string $itemtype GLPI itemtype
     * @return array<string, mixed> Validation result
     */
    public function validateLdapData(array $ldapData, string $itemtype): array
    {
        $issues = [];

        // Check if itemtype is supported
        if (!$this->canHandleItemtype($itemtype)) {
            $issues[] = "Itemtype '$itemtype' is not supported for inventory processing";
        }

        // Basic LDAP data validation
        if (empty($ldapData)) {
            $issues[] = "LDAP data is empty";
        }

        // Check for basic identifying fields
        $identifyingFields = ['cn', 'name', 'samaccountname', 'displayname'];
        $hasIdentifier = false;
        foreach ($identifyingFields as $field) {
            if (!empty($ldapData[$field][0])) {
                $hasIdentifier = true;
                break;
            }
        }

        if (!$hasIdentifier) {
            $issues[] = "No identifying field found in LDAP data (cn, name, samaccountname, displayname)";
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
        ];
    }
}
