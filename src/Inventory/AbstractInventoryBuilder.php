<?php

/**
 * -------------------------------------------------------------------------
 * AdvancedLDAP plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of AdvancedLDAP.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdvancedLDAP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdvancedLDAP. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @author    GLPI-Project
 * @copyright Copyright (C) GLPI-Project
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Inventory;

/**
 * Abstract builder for creating GLPI inventory JSON from LDAP entries.
 *
 * Each concrete builder (ComputerBuilder, PhoneBuilder, etc.) defines
 * the mapping between GLPI fields and JSON inventory paths.
 */
abstract class AbstractInventoryBuilder
{
    /**
     * Plugin version used as inventory client identifier.
     */
    protected const VERSION_CLIENT = 'advancedldap-plugin-1.0';

    /**
     * Build the inventory JSON array from LDAP entry data.
     *
     * @param string $device_id      Unique device identifier for GLPI inventory
     * @param array  $ldap_entry     Raw LDAP entry (with attributes as arrays)
     * @param array  $field_mappings Mapping of GLPI field => LDAP attribute
     *
     * @return array The inventory JSON structure ready for Glpi\Inventory\Inventory
     */
    public function build(string $device_id, array $ldap_entry, array $field_mappings): array
    {
        $inventory = $this->getBaseStructure($device_id);

        foreach ($field_mappings as $glpi_field => $ldap_attr) {
            $value = $this->extractLdapValue($ldap_entry, $ldap_attr);
            if ($value === null) {
                continue;
            }

            $json_path = $this->getJsonPathForField($glpi_field);
            if ($json_path !== null) {
                $this->setNestedValue($inventory['content'], $json_path, $value);
            }
        }

        return $inventory;
    }

    /**
     * Get the base inventory JSON structure.
     *
     * @param string $device_id Unique device identifier
     *
     * @return array Base structure with deviceid, action, and content sections
     */
    protected function getBaseStructure(string $device_id): array
    {
        return [
            'deviceid' => $device_id,
            'itemtype' => $this->getItemtype(),
            'action'   => 'inventory',
            'content'  => array_merge(
                ['versionclient' => self::VERSION_CLIENT],
                $this->getContentSections()
            ),
        ];
    }

    /**
     * Get the GLPI itemtype this builder produces.
     *
     * @return string Itemtype class name (e.g., 'Computer', 'Phone')
     */
    abstract public function getItemtype(): string;

    /**
     * Get the content sections for this itemtype.
     *
     * @return array Initialized sections (e.g., ['hardware' => [], 'bios' => []])
     */
    abstract protected function getContentSections(): array;

    /**
     * Get the JSON path for a GLPI field.
     *
     * @param string $glpi_field GLPI field name (e.g., 'name', 'serial')
     *
     * @return string|null Dot-notation path (e.g., 'hardware.name') or null if not mapped
     */
    abstract protected function getJsonPathForField(string $glpi_field): ?string;

    /**
     * Extract a single value from an LDAP entry.
     *
     * LDAP attributes are arrays; this extracts the first value.
     *
     * @param array  $ldap_entry Raw LDAP entry
     * @param string $ldap_attr  LDAP attribute name (case-insensitive)
     *
     * @return string|null The value or null if not present
     */
    protected function extractLdapValue(array $ldap_entry, string $ldap_attr): ?string
    {
        // LDAP attributes are case-insensitive, normalize to lowercase
        $ldap_attr_lower = strtolower($ldap_attr);

        foreach ($ldap_entry as $key => $value) {
            if (strtolower($key) === $ldap_attr_lower) {
                if (is_array($value) && isset($value[0])) {
                    return (string) $value[0];
                }
                if (is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Set a value in a nested array using dot notation.
     *
     * @param array  $array Reference to the array to modify
     * @param string $path  Dot-notation path (e.g., 'hardware.name')
     * @param mixed  $value Value to set
     *
     * @return void
     */
    protected function setNestedValue(array &$array, string $path, $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;
    }

    /**
     * Generate a device ID for an LDAP entry.
     *
     * Format: advancedldap-{syncfilter_id}-{identifier}
     *
     * @param int         $syncfilter_id SyncFilter ID
     * @param array       $ldap_entry    LDAP entry
     * @param string|null $guid_attr     LDAP attribute for GUID (e.g., 'objectGUID')
     *
     * @return string Unique device identifier
     */
    public static function generateDeviceId(int $syncfilter_id, array $ldap_entry, ?string $guid_attr = null): string
    {
        $identifier = null;

        // Try to use GUID attribute if provided
        if ($guid_attr !== null) {
            foreach ($ldap_entry as $key => $value) {
                if (strtolower($key) === strtolower($guid_attr)) {
                    $raw = is_array($value) ? ($value[0] ?? null) : $value;
                    if ($raw !== null) {
                        // Convert binary GUID to hex string if needed
                        $identifier = bin2hex($raw);
                        break;
                    }
                }
            }
        }

        // Fallback to DN hash
        if ($identifier === null) {
            $dn = $ldap_entry['dn'] ?? $ldap_entry['distinguishedname'] ?? '';
            if (is_array($dn)) {
                $dn = $dn[0] ?? '';
            }
            $identifier = md5($dn);
        }

        return sprintf('advancedldap-%d-%s', $syncfilter_id, $identifier);
    }
}
