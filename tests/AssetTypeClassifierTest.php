<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetTypeClassifier;
use Computer;
use Phone;
use Printer;
use NetworkEquipment;
use DbTestCase;

class AssetTypeClassifierTest extends DbTestCase
{
    private $classifier;

    public function setUp(): void
    {
        parent::setUp();
        $this->classifier = new AssetTypeClassifier();
    }

    /**
     * Test isInventoriableAsset method with valid inventoriable asset types
     */
    public function testIsInventoriableAssetWithValidTypes()
    {
        // Test with standard inventoriable types
        $this->assertTrue($this->classifier->isInventoriableAsset(Computer::class));
        $this->assertTrue($this->classifier->isInventoriableAsset(Phone::class));
        $this->assertTrue($this->classifier->isInventoriableAsset(Printer::class));
        $this->assertTrue($this->classifier->isInventoriableAsset(NetworkEquipment::class));
    }

    /**
     * Test isInventoriableAsset method with non-inventoriable asset types
     */
    public function testIsInventoriableAssetWithNonInventoriableTypes()
    {
        // Test with non-inventoriable types (examples)
        $this->assertFalse($this->classifier->isInventoriableAsset('User'));
        $this->assertFalse($this->classifier->isInventoriableAsset('Group'));
        $this->assertFalse($this->classifier->isInventoriableAsset('Location'));
    }

    /**
     * Test isInventoriableAsset method with invalid inputs
     */
    public function testIsInventoriableAssetWithInvalidInputs()
    {
        // Test with empty string
        $this->assertFalse($this->classifier->isInventoriableAsset(''));

        // Test with non-existent class
        $this->assertFalse($this->classifier->isInventoriableAsset('NonExistentClass'));

        // Test with invalid class name
        $this->assertFalse($this->classifier->isInventoriableAsset('Invalid/Class/Name'));
    }

    /**
     * Test getSyncMethod method with inventoriable assets
     */
    public function testGetSyncMethodWithInventoriableAssets()
    {
        $this->assertEquals('inventory', $this->classifier->getSyncMethod(Computer::class));
        $this->assertEquals('inventory', $this->classifier->getSyncMethod(Phone::class));
        $this->assertEquals('inventory', $this->classifier->getSyncMethod(Printer::class));
        $this->assertEquals('inventory', $this->classifier->getSyncMethod(NetworkEquipment::class));
    }

    /**
     * Test getSyncMethod method with non-inventoriable assets
     */
    public function testGetSyncMethodWithNonInventoriableAssets()
    {
        $this->assertEquals('traditional', $this->classifier->getSyncMethod('User'));
        $this->assertEquals('traditional', $this->classifier->getSyncMethod('Group'));
        $this->assertEquals('traditional', $this->classifier->getSyncMethod(''));
        $this->assertEquals('traditional', $this->classifier->getSyncMethod('NonExistentClass'));
    }

    /**
     * Test getInventoriableAssetTypes method
     */
    public function testGetInventoriableAssetTypes()
    {
        $types = $this->classifier->getInventoriableAssetTypes();

        // Should return an array
        $this->assertIsArray($types);

        // Should contain standard inventoriable types
        $this->assertContains(Computer::class, $types);
        $this->assertContains(Phone::class, $types);
        $this->assertContains(Printer::class, $types);
        $this->assertContains(NetworkEquipment::class, $types);

        // Should not be empty
        $this->assertNotEmpty($types);
    }

    /**
     * Test getInventoriableAssetTypes method returns consistent results
     */
    public function testGetInventoriableAssetTypesConsistency()
    {
        $types1 = $this->classifier->getInventoriableAssetTypes();
        $types2 = $this->classifier->getInventoriableAssetTypes();

        // Multiple calls should return the same result
        $this->assertEquals($types1, $types2);
    }

    /**
     * Test shouldUseInventoryWorkflow method with inventoriable assets
     */
    public function testShouldUseInventoryWorkflowWithInventoriableAssets()
    {
        $this->assertTrue($this->classifier->shouldUseInventoryWorkflow(Computer::class));
        $this->assertTrue($this->classifier->shouldUseInventoryWorkflow(Phone::class));
        $this->assertTrue($this->classifier->shouldUseInventoryWorkflow(Printer::class));
        $this->assertTrue($this->classifier->shouldUseInventoryWorkflow(NetworkEquipment::class));
    }

    /**
     * Test shouldUseInventoryWorkflow method with non-inventoriable assets
     */
    public function testShouldUseInventoryWorkflowWithNonInventoriableAssets()
    {
        $this->assertFalse($this->classifier->shouldUseInventoryWorkflow('User'));
        $this->assertFalse($this->classifier->shouldUseInventoryWorkflow('Group'));
        $this->assertFalse($this->classifier->shouldUseInventoryWorkflow(''));
        $this->assertFalse($this->classifier->shouldUseInventoryWorkflow('NonExistentClass'));
    }

    /**
     * Test shouldUseTraditionalWorkflow method with inventoriable assets
     */
    public function testShouldUseTraditionalWorkflowWithInventoriableAssets()
    {
        $this->assertFalse($this->classifier->shouldUseTraditionalWorkflow(Computer::class));
        $this->assertFalse($this->classifier->shouldUseTraditionalWorkflow(Phone::class));
        $this->assertFalse($this->classifier->shouldUseTraditionalWorkflow(Printer::class));
        $this->assertFalse($this->classifier->shouldUseTraditionalWorkflow(NetworkEquipment::class));
    }

    /**
     * Test shouldUseTraditionalWorkflow method with non-inventoriable assets
     */
    public function testShouldUseTraditionalWorkflowWithNonInventoriableAssets()
    {
        $this->assertTrue($this->classifier->shouldUseTraditionalWorkflow('User'));
        $this->assertTrue($this->classifier->shouldUseTraditionalWorkflow('Group'));
        $this->assertTrue($this->classifier->shouldUseTraditionalWorkflow(''));
        $this->assertTrue($this->classifier->shouldUseTraditionalWorkflow('NonExistentClass'));
    }

    /**
     * Test workflow methods are complementary
     */
    public function testWorkflowMethodsAreComplementary()
    {
        $testCases = [
            Computer::class,
            'User',
            '',
            'NonExistentClass',
            Phone::class,
            Printer::class,
        ];

        foreach ($testCases as $assetType) {
            $useInventory = $this->classifier->shouldUseInventoryWorkflow($assetType);
            $useTraditional = $this->classifier->shouldUseTraditionalWorkflow($assetType);

            // One should be true, the other false (they should be complementary)
            $this->assertTrue($useInventory XOR $useTraditional,
                "Workflow methods should be complementary for asset type: $assetType");
        }
    }

    /**
     * Test getAssetTypeInfo method with inventoriable asset
     */
    public function testGetAssetTypeInfoWithInventoriableAsset()
    {
        $info = $this->classifier->getAssetTypeInfo(Computer::class);

        $this->assertIsArray($info);
        $this->assertEquals(Computer::class, $info['asset_type']);
        $this->assertTrue($info['is_inventoriable']);
        $this->assertEquals('inventory', $info['sync_method']);
        $this->assertEquals('inventory', $info['workflow']);
        $this->assertTrue($info['use_inventory_php']);
    }

    /**
     * Test getAssetTypeInfo method with non-inventoriable asset
     */
    public function testGetAssetTypeInfoWithNonInventoriableAsset()
    {
        $info = $this->classifier->getAssetTypeInfo('User');

        $this->assertIsArray($info);
        $this->assertEquals('User', $info['asset_type']);
        $this->assertFalse($info['is_inventoriable']);
        $this->assertEquals('traditional', $info['sync_method']);
        $this->assertEquals('traditional', $info['workflow']);
        $this->assertFalse($info['use_inventory_php']);
    }

    /**
     * Test getAssetTypeInfo method with empty string
     */
    public function testGetAssetTypeInfoWithEmptyString()
    {
        $info = $this->classifier->getAssetTypeInfo('');

        $this->assertIsArray($info);
        $this->assertEquals('', $info['asset_type']);
        $this->assertFalse($info['is_inventoriable']);
        $this->assertEquals('traditional', $info['sync_method']);
        $this->assertEquals('traditional', $info['workflow']);
        $this->assertFalse($info['use_inventory_php']);
    }

    /**
     * Test getAssetTypeInfo method returns all required keys
     */
    public function testGetAssetTypeInfoContainsAllRequiredKeys()
    {
        $info = $this->classifier->getAssetTypeInfo(Computer::class);

        $expectedKeys = ['asset_type', 'is_inventoriable', 'sync_method', 'workflow', 'use_inventory_php'];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $info, "Missing key: $key");
        }

        $this->assertCount(count($expectedKeys), $info, "Info array should contain exactly " . count($expectedKeys) . " keys");
    }

    /**
     * Test consistency between different methods
     */
    public function testMethodsConsistency()
    {
        $testCases = [
            Computer::class,
            Phone::class,
            'User',
            '',
            'NonExistentClass',
        ];

        foreach ($testCases as $assetType) {
            $isInventoriable = $this->classifier->isInventoriableAsset($assetType);
            $syncMethod = $this->classifier->getSyncMethod($assetType);
            $useInventoryWorkflow = $this->classifier->shouldUseInventoryWorkflow($assetType);
            $useTraditionalWorkflow = $this->classifier->shouldUseTraditionalWorkflow($assetType);
            $info = $this->classifier->getAssetTypeInfo($assetType);

            // All methods should be consistent
            $this->assertEquals($isInventoriable, $info['is_inventoriable'], "isInventoriableAsset inconsistent with getAssetTypeInfo for: $assetType");
            $this->assertEquals($syncMethod, $info['sync_method'], "getSyncMethod inconsistent with getAssetTypeInfo for: $assetType");
            $this->assertEquals($useInventoryWorkflow, $info['use_inventory_php'], "shouldUseInventoryWorkflow inconsistent with getAssetTypeInfo for: $assetType");
            $this->assertEquals($isInventoriable, $useInventoryWorkflow, "isInventoriableAsset inconsistent with shouldUseInventoryWorkflow for: $assetType");
            $this->assertEquals(!$isInventoriable, $useTraditionalWorkflow, "isInventoriableAsset inconsistent with shouldUseTraditionalWorkflow for: $assetType");

            if ($isInventoriable) {
                $this->assertEquals('inventory', $syncMethod, "Inventoriable asset should have 'inventory' sync method: $assetType");
                $this->assertEquals('inventory', $info['workflow'], "Inventoriable asset should have 'inventory' workflow: $assetType");
            } else {
                $this->assertEquals('traditional', $syncMethod, "Non-inventoriable asset should have 'traditional' sync method: $assetType");
                $this->assertEquals('traditional', $info['workflow'], "Non-inventoriable asset should have 'traditional' workflow: $assetType");
            }
        }
    }
}