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
 * the Free Software Foundation; either version 2 of the License, or
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
 * @copyright Copyright (C) 2024-2025 by AdvancedLDAP plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Services;

use GlpiPlugin\Advancedldap\Models\SyncFilter;

/**
 * Service for validating LDAP parameters
 */
class LdapParameterValidator
{
    /**
     * Validate basic LDAP parameters (base DN, filter, asset type)
     *
     * @param string $base_dn Base DN
     * @param string $filter LDAP filter
     * @param string $asset_type Asset type class name
     * @return string|null Error message or null if valid
     */
    public function validateBasicParameters(string $base_dn, string $filter, string $asset_type): ?string
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
     * Validate asset type class exists
     *
     * @param string $asset_type Asset type class name or GenericAsset_ID
     * @return string|null Error message or null if valid
     */
    public function validateAssetTypeExists(string $asset_type): ?string
    {
        // Handle generic assets (format: GenericAsset_ID)
        if (str_starts_with($asset_type, 'GenericAsset_')) {
            $asset_definition_id = (int) str_replace('GenericAsset_', '', $asset_type);
            $definition = new \Glpi\Asset\AssetDefinition();
            if (!$definition->getFromDB($asset_definition_id)) {
                return sprintf(__('Asset definition %d not found', 'advancedldap'), $asset_definition_id);
            }
            return null;
        }

        // Validate native asset type
        if (!class_exists($asset_type)) {
            return sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type);
        }

        return null;
    }

    /**
     * Validate a SyncFilter object has all required fields
     *
     * @param SyncFilter $sync_filter The sync filter to validate
     * @return string|null Error message or null if valid
     */
    public function validateSyncFilter(SyncFilter $sync_filter): ?string
    {
        // Validate basic parameters
        $error = $this->validateBasicParameters(
            $sync_filter->getField('base_dn') ?? '',
            $sync_filter->getField('ldap_filter') ?? '',
            $sync_filter->getField('asset_type') ?? ''
        );

        if ($error !== null) {
            return $error;
        }

        // Validate field mappings exist
        $field_mappings = $sync_filter->getFieldMappings();
        if (empty($field_mappings)) {
            return __('Field mappings are required', 'advancedldap');
        }

        // Validate asset type exists
        $asset_type = $sync_filter->getField('asset_type');
        return $this->validateAssetTypeExists($asset_type);
    }

    /**
     * Validate LDAP connection parameters
     *
     * @param string $host LDAP host
     * @param int $port LDAP port
     * @param string $base_dn Base DN
     * @return string|null Error message or null if valid
     */
    public function validateConnectionParameters(string $host, int $port, string $base_dn): ?string
    {
        if (empty($host)) {
            return __('LDAP host is required', 'advancedldap');
        }

        if ($port <= 0 || $port > 65535) {
            return __('Invalid LDAP port', 'advancedldap');
        }

        if (empty($base_dn)) {
            return __('Base DN is required', 'advancedldap');
        }

        return null;
    }

    /**
     * Validate field mappings array
     *
     * @param array<int, array<string, mixed>> $field_mappings Field mappings array
     * @return string|null Error message or null if valid
     */
    public function validateFieldMappings(array $field_mappings): ?string
    {
        if (empty($field_mappings)) {
            return __('Field mappings are required', 'advancedldap');
        }

        foreach ($field_mappings as $mapping) {
            if (empty($mapping['ldap_field']) || empty($mapping['glpi_field'])) {
                return __('Invalid field mapping: both LDAP and GLPI fields are required', 'advancedldap');
            }
        }

        return null;
    }
}