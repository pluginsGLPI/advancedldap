<?php

namespace GlpiPlugin\Advancedldap\Tests;

use DbTestCase;
use Glpi\Asset\AssetDefinition;
use GlpiPlugin\Advancedldap\Providers\GenericAssetFieldProvider;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;

class GenericAssetFieldProviderTest extends DbTestCase
{
    private $provider;
    private $asset_definition;
    private $asset_definition_id;

    public function setUp(): void
    {
        parent::setUp();

        // Create provider instance
        $this->provider = new GenericAssetFieldProvider();

        // Create test asset definition
        $this->asset_definition = new AssetDefinition();
        $fields_data = [
            'name'                  => 'Test Asset Definition',
            'comment'               => 'Test asset definition for unit tests',
            'system_name'           => 'test_asset',
        ];

        $this->asset_definition_id = $this->asset_definition->add($fields_data);
        $this->assertGreaterThan(0, $this->asset_definition_id);
    }

    public function tearDown(): void
    {
        // Clean up test asset definition
        if ($this->asset_definition_id > 0) {
            $this->asset_definition->delete(['id' => $this->asset_definition_id], true);
        }

        parent::tearDown();
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
     * Test getItemTypeFields with valid generic asset itemtype
     */
    public function testGetItemTypeFieldsWithValidGenericAsset()
    {
        // Arrange
        $itemtype = 'GenericAsset_' . $this->asset_definition_id;

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Should contain basic fields but not technical fields
        $this->assertArrayHasKey('name', $result);

        // Should not contain technical fields
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('date_mod', $result);
        $this->assertArrayNotHasKey('date_creation', $result);
        $this->assertArrayNotHasKey('assets_assetdefinitions_id', $result);
    }

    /**
     * Test getItemTypeFields with invalid asset definition ID
     */
    public function testGetItemTypeFieldsWithInvalidAssetDefinitionId()
    {
        // Arrange
        $itemtype = 'GenericAsset_99999'; // Non-existent ID

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields with non-numeric asset definition ID
     */
    public function testGetItemTypeFieldsWithNonNumericAssetDefinitionId()
    {
        // Arrange
        $itemtype = 'GenericAsset_abc';

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
     * Test getItemTypeFields with itemtype that doesn't start with GenericAsset_
     */
    public function testGetItemTypeFieldsWithInvalidItemtypeFormat()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields with zero as asset definition ID
     */
    public function testGetItemTypeFieldsWithZeroAssetDefinitionId()
    {
        // Arrange
        $itemtype = 'GenericAsset_0';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields with negative asset definition ID
     */
    public function testGetItemTypeFieldsWithNegativeAssetDefinitionId()
    {
        // Arrange
        $itemtype = 'GenericAsset_-1';

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
        $itemtype = 'GenericAsset_' . $this->asset_definition_id;

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);

        // Values should be in alphabetical order (asort() sorts by values, not keys)
        $values = array_values($result);
        $sorted_values = $values;
        sort($sorted_values);

        $this->assertEquals($sorted_values, $values);
    }

    /**
     * Test getItemTypeFields verifies that technical fields are excluded
     */
    public function testGetItemTypeFieldsExcludesTechnicalFields()
    {
        // Arrange
        $itemtype = 'GenericAsset_' . $this->asset_definition_id;

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);

        // Technical fields should be excluded
        $technicalFields = ['id', 'date_mod', 'date_creation', 'assets_assetdefinitions_id'];
        foreach ($technicalFields as $technicalField) {
            $this->assertArrayNotHasKey(
                $technicalField,
                $result,
                "Technical field '$technicalField' should be excluded from results"
            );
        }
    }

    /**
     * Test getItemTypeFields with itemtype containing extra characters after ID
     */
    public function testGetItemTypeFieldsWithExtraCharactersAfterID()
    {
        // Arrange
        $itemtype = 'GenericAsset_' . $this->asset_definition_id . '_extra';

        // Act
        $result = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        // PHP (int) cast will extract the numeric part, so this should return the asset fields
        $this->assertNotEmpty($result);
    }

    /**
     * Test multiple calls return consistent results
     */
    public function testMultipleCallsReturnConsistentResults()
    {
        // Arrange
        $itemtype = 'GenericAsset_' . $this->asset_definition_id;

        // Act
        $result1 = $this->provider->getItemtypeFields($itemtype);
        $result2 = $this->provider->getItemtypeFields($itemtype);

        // Assert
        $this->assertEquals($result1, $result2);
    }
}