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

namespace GlpiPlugin\Advancedldap\Tests;

use DbTestCase;
use GlpiPlugin\Advancedldap\Service\FieldMappingService;
use GlpiPlugin\Advancedldap\SyncFilter;

/**
 * @method void assertTrue($condition, string $message = '')
 * @method void assertFalse($condition, string $message = '')
 * @method void assertEquals($expected, $actual, string $message = '')
 * @method void assertIsArray($actual, string $message = '')
 * @method void assertNotEmpty($actual, string $message = '')
 * @method void assertCount($expectedCount, $haystack, string $message = '')
 * @method void assertArrayHasKey($key, $array, string $message = '')
 */
final class FieldMappingServiceTest extends DbTestCase
{
    public function testGetMapping(): void
    {
        $service = new FieldMappingService();

        $mappings = [
            'name' => 'cn',
            'serial' => 'serialNumber',
            'otherserial' => 'inventoryNumber'
        ];

        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Mapping Filter',
            'connection_filter' => '(objectClass=computer)',
            'basedn' => 'ou=computers,dc=example,dc=com',
            'itemtype' => 'Computer',
            'field_mappings' => json_encode($mappings),
        ], ['field_mappings']);

        $result = $service->getMapping($syncfilter);

        $this->assertIsArray($result);
        $this->assertEquals('cn', $result['name']);
        $this->assertEquals('serialNumber', $result['serial']);
        $this->assertEquals('inventoryNumber', $result['otherserial']);
    }

    public function testGetAvailableFields(): void
    {
        $service = new FieldMappingService();

        $fields = $service->getAvailableFields('Computer');

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('serial', $fields);
    }

    public function testCleanMappings(): void
    {
        $service = new FieldMappingService();

        $raw_mappings = [
            ['glpi_field' => 'name', 'ldap_attr' => 'cn'],
            ['glpi_field' => 'serial', 'ldap_attr' => 'serialNumber'],
            ['glpi_field' => '', 'ldap_attr' => 'someAttr'],
            ['glpi_field' => 'otherserial', 'ldap_attr' => ''],
            ['glpi_field' => 'contact', 'ldap_attr' => 'contactPerson'],
        ];

        $cleaned = $service->cleanMappings($raw_mappings);

        $this->assertIsArray($cleaned);
        $this->assertCount(3, $cleaned);
        $this->assertEquals('cn', $cleaned['name']);
        $this->assertEquals('serialNumber', $cleaned['serial']);
        $this->assertEquals('contactPerson', $cleaned['contact']);
    }
}
