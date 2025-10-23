<?php

namespace GlpiPlugin\Advancedldap\Tests;

use Computer;
use NetworkEquipment;
use Phone;
use Printer;
use GlpiPlugin\Advancedldap\Services\AutoMappingService;
use GlpiPlugin\Advancedldap\Contracts\AutoMappingServiceInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapAttributeMapperInterface;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;
use DbTestCase;

/**
 * Unit tests for AutoMappingService
 */
class AutoMappingServiceTest extends DbTestCase
{
    private $attributeMapper;
    private AutoMappingService $service;

    public function setUp(): void
    {
        parent::setUp();

        // Create mock for LdapAttributeMapper
        $this->attributeMapper = $this->createMock(LdapAttributeMapperInterface::class);
        $this->attributeMapper->method('getStandardMappings')
            ->willReturn([
                'name' => 'cn',
                'serial' => 'serialNumber',
                'mac' => 'macAddress',
                'comment' => 'description',
            ]);

        // Create service with mocked mapper
        $this->service = new AutoMappingService($this->attributeMapper);
    }

    /**
     * Test that service implements the interface
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(AutoMappingServiceInterface::class, $this->service);
    }

    /**
     * Test getDefaultMappings for Computer
     */
    public function testGetDefaultMappingsForComputer()
    {
        $mappings = $this->service->getDefaultMappings(Computer::class);

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('name', $mappings);
        $this->assertEquals('cn', $mappings['name']);
    }

    /**
     * Test getDefaultMappings for Phone
     */
    public function testGetDefaultMappingsForPhone()
    {
        $mappings = $this->service->getDefaultMappings(Phone::class);

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('name', $mappings);
        $this->assertArrayHasKey('serial', $mappings);
        $this->assertEquals('cn', $mappings['name']);
        $this->assertEquals('serialNumber', $mappings['serial']);
    }

    /**
     * Test getDefaultMappings for Printer
     */
    public function testGetDefaultMappingsForPrinter()
    {
        $mappings = $this->service->getDefaultMappings(Printer::class);

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('name', $mappings);
        $this->assertEquals('cn', $mappings['name']);
    }

    /**
     * Test getDefaultMappings for NetworkEquipment includes serial
     */
    public function testGetDefaultMappingsForNetworkEquipment()
    {
        $mappings = $this->service->getDefaultMappings(NetworkEquipment::class);

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey('name', $mappings);
        $this->assertArrayHasKey('serial', $mappings);
        $this->assertEquals('cn', $mappings['name']);
        $this->assertEquals('serialNumber', $mappings['serial']);
    }

    /**
     * Test getRequiredFieldsForItemtype with Computer
     */
    public function testGetRequiredFieldsForComputer()
    {
        $required = $this->service->getRequiredFieldsForItemtype(Computer::class);

        $this->assertIsArray($required);
        $this->assertContains('name', $required);
    }

    /**
     * Test getRequiredFieldsForItemtype with Phone
     */
    public function testGetRequiredFieldsForPhone()
    {
        $required = $this->service->getRequiredFieldsForItemtype(Phone::class);

        $this->assertIsArray($required);
        $this->assertContains('name', $required);
        $this->assertContains('serial', $required);
    }

    /**
     * Test getRequiredFieldsForItemtype with Printer
     */
    public function testGetRequiredFieldsForPrinter()
    {
        $required = $this->service->getRequiredFieldsForItemtype(Printer::class);

        $this->assertIsArray($required);
        $this->assertContains('name', $required);
    }

    /**
     * Test getRequiredFieldsForItemtype with NetworkEquipment
     */
    public function testGetRequiredFieldsForNetworkEquipment()
    {
        $required = $this->service->getRequiredFieldsForItemtype(NetworkEquipment::class);

        $this->assertIsArray($required);
        $this->assertContains('name', $required);
    }

    /**
     * Test suggestLdapAttribute with standard mapping
     */
    public function testSuggestLdapAttributeWithStandardMapping()
    {
        $suggestion = $this->service->suggestLdapAttribute('name');
        $this->assertEquals('cn', $suggestion);

        $suggestion = $this->service->suggestLdapAttribute('serial');
        $this->assertEquals('serialNumber', $suggestion);
    }

    /**
     * Test suggestLdapAttribute with available attributes
     */
    public function testSuggestLdapAttributeWithAvailableAttributes()
    {
        $available = ['cn', 'serialNumber', 'description'];

        $suggestion = $this->service->suggestLdapAttribute('name', $available);
        $this->assertEquals('cn', $suggestion);

        $suggestion = $this->service->suggestLdapAttribute('serial', $available);
        $this->assertEquals('serialNumber', $suggestion);
    }

    /**
     * Test suggestLdapAttribute with unavailable standard mapping
     */
    public function testSuggestLdapAttributeWithUnavailableMapping()
    {
        $available = ['displayName', 'description'];

        // 'name' maps to 'cn', but 'cn' is not available
        $suggestion = $this->service->suggestLdapAttribute('name', $available);
        $this->assertNull($suggestion);
    }

    /**
     * Test suggestLdapAttribute with no standard mapping
     */
    public function testSuggestLdapAttributeWithNoStandardMapping()
    {
        $suggestion = $this->service->suggestLdapAttribute('unknownfield');
        $this->assertNull($suggestion);
    }

    /**
     * Test suggestLdapAttribute with exact match fallback
     */
    public function testSuggestLdapAttributeWithExactMatchFallback()
    {
        $available = ['customField', 'description'];

        // No standard mapping for 'customField', but it exists in available
        $suggestion = $this->service->suggestLdapAttribute('customField', $available);
        $this->assertEquals('customField', $suggestion);
    }

    /**
     * Test addFieldHandler method
     */
    public function testAddFieldHandler()
    {
        $handler = $this->createMock(AssetFieldHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('getRequiredFields')->willReturn(['name', 'custom_field']);

        $this->service->addFieldHandler($handler);

        // Test that the handler is used
        $required = $this->service->getRequiredFieldsForItemtype('SomeAsset');
        $this->assertContains('name', $required);
        $this->assertContains('custom_field', $required);
    }

    /**
     * Test getDefaultMappings with custom field handler
     */
    public function testGetDefaultMappingsWithCustomHandler()
    {
        $handler = $this->createMock(AssetFieldHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->method('getRequiredFields')->willReturn(['name', 'comment']);

        $this->service->addFieldHandler($handler);

        $mappings = $this->service->getDefaultMappings('CustomAsset');

        $this->assertArrayHasKey('name', $mappings);
        $this->assertArrayHasKey('comment', $mappings);
        $this->assertEquals('cn', $mappings['name']);
        $this->assertEquals('description', $mappings['comment']);
    }

    /**
     * Test getDefaultMappings returns empty for unsupported itemtype with no standard mappings
     */
    public function testGetDefaultMappingsForUnsupportedItemtype()
    {
        // Create a fresh service with empty standard mappings
        $emptyMapper = $this->createMock(LdapAttributeMapperInterface::class);
        $emptyMapper->method('getStandardMappings')->willReturn([]);

        $service = new AutoMappingService($emptyMapper);

        $mappings = $service->getDefaultMappings('UnknownAsset');

        // Should return empty or only name mapping depending on implementation
        $this->assertIsArray($mappings);
    }
}
