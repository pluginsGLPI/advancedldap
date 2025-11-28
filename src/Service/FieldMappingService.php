<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of advancedldap.
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
 * @copyright Copyright (C) 2018-2023 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Service;

use CommonDBTM;
use GlpiPlugin\Advancedldap\SyncFilter;
use Search;

use function Safe\json_decode;

class FieldMappingService
{
    /**
     * Get field mappings for a sync filter
     *
     * @param SyncFilter $syncfilter The sync filter instance
     * @return array<string, string> Associative array of GLPI field => LDAP attribute mappings
     */
    public function getMapping(SyncFilter $syncfilter): array
    {
        $mappings_json = $syncfilter->fields['field_mappings'] ?? null;

        if (empty($mappings_json) || !is_string($mappings_json)) {
            return $this->getDefaultMapping();
        }

        $mappings = json_decode($mappings_json, true);

        if (!is_array($mappings)) {
            return $this->getDefaultMapping();
        }

        // Ensure all keys and values are strings
        $result = [];
        foreach ($mappings as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Get default field mapping preset
     *
     * @return array<string, string> Default field mappings
     */
    public function getDefaultMapping(): array
    {
        return [
            'name' => '',
            'serial' => '',
        ];
    }

    /**
     * Get available fields for a given itemtype
     *
     * @param string $itemtype The GLPI itemtype
     * @return array<string, string> Associative array of field_name => field_label
     */
    public function getAvailableFields(string $itemtype): array
    {
        if (!class_exists($itemtype) || !is_subclass_of($itemtype, CommonDBTM::class)) {
            return [];
        }

        /** @var class-string<CommonDBTM> $itemtype */
        $search_options = Search::getOptions($itemtype);
        $available_fields = [];

        $main_table = getTableForItemType($itemtype);

        foreach ($search_options as $option) {
            if (!is_array($option)) {
                continue;
            }

            if (!isset($option['field']) || !isset($option['table']) || !isset($option['name'])) {
                continue;
            }

            if ($option['table'] !== $main_table) {
                continue;
            }

            $field = $option['field'];
            if (!is_string($field)) {
                continue;
            }

            if (in_array($field, ['id', 'entities_id', 'is_recursive', 'is_deleted', 'is_template'])) {
                continue;
            }

            $name = $option['name'];
            if (!is_string($name)) {
                continue;
            }
            $available_fields[$field] = $name;
        }

        asort($available_fields);

        return $available_fields;
    }

    /**
     * Validate and clean field mappings
     *
     * @param array<int, array{glpi_field?: string, ldap_attr?: string}> $mappings Raw mappings from form
     * @return array<string, string> Cleaned mappings ready for storage
     */
    public function cleanMappings(array $mappings): array
    {
        $cleaned = [];

        foreach ($mappings as $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            $glpi_field = $mapping['glpi_field'] ?? '';
            $ldap_attr = $mapping['ldap_attr'] ?? '';

            if (empty($glpi_field) || empty($ldap_attr) || !is_string($glpi_field) || !is_string($ldap_attr)) {
                continue;
            }

            $cleaned[$glpi_field] = $ldap_attr;
        }

        return $cleaned;
    }

    /**
     * Convert mappings to indexed array format for display
     *
     * @param array<string, string> $mappings Associative array of field => attribute
     * @return array<int, array{glpi_field: string, ldap_attr: string}> Indexed array of mapping objects
     */
    public function mappingsToIndexedArray(array $mappings): array
    {
        $indexed = [];

        foreach ($mappings as $glpi_field => $ldap_attr) {
            $indexed[] = [
                'glpi_field' => (string) $glpi_field,
                'ldap_attr' => (string) $ldap_attr,
            ];
        }

        // Add empty row for new mapping if less than 10 mappings
        if (count($indexed) < 10) {
            $indexed[] = [
                'glpi_field' => '',
                'ldap_attr' => '',
            ];
        }

        return $indexed;
    }
}
