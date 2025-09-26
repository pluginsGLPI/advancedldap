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

use Computer;
use NetworkEquipment;
use Phone;
use Printer;
use Toolbox;

/**
 * Asset type classification service
 *
 * Determines if an asset type is inventoriable (managed by Inventory.php)
 * or should use traditional GLPI synchronization methods.
 *
 * Uses native GLPI configuration $CFG_GLPI['inventory_types'] as source of truth.
 */
class AssetTypeClassifier
{
    /**
     * Check if an asset type is inventoriable
     *
     * @param string $asset_type Asset class name (e.g., 'Computer')
     * @return bool True if asset is inventoriable
     */
    public function isInventoriableAsset(string $asset_type): bool
    {
        global $CFG_GLPI;

        if (empty($asset_type) || !class_exists($asset_type)) {
            return false;
        }

        // Get inventoriable types from GLPI core configuration
        $inventoriable_types = $this->getInventoriableAssetTypes();
        return in_array($asset_type, $inventoriable_types, true);
    }

    /**
     * Get the synchronization method for an asset type
     *
     * @param string $asset_type Asset class name
     * @return string 'inventory' for inventoriable assets, 'traditional' for others
     */
    public function getSyncMethod(string $asset_type): string
    {
        return $this->isInventoriableAsset($asset_type) ? 'inventory' : 'traditional';
    }

    /**
     * Get all inventoriable asset types from GLPI configuration
     *
     * @return array<string> Array of inventoriable asset class names
     */
    public function getInventoriableAssetTypes(): array
    {
        global $CFG_GLPI;

        // Fallback to hardcoded types if CFG_GLPI not available (shouldn't happen in normal GLPI context)
        if (!isset($CFG_GLPI['inventory_types'])) {
            return [
                Computer::class,
                Phone::class,
                Printer::class,
                NetworkEquipment::class,
            ];
        }

        return $CFG_GLPI['inventory_types'];
    }

    /**
     * Check if asset type should use new inventory workflow
     *
     * @param string $asset_type Asset class name
     * @return bool True if should use inventory workflow
     */
    public function shouldUseInventoryWorkflow(string $asset_type): bool
    {
        return $this->isInventoriableAsset($asset_type);
    }

    /**
     * Check if asset type should use traditional workflow
     *
     * @param string $asset_type Asset class name
     * @return bool True if should use traditional workflow
     */
    public function shouldUseTraditionalWorkflow(string $asset_type): bool
    {
        return !$this->isInventoriableAsset($asset_type);
    }

    /**
     * Get asset type classification info
     *
     * @param string $asset_type Asset class name
     * @return array<string, mixed> Classification information
     */
    public function getAssetTypeInfo(string $asset_type): array
    {
        $is_inventoriable = $this->isInventoriableAsset($asset_type);

        return [
            'asset_type' => $asset_type,
            'is_inventoriable' => $is_inventoriable,
            'sync_method' => $this->getSyncMethod($asset_type),
            'workflow' => $is_inventoriable ? 'inventory' : 'traditional',
            'use_inventory_php' => $is_inventoriable,
        ];
    }
}
