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

use GlpiPlugin\Advancedldap\SyncFilter;
use Search;

class FieldMappingService
{
    /**
     * Get field mappings for a sync filter
     *
     * @param SyncFilter $syncfilter The sync filter instance
     * @return array Associative array of GLPI field => LDAP attribute mappings
     */
    public function getMapping(SyncFilter $syncfilter): array
    {
        $mappings_json = $syncfilter->fields['field_mappings'] ?? null;

        if (empty($mappings_json)) {
            return $this->getDefaultMapping();
        }

        $mappings = json_decode((string) $mappings_json, true);

        // Return default if JSON decode fails
        if (!is_array($mappings)) {
            return $this->getDefaultMapping();
        }

        return $mappings;
    }

    /**
     * Get default field mapping preset
     *
     * @return array Default field mappings
     */
    public function getDefaultMapping(): array
    {
        return [
            'name' => '',
            'serial' => ''
        ];
    }

    /**
     * Get available fields for a given itemtype
     *
     * @param string $itemtype The GLPI itemtype (Computer, Phone, Printer, NetworkEquipment)
     * @return array Associative array of field_name => field_label
     */
    public function getAvailableFields(string $itemtype): array
    {
        if (!class_exists($itemtype)) {
            return [];
        }

        $search_options = Search::getOptions($itemtype);
        $available_fields = [];

        $main_table = getTableForItemType($itemtype);

        foreach ($search_options as $option) {

            if (!isset($option['field']) || !isset($option['table']) || !isset($option['name'])) {
                continue;
            }

            if ($option['table'] !== $main_table) {
                continue;
            }

            if (in_array($option['field'], ['id', 'entities_id', 'is_recursive', 'is_deleted', 'is_template'])) {
                continue;
            }

            $available_fields[$option['field']] = $option['name'];
        }

        asort($available_fields);

        return $available_fields;
    }

    /**
     * Validate and clean field mappings
     *
     * @param array $mappings Raw mappings from form
     * @return array Cleaned mappings ready for storage
     */
    public function cleanMappings(array $mappings): array
    {
        $cleaned = [];

        foreach ($mappings as $mapping) {
            if (empty($mapping['glpi_field']) || empty($mapping['ldap_attr'])) {
                continue;
            }

            $cleaned[$mapping['glpi_field']] = $mapping['ldap_attr'];
        }

        return $cleaned;
    }

    /**
     * Convert mappings to indexed array format for display
     *
     * @param array $mappings Associative array of field => attribute
     * @return array Indexed array of mapping objects
     */
    public function mappingsToIndexedArray(array $mappings): array
    {
        $indexed = [];

        foreach ($mappings as $glpi_field => $ldap_attr) {
            $indexed[] = [
                'glpi_field' => $glpi_field,
                'ldap_attr' => $ldap_attr
            ];
        }

        // Add empty row for new mapping if less than 10 mappings
        if (count($indexed) < 10) {
            $indexed[] = [
                'glpi_field' => '',
                'ldap_attr' => ''
            ];
        }

        return $indexed;
    }
}