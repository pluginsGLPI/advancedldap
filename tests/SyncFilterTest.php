<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Models\SyncFilter;
use DbTestCase;

class SyncFilterTest extends DbTestCase
{
    private $syncFilter;

    public function setUp(): void
    {
        parent::setUp();
        $this->syncFilter = new SyncFilter();

        // Initialize the fields array to avoid null errors
        $this->syncFilter->fields = [];
    }

    /**
     * Test getFieldMappings with valid JSON
     */
    public function testGetFieldMappingsWithValidJson()
    {
        // Arrange
        $expectedMappings = [
            'name' => 'cn',
            'serial' => 'serialNumber',
            'comment' => 'description'
        ];

        $this->syncFilter->fields = [
            'field_mappings' => json_encode($expectedMappings)
        ];

        // Act
        $result = $this->syncFilter->getFieldMappings();

        // Assert
        $this->assertEquals($expectedMappings, $result);
    }

    /**
     * Test getFieldMappings with empty field_mappings
     */
    public function testGetFieldMappingsWithEmptyMappings()
    {
        // Arrange
        $this->syncFilter->fields = ['field_mappings' => ''];

        // Act
        $result = $this->syncFilter->getFieldMappings();

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getFieldMappings with invalid JSON
     */
    public function testGetFieldMappingsWithInvalidJson()
    {
        // Arrange
        $this->syncFilter->fields = ['field_mappings' => 'invalid-json'];

        // Act
        $result = $this->syncFilter->getFieldMappings();

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test prepareInputForAdd with asset_fields array
     */
    public function testPrepareInputForAddWithAssetFields()
    {
        // Arrange
        $input = [
            'name' => 'Test Filter',
            'ldap_filter' => '(&(objectClass=computer)(cn=*))',
            'asset_fields' => ['name', 'serial', 'comment'],
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer'
        ];

        // Act
        $result = $this->syncFilter->prepareInputForAdd($input);

        // Assert
        $this->assertArrayHasKey('field_mappings', $result);
        $this->assertTrue(is_string($result['field_mappings']));

        $mappings = json_decode($result['field_mappings'], true);
        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('name', $mappings);
        $this->assertArrayHasKey('serial', $mappings);
        $this->assertArrayHasKey('comment', $mappings);
    }

    /**
     * Test prepareInputForAdd with single asset_field
     */
    public function testPrepareInputForAddWithSingleAssetField()
    {
        // Arrange
        $input = [
            'name' => 'Test Filter',
            'ldap_filter' => '(&(objectClass=computer)(cn=*))',
            'asset_field' => 'name',
            'base_dn' => 'OU=Computers,DC=example,DC=com',
            'asset_type' => 'Computer'
        ];

        // Act
        $result = $this->syncFilter->prepareInputForAdd($input);

        // Assert
        $this->assertArrayHasKey('field_mappings', $result);

        $mappings = json_decode($result['field_mappings'], true);
        $this->assertEquals(['name' => 'cn'], $mappings);
    }

    /**
     * Test prepareInputForUpdate with asset_fields array
     */
    public function testPrepareInputForUpdateWithAssetFields()
    {
        // Arrange
        $input = [
            'id' => 1,
            'ldap_filter' => '(&(objectClass=computer)(serialNumber=*)(cn=*))',
            'asset_fields' => ['serial', 'name'],
            'asset_type' => 'Computer'
        ];

        // Act
        $result = $this->syncFilter->prepareInputForUpdate($input);

        // Assert
        $this->assertArrayHasKey('field_mappings', $result);

        $mappings = json_decode($result['field_mappings'], true);
        $this->assertArrayHasKey('serial', $mappings);
        $this->assertArrayHasKey('name', $mappings);
        $this->assertEquals('serialNumber', $mappings['serial']);
        $this->assertEquals('cn', $mappings['name']);
    }

    /**
     * Test setFieldMappings method
     */
    public function testSetFieldMappings()
    {
        // Arrange
        $mappings = [
            'name' => 'cn',
            'serial' => 'serialNumber'
        ];

        // We need to mock the update method since we can't test database operations
        // This test focuses on the data preparation logic
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
                'field_mappings' => json_encode($mappings)
            ])
            ->willReturn(true);

        // Act
        $result = $syncFilter->setFieldMappings($mappings);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test LDAP filter attribute parsing
     */
    public function testParseLdapFilterAttributes()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass(SyncFilter::class);
        $method = $reflection->getMethod('parseLdapFilterAttributes');
        $method->setAccessible(true);

        // Test various LDAP filter patterns
        $testCases = [
            '(&(objectClass=computer)(cn=*))' => ['objectClass', 'cn'],
            '(|(serialNumber=*)(name=*))' => ['serialNumber', 'name'],
            '(&(!(disabled=*))(mail=*))'  => ['disabled', 'mail'],
            '(description=test)'          => ['description']
        ];

        foreach ($testCases as $filter => $expectedAttributes) {
            $result = $method->invoke($this->syncFilter, $filter);
            $this->assertEquals($expectedAttributes, array_values($result),
                "Failed for filter: $filter");
        }
    }

    /**
     * Test GLPI field to LDAP attribute mapping
     */
    public function testFindMatchingLdapAttribute()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass(SyncFilter::class);
        $method = $reflection->getMethod('findMatchingLdapAttribute');
        $method->setAccessible(true);

        $availableAttributes = ['cn', 'serialNumber', 'description', 'mail', 'telephoneNumber'];

        $testCases = [
            'name' => 'cn',
            'serial' => 'serialNumber',
            'comment' => 'description',
            'email' => 'mail',
            'phone' => 'telephoneNumber',
            'unknown_field' => 'unknown_field' // Fallback case
        ];

        foreach ($testCases as $glpiField => $expectedLdapAttr) {
            $result = $method->invoke($this->syncFilter, $glpiField, $availableAttributes);
            $this->assertEquals($expectedLdapAttr, $result,
                "Failed mapping for GLPI field: $glpiField");
        }
    }
}