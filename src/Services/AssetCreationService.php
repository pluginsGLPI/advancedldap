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

use CommonDBTM;
use Exception;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use Location;
use Session;

/**
 * Asset creation and update service
 *
 * Handles the creation and updating of GLPI assets from synchronized LDAP data.
 * Supports various asset types (Computer, Printer, NetworkEquipment, User, etc.)
 */
class AssetCreationService
{
    private DatabaseInterface $database;

    /**
     * @param DatabaseInterface $database
     */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * Create or update a GLPI asset
     *
     * @param string $asset_type Asset class name (Computer, Printer, etc.)
     * @param array<string, mixed> $asset_data Asset data from LDAP
     * @return array{success: bool, action: string|null, asset_id: int|null, error: string|null} Creation result
     */
    public function createOrUpdateAsset(string $asset_type, array $asset_data): array
    {
        $result = [
            'success' => false,
            'action' => null,
            'asset_id' => null,
            'error' => null,
        ];

        try {
            // Validate asset type
            if (!class_exists($asset_type)) {
                $result['error'] = sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type);
                return $result;
            }

            // Validate required data
            if (empty($asset_data['name'])) {
                $result['error'] = __('Asset name is required', 'advancedldap');
                return $result;
            }

            // Create asset instance
            /** @phpstan-ignore glpi.forbidDynamicInstantiation */
            $asset = new $asset_type();
            if (!$asset instanceof CommonDBTM) {
                $result['error'] = sprintf(__('Invalid asset type %s', 'advancedldap'), $asset_type);
                return $result;
            }

            // Prepare asset data for GLPI
            $prepared_data = $this->prepareAssetData($asset_data, $asset_type);

            // Check if asset already exists
            $existing_asset = $this->findExistingAsset($asset, $prepared_data);

            if ($existing_asset) {
                // Update existing asset
                $update_data = $prepared_data;
                $update_data['id'] = $existing_asset['id'];

                if ($asset->update($update_data)) {
                    $result['success'] = true;
                    $result['action'] = 'updated';
                    $result['asset_id'] = $existing_asset['id'];
                } else {
                    $result['error'] = __('Failed to update asset', 'advancedldap');
                }
            } else {
                // Create new asset
                $asset_id = $asset->add($prepared_data);
                if ($asset_id) {
                    $result['success'] = true;
                    $result['action'] = 'created';
                    $result['asset_id'] = $asset_id;
                } else {
                    $result['error'] = __('Failed to create asset', 'advancedldap');
                }
            }

        } catch (Exception $e) {
            $result['error'] = sprintf(__('Asset creation error: %s', 'advancedldap'), $e->getMessage());
        }

        return $result;
    }

    /**
     * Prepare asset data for GLPI standards
     *
     * @param array<string, mixed> $asset_data Raw asset data
     * @param string $asset_type Asset type
     * @return array<string, mixed> Prepared data
     */
    private function prepareAssetData(array $asset_data, string $asset_type): array
    {
        $prepared = [];

        // Copy basic fields
        foreach ($asset_data as $field => $value) {
            $prepared[$field] = $this->sanitizeFieldValue($value);
        }

        // Handle special fields based on asset type
        $prepared = $this->handleSpecialFields($prepared, $asset_type);

        // Set default values for required fields
        $prepared = $this->setDefaultValues($prepared, $asset_type);

        return $prepared;
    }

    /**
     * Handle special fields based on asset type
     *
     * @param array<string, mixed> $data Asset data
     * @param string $asset_type Asset type
     * @return array<string, mixed> Modified data
     */
    private function handleSpecialFields(array $data, string $asset_type): array
    {
        // Handle location field (convert location name to ID)
        if (isset($data['locations_id']) && !is_numeric($data['locations_id'])) {
            $data['locations_id'] = $this->getLocationId($data['locations_id']);
        }

        // Handle entity (use current session entity)
        if (!isset($data['entities_id'])) {
            $data['entities_id'] = Session::getActiveEntity();
        }

        // Asset type specific handling
        switch ($asset_type) {
            case 'Computer':
                $data = $this->handleComputerFields($data);
                break;

            case 'Printer':
                $data = $this->handlePrinterFields($data);
                break;

            case 'NetworkEquipment':
                $data = $this->handleNetworkEquipmentFields($data);
                break;

            case 'User':
                $data = $this->handleUserFields($data);
                break;
        }

        return $data;
    }

    /**
     * Handle Computer-specific fields
     *
     * @param array<string, mixed> $data Asset data
     * @return array<string, mixed> Modified data
     */
    private function handleComputerFields(array $data): array
    {
        // Set default computer type if not specified
        if (!isset($data['computertypes_id'])) {
            $data['computertypes_id'] = 0; // Will be handled by GLPI defaults
        }

        // Set default state
        if (!isset($data['states_id'])) {
            $data['states_id'] = 0; // Default state
        }

        return $data;
    }

    /**
     * Handle Printer-specific fields
     *
     * @param array<string, mixed> $data Asset data
     * @return array<string, mixed> Modified data
     */
    private function handlePrinterFields(array $data): array
    {
        // Set default printer type if not specified
        if (!isset($data['printertypes_id'])) {
            $data['printertypes_id'] = 0;
        }

        return $data;
    }

    /**
     * Handle NetworkEquipment-specific fields
     *
     * @param array<string, mixed> $data Asset data
     * @return array<string, mixed> Modified data
     */
    private function handleNetworkEquipmentFields(array $data): array
    {
        // Set default network equipment type if not specified
        if (!isset($data['networkequipmenttypes_id'])) {
            $data['networkequipmenttypes_id'] = 0;
        }

        return $data;
    }

    /**
     * Handle User-specific fields
     *
     * @param array<string, mixed> $data Asset data
     * @return array<string, mixed> Modified data
     */
    private function handleUserFields(array $data): array
    {
        // Handle email field (GLPI uses different field name)
        if (isset($data['emails']) && !isset($data['_useremails'])) {
            $data['_useremails'] = [$data['emails']];
        }

        // Set default user category if not specified
        if (!isset($data['usercategories_id'])) {
            $data['usercategories_id'] = 0;
        }

        return $data;
    }

    /**
     * Set default values for required fields
     *
     * @param array<string, mixed> $data Asset data
     * @param string $asset_type Asset type
     * @return array<string, mixed> Data with defaults
     */
    private function setDefaultValues(array $data, string $asset_type): array
    {
        // Ensure name is set
        if (empty($data['name'])) {
            $data['name'] = __('Unnamed Asset', 'advancedldap');
        }

        // Set creation date
        if (!isset($data['date_creation'])) {
            $data['date_creation'] = date('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * Sanitize field value
     *
     * @param mixed $value Field value
     * @return mixed Sanitized value
     */
    private function sanitizeFieldValue($value): mixed
    {
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    /**
     * Find existing asset by name
     *
     * @param CommonDBTM $asset Asset instance
     * @param array<string, mixed> $asset_data Asset data
     * @return array<string, mixed>|null Existing asset data or null
     */
    private function findExistingAsset(CommonDBTM $asset, array $asset_data): ?array
    {
        $table = $asset->getTable();
        $name = $asset_data['name'];

        $iterator = $this->database->request([
            'FROM' => $table,
            'WHERE' => ['name' => $name],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $data) {
            return $data;
        }

        return null;
    }

    /**
     * Get location ID by name, create if not exists
     *
     * @param string $location_name Location name
     * @return int Location ID
     */
    private function getLocationId(string $location_name): int
    {
        if (empty($location_name)) {
            return 0;
        }

        // Try to find existing location
        $iterator = $this->database->request([
            'FROM' => 'glpi_locations',
            'WHERE' => ['name' => $location_name],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $data) {
            return (int) $data['id'];
        }

        // Create new location if not found
        $location = new Location();
        $location_id = $location->add([
            'name' => $location_name,
            'entities_id' => Session::getActiveEntity(),
        ]);

        return $location_id ?: 0;
    }

    /**
     * Validate asset data before creation
     *
     * @param array<string, mixed> $asset_data Asset data
     * @param string $asset_type Asset type
     * @return array{valid: bool, errors: array<string>} Validation result
     */
    public function validateAssetData(array $asset_data, string $asset_type): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
        ];

        // Check required fields
        if (empty($asset_data['name'])) {
            $result['valid'] = false;
            $result['errors'][] = __('Asset name is required', 'advancedldap');
        }

        // Check asset type validity
        if (!class_exists($asset_type)) {
            $result['valid'] = false;
            $result['errors'][] = sprintf(__('Invalid asset type: %s', 'advancedldap'), $asset_type);
        }

        return $result;
    }
}
