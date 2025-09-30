<?php

namespace GlpiPlugin\Advancedldap\Tests;

use DbTestCase;
use GlpiPlugin\Advancedldap\Services\LdapDataExtractor;

class LdapDataExtractorTest extends DbTestCase
{
    private $extractor;

    public function setUp(): void
    {
        parent::setUp();

        // Create extractor instance
        $this->extractor = new LdapDataExtractor();
    }

    /**
     * Test extractDeviceName with cn attribute
     */
    public function testExtractDeviceNameWithCn()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('Computer01', $result);
    }

    /**
     * Test extractDeviceName with displayName attribute
     */
    public function testExtractDeviceNameWithDisplayName()
    {
        // Arrange
        $ldap_entry = [
            'displayname' => ['count' => 1, 0 => 'Display Computer']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('Display Computer', $result);
    }

    /**
     * Test extractDeviceName with multiple attributes (priority order)
     */
    public function testExtractDeviceNameWithMultipleAttributesPriority()
    {
        // Arrange - cn should have priority over displayName
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'CnComputer'],
            'displayname' => ['count' => 1, 0 => 'DisplayComputer']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('CnComputer', $result);
    }

    /**
     * Test extractDeviceName with sAMAccountName attribute
     */
    public function testExtractDeviceNameWithSamAccountName()
    {
        // Arrange
        $ldap_entry = [
            'samaccountname' => ['count' => 1, 0 => 'SAMComputer']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('SAMComputer', $result);
    }

    /**
     * Test extractDeviceName with uid attribute
     */
    public function testExtractDeviceNameWithUid()
    {
        // Arrange
        $ldap_entry = [
            'uid' => ['count' => 1, 0 => 'uid123']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('uid123', $result);
    }

    /**
     * Test extractDeviceName with hostname attribute
     */
    public function testExtractDeviceNameWithHostname()
    {
        // Arrange
        $ldap_entry = [
            'hostname' => ['count' => 1, 0 => 'host.example.com']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('host.example.com', $result);
    }

    /**
     * Test extractDeviceName with empty entry returns default fallback
     */
    public function testExtractDeviceNameWithEmptyEntryReturnsDefault()
    {
        // Arrange
        $ldap_entry = [];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('Unknown Device', $result);
    }

    /**
     * Test extractDeviceName with custom fallback
     */
    public function testExtractDeviceNameWithCustomFallback()
    {
        // Arrange
        $ldap_entry = [];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry, 'Custom Fallback');

        // Assert
        $this->assertEquals('Custom Fallback', $result);
    }

    /**
     * Test extractDeviceName with empty attribute value skips to next
     */
    public function testExtractDeviceNameWithEmptyAttributeValue()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 0],
            'displayname' => ['count' => 1, 0 => 'ValidName']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertEquals('ValidName', $result);
    }

    /**
     * Test extractDeviceName with null fallback
     */
    public function testExtractDeviceNameWithNullFallback()
    {
        // Arrange
        $ldap_entry = [];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry, null);

        // Assert
        $this->assertEquals('Unknown Device', $result);
    }

    /**
     * Test extractAssetData with valid field mappings
     */
    public function testExtractAssetDataWithValidMappings()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01'],
            'serialnumber' => ['count' => 1, 0 => 'SN123456'],
            'description' => ['count' => 1, 0 => 'Test computer']
        ];
        $field_mappings = [
            'name' => 'cn',
            'serial' => 'serialNumber',
            'comment' => 'description'
        ];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('Computer01', $result['name']);
        $this->assertEquals('SN123456', $result['serial']);
        $this->assertEquals('Test computer', $result['comment']);
    }

    /**
     * Test extractAssetData with missing LDAP attributes
     */
    public function testExtractAssetDataWithMissingAttributes()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];
        $field_mappings = [
            'name' => 'cn',
            'serial' => 'serialNumber',
            'comment' => 'description'
        ];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('Computer01', $result['name']);
        $this->assertArrayNotHasKey('serial', $result);
        $this->assertArrayNotHasKey('comment', $result);
    }

    /**
     * Test extractAssetData ensures name field exists using fallback
     */
    public function testExtractAssetDataEnsuresNameWithFallback()
    {
        // Arrange
        $ldap_entry = [
            'displayname' => ['count' => 1, 0 => 'Display Computer'],
            'serialnumber' => ['count' => 1, 0 => 'SN123456']
        ];
        $field_mappings = [
            'serial' => 'serialNumber'
        ];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('Display Computer', $result['name']);
        $this->assertEquals('SN123456', $result['serial']);
    }

    /**
     * Test extractAssetData with empty name mapping uses fallback
     */
    public function testExtractAssetDataWithEmptyNameMappingUsesFallback()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'FallbackName'],
            'otherfield' => ['count' => 1, 0 => '']
        ];
        $field_mappings = [
            'name' => 'otherfield',
            'serial' => 'cn'
        ];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('FallbackName', $result['name']);
    }

    /**
     * Test extractAssetData with empty field mappings
     */
    public function testExtractAssetDataWithEmptyMappings()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];
        $field_mappings = [];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('Computer01', $result['name']);
    }

    /**
     * Test extractAssetData with case-insensitive attribute matching
     */
    public function testExtractAssetDataWithCaseInsensitiveMatching()
    {
        // Arrange
        $ldap_entry = [
            'serialnumber' => ['count' => 1, 0 => 'SN123456'],
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];
        $field_mappings = [
            'serial' => 'SerialNumber',
            'name' => 'CN'
        ];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('SN123456', $result['serial']);
        $this->assertEquals('Computer01', $result['name']);
    }

    /**
     * Test normalizeValue with scalar value
     */
    public function testNormalizeValueWithScalarValue()
    {
        // Arrange
        $value = 'SimpleValue';

        // Act
        $result = $this->extractor->normalizeValue($value);

        // Assert
        $this->assertEquals('SimpleValue', $result);
    }

    /**
     * Test normalizeValue with array containing count
     */
    public function testNormalizeValueWithArrayContainingCount()
    {
        // Arrange
        $value = ['count' => 1, 0 => 'FirstValue'];

        // Act
        $result = $this->extractor->normalizeValue($value);

        // Assert
        $this->assertEquals('FirstValue', $result);
    }

    /**
     * Test normalizeValue with array containing multiple values (no join)
     */
    public function testNormalizeValueWithMultipleValuesNoJoin()
    {
        // Arrange
        $value = ['count' => 2, 0 => 'Value1', 1 => 'Value2'];

        // Act
        $result = $this->extractor->normalizeValue($value, false);

        // Assert
        $this->assertEquals('Value1', $result);
    }

    /**
     * Test normalizeValue with array containing multiple values (with join)
     */
    public function testNormalizeValueWithMultipleValuesWithJoin()
    {
        // Arrange
        $value = ['count' => 3, 0 => 'Value1', 1 => 'Value2', 2 => 'Value3'];

        // Act
        $result = $this->extractor->normalizeValue($value, true);

        // Assert
        $this->assertEquals('Value1, Value2, Value3', $result);
    }

    /**
     * Test normalizeValue with empty array after removing count
     */
    public function testNormalizeValueWithEmptyArrayAfterCount()
    {
        // Arrange
        $value = ['count' => 0];

        // Act
        $result = $this->extractor->normalizeValue($value);

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * Test normalizeValue with single value array
     */
    public function testNormalizeValueWithSingleValueArray()
    {
        // Arrange
        $value = ['count' => 1, 0 => 'OnlyValue'];

        // Act
        $result = $this->extractor->normalizeValue($value);

        // Assert
        $this->assertEquals('OnlyValue', $result);
    }

    /**
     * Test normalizeValue with integer value
     */
    public function testNormalizeValueWithIntegerValue()
    {
        // Arrange
        $value = 42;

        // Act
        $result = $this->extractor->normalizeValue($value);

        // Assert
        $this->assertEquals(42, $result);
    }

    /**
     * Test normalizeValue with null value
     */
    public function testNormalizeValueWithNullValue()
    {
        // Arrange
        $value = null;

        // Act
        $result = $this->extractor->normalizeValue($value);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test normalizeValue with boolean value
     */
    public function testNormalizeValueWithBooleanValue()
    {
        // Arrange
        $value = true;

        // Act
        $result = $this->extractor->normalizeValue($value);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test hasAttribute with existing attribute
     */
    public function testHasAttributeWithExistingAttribute()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];

        // Act
        $result = $this->extractor->hasAttribute($ldap_entry, 'cn');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test hasAttribute with non-existing attribute
     */
    public function testHasAttributeWithNonExistingAttribute()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];

        // Act
        $result = $this->extractor->hasAttribute($ldap_entry, 'serialnumber');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasAttribute with empty attribute value
     */
    public function testHasAttributeWithEmptyValue()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 0]
        ];

        // Act
        $result = $this->extractor->hasAttribute($ldap_entry, 'cn');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test hasAttribute with case-insensitive matching
     */
    public function testHasAttributeWithCaseInsensitiveMatching()
    {
        // Arrange
        $ldap_entry = [
            'serialnumber' => ['count' => 1, 0 => 'SN123456']
        ];

        // Act
        $result = $this->extractor->hasAttribute($ldap_entry, 'SerialNumber');

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test hasAttribute with empty string value
     */
    public function testHasAttributeWithEmptyStringValue()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => '']
        ];

        // Act
        $result = $this->extractor->hasAttribute($ldap_entry, 'cn');

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getAttribute with existing attribute
     */
    public function testGetAttributeWithExistingAttribute()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'cn');

        // Assert
        $this->assertEquals('Computer01', $result);
    }

    /**
     * Test getAttribute with non-existing attribute returns default
     */
    public function testGetAttributeWithNonExistingAttributeReturnsDefault()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'serialnumber', 'DefaultValue');

        // Assert
        $this->assertEquals('DefaultValue', $result);
    }

    /**
     * Test getAttribute with non-existing attribute returns null by default
     */
    public function testGetAttributeWithNonExistingAttributeReturnsNull()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'serialnumber');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test getAttribute with case-insensitive matching
     */
    public function testGetAttributeWithCaseInsensitiveMatching()
    {
        // Arrange
        $ldap_entry = [
            'serialnumber' => ['count' => 1, 0 => 'SN123456']
        ];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'SerialNumber');

        // Assert
        $this->assertEquals('SN123456', $result);
    }

    /**
     * Test getAttribute with multi-value join
     */
    public function testGetAttributeWithMultiValueJoin()
    {
        // Arrange
        $ldap_entry = [
            'memberof' => ['count' => 2, 0 => 'Group1', 1 => 'Group2']
        ];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'memberOf', null, true);

        // Assert
        $this->assertEquals('Group1, Group2', $result);
    }

    /**
     * Test getAttribute with multi-value no join
     */
    public function testGetAttributeWithMultiValueNoJoin()
    {
        // Arrange
        $ldap_entry = [
            'memberof' => ['count' => 2, 0 => 'Group1', 1 => 'Group2']
        ];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'memberOf', null, false);

        // Assert
        $this->assertEquals('Group1', $result);
    }

    /**
     * Test getAttribute with empty array returns default
     */
    public function testGetAttributeWithEmptyArrayReturnsDefault()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 0]
        ];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'cn', 'DefaultValue');

        // Assert
        $this->assertEquals('', $result);
    }

    /**
     * Test getAttribute with numeric default value
     */
    public function testGetAttributeWithNumericDefault()
    {
        // Arrange
        $ldap_entry = [];

        // Act
        $result = $this->extractor->getAttribute($ldap_entry, 'cn', 0);

        // Assert
        $this->assertEquals(0, $result);
    }

    /**
     * Test extractAssetData with multi-value attributes not joined
     */
    public function testExtractAssetDataWithMultiValueAttributesNotJoined()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01'],
            'memberof' => ['count' => 2, 0 => 'Group1', 1 => 'Group2']
        ];
        $field_mappings = [
            'name' => 'cn',
            'groups' => 'memberOf'
        ];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('Computer01', $result['name']);
        $this->assertEquals('Group1, Group2', $result['groups']);
    }

    /**
     * Test extractDeviceName returns string type
     */
    public function testExtractDeviceNameReturnsStringType()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer01']
        ];

        // Act
        $result = $this->extractor->extractDeviceName($ldap_entry);

        // Assert
        $this->assertIsString($result);
    }

    /**
     * Test extractAssetData with special characters in values
     */
    public function testExtractAssetDataWithSpecialCharacters()
    {
        // Arrange
        $ldap_entry = [
            'cn' => ['count' => 1, 0 => 'Computer-01_test@domain'],
            'description' => ['count' => 1, 0 => 'Test & Production <Server>']
        ];
        $field_mappings = [
            'name' => 'cn',
            'comment' => 'description'
        ];

        // Act
        $result = $this->extractor->extractAssetData($ldap_entry, $field_mappings);

        // Assert
        $this->assertEquals('Computer-01_test@domain', $result['name']);
        $this->assertEquals('Test & Production <Server>', $result['comment']);
    }
}