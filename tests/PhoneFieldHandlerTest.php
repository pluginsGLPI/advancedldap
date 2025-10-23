<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetFieldHandlers\PhoneFieldHandler;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;
use DbTestCase;

/**
 * Unit tests for PhoneFieldHandler
 */
class PhoneFieldHandlerTest extends DbTestCase
{
    private PhoneFieldHandler $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new PhoneFieldHandler();
    }

    /**
     * Test that handler implements AssetFieldHandlerInterface
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(AssetFieldHandlerInterface::class, $this->handler);
    }

    /**
     * Test supports method returns true for Phone
     */
    public function testSupportsPhone()
    {
        $this->assertTrue($this->handler->supports('Phone'));
    }

    /**
     * Test supports method returns false for other asset types
     */
    public function testDoesNotSupportOtherAssets()
    {
        $this->assertFalse($this->handler->supports('Computer'));
        $this->assertFalse($this->handler->supports('Printer'));
        $this->assertFalse($this->handler->supports('NetworkEquipment'));
        $this->assertFalse($this->handler->supports('User'));
    }

    /**
     * Test getRequiredFields returns name and serial
     */
    public function testGetRequiredFields()
    {
        $required = $this->handler->getRequiredFields();

        $this->assertIsArray($required);
        $this->assertCount(2, $required);
        $this->assertContains('name', $required);
        $this->assertContains('serial', $required);
    }

    /**
     * Test getDefaultValues returns correct defaults
     */
    public function testGetDefaultValues()
    {
        $defaults = $this->handler->getDefaultValues();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('phonetypes_id', $defaults);
        $this->assertArrayHasKey('states_id', $defaults);
        $this->assertEquals(0, $defaults['phonetypes_id']);
        $this->assertEquals(0, $defaults['states_id']);
    }

    /**
     * Test handle method applies default values for missing fields
     */
    public function testHandleAppliesDefaults()
    {
        $data = [
            'name' => 'iPhone 14',
            'serial' => 'ABC123',
        ];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('serial', $result);
        $this->assertArrayHasKey('phonetypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals('iPhone 14', $result['name']);
        $this->assertEquals('ABC123', $result['serial']);
        $this->assertEquals(0, $result['phonetypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method preserves existing values
     */
    public function testHandlePreservesExistingValues()
    {
        $data = [
            'name' => 'Samsung Galaxy',
            'serial' => 'XYZ789',
            'phonetypes_id' => 5,
            'states_id' => 3,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Samsung Galaxy', $result['name']);
        $this->assertEquals('XYZ789', $result['serial']);
        $this->assertEquals(5, $result['phonetypes_id']);
        $this->assertEquals(3, $result['states_id']);
    }

    /**
     * Test handle method with empty data
     */
    public function testHandleWithEmptyData()
    {
        $data = [];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('phonetypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['phonetypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with partial data
     */
    public function testHandleWithPartialData()
    {
        $data = [
            'name' => 'Nokia',
            'phonetypes_id' => 2,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Nokia', $result['name']);
        $this->assertEquals(2, $result['phonetypes_id']);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['states_id']);
    }
}
