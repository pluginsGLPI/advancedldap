<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by the advancedldap plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Tests;

use AuthLDAP;
use GlpiPlugin\Advancedldap\Models\AuthLdapSyncFilter;
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use DbTestCase;

/**
 * Test SyncFilter model public methods
 */
class SyncFilterTest extends DbTestCase
{
    private SyncFilter $syncFilter;

    public function setUp(): void
    {
        parent::setUp();
        $this->syncFilter = new SyncFilter();
        $this->syncFilter->fields = [];
    }

    public function tearDown(): void
    {
        // Clear session messages to avoid "Some messages has not been handled" errors
        // This is needed because validateLdapInputs() uses Session::addMessageAfterRedirect()
        if (isset($_SESSION['MESSAGE_AFTER_REDIRECT'])) {
            $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];
        }
        parent::tearDown();
    }

    // ========== Static configuration methods ==========

    /**
     * Test getTable returns correct table name
     */
    public function testGetTable(): void
    {
        $this->assertEquals('glpi_plugin_advancedldap_syncfilters', SyncFilter::getTable());
    }

    /**
     * Test getTypeName returns correct singular name
     */
    public function testGetTypeNameSingular(): void
    {
        $result = SyncFilter::getTypeName(1);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getTypeName returns correct plural name
     */
    public function testGetTypeNamePlural(): void
    {
        $result = SyncFilter::getTypeName(2);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getType returns legacy class name
     */
    public function testGetType(): void
    {
        $this->assertEquals('PluginAdvancedldapSyncFilter', SyncFilter::getType());
    }

    /**
     * Test getSearchURL with full path
     */
    public function testGetSearchURLFull(): void
    {
        $url = SyncFilter::getSearchURL(true);
        $this->assertStringContainsString('plugins/advancedldap/front/syncfilter.php', $url);
        $this->assertStringStartsWith(GLPI_ROOT, $url);
    }

    /**
     * Test getSearchURL without full path
     */
    public function testGetSearchURLRelative(): void
    {
        $url = SyncFilter::getSearchURL(false);
        $this->assertStringContainsString('plugins/advancedldap/front/syncfilter.php', $url);
        $this->assertStringStartsNotWith(GLPI_ROOT, $url);
    }

    /**
     * Test getFormURL with full path
     */
    public function testGetFormURLFull(): void
    {
        $url = SyncFilter::getFormURL(true);
        $this->assertStringContainsString('plugins/advancedldap/front/syncfilter.form.php', $url);
        $this->assertStringStartsWith(GLPI_ROOT, $url);
    }

    /**
     * Test getFormURL without full path
     */
    public function testGetFormURLRelative(): void
    {
        $url = SyncFilter::getFormURL(false);
        $this->assertStringContainsString('plugins/advancedldap/front/syncfilter.form.php', $url);
        $this->assertStringStartsNotWith(GLPI_ROOT, $url);
    }

    /**
     * Test getIcon returns icon identifier
     */
    public function testGetIcon(): void
    {
        $icon = SyncFilter::getIcon();
        $this->assertEquals('ti ti-filter', $icon);
    }

    // ========== Search options ==========

    /**
     * Test rawSearchOptions returns proper array structure
     */
    public function testRawSearchOptions(): void
    {
        $options = $this->syncFilter->rawSearchOptions();

        $this->assertIsArray($options);
        $this->assertNotEmpty($options);

        // Check that we have both the 'common' group and actual field options
        // Note: IDs can be strings or integers depending on GLPI version
        $ids = array_column($options, 'id');
        $this->assertContains('common', $ids); // common group
        $this->assertTrue(in_array('1', $ids) || in_array(1, $ids)); // name
        $this->assertTrue(in_array('2', $ids) || in_array(2, $ids)); // ldap_filter
        $this->assertTrue(in_array('3', $ids) || in_array(3, $ids)); // base_dn
        $this->assertTrue(in_array('4', $ids) || in_array(4, $ids)); // asset_type
        $this->assertTrue(in_array('5', $ids) || in_array(5, $ids)); // is_active
    }

    // ========== Field mappings methods ==========

    /**
     * Test getFieldMappings with valid JSON
     */
    public function testGetFieldMappingsWithValidJson(): void
    {
        $expectedMappings = [
            'name' => 'cn',
            'serial' => 'serialNumber',
            'comment' => 'description',
        ];

        $this->syncFilter->fields = [
            'field_mappings' => json_encode($expectedMappings),
        ];

        $result = $this->syncFilter->getFieldMappings();
        $this->assertEquals($expectedMappings, $result);
    }

    /**
     * Test getFieldMappings with empty field_mappings
     */
    public function testGetFieldMappingsWithEmptyMappings(): void
    {
        $this->syncFilter->fields = ['field_mappings' => ''];

        $result = $this->syncFilter->getFieldMappings();
        $this->assertEquals([], $result);
    }

    /**
     * Test getFieldMappings with invalid JSON
     */
    public function testGetFieldMappingsWithInvalidJson(): void
    {
        $this->syncFilter->fields = ['field_mappings' => 'invalid-json'];

        // No log will be generated because getID() returns 0 by default
        $result = $this->syncFilter->getFieldMappings();
        $this->assertEquals([], $result);
    }

    /**
     * Test getFieldMappings when field_mappings is not set
     */
    public function testGetFieldMappingsWhenFieldNotSet(): void
    {
        $this->syncFilter->fields = [];

        $result = $this->syncFilter->getFieldMappings();
        $this->assertEquals([], $result);
    }

    /**
     * Test setFieldMappings method
     */
    public function testSetFieldMappings(): void
    {
        $mappings = [
            'name' => 'cn',
            'serial' => 'serialNumber',
        ];

        $syncFilter = $this->getMockBuilder(SyncFilter::class)
            ->onlyMethods(['update', 'getID'])
            ->getMock();

        $syncFilter->expects($this->once())
            ->method('getID')
            ->willReturn(1);

        $syncFilter->expects($this->once())
            ->method('update')
            ->with([
                'id' => 1,
                'field_mappings' => json_encode($mappings),
            ])
            ->willReturn(true);

        $result = $syncFilter->setFieldMappings($mappings);
        $this->assertTrue($result);
    }

    // ========== Input preparation methods ==========

    /**
     * Test prepareInputForAdd converts field_mappings array to JSON
     * Note: Testing with asset_fields triggers LdapAttributeMapper debug logs
     * which are expected behavior, so we test the JSON conversion directly
     */
    public function testPrepareInputForAddConvertsArrayToJson(): void
    {
        $mappings = ['name' => 'cn', 'serial' => 'serialNumber'];
        $input = [
            'name' => 'Test Filter',
            'field_mappings' => $mappings,
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer',
        ];

        $result = $this->syncFilter->prepareInputForAdd($input);

        $this->assertArrayHasKey('field_mappings', $result);
        $this->assertIsString($result['field_mappings']);
        $this->assertEquals(json_encode($mappings), $result['field_mappings']);
    }

    /**
     * Test prepareInputForAdd preserves existing JSON field_mappings
     */
    public function testPrepareInputForAddPreservesJsonString(): void
    {
        $mappings = ['name' => 'cn', 'serial' => 'serialNumber'];
        $input = [
            'name' => 'Test Filter',
            'field_mappings' => json_encode($mappings),
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer',
        ];

        $result = $this->syncFilter->prepareInputForAdd($input);

        $this->assertArrayHasKey('field_mappings', $result);
        $this->assertEquals(json_encode($mappings), $result['field_mappings']);
    }

    /**
     * Test prepareInputForAdd handles empty input gracefully
     */
    public function testPrepareInputForAddWithEmptyInput(): void
    {
        $input = [
            'name' => 'Test Filter',
            'base_dn' => 'OU=Computers,DC=example,DC=com',
        ];

        $result = $this->syncFilter->prepareInputForAdd($input);

        // When no field_mappings or asset_fields provided, input is returned as-is
        $this->assertArrayNotHasKey('field_mappings', $result);
    }

    /**
     * Test prepareInputForUpdate converts field_mappings array to JSON
     */
    public function testPrepareInputForUpdateConvertsArrayToJson(): void
    {
        $mappings = ['name' => 'cn', 'serial' => 'serialNumber'];
        $input = [
            'id' => 1,
            'field_mappings' => $mappings,
            'asset_type' => 'Computer',
        ];

        $result = $this->syncFilter->prepareInputForUpdate($input);

        $this->assertArrayHasKey('field_mappings', $result);
        $this->assertIsString($result['field_mappings']);
        $this->assertEquals(json_encode($mappings), $result['field_mappings']);
    }

    // ========== Associated AuthLDAPs ==========

    /**
     * Test getAssociatedAuthLDAPs returns empty array when no ID
     */
    public function testGetAssociatedAuthLDAPsWithNoId(): void
    {
        $result = $this->syncFilter->getAssociatedAuthLDAPs();
        $this->assertEquals([], $result);
    }

    /**
     * Test getParentAuthLdapId returns null when no ID
     */
    public function testGetParentAuthLdapIdWithNoId(): void
    {
        $result = $this->syncFilter->getParentAuthLdapId();
        $this->assertNull($result);
    }

    /**
     * Test getParentAuthLdap returns null when no ID
     */
    public function testGetParentAuthLdapWithNoId(): void
    {
        $result = $this->syncFilter->getParentAuthLdap();
        $this->assertNull($result);
    }

    // ========== Massive actions ==========

    /**
     * Test getForbiddenStandardMassiveAction includes update action
     */
    public function testGetForbiddenStandardMassiveAction(): void
    {
        $forbidden = $this->syncFilter->getForbiddenStandardMassiveAction();

        $this->assertIsArray($forbidden);
        $this->assertContains('MassiveAction:update', $forbidden);
    }

    /**
     * Test getSpecificMassiveActions returns array
     */
    public function testGetSpecificMassiveActions(): void
    {
        $this->login();
        $result = $this->syncFilter->getSpecificMassiveActions();

        $this->assertIsArray($result);
    }

    // ========== Tabs definition ==========

    /**
     * Test defineTabs returns array with tabs
     */
    public function testDefineTabs(): void
    {
        $tabs = $this->syncFilter->defineTabs();

        $this->assertIsArray($tabs);
        $this->assertNotEmpty($tabs);
    }

    // ========== Rights checks ==========

    /**
     * Test canView without login
     */
    public function testCanViewWithoutLogin(): void
    {
        $this->assertFalse(SyncFilter::canView());
    }

    /**
     * Test canCreate without login
     */
    public function testCanCreateWithoutLogin(): void
    {
        $this->assertFalse(SyncFilter::canCreate());
    }

    /**
     * Test canUpdate without login
     */
    public function testCanUpdateWithoutLogin(): void
    {
        $this->assertFalse(SyncFilter::canUpdate());
    }

    /**
     * Test canDelete without login
     */
    public function testCanDeleteWithoutLogin(): void
    {
        $this->assertFalse(SyncFilter::canDelete());
    }

    /**
     * Test canPurge without login
     */
    public function testCanPurgeWithoutLogin(): void
    {
        $this->assertFalse(SyncFilter::canPurge());
    }

    /**
     * Test canView with admin login
     */
    public function testCanViewWithLogin(): void
    {
        $this->login();
        $this->assertTrue(SyncFilter::canView());
    }

    /**
     * Test canCreate with admin login
     */
    public function testCanCreateWithLogin(): void
    {
        $this->login();
        $this->assertTrue(SyncFilter::canCreate());
    }

    /**
     * Test canUpdate with admin login
     */
    public function testCanUpdateWithLogin(): void
    {
        $this->login();
        $this->assertTrue(SyncFilter::canUpdate());
    }

    /**
     * Test canDelete with admin login
     */
    public function testCanDeleteWithLogin(): void
    {
        $this->login();
        $this->assertTrue(SyncFilter::canDelete());
    }

    /**
     * Test canPurge with admin login
     */
    public function testCanPurgeWithLogin(): void
    {
        $this->login();
        $this->assertTrue(SyncFilter::canPurge());
    }

    // ========== Integration tests ==========

    /**
     * Test post_addItem creates AuthLDAP relation
     */
    public function testPostAddItemCreatesAuthLdapRelation(): void
    {
        $this->login();

        // Create an AuthLDAP server
        $authldap = new AuthLDAP();
        $authldap_id = $authldap->add([
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);
        $this->assertGreaterThan(0, $authldap_id);

        // Create a SyncFilter with authldap_id in input
        $syncFilter = new SyncFilter();
        $filter_id = $syncFilter->add([
            'name' => 'Test Filter',
            'ldap_filter' => '(objectClass=computer)',
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer',
            'is_active' => 1,
            'authldap_id' => $authldap_id,
        ]);

        $this->assertGreaterThan(0, $filter_id);

        // Verify relation was created
        $relation = new AuthLdapSyncFilter();
        $found = $relation->getFromDBByCrit([
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $filter_id,
        ]);

        $this->assertTrue($found);
        $this->assertEquals(1, $relation->fields['is_active']);
    }

    /**
     * Test post_addItem without authldap_id does not create relation
     */
    public function testPostAddItemWithoutAuthLdapId(): void
    {
        $this->login();

        // Create a SyncFilter without authldap_id
        $syncFilter = new SyncFilter();
        $filter_id = $syncFilter->add([
            'name' => 'Test Filter No LDAP',
            'ldap_filter' => '(objectClass=computer)',
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer',
            'is_active' => 1,
        ]);

        $this->assertGreaterThan(0, $filter_id);

        // Verify no relation was created
        global $DB;
        $iterator = $DB->request([
            'FROM' => AuthLdapSyncFilter::getTable(),
            'WHERE' => ['syncfilter_id' => $filter_id],
        ]);

        $this->assertEquals(0, count($iterator));
    }

    /**
     * Test pre_deleteItem deletes AuthLDAP relations
     */
    public function testPreDeleteItemDeletesRelations(): void
    {
        $this->login();

        // Create an AuthLDAP server
        $authldap = new AuthLDAP();
        $authldap_id = $authldap->add([
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);
        $this->assertGreaterThan(0, $authldap_id);

        // Create a SyncFilter with relation
        $syncFilter = new SyncFilter();
        $filter_id = $syncFilter->add([
            'name' => 'Test Filter',
            'ldap_filter' => '(objectClass=computer)',
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer',
            'is_active' => 1,
            'authldap_id' => $authldap_id,
        ]);

        $this->assertGreaterThan(0, $filter_id);

        // Verify relation exists
        global $DB;
        $iterator = $DB->request([
            'FROM' => AuthLdapSyncFilter::getTable(),
            'WHERE' => ['syncfilter_id' => $filter_id],
        ]);
        $this->assertEquals(1, count($iterator));

        // Delete the SyncFilter
        $result = $syncFilter->delete(['id' => $filter_id]);
        $this->assertTrue($result);

        // Verify relation was deleted
        $iterator = $DB->request([
            'FROM' => AuthLdapSyncFilter::getTable(),
            'WHERE' => ['syncfilter_id' => $filter_id],
        ]);
        $this->assertEquals(0, count($iterator));
    }

    /**
     * Test getAssociatedAuthLDAPs with valid ID
     */
    public function testGetAssociatedAuthLDAPsWithValidId(): void
    {
        $this->login();

        // Create AuthLDAP servers
        $authldap1 = new AuthLDAP();
        $authldap_id1 = $authldap1->add([
            'name' => 'LDAP Server 1',
            'host' => 'ldap1.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);

        $authldap2 = new AuthLDAP();
        $authldap_id2 = $authldap2->add([
            'name' => 'LDAP Server 2',
            'host' => 'ldap2.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);

        // Create SyncFilter
        $syncFilter = new SyncFilter();
        $filter_id = $syncFilter->add([
            'name' => 'Test Filter',
            'ldap_filter' => '(objectClass=computer)',
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer',
            'is_active' => 1,
            'authldap_id' => $authldap_id1,
        ]);

        // Add second relation manually
        $relation = new AuthLdapSyncFilter();
        $relation->add([
            'authldap_id' => $authldap_id2,
            'syncfilter_id' => $filter_id,
            'is_active' => 1,
        ]);

        // Test getAssociatedAuthLDAPs
        $syncFilter->getFromDB($filter_id);
        $associated = $syncFilter->getAssociatedAuthLDAPs();

        $this->assertIsArray($associated);
        $this->assertCount(2, $associated);
        $this->assertContains($authldap_id1, $associated);
        $this->assertContains($authldap_id2, $associated);
    }

    // ========== LDAP Validation Tests (RFC 4515/4514) ==========

    /**
     * Test prepareInputForAdd rejects invalid LDAP Base DN
     */
    public function testPrepareInputForAddRejectsInvalidBaseDN(): void
    {
        $this->login();

        $input = [
            'name' => 'Test Filter',
            'base_dn' => 'invalid)(dn=injection',  // Injection attempt
            'ldap_filter' => '(objectClass=person)',
            'asset_type' => 'Computer',
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForAdd($input);

        // Validation should reject this input
        $this->assertFalse($result);
    }

    /**
     * Test prepareInputForAdd rejects invalid LDAP filter
     */
    public function testPrepareInputForAddRejectsInvalidFilter(): void
    {
        $this->login();

        $input = [
            'name' => 'Test Filter',
            'base_dn' => 'ou=users,dc=example,dc=com',
            'ldap_filter' => '(uid=admin',  // Unbalanced parentheses - truly invalid
            'asset_type' => 'Computer',
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForAdd($input);

        // Validation should reject this input
        $this->assertFalse($result);
    }

    /**
     * Test prepareInputForAdd accepts valid LDAP inputs
     */
    public function testPrepareInputForAddAcceptsValidLdapInputs(): void
    {
        $this->login();

        $input = [
            'name' => 'Test Filter',
            'base_dn' => 'ou=computers,dc=example,dc=com',
            'ldap_filter' => '(&(objectClass=computer)(cn=*))',
            'asset_type' => 'Computer',
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForAdd($input);

        // Valid inputs should be accepted
        $this->assertIsArray($result);
        $this->assertEquals('ou=computers,dc=example,dc=com', $result['base_dn']);
        $this->assertEquals('(&(objectClass=computer)(cn=*))', $result['ldap_filter']);
    }

    /**
     * Test prepareInputForAdd validates only if base_dn is provided
     */
    public function testPrepareInputForAddSkipsValidationWithoutBaseDN(): void
    {
        $this->login();

        $input = [
            'name' => 'Test Filter',
            'ldap_filter' => '(objectClass=computer)',
            'asset_type' => 'Computer',
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForAdd($input);

        // Should not fail if base_dn is not provided
        $this->assertIsArray($result);
    }

    /**
     * Test prepareInputForUpdate rejects invalid LDAP Base DN
     */
    public function testPrepareInputForUpdateRejectsInvalidBaseDN(): void
    {
        $this->login();

        $input = [
            'id' => 1,
            'base_dn' => 'malicious)(uid=*',  // Injection attempt
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForUpdate($input);

        // Validation should reject this input
        $this->assertFalse($result);
    }

    /**
     * Test prepareInputForUpdate rejects invalid LDAP filter
     */
    public function testPrepareInputForUpdateRejectsInvalidFilter(): void
    {
        $this->login();

        $input = [
            'id' => 1,
            'ldap_filter' => '((uid=*',  // Unbalanced parentheses
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForUpdate($input);

        // Validation should reject this input
        $this->assertFalse($result);
    }

    /**
     * Test prepareInputForUpdate accepts valid LDAP inputs
     */
    public function testPrepareInputForUpdateAcceptsValidLdapInputs(): void
    {
        $this->login();

        $input = [
            'id' => 1,
            'base_dn' => 'ou=people,dc=test,dc=org',
            'ldap_filter' => '(|(uid=*)(mail=*))',
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForUpdate($input);

        // Valid inputs should be accepted
        $this->assertIsArray($result);
        $this->assertEquals('ou=people,dc=test,dc=org', $result['base_dn']);
        $this->assertEquals('(|(uid=*)(mail=*))', $result['ldap_filter']);
    }

    /**
     * Test prepareInputForUpdate sanitizes and returns validated filter
     */
    public function testPrepareInputForUpdateSanitizesFilter(): void
    {
        $this->login();

        $input = [
            'id' => 1,
            'base_dn' => 'ou=users,dc=example,dc=com',
            'ldap_filter' => '(uid=test)',  // Valid filter
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForUpdate($input);

        // Filter should be sanitized and returned
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ldap_filter', $result);
        $this->assertStringContainsString('uid=test', $result['ldap_filter']);
    }

    /**
     * Test prepareInputForAdd rejects DN with LDAP metacharacters
     */
    public function testPrepareInputForAddRejectsDNWithMetacharacters(): void
    {
        $this->login();

        $input = [
            'name' => 'Test Filter',
            'base_dn' => 'ou=users*,dc=example,dc=com',  // Wildcard in DN
            'ldap_filter' => '(objectClass=person)',
            'asset_type' => 'Computer',
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForAdd($input);

        // Should reject DN with metacharacters
        $this->assertFalse($result);
    }

    /**
     * Test prepareInputForUpdate with empty base_dn and filter (no validation)
     */
    public function testPrepareInputForUpdateWithEmptyFields(): void
    {
        $this->login();

        $input = [
            'id' => 1,
            'name' => 'Updated Filter Name',
            'base_dn' => '',
            'ldap_filter' => '',
        ];

        $syncFilter = new SyncFilter();
        $result = $syncFilter->prepareInputForUpdate($input);

        // Empty values should not trigger validation (skip validation)
        $this->assertIsArray($result);
        $this->assertEquals('Updated Filter Name', $result['name']);
    }

}
