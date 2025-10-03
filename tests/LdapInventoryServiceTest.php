<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\LdapInventoryService;
use GlpiPlugin\Advancedldap\Services\LdapToInventoryConverter;
use DbTestCase;

/**
 * Unit tests for LdapInventoryService
 * Tests only public methods as per GLPI testing conventions
 *
 * Note: syncInventoriableAsset() is not unit tested because:
 * - It's an orchestration method that delegates to GLPI's native Inventory system
 * - Any call to this method generates debug logs via Toolbox::logDebug()
 * - The GLPI test framework rejects "unexpected log entries" causing test failures
 * - This method should be tested via integration tests instead
 *
 * The other public methods (canHandleItemtype, getSupportedItemtypes, validateLdapData)
 * are thoroughly tested as they don't generate logs.
 *
 * Recent additions (03/10/2025):
 * - Silent failure detection: syncInventoriableAsset() now checks if assetId <= 0 after doInventory()
 * - getMinimumFieldRequirements(): Returns minimum field requirements per itemtype (private method)
 * - Field mappings support: syncInventoriableAsset() now accepts $fieldMappings parameter
 * These features are tested indirectly through integration tests (see tests/INTEGRATION_TESTS.md)
 */
class LdapInventoryServiceTest extends DbTestCase
{
    private $ldapInventoryService;
    private $converter;

    public function setUp(): void
    {
        parent::setUp();

        // Create mock for LdapToInventoryConverter dependency
        $this->converter = $this->createMock(LdapToInventoryConverter::class);

        // Create service instance with mocked dependency
        $this->ldapInventoryService = new LdapInventoryService($this->converter);
    }

    /**
     * Test constructor with required dependency
     */
    public function testConstructor()
    {
        // Arrange & Act - constructor called in setUp

        // Assert - verify instance is created properly
        $this->assertInstanceOf(LdapInventoryService::class, $this->ldapInventoryService);
    }

    /**
     * Test canHandleItemtype method with supported itemtype
     */
    public function testCanHandleItemtypeWithSupportedType()
    {
        // Arrange
        $itemtype = 'Computer';
        $supportedTypes = ['Computer', 'NetworkEquipment'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->canHandleItemtype($itemtype);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test canHandleItemtype method with unsupported itemtype
     */
    public function testCanHandleItemtypeWithUnsupportedType()
    {
        // Arrange
        $itemtype = 'UnsupportedAsset';
        $supportedTypes = ['Computer', 'NetworkEquipment'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->canHandleItemtype($itemtype);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test canHandleItemtype method with empty supported types
     */
    public function testCanHandleItemtypeWithEmptySupportedTypes()
    {
        // Arrange
        $itemtype = 'Computer';
        $supportedTypes = [];

        // Mock converter to return empty array
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->canHandleItemtype($itemtype);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getSupportedItemtypes method
     */
    public function testGetSupportedItemtypes()
    {
        // Arrange
        $expectedTypes = ['Computer', 'NetworkEquipment', 'Phone'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($expectedTypes);

        // Act
        $result = $this->ldapInventoryService->getSupportedItemtypes();

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($expectedTypes, $result);
        $this->assertCount(3, $result);
    }

    /**
     * Test getSupportedItemtypes method with empty array
     */
    public function testGetSupportedItemtypesWithEmptyArray()
    {
        // Arrange
        $expectedTypes = [];

        // Mock converter to return empty array
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($expectedTypes);

        // Act
        $result = $this->ldapInventoryService->getSupportedItemtypes();

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($expectedTypes, $result);
        $this->assertEmpty($result);
    }

    /**
     * Test validateLdapData method with valid data and supported itemtype
     */
    public function testValidateLdapDataWithValidData()
    {
        // Arrange
        $ldapData = [
            'cn' => ['COMPUTER01'],
            'description' => ['Test computer']
        ];
        $itemtype = 'Computer';
        $supportedTypes = ['Computer', 'NetworkEquipment'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->validateLdapData($ldapData, $itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertTrue($result['valid']);
        $this->assertIsArray($result['issues']);
        $this->assertEmpty($result['issues']);
    }

    /**
     * Test validateLdapData method with unsupported itemtype
     */
    public function testValidateLdapDataWithUnsupportedItemtype()
    {
        // Arrange
        $ldapData = [
            'cn' => ['ASSET01']
        ];
        $itemtype = 'UnsupportedAsset';
        $supportedTypes = ['Computer', 'NetworkEquipment'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->validateLdapData($ldapData, $itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['issues']);
        $this->assertNotEmpty($result['issues']);
        $this->assertStringContainsString('UnsupportedAsset', $result['issues'][0]);
        $this->assertStringContainsString('not supported', $result['issues'][0]);
    }

    /**
     * Test validateLdapData method with empty LDAP data
     */
    public function testValidateLdapDataWithEmptyData()
    {
        // Arrange
        $ldapData = [];
        $itemtype = 'Computer';
        $supportedTypes = ['Computer'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->validateLdapData($ldapData, $itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['issues']);
        $this->assertCount(2, $result['issues']); // Empty data + no identifying fields
        $this->assertContains('LDAP data is empty', $result['issues']);
        $this->assertContains('No identifying field found in LDAP data (cn, name, samaccountname, displayname)', $result['issues']);
    }

    /**
     * Test validateLdapData method with missing identifying fields
     */
    public function testValidateLdapDataWithMissingIdentifyingFields()
    {
        // Arrange
        $ldapData = [
            'description' => ['Some description'],
            'location' => ['Some location']
        ];
        $itemtype = 'Computer';
        $supportedTypes = ['Computer'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->validateLdapData($ldapData, $itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['issues']);
        $this->assertNotEmpty($result['issues']);
        $this->assertContains('No identifying field found in LDAP data (cn, name, samaccountname, displayname)', $result['issues']);
    }

    /**
     * Test validateLdapData method with empty identifying field values
     */
    public function testValidateLdapDataWithEmptyIdentifyingFieldValues()
    {
        // Arrange
        $ldapData = [
            'cn' => [''],
            'name' => [],
            'samaccountname' => [''],
            'description' => ['Some description']
        ];
        $itemtype = 'Computer';
        $supportedTypes = ['Computer'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->validateLdapData($ldapData, $itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['issues']);
        $this->assertNotEmpty($result['issues']);
        $this->assertContains('No identifying field found in LDAP data (cn, name, samaccountname, displayname)', $result['issues']);
    }

    /**
     * Test validateLdapData method with valid identifying field (displayname)
     */
    public function testValidateLdapDataWithDisplayname()
    {
        // Arrange
        $ldapData = [
            'displayname' => ['Computer Display Name'],
            'description' => ['Some description']
        ];
        $itemtype = 'Computer';
        $supportedTypes = ['Computer'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->validateLdapData($ldapData, $itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['valid']);
        $this->assertIsArray($result['issues']);
        $this->assertEmpty($result['issues']);
    }

    /**
     * Test validateLdapData method with multiple validation issues
     */
    public function testValidateLdapDataWithMultipleIssues()
    {
        // Arrange
        $ldapData = []; // Empty data + unsupported itemtype
        $itemtype = 'UnsupportedAsset';
        $supportedTypes = ['Computer'];

        // Mock converter to return supported itemtypes
        $this->converter
            ->expects($this->once())
            ->method('getSupportedItemtypes')
            ->willReturn($supportedTypes);

        // Act
        $result = $this->ldapInventoryService->validateLdapData($ldapData, $itemtype);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['issues']);
        $this->assertCount(3, $result['issues']); // Unsupported itemtype + empty data + no identifying fields
        $this->assertStringContainsString('UnsupportedAsset', $result['issues'][0]);
        $this->assertContains('LDAP data is empty', $result['issues']);
        $this->assertContains('No identifying field found in LDAP data (cn, name, samaccountname, displayname)', $result['issues']);
    }
}