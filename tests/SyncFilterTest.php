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

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Advancedldap\SyncFilter;

use function Safe\json_decode;
use function Safe\json_encode;

/**
 * @method void assertTrue($condition, string $message = '')
 * @method void assertFalse($condition, string $message = '')
 * @method void assertEquals($expected, $actual, string $message = '')
 * @method void assertNotFalse($condition, string $message = '')
 * @method void assertNotEmpty($actual, string $message = '')
 * @method void assertIsArray($actual, string $message = '')
 * @method void assertGreaterThan($expected, $actual, string $message = '')
 */
final class SyncFilterTest extends DbTestCase
{
    public function testCreate(): void
    {
        $totalSyncFilters = countElementsInTable(SyncFilter::getTable());

        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=computer)',
            'basedn' => 'ou=computers,dc=example,dc=com',
            'itemtype' => 'Computer',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $this->assertNotFalse($syncfilter_id);
        $this->assertGreaterThan(0, $syncfilter_id);
        $this->assertEquals($totalSyncFilters + 1, countElementsInTable(SyncFilter::getTable()));
    }

    public function testRead(): void
    {
        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Read Filter',
            'connection_filter' => '(objectClass=user)',
            'basedn' => 'ou=users,dc=example,dc=com',
            'itemtype' => 'User',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $loadedfilter = new SyncFilter();
        $result = $loadedfilter->getFromDB($syncfilter_id);

        $this->assertTrue($result);
        $this->assertEquals($syncfilter_id, $loadedfilter->getID());
        $this->assertEquals('Test Read Filter', $loadedfilter->getField('name'));
        $this->assertEquals('(objectClass=user)', $loadedfilter->getField('connection_filter'));
        $this->assertEquals('ou=users,dc=example,dc=com', $loadedfilter->getField('basedn'));
        $this->assertEquals('User', $loadedfilter->getField('itemtype'));
    }

    public function testUpdate(): void
    {
        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Original Name',
            'connection_filter' => '(objectClass=printer)',
            'basedn' => 'ou=printers,dc=example,dc=com',
            'itemtype' => 'Printer',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $this->updateItem(SyncFilter::class, $syncfilter_id, [
            'name' => 'Updated Name',
            'connection_filter' => '(objectClass=networkPrinter)',
            'basedn' => 'ou=network-printers,dc=example,dc=com',
        ]);

        $updatedfilter = new SyncFilter();
        $updatedfilter->getFromDB($syncfilter_id);

        $this->assertEquals('Updated Name', $updatedfilter->getField('name'));
        $this->assertEquals('(objectClass=networkPrinter)', $updatedfilter->getField('connection_filter'));
        $this->assertEquals('ou=network-printers,dc=example,dc=com', $updatedfilter->getField('basedn'));
        $this->assertEquals('Printer', $updatedfilter->getField('itemtype'));
    }

    public function testDelete(): void
    {
        $totalSyncFilters = countElementsInTable(SyncFilter::getTable());

        $syncfilter = new SyncFilter();
        $syncfilter_id = $syncfilter->add([
            'name' => 'Filter to Delete',
            'connection_filter' => '(objectClass=monitor)',
            'basedn' => 'ou=monitors,dc=example,dc=com',
            'itemtype' => 'Monitor',
        ]);

        $this->assertNotFalse($syncfilter_id);
        /** @var int $syncfilter_id */
        $this->assertEquals($totalSyncFilters + 1, countElementsInTable(SyncFilter::getTable()));

        $result = $syncfilter->delete(['id' => $syncfilter_id]);

        $this->assertTrue($result);
        $this->assertEquals($totalSyncFilters, countElementsInTable(SyncFilter::getTable()));

        $deletedfilter = new SyncFilter();
        $loadResult = $deletedfilter->getFromDB($syncfilter_id);
        $this->assertFalse($loadResult);
    }

    public function testUpdateWithMappings(): void
    {
        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Mapping Update',
            'connection_filter' => '(objectClass=computer)',
            'basedn' => 'ou=computers,dc=example,dc=com',
            'itemtype' => 'Computer',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $result = $syncfilter->update([
            'id' => $syncfilter_id,
            'field_mappings' => json_encode([
                'name' => 'cn',
                'serial' => 'serialNumber',
            ]),
        ]);
        $this->assertTrue($result);

        $updatedfilter = new SyncFilter();
        $updatedfilter->getFromDB($syncfilter_id);

        /** @var string $field_mappings */
        $field_mappings = $updatedfilter->getField('field_mappings');
        $this->assertNotEmpty($field_mappings);

        /** @var array<string, string> $decoded */
        $decoded = json_decode($field_mappings, true);
        $this->assertIsArray($decoded);
        $this->assertEquals('cn', $decoded['name']);
        $this->assertEquals('serialNumber', $decoded['serial']);
    }
}
