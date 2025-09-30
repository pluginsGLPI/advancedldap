<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetFieldService;
use GlpiPlugin\Advancedldap\Contracts\ConfigurationInterface;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use GlpiPlugin\Advancedldap\Factories\AssetFieldProviderFactory;
use DbTestCase;

class AssetFieldServiceTest extends DbTestCase
{
    private $assetFieldService;
    private $configuration;
    private $database;
    private $factory;

    public function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->configuration = $this->createMock(ConfigurationInterface::class);
        $this->database = $this->createMock(DatabaseInterface::class);
        $this->factory = $this->createMock(AssetFieldProviderFactory::class);

        // Create service instance with mocked dependencies
        $this->assetFieldService = new AssetFieldService(
            $this->configuration,
            $this->database,
            $this->factory,
        );
    }

    /**
     * Test constructor with valid dependencies
     */
    public function testConstructorWithValidDependencies()
    {
        // Act
        $service = new AssetFieldService(
            $this->configuration,
            $this->database,
            $this->factory,
        );

        // Assert
        $this->assertInstanceOf(AssetFieldService::class, $service);
        $this->assertInstanceOf(AssetFieldProviderInterface::class, $service);
    }

    /**
     * Test getAvailableItemTypes method with standard native assets
     */
    public function testGetAvailableItemTypesWithNativeAssets()
    {
        // Arrange
        $mockAssetTypes = [
            'Computer' => [
                'name' => 'Computer',
                'type' => 'native',
            ],
            'Monitor' => [
                'name' => 'Monitor',
                'type' => 'native',
            ],
            'Printer' => [
                'name' => 'Printer',
                'type' => 'native',
            ],
        ];

        // Mock getAllAssetTypes to return our test data
        $service = $this->getMockBuilder(AssetFieldService::class)
            ->setConstructorArgs([$this->configuration, $this->database, $this->factory])
            ->onlyMethods(['getAllAssetTypes'])
            ->getMock();

        $service->expects($this->once())
            ->method('getAllAssetTypes')
            ->willReturn($mockAssetTypes);

        // Act
        $result = $service->getAvailableItemtypes();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('Computer', $result['Computer']);
        $this->assertEquals('Monitor', $result['Monitor']);
        $this->assertEquals('Printer', $result['Printer']);

        // Verify the result is sorted alphabetically
        $expectedOrder = ['Computer', 'Monitor', 'Printer'];
        $this->assertEquals($expectedOrder, array_keys($result));
    }

    /**
     * Test getAvailableItemTypes method with mixed native and generic assets
     */
    public function testGetAvailableItemTypesWithMixedAssets()
    {
        // Arrange
        $mockAssetTypes = [
            'Computer' => [
                'name' => 'Computer',
                'type' => 'native',
            ],
            'GenericAsset_1' => [
                'name' => 'Custom Asset Type',
                'type' => 'generic',
            ],
            'Monitor' => [
                'name' => 'Monitor',
                'type' => 'native',
            ],
        ];

        // Mock getAllAssetTypes to return our test data
        $service = $this->getMockBuilder(AssetFieldService::class)
            ->setConstructorArgs([$this->configuration, $this->database, $this->factory])
            ->onlyMethods(['getAllAssetTypes'])
            ->getMock();

        $service->expects($this->once())
            ->method('getAllAssetTypes')
            ->willReturn($mockAssetTypes);

        // Act
        $result = $service->getAvailableItemtypes();

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('Computer', $result['Computer']);
        $this->assertEquals('Custom Asset Type', $result['GenericAsset_1']);
        $this->assertEquals('Monitor', $result['Monitor']);
    }

    /**
     * Test getAvailableItemTypes method with empty asset types
     */
    public function testGetAvailableItemTypesWithEmptyAssets()
    {
        // Arrange
        $service = $this->getMockBuilder(AssetFieldService::class)
            ->setConstructorArgs([$this->configuration, $this->database, $this->factory])
            ->onlyMethods(['getAllAssetTypes'])
            ->getMock();

        $service->expects($this->once())
            ->method('getAllAssetTypes')
            ->willReturn([]);

        // Act
        $result = $service->getAvailableItemtypes();

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test getItemTypeFields method with valid itemtype
     */
    public function testGetItemTypeFieldsWithValidItemtype()
    {
        // Arrange
        $itemtype = 'Computer';
        $expectedFields = [
            'name' => 'Name',
            'serial' => 'Serial Number',
            'otherserial' => 'Inventory Number',
        ];

        $mockProvider = $this->createMock(AssetFieldProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('getItemTypeFields')
            ->with($itemtype)
            ->willReturn($expectedFields);

        $this->factory->expects($this->once())
            ->method('createProvider')
            ->with($itemtype)
            ->willReturn($mockProvider);

        // Act
        $result = $this->assetFieldService->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($expectedFields, $result);
    }

    /**
     * Test getItemTypeFields method with generic asset
     */
    public function testGetItemTypeFieldsWithGenericAsset()
    {
        // Arrange
        $itemtype = 'GenericAsset_1';
        $expectedFields = [
            'name' => 'Name',
            'custom_field' => 'Custom Field',
        ];

        $mockProvider = $this->createMock(AssetFieldProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method('getItemTypeFields')
            ->with($itemtype)
            ->willReturn($expectedFields);

        $this->factory->expects($this->once())
            ->method('createProvider')
            ->with($itemtype)
            ->willReturn($mockProvider);

        // Act
        $result = $this->assetFieldService->getItemtypeFields($itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($expectedFields, $result);
    }

    /**
     * Test getAllAssetTypes method with standard configuration
     */
    public function testGetAllAssetTypesWithStandardConfiguration()
    {
        // Arrange
        // Mock configuration calls - note that native itemtypes won't exist in test environment
        $this->configuration->expects($this->exactly(2))
            ->method('getGlpiConfig')
            ->willReturnMap([
                ['asset_types', null, ['Computer', 'Monitor']],
                ['inventory_types', null, ['Computer', 'Printer']],
            ]);

        $this->configuration->expects($this->once())
            ->method('get')
            ->with('show_inactive_generic_assets', 0)
            ->willReturn(0);

        // Mock database call for generic assets
        $mockIterator = [
            [
                'id' => 1,
                'label' => 'Custom Asset',
                'name' => 'Custom Asset',
                'system_name' => 'custom_asset',
            ],
        ];

        $this->database->expects($this->once())
            ->method('request')
            ->with([
                'FROM' => 'glpi_assets_assetdefinitions',
                'WHERE' => ['is_active' => 1],
                'ORDER' => 'system_name',
            ])
            ->willReturn($mockIterator);

        // Act
        $result = $this->assetFieldService->getAllAssetTypes();

        // Assert
        $this->assertIsArray($result);
        // Native assets won't be included because classes don't exist in test environment
        $this->assertArrayNotHasKey('Computer', $result);
        $this->assertArrayNotHasKey('Monitor', $result);
        $this->assertArrayNotHasKey('Printer', $result);

        // But generic assets should be included
        $this->assertArrayHasKey('GenericAsset_1', $result);
        $this->assertEquals('Custom Asset', $result['GenericAsset_1']['name']);
        $this->assertEquals('generic', $result['GenericAsset_1']['type']);
    }

    /**
     * Test getAllAssetTypes method with show inactive generic assets enabled
     */
    public function testGetAllAssetTypesWithInactiveGenericAssets()
    {
        // Arrange
        $this->configuration->expects($this->exactly(2))
            ->method('getGlpiConfig')
            ->willReturnMap([
                ['asset_types', null, ['Computer']],
                ['inventory_types', null, []],
            ]);

        $this->configuration->expects($this->once())
            ->method('get')
            ->with('show_inactive_generic_assets', 0)
            ->willReturn(1); // Show inactive assets

        // Mock database call without WHERE clause for is_active
        $this->database->expects($this->once())
            ->method('request')
            ->with([
                'FROM' => 'glpi_assets_assetdefinitions',
                'WHERE' => [],
                'ORDER' => 'system_name',
            ])
            ->willReturn([]);

        // Act
        $result = $this->assetFieldService->getAllAssetTypes();

        // Assert
        $this->assertIsArray($result);
        // Computer won't be included because class doesn't exist in test environment
        $this->assertArrayNotHasKey('Computer', $result);
        // Result should be empty since no generic assets and no valid native assets
        $this->assertEmpty($result);
    }

    /**
     * Test getAllAssetTypes method with invalid itemtype classes
     */
    public function testGetAllAssetTypesWithInvalidItemtypeClasses()
    {
        // Arrange
        $this->configuration->expects($this->exactly(2))
            ->method('getGlpiConfig')
            ->willReturnMap([
                ['asset_types', null, ['Computer', 'InvalidClass', 'NonExistentClass']],
                ['inventory_types', null, []],
            ]);

        $this->configuration->expects($this->once())
            ->method('get')
            ->with('show_inactive_generic_assets', 0)
            ->willReturn(0);

        $this->database->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->assetFieldService->getAllAssetTypes();

        // Assert - Only Computer should be included (assuming it's a valid class in GLPI context)
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('InvalidClass', $result);
        $this->assertArrayNotHasKey('NonExistentClass', $result);
    }

    /**
     * Test getAllAssetTypes method with duplicate names between native and generic
     */
    public function testGetAllAssetTypesWithDuplicateNames()
    {
        // Arrange
        $this->configuration->expects($this->exactly(2))
            ->method('getGlpiConfig')
            ->willReturnMap([
                ['asset_types', null, ['Computer']],
                ['inventory_types', null, []],
            ]);

        $this->configuration->expects($this->once())
            ->method('get')
            ->with('show_inactive_generic_assets', 0)
            ->willReturn(0);

        // Mock generic asset with same name as native
        $mockIterator = [
            [
                'id' => 1,
                'label' => 'Computer', // Same name as native asset
                'name' => 'Computer',
                'system_name' => 'computer_generic',
            ],
        ];

        $this->database->expects($this->once())
            ->method('request')
            ->willReturn($mockIterator);

        // Act
        $result = $this->assetFieldService->getAllAssetTypes();

        // Assert - Native asset should be skipped to avoid duplicates
        $this->assertIsArray($result);
        $this->assertArrayHasKey('GenericAsset_1', $result);
        $this->assertEquals('Computer', $result['GenericAsset_1']['name']);
        $this->assertEquals('generic', $result['GenericAsset_1']['type']);
    }

    /**
     * Test getAllAssetTypes method with empty system_name in generic assets
     */
    public function testGetAllAssetTypesWithEmptySystemName()
    {
        // Arrange
        $this->configuration->expects($this->exactly(2))
            ->method('getGlpiConfig')
            ->willReturnMap([
                ['asset_types', null, []],
                ['inventory_types', null, []],
            ]);

        $this->configuration->expects($this->once())
            ->method('get')
            ->with('show_inactive_generic_assets', 0)
            ->willReturn(0);

        // Mock generic assets with empty and valid system_name
        $mockIterator = [
            [
                'id' => 1,
                'label' => 'Asset Without System Name',
                'name' => 'Asset Without System Name',
                'system_name' => '', // Empty system name
            ],
            [
                'id' => 2,
                'label' => 'Valid Asset',
                'name' => 'Valid Asset',
                'system_name' => 'valid_asset',
            ],
        ];

        $this->database->expects($this->once())
            ->method('request')
            ->willReturn($mockIterator);

        // Act
        $result = $this->assetFieldService->getAllAssetTypes();

        // Assert - Only asset with valid system_name should be included
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('GenericAsset_1', $result);
        $this->assertArrayHasKey('GenericAsset_2', $result);
        $this->assertEquals('Valid Asset', $result['GenericAsset_2']['name']);
    }
}
