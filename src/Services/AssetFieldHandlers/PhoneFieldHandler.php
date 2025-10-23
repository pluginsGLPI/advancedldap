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

namespace GlpiPlugin\Advancedldap\Services\AssetFieldHandlers;

use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;

/**
 * Handler for Phone-specific fields
 *
 * Phones require name + serial number for unique identification in GLPI inventory system
 * @see LdapInventoryService::getMinimumFieldRequirements()
 */
class PhoneFieldHandler implements AssetFieldHandlerInterface
{
    public function supports(string $assetType): bool
    {
        return $assetType === 'Phone';
    }

    public function handle(array $data): array
    {
        // Apply default values for missing fields
        $defaults = $this->getDefaultValues();
        foreach ($defaults as $field => $value) {
            if (!isset($data[$field])) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * Get required fields for Phone assets
     *
     * According to GLPI inventory system requirements, Phone needs:
     * - name: primary identifier
     * - serial: unique identifier (recommended for proper identification)
     *
     * @return array<int, string> Array of required field names
     */
    public function getRequiredFields(): array
    {
        return ['name', 'serial'];
    }

    public function getDefaultValues(): array
    {
        return [
            'phonetypes_id' => 0,
            'states_id' => 0,
        ];
    }
}
