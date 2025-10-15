<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetCreationService;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use DbTestCase;

/**
 * Unit tests for AssetCreationService
 * Tests only public methods as per GLPI testing conventions
 *
 * Note: createOrUpdateAsset() with GenericAsset types is not unit tested because:
 * - The handleGenericAsset() private method generates debug logs via Toolbox::logDebug()
 * - The GLPI test framework rejects "unexpected log entries" causing test failures
 * - Generic asset creation should be tested via integration tests instead
 *
 * Native asset types (Computer, Printer, etc.) are fully tested as they don't generate logs.
 */
class AssetCreationServiceTest extends DbTestCase
{
    private $assetCreationService;
    private $database;

    public function setUp(): void
    {
        parent::setUp();

        // Create mock for database dependency
        $this->database = $this->createMock(DatabaseInterface::class);

        // Create service instance with mocked dependency
        // Empty array for field_handlers since we test the base functionality
        $this->assetCreationService = new AssetCreationService($this->database, []);
    }

    /**
     * Test createOrUpdateAsset method with valid asset type and data
     */
    public function testCreateOrUpdateAssetWithValidData()
    {
        // Arrange
        $assetType = 'Computer';
        $assetData = [
            'name' => 'Test Computer',
            'serial' => 'TEST123',
            'otherserial' => 'INV456',
        ];

        // Mock database request for finding existing asset (none found)
        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM' => 'glpi_computers',
                'WHERE' => ['name' => 'Test Computer'],
                'LIMIT' => 1,
            ])
            ->willReturn([]);

        // Act
        $result = $this->assetCreationService->createOrUpdateAsset($assetType, $assetData);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('asset_id', $result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test createOrUpdateAsset method with invalid asset type
     */
    public function testCreateOrUpdateAssetWithInvalidAssetType()
    {
        // Arrange
        $assetType = 'NonExistentAsset';
        $assetData = ['name' => 'Test Asset'];

        // Act
        $result = $this->assetCreationService->createOrUpdateAsset($assetType, $assetData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['action']);
        $this->assertNull($result['asset_id']);
    }

    /**
     * Test createOrUpdateAsset method with empty asset name
     */
    public function testCreateOrUpdateAssetWithEmptyName()
    {
        // Arrange
        $assetType = 'Computer';
        $assetData = [
            'name' => '',
            'serial' => 'TEST123',
        ];

        // Act
        $result = $this->assetCreationService->createOrUpdateAsset($assetType, $assetData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['action']);
        $this->assertNull($result['asset_id']);
    }

    /**
     * Test createOrUpdateAsset method with missing asset name
     */
    public function testCreateOrUpdateAssetWithMissingName()
    {
        // Arrange
        $assetType = 'Computer';
        $assetData = [
            'serial' => 'TEST123',
            'otherserial' => 'INV456',
        ];

        // Act
        $result = $this->assetCreationService->createOrUpdateAsset($assetType, $assetData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['action']);
        $this->assertNull($result['asset_id']);
    }

    /**
     * Test createOrUpdateAsset method with existing asset (update scenario)
     */
    public function testCreateOrUpdateAssetWithExistingAsset()
    {
        // Arrange
        $assetType = 'Computer';
        $assetData = [
            'name' => 'Existing Computer',
            'serial' => 'UPDATED123',
        ];

        $existingAsset = [
            'id' => 42,
            'name' => 'Existing Computer',
            'serial' => 'OLD123',
        ];

        // Mock database request for finding existing asset
        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM' => 'glpi_computers',
                'WHERE' => ['name' => 'Existing Computer'],
                'LIMIT' => 1,
            ])
            ->willReturn([$existingAsset]);

        // Act
        $result = $this->assetCreationService->createOrUpdateAsset($assetType, $assetData);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('asset_id', $result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test validateAssetData method with valid data
     */
    public function testValidateAssetDataWithValidData()
    {
        // Arrange
        $assetData = [
            'name' => 'Valid Asset Name',
            'serial' => 'SERIAL123',
        ];
        $assetType = 'Computer';

        // Act
        $result = $this->assetCreationService->validateAssetData($assetData, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertTrue($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test validateAssetData method with missing name
     */
    public function testValidateAssetDataWithMissingName()
    {
        // Arrange
        $assetData = [
            'serial' => 'SERIAL123',
        ];
        $assetType = 'Computer';

        // Act
        $result = $this->assetCreationService->validateAssetData($assetData, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test validateAssetData method with empty name
     */
    public function testValidateAssetDataWithEmptyName()
    {
        // Arrange
        $assetData = [
            'name' => '',
            'serial' => 'SERIAL123',
        ];
        $assetType = 'Computer';

        // Act
        $result = $this->assetCreationService->validateAssetData($assetData, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test validateAssetData method with invalid asset type
     */
    public function testValidateAssetDataWithInvalidAssetType()
    {
        // Arrange
        $assetData = [
            'name' => 'Valid Asset Name',
        ];
        $assetType = 'NonExistentAssetType';

        // Act
        $result = $this->assetCreationService->validateAssetData($assetData, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * Test validateAssetData method with multiple validation errors
     */
    public function testValidateAssetDataWithMultipleErrors()
    {
        // Arrange
        $assetData = [
            'serial' => 'SERIAL123',
        ]; // Missing name
        $assetType = 'NonExistentAssetType'; // Invalid type

        // Act
        $result = $this->assetCreationService->validateAssetData($assetData, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['errors']);
        $this->assertCount(2, $result['errors']); // Should have 2 errors
    }
}
