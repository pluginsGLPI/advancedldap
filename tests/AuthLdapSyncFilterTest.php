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
    /**
     * Test la création d'une relation valide entre AuthLDAP et SyncFilter
     */
    public function testCreateRelation(): void
    {
        // Créer un AuthLDAP de test
        $authldap = new AuthLDAP();
        $authldap_id = $authldap->add([
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);
        $this->assertGreaterThan(0, $authldap_id);

        // Créer un SyncFilter de test
        $syncFilter = new SyncFilter();
        $syncfilter_id = $syncFilter->add([
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=computer)',
            'basedn' => 'ou=computers,dc=example,dc=com',
            'itemtype' => 'Computer',
        ]);
        $this->assertGreaterThan(0, $syncfilter_id);

        // Créer la relation
        $relation = new AuthLdapSyncFilter();
        $relation_id = $relation->add([
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);

        $this->assertNotFalse($relation_id);
        $this->assertGreaterThan(0, $relation_id);

        // Vérifier que la relation existe bien en base
        $this->assertEquals(1, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['authldap_id' => $authldap_id, 'syncfilter_id' => $syncfilter_id],
        ));
    }

    /**
     * Test la détection des doublons - ne doit pas créer deux fois la même relation
     */
    public function testPreventDuplicateRelation(): void
    {
        // Créer un AuthLDAP de test
        $authldap = new AuthLDAP();
        $authldap_id = $authldap->add([
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);

        // Créer un SyncFilter de test
        $syncFilter = new SyncFilter();
        $syncfilter_id = $syncFilter->add([
            'name' => 'Test Sync Filter',
            'connection_filter' => '(objectClass=user)',
            'basedn' => 'ou=users,dc=example,dc=com',
            'itemtype' => 'User',
        ]);

        // Créer la première relation
        $relation1 = new AuthLdapSyncFilter();
        $relation1_id = $relation1->add([
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);
        $this->assertGreaterThan(0, $relation1_id);

        // Tenter de créer une relation identique (doit échouer)
        $relation2 = new AuthLdapSyncFilter();
        $relation2_id = $relation2->add([
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ]);

        $this->assertFalse($relation2_id, 'La création d\'une relation en doublon devrait échouer');

        // Vérifier que le message d'erreur approprié a été ajouté
        $this->hasSessionMessages(ERROR, ['Relationship already exists']);

        // Vérifier qu'il n'y a toujours qu'une seule relation
        $this->assertEquals(1, countElementsInTable(
            AuthLdapSyncFilter::getTable(),
            ['authldap_id' => $authldap_id, 'syncfilter_id' => $syncfilter_id],
        ));
    }
}
