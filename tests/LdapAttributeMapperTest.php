<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\LdapAttributeMapper;
use DbTestCase;

class LdapAttributeMapperTest extends DbTestCase
{
    private $mapper;

    public function setUp(): void
    {
        parent::setUp();

        // Create service instance
        $this->mapper = new LdapAttributeMapper();
    }

    /**
     * Test findMatchingAttribute method with RFC 4519 standard mapping
     */
    public function testFindMatchingAttributeWithStandardMapping()
    {
        // Arrange
        $glpi_field = 'serial';
        $available_attributes = ['cn', 'serialNumber', 'description', 'mail'];

        // Act
        $result = $this->mapper->findMatchingAttribute($glpi_field, $available_attributes);

        // Assert
        $this->assertEquals('serialNumber', $result);
    }

    /**
     * Test findMatchingAttribute method with case-insensitive field name
     */
    public function testFindMatchingAttributeWithCaseInsensitiveField()
    {
        // Arrange
        $glpi_field = 'SERIAL';
        $available_attributes = ['cn', 'serialNumber', 'description'];

        // Act
        $result = $this->mapper->findMatchingAttribute($glpi_field, $available_attributes);

        // Assert
        $this->assertEquals('serialNumber', $result);
    }

    /**
     * Test findMatchingAttribute method with exact field name match fallback
     */
    public function testFindMatchingAttributeWithExactFieldNameMatch()
    {
        // Arrange
        $glpi_field = 'customField';
        $available_attributes = ['cn', 'customField', 'description'];

        // Act
        $result = $this->mapper->findMatchingAttribute($glpi_field, $available_attributes);

        // Assert
        $this->assertEquals('customField', $result);
    }

    /**
     * Test findMatchingAttribute method with no mapping found
     */
    public function testFindMatchingAttributeWithNoMappingFound()
    {
        // Arrange
        $glpi_field = 'unknownField';
        $available_attributes = ['cn', 'mail', 'description'];

        // Act
        $result = $this->mapper->findMatchingAttribute($glpi_field, $available_attributes);

        // Assert
        $this->assertEquals('unknownField', $result);
    }

    /**
     * Test findMatchingAttribute method with standard mapping but attribute not available
     */
    public function testFindMatchingAttributeWithMappingButAttributeNotAvailable()
    {
        // Arrange
        $glpi_field = 'serial';
        $available_attributes = ['cn', 'mail', 'description']; // serialNumber not available

        // Act
        $result = $this->mapper->findMatchingAttribute($glpi_field, $available_attributes);

        // Assert
        $this->assertEquals('serial', $result); // Falls back to field name
    }

    /**
     * Test findMatchingAttribute method with empty available attributes
     */
    public function testFindMatchingAttributeWithEmptyAvailableAttributes()
    {
        // Arrange
        $glpi_field = 'serial';
        $available_attributes = [];

        // Act
        $result = $this->mapper->findMatchingAttribute($glpi_field, $available_attributes);

        // Assert
        $this->assertEquals('serial', $result);
    }

    /**
     * Test findMatchingAttribute method with various RFC 4519 mappings
     */
    public function testFindMatchingAttributeWithVariousRFC4519Mappings()
    {
        // Arrange & Act & Assert
        $test_cases = [
            ['name', ['cn', 'mail'], 'cn'],
            ['firstname', ['givenName', 'sn'], 'givenName'],
            ['realname', ['sn', 'givenName'], 'sn'],
            ['mail', ['mail', 'cn'], 'mail'],
            ['phone', ['telephoneNumber', 'mobile'], 'telephoneNumber'],
            ['mobile', ['mobile', 'telephoneNumber'], 'mobile'],
            ['location', ['l', 'c'], 'l'],
            ['city', ['l', 'c'], 'l'],
            ['comment', ['description'], 'description'],
        ];

        foreach ($test_cases as [$field, $attributes, $expected]) {
            $result = $this->mapper->findMatchingAttribute($field, $attributes);
            $this->assertEquals($expected, $result, "Failed for field: $field");
        }
    }

    /**
     * Test getStandardMappings method
     */
    public function testGetStandardMappings()
    {
        // Act
        $result = $this->mapper->getStandardMappings();

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('serial', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('mail', $result);
        $this->assertEquals('serialNumber', $result['serial']);
        $this->assertEquals('cn', $result['name']);
        $this->assertEquals('mail', $result['mail']);
    }

    /**
     * Test getStandardMappings method returns all expected mappings
     */
    public function testGetStandardMappingsContainsAllExpectedMappings()
    {
        // Act
        $result = $this->mapper->getStandardMappings();

        // Assert - Check all RFC 4519 mappings
        $expected_keys = [
            'serial',
            'name',
            'comment',
            'location',
            'phone',
            'mail',
            'email',
            'emails',
            'firstname',
            'realname',
            'mobile',
            'title',
            'uuid',
            'dn',
            'address',
            'street',
            'city',
            'postalcode',
            'country',
            'fax',
        ];

        foreach ($expected_keys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing mapping for: $key");
        }
    }

    /**
     * Test getGlpiFieldForAttribute method with valid LDAP attribute
     */
    public function testGetGlpiFieldForAttributeWithValidAttribute()
    {
        // Arrange
        $ldap_attribute = 'serialNumber';

        // Act
        $result = $this->mapper->getGlpiFieldForAttribute($ldap_attribute);

        // Assert
        $this->assertEquals('serial', $result);
    }

    /**
     * Test getGlpiFieldForAttribute method with another valid LDAP attribute
     */
    public function testGetGlpiFieldForAttributeWithAnotherValidAttribute()
    {
        // Arrange
        $ldap_attribute = 'cn';

        // Act
        $result = $this->mapper->getGlpiFieldForAttribute($ldap_attribute);

        // Assert
        $this->assertEquals('name', $result);
    }

    /**
     * Test getGlpiFieldForAttribute method with invalid LDAP attribute
     */
    public function testGetGlpiFieldForAttributeWithInvalidAttribute()
    {
        // Arrange
        $ldap_attribute = 'unknownAttribute';

        // Act
        $result = $this->mapper->getGlpiFieldForAttribute($ldap_attribute);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test getGlpiFieldForAttribute method with various RFC 4519 attributes
     */
    public function testGetGlpiFieldForAttributeWithVariousRFC4519Attributes()
    {
        // Arrange & Act & Assert
        $test_cases = [
            ['givenName', 'firstname'],
            ['sn', 'realname'],
            ['telephoneNumber', 'phone'],
            ['mobile', 'mobile'],
            ['description', 'comment'],
            ['street', 'street'], // Note: street maps to both address and street, array_flip keeps last one (street)
            ['postalCode', 'postalcode'],
        ];

        foreach ($test_cases as [$attribute, $expected]) {
            $result = $this->mapper->getGlpiFieldForAttribute($attribute);
            $this->assertEquals($expected, $result, "Failed for attribute: $attribute");
        }
    }

    /**
     * Test getGlpiFieldForAttribute method with attributes that have multiple GLPI field mappings
     */
    public function testGetGlpiFieldForAttributeWithMultipleMappings()
    {
        // Arrange & Act
        // Note: when multiple GLPI fields map to the same LDAP attribute,
        // array_flip() will only keep one (the last one in the array)
        $mail_result = $this->mapper->getGlpiFieldForAttribute('mail');
        $l_result = $this->mapper->getGlpiFieldForAttribute('l');

        // Assert - Should return one of the possible fields (the last one due to array_flip)
        $this->assertNotNull($mail_result);
        $this->assertIsString($mail_result);
        $this->assertContains($mail_result, ['mail', 'email', 'emails']);

        $this->assertNotNull($l_result);
        $this->assertIsString($l_result);
        $this->assertContains($l_result, ['location', 'city']);
    }

    /**
     * Test getGlpiFieldForAttribute method with empty attribute
     */
    public function testGetGlpiFieldForAttributeWithEmptyAttribute()
    {
        // Arrange
        $ldap_attribute = '';

        // Act
        $result = $this->mapper->getGlpiFieldForAttribute($ldap_attribute);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test isAttributeAvailable method with available attribute
     */
    public function testIsAttributeAvailableWithAvailableAttribute()
    {
        // Arrange
        $ldap_attribute = 'serialNumber';
        $available_attributes = ['cn', 'serialNumber', 'mail'];

        // Act
        $result = $this->mapper->isAttributeAvailable($ldap_attribute, $available_attributes);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isAttributeAvailable method with unavailable attribute
     */
    public function testIsAttributeAvailableWithUnavailableAttribute()
    {
        // Arrange
        $ldap_attribute = 'missingAttribute';
        $available_attributes = ['cn', 'serialNumber', 'mail'];

        // Act
        $result = $this->mapper->isAttributeAvailable($ldap_attribute, $available_attributes);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isAttributeAvailable method with empty available attributes
     */
    public function testIsAttributeAvailableWithEmptyAvailableAttributes()
    {
        // Arrange
        $ldap_attribute = 'cn';
        $available_attributes = [];

        // Act
        $result = $this->mapper->isAttributeAvailable($ldap_attribute, $available_attributes);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isAttributeAvailable method with case sensitivity
     */
    public function testIsAttributeAvailableIsCaseSensitive()
    {
        // Arrange
        $ldap_attribute = 'serialNumber';
        $available_attributes = ['cn', 'SerialNumber', 'mail']; // Different case

        // Act
        $result = $this->mapper->isAttributeAvailable($ldap_attribute, $available_attributes);

        // Assert
        $this->assertFalse($result); // Should be case-sensitive
    }

    /**
     * Test isAttributeAvailable method with multiple occurrences
     */
    public function testIsAttributeAvailableWithMultipleOccurrences()
    {
        // Arrange
        $ldap_attribute = 'cn';
        $available_attributes = ['cn', 'mail', 'cn', 'description']; // Duplicate

        // Act
        $result = $this->mapper->isAttributeAvailable($ldap_attribute, $available_attributes);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test integration: mapping workflow from GLPI field to LDAP attribute and back
     */
    public function testIntegrationMappingWorkflow()
    {
        // Arrange
        $glpi_field = 'serial';
        $available_attributes = ['cn', 'serialNumber', 'mail'];

        // Act - Find LDAP attribute for GLPI field
        $ldap_attribute = $this->mapper->findMatchingAttribute($glpi_field, $available_attributes);

        // Assert - Verify it's available
        $is_available = $this->mapper->isAttributeAvailable($ldap_attribute, $available_attributes);
        $this->assertTrue($is_available);

        // Act - Get back GLPI field from LDAP attribute
        $reverse_field = $this->mapper->getGlpiFieldForAttribute($ldap_attribute);

        // Assert - Should match original field
        $this->assertEquals($glpi_field, $reverse_field);
    }
}