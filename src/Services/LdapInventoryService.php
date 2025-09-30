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
use Toolbox;

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
     * @param array $ldapData LDAP attributes
     * @param string $itemtype GLPI itemtype (Computer, NetworkEquipment, etc.)
     * @param array $syncFilterConfig SyncFilter configuration
     * @return array Result with success status and details
     */
    public function syncInventoriableAsset(array $ldapData, string $itemtype, array $syncFilterConfig = []): array
    {
        $assetName = $ldapData['name'][0] ?? $ldapData['cn'][0] ?? 'Unknown';
        Toolbox::logDebug("LdapInventoryService: Starting inventory sync for asset '$assetName' (type: $itemtype)");

        try {
            $inventoryData = $this->converter->convertToInventoryFormat($ldapData, $itemtype, $syncFilterConfig);

            // Convert array to object for GLPI schema validation
            $inventoryObject = json_decode(json_encode($inventoryData));

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
            $assetId = null;

            if ($item && is_object($item) && method_exists($item, 'getID')) {
                $assetId = $item->getID();
            }

            Toolbox::logDebug("LdapInventoryService: SUCCESS - Asset '$assetName' synchronized via inventory system (ID: $assetId)");

            return [
                'success' => true,
                'action' => 'inventory',
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
     * @return array List of supported itemtypes
     */
    public function getSupportedItemtypes(): array
    {
        return $this->converter->getSupportedItemtypes();
    }

    /**
     * Validate LDAP data before processing (optional pre-check)
     *
     * @param array $ldapData LDAP attributes
     * @param string $itemtype GLPI itemtype
     * @return array Validation result
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
