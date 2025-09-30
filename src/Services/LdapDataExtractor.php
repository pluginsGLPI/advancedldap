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
 */

namespace GlpiPlugin\Advancedldap\Services;

/**
 * Service for extracting and normalizing data from LDAP entries
 *
 * Centralizes common LDAP data extraction logic to avoid duplication
 * across different services.
 */
class LdapDataExtractor
{
    /**
     * Common LDAP attributes used for device/asset naming
     * Ordered by priority
     */
    private const NAME_ATTRIBUTES = [
        'cn',
        'displayName',
        'name',
        'displayname',
        'sAMAccountName',
        'samaccountname',
        'uid',
        'hostname'
    ];

    /**
     * Extract device/asset name from LDAP entry
     *
     * Tries multiple common LDAP naming attributes in order of priority
     * and returns the first non-empty value found.
     *
     * @param array $ldap_entry LDAP entry data
     * @param string|null $fallback Fallback value if no name found (default: 'Unknown Device')
     * @return string Extracted device name or fallback
     */
    public function extractDeviceName(array $ldap_entry, ?string $fallback = 'Unknown Device'): string
    {
        foreach (self::NAME_ATTRIBUTES as $attribute) {
            $normalized_attr = strtolower($attribute);

            if (isset($ldap_entry[$normalized_attr])) {
                $value = $this->normalizeValue($ldap_entry[$normalized_attr]);
                if (!empty($value)) {
                    return (string) $value;
                }
            }
        }

        return $fallback ?? 'Unknown Device';
    }

    /**
     * Extract asset data from LDAP entry using field mappings
     *
     * Maps LDAP attributes to GLPI fields according to provided mappings.
     * Automatically ensures a 'name' field is present using extractDeviceName()
     * as fallback.
     *
     * @param array $ldap_entry LDAP entry data
     * @param array $field_mappings Mapping of GLPI field => LDAP attribute
     * @return array Extracted asset data with GLPI field names as keys
     */
    public function extractAssetData(array $ldap_entry, array $field_mappings): array
    {
        $asset_data = [];

        foreach ($field_mappings as $glpi_field => $ldap_attribute) {
            // Normalize LDAP attribute name to lowercase (PHP ldap_get_entries normalizes all keys)
            $normalized_ldap_attribute = strtolower($ldap_attribute);

            if (isset($ldap_entry[$normalized_ldap_attribute])) {
                $ldap_value = $ldap_entry[$normalized_ldap_attribute];
                $asset_data[$glpi_field] = $this->normalizeValue($ldap_value, true);
            }
        }

        // Ensure we have at least a name
        if (empty($asset_data['name'])) {
            $asset_data['name'] = $this->extractDeviceName($ldap_entry);
        }

        return $asset_data;
    }

    /**
     * Normalize LDAP value
     *
     * Handles LDAP array values by extracting the first element
     * or joining multiple values with commas.
     *
     * @param mixed $value LDAP value (can be scalar or array)
     * @param bool $multi_value_join If true, join multiple array values with comma.
     *                               If false, return only first value (default: false)
     * @return mixed Normalized value
     */
    public function normalizeValue($value, bool $multi_value_join = false)
    {
        if (!is_array($value)) {
            return $value;
        }

        // Remove LDAP count metadata
        if (isset($value['count'])) {
            unset($value['count']);
        }

        // Empty array after removing count
        if (empty($value)) {
            return '';
        }

        // Single value
        if (count($value) === 1) {
            return $value[0];
        }

        // Multiple values
        if ($multi_value_join) {
            return implode(', ', $value);
        }

        return $value[0];
    }

    /**
     * Check if LDAP entry has a specific attribute with a non-empty value
     *
     * @param array $ldap_entry LDAP entry data
     * @param string $attribute Attribute name (case-insensitive)
     * @return bool True if attribute exists and has a non-empty value
     */
    public function hasAttribute(array $ldap_entry, string $attribute): bool
    {
        $normalized = strtolower($attribute);

        if (!isset($ldap_entry[$normalized])) {
            return false;
        }

        $value = $this->normalizeValue($ldap_entry[$normalized]);
        return !empty($value);
    }

    /**
     * Get attribute value from LDAP entry
     *
     * Convenience method that normalizes the attribute name and value
     *
     * @param array $ldap_entry LDAP entry data
     * @param string $attribute Attribute name (case-insensitive)
     * @param mixed $default Default value if attribute not found
     * @param bool $multi_value_join Join multiple values with comma
     * @return mixed Attribute value or default
     */
    public function getAttribute(array $ldap_entry, string $attribute, $default = null, bool $multi_value_join = false)
    {
        $normalized = strtolower($attribute);

        if (!isset($ldap_entry[$normalized])) {
            return $default;
        }

        return $this->normalizeValue($ldap_entry[$normalized], $multi_value_join);
    }
}