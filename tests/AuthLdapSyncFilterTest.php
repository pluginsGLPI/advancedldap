<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Models\AuthLdapSyncFilter;
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use DbTestCase;

class AuthLdapSyncFilterTest extends DbTestCase
{
    /**
     * Test getTable static method
     */
    public function testGetTable()
    {
        // Act
        $tableName = AuthLdapSyncFilter::getTable();

        // Assert
        $this->assertEquals('glpi_plugin_advancedldap_authldap_syncfilters', $tableName);
    }

    /**
     * Test getTable static method with classname parameter
     */
    public function testGetTableWithClassname()
    {
        // Act
        $tableName = AuthLdapSyncFilter::getTable('SomeOtherClass');

        // Assert
        $this->assertEquals('glpi_plugin_advancedldap_authldap_syncfilters', $tableName);
    }

    /**
     * Test getTypeName static method with default parameter
     */
    public function testGetTypeNameSingular()
    {
        // Act
        $typeName = AuthLdapSyncFilter::getTypeName();

        // Assert - We can't test the exact translated string, but we can verify it returns a string
        $this->assertIsString($typeName);
        $this->assertNotEmpty($typeName);
    }

    /**
     * Test getTypeName static method with singular form (nb = 1)
     */
    public function testGetTypeNameSingularExplicit()
    {
        // Act
        $typeName = AuthLdapSyncFilter::getTypeName(1);

        // Assert
        $this->assertIsString($typeName);
        $this->assertNotEmpty($typeName);
    }

    /**
     * Test getTypeName static method with plural form (nb = 2)
     */
    public function testGetTypeNamePlural()
    {
        // Act
        $typeName = AuthLdapSyncFilter::getTypeName(2);

        // Assert
        $this->assertIsString($typeName);
        $this->assertNotEmpty($typeName);
    }

    /**
     * Test class constants and static properties
     */
    public function testClassConstants()
    {
        // Assert static properties are correctly defined
        $this->assertEquals('config', AuthLdapSyncFilter::$rightname);
        $this->assertEquals('glpi_plugin_advancedldap_authldap_syncfilters', AuthLdapSyncFilter::$table);
        $this->assertEquals('AuthLDAP', AuthLdapSyncFilter::$itemtype_1);
        $this->assertEquals('authldap_id', AuthLdapSyncFilter::$items_id_1);
        $this->assertEquals(SyncFilter::class, AuthLdapSyncFilter::$itemtype_2);
        $this->assertEquals('syncfilter_id', AuthLdapSyncFilter::$items_id_2);
    }

    /**
     * Test rawSearchOptions method returns valid array structure
     */
    public function testRawSearchOptionsStructure()
    {
        // Arrange
        $authLdapSyncFilter = new AuthLdapSyncFilter();

        // Act
        $searchOptions = $authLdapSyncFilter->rawSearchOptions();

        // Assert basic structure
        $this->assertIsArray($searchOptions);
        $this->assertNotEmpty($searchOptions);

        // Check that each option has required keys
        foreach ($searchOptions as $option) {
            $this->assertIsArray($option);
            $this->assertArrayHasKey('id', $option);
            $this->assertArrayHasKey('name', $option);
        }
    }

    /**
     * Test rawSearchOptions method returns expected search option IDs
     */
    public function testRawSearchOptionsIds()
    {
        // Arrange
        $authLdapSyncFilter = new AuthLdapSyncFilter();

        // Act
        $searchOptions = $authLdapSyncFilter->rawSearchOptions();

        // Assert expected IDs are present
        $expectedIds = ['common', '1', '2', '3', '121'];
        $actualIds = array_column($searchOptions, 'id');

        foreach ($expectedIds as $expectedId) {
            $this->assertContains($expectedId, $actualIds, "Search option ID '$expectedId' should be present");
        }
    }

    /**
     * Test rawSearchOptions method returns correct table references
     */
    public function testRawSearchOptionsTableReferences()
    {
        // Arrange
        $authLdapSyncFilter = new AuthLdapSyncFilter();

        // Act
        $searchOptions = $authLdapSyncFilter->rawSearchOptions();

        // Assert specific table references for key options
        $optionsById = [];
        foreach ($searchOptions as $option) {
            if (isset($option['table'])) {
                $optionsById[$option['id']] = $option;
            }
        }

        // Check AuthLDAP reference (ID 1)
        $this->assertArrayHasKey('1', $optionsById);
        $this->assertEquals('glpi_authldaps', $optionsById['1']['table']);
        $this->assertEquals('name', $optionsById['1']['field']);

        // Check SyncFilter reference (ID 2)
        $this->assertArrayHasKey('2', $optionsById);
        $this->assertEquals(SyncFilter::getTable(), $optionsById['2']['table']);
        $this->assertEquals('name', $optionsById['2']['field']);
    }

    /**
     * Test defineTabs method returns valid array structure
     */
    public function testDefineTabsStructure()
    {
        // Arrange
        $authLdapSyncFilter = new AuthLdapSyncFilter();

        // Act
        $tabs = $authLdapSyncFilter->defineTabs();

        // Assert
        $this->assertIsArray($tabs);
        // The method should return an array (could be empty or contain tabs)
    }

    /**
     * Test defineTabs method with options parameter
     */
    public function testDefineTabsWithOptions()
    {
        // Arrange
        $authLdapSyncFilter = new AuthLdapSyncFilter();
        $options = ['test_option' => 'test_value'];

        // Act
        $tabs = $authLdapSyncFilter->defineTabs($options);

        // Assert
        $this->assertIsArray($tabs);
    }
}