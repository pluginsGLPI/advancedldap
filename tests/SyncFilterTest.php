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
use GlpiPlugin\Advancedldap\SyncFilter;

/**
 * @method void assertTrue($condition, string $message = '')
 * @method void assertFalse($condition, string $message = '')
 * @method void assertEquals($expected, $actual, string $message = '')
 * @method void assertNotFalse($condition, string $message = '')
 * @method void assertGreaterThan($expected, $actual, string $message = '')
 */
final class SyncFilterTest extends DbTestCase
{
    public function testCreate(): void
    {
        $totalSyncFilters = countElementsInTable(SyncFilter::getTable());

        $syncfilter_id = $this->createItem(SyncFilter::class, [
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=computer)',
            'basedn' => 'ou=computers,dc=example,dc=com',
            'itemtype' => 'Computer',
        ]);

        $this->assertNotFalse($syncfilter_id);
        /** @var int $syncFilter_id */
        $this->assertGreaterThan(0, $syncfilter_id);
        $this->assertEquals($totalSyncFilters + 1, countElementsInTable(SyncFilter::getTable()));
    }

    public function testRead(): void
    {
        $syncfilter_id = $this->createItem(SyncFilter::class, [
            'name' => 'Test Read Filter',
            'connection_filter' => '(objectClass=user)',
            'basedn' => 'ou=users,dc=example,dc=com',
            'itemtype' => 'User',
        ]);

        $loadedFilter = new SyncFilter();
        /** @var int $syncFilter_id */
        $result = $loadedFilter->getFromDB($syncfilter_id);

        $this->assertTrue($result);
        $this->assertEquals($syncFilter_id, $loadedFilter->getID());
        $this->assertEquals('Test Read Filter', $loadedFilter->getField('name'));
        $this->assertEquals('(objectClass=user)', $loadedFilter->getField('connection_filter'));
        $this->assertEquals('ou=users,dc=example,dc=com', $loadedFilter->getField('basedn'));
        $this->assertEquals('User', $loadedFilter->getField('itemtype'));
    }

    public function testUpdate(): void
    {
        $syncfilter_id = $this->createItem(SyncFilter::class, [
            'name' => 'Original Name',
            'connection_filter' => '(objectClass=printer)',
            'basedn' => 'ou=printers,dc=example,dc=com',
            'itemtype' => 'Printer',
        ])->getID();

        $this->updateItem(SyncFilter::class, $syncfilter_id, [
            'name' => 'Updated Name',
            'connection_filter' => '(objectClass=networkPrinter)',
            'basedn' => 'ou=network-printers,dc=example,dc=com',
        ]);

        $updatedFilter = new SyncFilter();
        /** @var int $syncfilter_id */
        $updatedFilter->getFromDB($syncfilter_id);

        $this->assertEquals('Updated Name', $updatedFilter->getField('name'));
        $this->assertEquals('(objectClass=networkPrinter)', $updatedFilter->getField('connection_filter'));
        $this->assertEquals('ou=network-printers,dc=example,dc=com', $updatedFilter->getField('basedn'));
        $this->assertEquals('Printer', $updatedFilter->getField('itemtype'));
    }

    public function testDelete(): void
    {
        $totalSyncFilters = countElementsInTable(SyncFilter::getTable());

        $syncFilter = new SyncFilter();
        $syncFilter_id = $syncFilter->add([
            'name' => 'Filter to Delete',
            'connection_filter' => '(objectClass=monitor)',
            'basedn' => 'ou=monitors,dc=example,dc=com',
            'itemtype' => 'Monitor',
        ]);

        $this->assertEquals($totalSyncFilters + 1, countElementsInTable(SyncFilter::getTable()));

        $result = $syncFilter->delete(['id' => $syncFilter_id]);

        $this->assertTrue($result);
        $this->assertEquals($totalSyncFilters, countElementsInTable(SyncFilter::getTable()));

        $deletedFilter = new SyncFilter();
        /** @var int $syncFilter_id */
        $loadResult = $deletedFilter->getFromDB($syncFilter_id);
        $this->assertFalse($loadResult);
    }
}
