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

namespace GlpiPlugin\Advancedldap\Tests\Inventory;

use Computer;
use Glpi\Inventory\Inventory;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Advancedldap\Inventory\ComputerBuilder;

/**
 * @method void assertTrue($condition, string $message = '')
 * @method void assertFalse($condition, string $message = '')
 * @method void assertEquals($expected, $actual, string $message = '')
 * @method void assertIsArray($actual, string $message = '')
 * @method void assertArrayHasKey($key, $array, string $message = '')
 */
final class ComputerBuilderTest extends DbTestCase
{
    public function testBuildProducesValidStructure(): void
    {
        $builder = new ComputerBuilder();
        $device_id = 'advancedldap-1-abc123';
        $ldap_entry = [
            'cn' => ['PC001'],
            'serialnumber' => ['ABC123'],
        ];
        $field_mappings = [
            'name' => 'cn',
            'serial' => 'serialnumber',
        ];

        $result = $builder->build($device_id, $ldap_entry, $field_mappings);

        // Base structure
        $this->assertIsArray($result);
        $this->assertEquals('advancedldap-1-abc123', $result['deviceid']);
        $this->assertEquals(Computer::class, $result['itemtype']);
        $this->assertEquals('inventory', $result['action']);
        $this->assertArrayHasKey('content', $result);

        // Content sections
        $this->assertArrayHasKey('versionclient', $result['content']);
        $this->assertArrayHasKey('hardware', $result['content']);
        $this->assertArrayHasKey('bios', $result['content']);
    }

    public function testBuildMapsFieldsCorrectly(): void
    {
        $builder = new ComputerBuilder();
        $device_id = 'advancedldap-1-test';
        $ldap_entry = [
            'cn' => ['TestComputer'],
            'serialnumber' => ['SN123456'],
            'objectguid' => ['uuid-value'],
        ];
        $field_mappings = [
            'name' => 'cn',
            'serial' => 'serialnumber',
            'uuid' => 'objectguid',
        ];

        $result = $builder->build($device_id, $ldap_entry, $field_mappings);

        // Hardware section mappings
        $this->assertEquals('TestComputer', $result['content']['hardware']['name']);
        $this->assertEquals('uuid-value', $result['content']['hardware']['uuid']);

        // Bios section mappings
        $this->assertEquals('SN123456', $result['content']['bios']['ssn']);
    }

    public function testInventoryAcceptsBuilderOutput(): void
    {
        $builder = new ComputerBuilder();
        $device_id = 'advancedldap-99-inventorytest';
        $ldap_entry = [
            'cn' => ['InventoryTestPC'],
            'serialnumber' => ['INV123'],
        ];
        $field_mappings = [
            'name' => 'cn',
            'serial' => 'serialnumber',
        ];

        $inventory_data = $builder->build($device_id, $ldap_entry, $field_mappings);

        // Convert array to stdClass (required by Inventory::setData schema validation)
        $json_data = json_decode(json_encode($inventory_data));

        $inventory = new Inventory();
        $result = $inventory->setData($json_data);

        $this->assertTrue(
            $result,
            'Inventory::setData should return true. Errors: ' . implode(', ', $inventory->getErrors())
        );
    }
}
