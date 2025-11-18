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
use AuthLDAP;
use GlpiPlugin\Advancedldap\SyncFilter;
use GlpiPlugin\Advancedldap\AuthLdapSyncFilter;

/**
 * @method void assertTrue($condition, string $message = '')
 * @method void assertFalse($condition, string $message = '')
 * @method void assertEquals($expected, $actual, string $message = '')
 * @method void assertNotFalse($condition, string $message = '')
 * @method void assertGreaterThan($expected, $actual, string $message = '')
 */
final class AuthLdapSyncFilterTest extends DbTestCase
{
    public function testCreateRelation(): void
    {
        $authldap = $this->createItem(AuthLDAP::class, [
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);
        $authldap_id = $authldap->getID();

        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=computer)',
            'basedn' => 'ou=computers,dc=example,dc=com',
            'itemtype' => 'Computer',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $relation = $this->createItem(AuthLdapSyncFilter::class, [
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);
        $relation->getID();

        $this->assertEquals(1, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['authldap_id' => $authldap_id, 'syncfilter_id' => $syncfilter_id],
        ));
    }

    public function testPreventDuplicateRelation(): void
    {
        $authldap = $this->createItem(AuthLDAP::class, [
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);
        $authldap_id = $authldap->getID();

        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=user)',
            'basedn' => 'ou=users,dc=example,dc=com',
            'itemtype' => 'User',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $this->createItem(AuthLdapSyncFilter::class, [
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);

        $relation2 = new AuthLdapSyncFilter();
        $relation2_id = $relation2->add([
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);

        $this->assertFalse($relation2_id);

        $this->hasSessionMessages(ERROR, ['Relationship already exists']);

        $this->assertEquals(1, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['authldap_id' => $authldap_id, 'syncfilter_id' => $syncfilter_id],
        ));
    }

    public function testPurgeAuthLdapCleansRelations(): void
    {
        $authldap = $this->createItem(AuthLDAP::class, [
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);
        $authldap_id = $authldap->getID();

        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=computer)',
            'basedn' => 'ou=computers,dc=example,dc=com',
            'itemtype' => 'Computer',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $this->createItem(AuthLdapSyncFilter::class, [
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);

        $this->assertEquals(1, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['authldap_id' => $authldap_id],
        ));

        $this->deleteItem(AuthLDAP::class, $authldap_id, true);

        $this->assertEquals(0, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['authldap_id' => $authldap_id],
        ));
    }

    public function testPurgeSyncFilterCleansRelations(): void
    {
        $syncfilter = $this->createItem(SyncFilter::class, [
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=user)',
            'basedn' => 'ou=users,dc=example,dc=com',
            'itemtype' => 'User',
        ]);
        $syncfilter_id = $syncfilter->getID();

        $authldap = $this->createItem(AuthLDAP::class, [
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);
        $authldap_id = $authldap->getID();

        $this->createItem(AuthLdapSyncFilter::class, [
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);

        $this->assertEquals(1, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['syncfilter_id' => $syncfilter_id],
        ));

        $this->deleteItem(SyncFilter::class, $syncfilter_id, true);

        $this->assertEquals(0, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['syncfilter_id' => $syncfilter_id],
        ));
    }
}
