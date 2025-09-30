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
}
