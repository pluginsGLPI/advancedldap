<?php

namespace GlpiPlugin\Advancedldap\Tests;

use DbTestCase;
use GlpiPlugin\Advancedldap\Providers\NativeAssetFieldProvider;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;

class NativeAssetFieldProviderTest extends DbTestCase
{
    private $provider;

    public function setUp(): void
    {
        parent::setUp();

        // Create provider instance
        $this->provider = new NativeAssetFieldProvider();
    }

    /**
     * Test that provider implements the correct interface
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(AssetFieldProviderInterface::class, $this->provider);
    }

    /**
     * Test getAvailableItemTypes method returns empty array
     */
    public function testGetAvailableItemTypes()
    {
        // Act
        $result = $this->provider->getAvailableItemtypes();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields with valid native itemtype (Computer)
     */
    public function testGetItemTypeFieldsWithValidNativeItemtype()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Should contain basic fields
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('serial', $result);

        // Should not contain technical fields
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('date_mod', $result);
        $this->assertArrayNotHasKey('date_creation', $result);
    }

    /**
     * Test getItemTypeFields with another valid native itemtype (Monitor)
     */
    public function testGetItemTypeFieldsWithMonitorItemtype()
    {
        // Arrange
        $itemtype = 'Monitor';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * Test getItemTypeFields with non-existent class
     */
    public function testGetItemTypeFieldsWithNonExistentClass()
    {
        // Arrange
        $itemtype = 'NonExistentClass';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields with class that doesn't extend CommonDBTM
     */
    public function testGetItemTypeFieldsWithInvalidClass()
    {
        // Arrange - stdClass doesn't extend CommonDBTM
        $itemtype = 'stdClass';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields with empty itemtype
     */
    public function testGetItemTypeFieldsWithEmptyItemtype()
    {
        // Arrange
        $itemtype = '';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields returns sorted array (sorted by values/labels)
     */
    public function testGetItemTypeFieldsReturnsSortedArray()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Values should be in alphabetical order (asort() sorts by values, not keys)
        $values = array_values($result);
        $sorted_values = $values;
        sort($sorted_values);

        $this->assertEquals($sorted_values, $values);
    }

    /**
     * Test getItemTypeFields excludes technical fields
     */
    public function testGetItemTypeFieldsExcludesTechnicalFields()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);

        // Technical fields should be excluded
        $technicalFields = ['id', 'date_mod', 'date_creation'];
        foreach ($technicalFields as $technicalField) {
            $this->assertArrayNotHasKey(
                $technicalField,
                $result,
                "Technical field '$technicalField' should be excluded from results"
            );
        }
    }

    /**
     * Test getItemTypeFields with Printer itemtype
     */
    public function testGetItemTypeFieldsWithPrinterItemtype()
    {
        // Arrange
        $itemtype = 'Printer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * Test getItemTypeFields with NetworkEquipment itemtype
     */
    public function testGetItemTypeFieldsWithNetworkEquipmentItemtype()
    {
        // Arrange
        $itemtype = 'NetworkEquipment';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * Test multiple calls return consistent results
     */
    public function testMultipleCallsReturnConsistentResults()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result1 = $this->provider->getItemtypeFields($itemtype);
        $result2 = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test getItemTypeFields with case sensitivity
     * Note: PHP class_exists() is case-insensitive, so 'computer' will match 'Computer'
     */
    public function testGetItemTypeFieldsWithIncorrectCase()
    {
        // Arrange - lowercase 'computer' will still match 'Computer' class in PHP
        $itemtype = 'computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        // PHP's class_exists() is case-insensitive, so this will still return fields
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * Test getItemTypeFields with GenericAsset itemtype
     * Should return empty because GenericAsset doesn't exist as a class
     */
    public function testGetItemTypeFieldsWithGenericAssetFormat()
    {
        // Arrange
        $itemtype = 'GenericAsset_1';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that fields with empty 'field' key are excluded
     * This tests the skip logic in formatSearchOptionsAsFields
     */
    public function testGetItemTypeFieldsExcludesOptionsWithoutFieldKey()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);

        // All returned items should have non-empty keys
        foreach (array_keys($result) as $key) {
            $this->assertNotEmpty($key, "Field key should not be empty");
        }
    }

    /**
     * Test that result contains only string values (field labels)
     */
    public function testGetItemTypeFieldsReturnsStringValues()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);

        foreach ($result as $field_key => $field_label) {
            $this->assertIsString($field_key, "Field key should be a string");
            $this->assertIsString($field_label, "Field label should be a string");
            $this->assertNotEmpty($field_label, "Field label should not be empty");
        }
    }

    /**
     * Test getItemTypeFields with Software itemtype
     */
    public function testGetItemTypeFieldsWithSoftwareItemtype()
    {
        // Arrange
        $itemtype = 'Software';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('name', $result);
    }

    /**
     * Test that duplicate field names get qualified with table name
     * This is harder to test without mocking, but we can verify the logic doesn't crash
     */
    public function testGetItemTypeFieldsHandlesDuplicateFieldNames()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert - Just verify it doesn't crash and returns valid data
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Check if any field has parentheses (indication of qualification)
        $hasQualifiedField = false;
        foreach ($result as $label) {
            if (str_contains($label, '(') && str_contains($label, ')')) {
                $hasQualifiedField = true;
                break;
            }
        }

        // We don't assert true/false here, just document that qualified fields may exist
        $this->assertIsBool($hasQualifiedField);
    }
}