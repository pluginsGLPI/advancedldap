<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Factories\AssetFieldProviderFactory;
use GlpiPlugin\Advancedldap\Providers\GenericAssetFieldProvider;
use GlpiPlugin\Advancedldap\Providers\NativeAssetFieldProvider;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use DbTestCase;

class AssetFieldProviderFactoryTest extends DbTestCase
{
    private $factory;

    public function setUp(): void
    {
        parent::setUp();

        // Create factory instance
        $this->factory = new AssetFieldProviderFactory();
    }

    /**
     * Test createProvider method with generic asset itemtype (GenericAsset_ prefix)
     */
    public function testCreateProviderWithGenericAssetItemtype()
    {
        // Arrange
        $itemtype = 'GenericAsset_1';

        // Act
        $result = $this->factory->createProvider($itemtype);

        // Assert
        $this->assertInstanceOf(GenericAssetFieldProvider::class, $result);
        $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
    }

    /**
     * Test createProvider method with generic asset itemtype with different ID
     */
    public function testCreateProviderWithGenericAssetItemtypeVariousIds()
    {
        // Arrange
        $itemtypes = [
            'GenericAsset_5',
            'GenericAsset_123',
            'GenericAsset_999'
        ];

        foreach ($itemtypes as $itemtype) {
            // Act
            $result = $this->factory->createProvider($itemtype);

            // Assert
            $this->assertInstanceOf(GenericAssetFieldProvider::class, $result);
            $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
        }
    }

    /**
     * Test createProvider method with native asset itemtype (Computer)
     */
    public function testCreateProviderWithNativeAssetItemtype()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result = $this->factory->createProvider($itemtype);

        // Assert
        $this->assertInstanceOf(NativeAssetFieldProvider::class, $result);
        $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
    }

    /**
     * Test createProvider method with various native asset itemtypes
     */
    public function testCreateProviderWithVariousNativeAssetItemtypes()
    {
        // Arrange
        $itemtypes = [
            'Monitor',
            'Printer',
            'NetworkEquipment',
            'Phone',
            'Peripheral'
        ];

        foreach ($itemtypes as $itemtype) {
            // Act
            $result = $this->factory->createProvider($itemtype);

            // Assert
            $this->assertInstanceOf(NativeAssetFieldProvider::class, $result);
            $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
        }
    }

    /**
     * Test createProvider method with empty string itemtype
     */
    public function testCreateProviderWithEmptyStringItemtype()
    {
        // Arrange
        $itemtype = '';

        // Act
        $result = $this->factory->createProvider($itemtype);

        // Assert
        $this->assertInstanceOf(NativeAssetFieldProvider::class, $result);
        $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
    }

    /**
     * Test createProvider method with itemtype that starts with GenericAsset but is not valid format
     */
    public function testCreateProviderWithInvalidGenericAssetFormat()
    {
        // Arrange
        $itemtypes = [
            'GenericAsset',      // Missing underscore and ID - should return NativeAssetFieldProvider
            'GenericAssetTest',  // No underscore - should return NativeAssetFieldProvider
        ];

        foreach ($itemtypes as $itemtype) {
            // Act
            $result = $this->factory->createProvider($itemtype);

            // Assert - Should default to NativeAssetFieldProvider
            $this->assertInstanceOf(NativeAssetFieldProvider::class, $result);
            $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
        }
    }

    /**
     * Test createProvider method with GenericAsset_ prefix but empty ID
     */
    public function testCreateProviderWithGenericAssetEmptyId()
    {
        // Arrange
        $itemtype = 'GenericAsset_'; // Has prefix but empty ID

        // Act
        $result = $this->factory->createProvider($itemtype);

        // Assert - Should still return GenericAssetFieldProvider because it starts with 'GenericAsset_'
        $this->assertInstanceOf(GenericAssetFieldProvider::class, $result);
        $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
    }

    /**
     * Test createProvider method with case sensitivity
     */
    public function testCreateProviderWithCaseSensitivity()
    {
        // Arrange
        $itemtypes = [
            'genericasset_1',    // Lowercase
            'GENERICASSET_1',    // Uppercase
            'GenericAsseT_1',    // Mixed case
        ];

        foreach ($itemtypes as $itemtype) {
            // Act
            $result = $this->factory->createProvider($itemtype);

            // Assert - Should default to NativeAssetFieldProvider since case-sensitive
            $this->assertInstanceOf(NativeAssetFieldProvider::class, $result);
            $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
        }
    }

    /**
     * Test createProvider method with special characters in itemtype
     */
    public function testCreateProviderWithSpecialCharacters()
    {
        // Arrange
        $itemtypes = [
            'Test-Item',
            'Test@Item',
            'Test Item',
            'Test.Item',
        ];

        foreach ($itemtypes as $itemtype) {
            // Act
            $result = $this->factory->createProvider($itemtype);

            // Assert
            $this->assertInstanceOf(NativeAssetFieldProvider::class, $result);
            $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
        }
    }

    /**
     * Test createProvider method returns different instances for each call
     */
    public function testCreateProviderReturnsNewInstancesEachCall()
    {
        // Arrange
        $itemtype = 'Computer';

        // Act
        $result1 = $this->factory->createProvider($itemtype);
        $result2 = $this->factory->createProvider($itemtype);

        // Assert
        $this->assertInstanceOf(NativeAssetFieldProvider::class, $result1);
        $this->assertInstanceOf(NativeAssetFieldProvider::class, $result2);
        $this->assertNotSame($result1, $result2); // Different instances
    }

    /**
     * Test createProvider method with GenericAsset prefix and numeric ID
     */
    public function testCreateProviderWithGenericAssetNumericId()
    {
        // Arrange
        $itemtypes = [
            'GenericAsset_0',
            'GenericAsset_1',
            'GenericAsset_100',
            'GenericAsset_9999999',
        ];

        foreach ($itemtypes as $itemtype) {
            // Act
            $result = $this->factory->createProvider($itemtype);

            // Assert
            $this->assertInstanceOf(GenericAssetFieldProvider::class, $result);
            $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
        }
    }

    /**
     * Test createProvider method with GenericAsset prefix but non-numeric ID
     */
    public function testCreateProviderWithGenericAssetNonNumericId()
    {
        // Arrange
        $itemtypes = [
            'GenericAsset_abc',
            'GenericAsset_test',
            'GenericAsset_1a',
            'GenericAsset_a1',
        ];

        foreach ($itemtypes as $itemtype) {
            // Act
            $result = $this->factory->createProvider($itemtype);

            // Assert - Should still create GenericAssetFieldProvider since it only checks prefix
            $this->assertInstanceOf(GenericAssetFieldProvider::class, $result);
            $this->assertInstanceOf(AssetFieldProviderInterface::class, $result);
        }
    }
}