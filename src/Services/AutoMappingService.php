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

namespace GlpiPlugin\Advancedldap\Services;

use Computer;
use NetworkEquipment;
use Phone;
use Printer;
use GlpiPlugin\Advancedldap\Contracts\AutoMappingServiceInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapAttributeMapperInterface;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;

/**
 * Service for automatic field mapping between GLPI and LDAP
 *
 * Generates default mappings based on:
 * - Asset-specific required fields (via AssetFieldHandlers)
 * - RFC 4519 standard LDAP attribute mappings
 * - GLPI inventory system requirements
 */
class AutoMappingService implements AutoMappingServiceInterface
{
    private LdapAttributeMapperInterface $attributeMapper;

    /** @var AssetFieldHandlerInterface[] */
    private array $fieldHandlers = [];

    /**
     * @param LdapAttributeMapperInterface $attributeMapper
     * @param array<AssetFieldHandlerInterface> $fieldHandlers Optional field handlers
     */
    public function __construct(
        LdapAttributeMapperInterface $attributeMapper,
        array $fieldHandlers = []
    ) {
        $this->attributeMapper = $attributeMapper;
        $this->fieldHandlers = $fieldHandlers;
    }

    /**
     * Add a field handler
     *
     * @param AssetFieldHandlerInterface $handler
     * @return void
     */
    public function addFieldHandler(AssetFieldHandlerInterface $handler): void
    {
        $this->fieldHandlers[] = $handler;
    }

    /**
     * Get default field mappings for a given itemtype
     *
     * @param string $itemtype GLPI itemtype (Computer, Phone, Printer, NetworkEquipment, etc.)
     * @return array<string, string> Array of [glpi_field => ldap_attribute]
     */
    public function getDefaultMappings(string $itemtype): array
    {
        $mappings = [];

        // Get required fields from handlers or use itemtype-specific defaults
        $requiredFields = $this->getRequiredFieldsForItemtype($itemtype);

        // Add special handling for NetworkEquipment which needs serial OR mac
        if ($itemtype === NetworkEquipment::class) {
            // NetworkEquipment requires name + (serial OR mac)
            // We suggest both to let the user choose based on their LDAP schema
            $requiredFields = array_merge($requiredFields, ['serial']);
        }

        // Map each required field to its RFC 4519 LDAP attribute
        foreach ($requiredFields as $glpiField) {
            $ldapAttribute = $this->suggestLdapAttribute($glpiField);
            if ($ldapAttribute !== null) {
                $mappings[$glpiField] = $ldapAttribute;
            }
        }

        return $mappings;
    }

    /**
     * Get required fields for a given itemtype
     *
     * @param string $itemtype GLPI itemtype
     * @return array<int, string> Array of required GLPI field names
     */
    public function getRequiredFieldsForItemtype(string $itemtype): array
    {
        // Try to get required fields from registered handlers
        foreach ($this->fieldHandlers as $handler) {
            if ($handler->supports($this->getAssetTypeFromClass($itemtype))) {
                return $handler->getRequiredFields();
            }
        }

        // Fallback to hardcoded defaults based on LdapInventoryService requirements
        return match ($itemtype) {
            Computer::class => ['name'],
            Phone::class => ['name', 'serial'],
            Printer::class => ['name'],
            NetworkEquipment::class => ['name'], // serial OR mac also required (handled separately)
            default => ['name'],
        };
    }

    /**
     * Suggest an LDAP attribute for a given GLPI field
     *
     * @param string $glpiField GLPI field name
     * @param array<string> $availableLdapAttributes Optional list of available LDAP attributes to check
     * @return string|null Suggested LDAP attribute or null if no suggestion available
     */
    public function suggestLdapAttribute(string $glpiField, array $availableLdapAttributes = []): ?string
    {
        // Get RFC 4519 standard mappings
        $standardMappings = $this->attributeMapper->getStandardMappings();

        // Normalize field name
        $normalizedField = strtolower($glpiField);

        // Check if we have a standard mapping
        if (isset($standardMappings[$normalizedField])) {
            $suggestedAttribute = $standardMappings[$normalizedField];

            // If available attributes provided, verify the suggestion exists
            if ($availableLdapAttributes !== []) {
                if (in_array($suggestedAttribute, $availableLdapAttributes, true)) {
                    return $suggestedAttribute;
                }
                // Fallback: check if the exact field name exists
                if (in_array($glpiField, $availableLdapAttributes, true)) {
                    return $glpiField;
                }
                return null;
            }

            return $suggestedAttribute;
        }

        // No standard mapping found - try exact match if attributes provided
        if ($availableLdapAttributes !== [] && in_array($glpiField, $availableLdapAttributes, true)) {
            return $glpiField;
        }

        // No suggestion available
        return null;
    }

    /**
     * Get asset type name from full class name
     *
     * @param string $itemtype Full class name (e.g., 'Computer', 'Phone')
     * @return string Asset type name (e.g., 'Computer', 'Phone')
     */
    private function getAssetTypeFromClass(string $itemtype): string
    {
        // Handle full class names (e.g., 'Computer') and extract base name
        return basename(str_replace('\\', '/', $itemtype));
    }
}
