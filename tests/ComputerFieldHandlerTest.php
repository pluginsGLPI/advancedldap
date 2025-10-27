<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetFieldHandlers\ComputerFieldHandler;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;
use DbTestCase;

/**
 * Unit tests for ComputerFieldHandler
 */
class ComputerFieldHandlerTest extends DbTestCase
{
    private ComputerFieldHandler $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new ComputerFieldHandler();
    }

    /**
     * Test that handler implements AssetFieldHandlerInterface
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(AssetFieldHandlerInterface::class, $this->handler);
    }

    /**
     * Test supports method returns true for Computer
     */
    public function testSupportsComputer()
    {
        $this->assertTrue($this->handler->supports('Computer'));
    }

    /**
     * Test supports method returns false for other asset types
     */
    public function testDoesNotSupportOtherAssets()
    {
        $this->assertFalse($this->handler->supports('Phone'));
        $this->assertFalse($this->handler->supports('Printer'));
        $this->assertFalse($this->handler->supports('NetworkEquipment'));
        $this->assertFalse($this->handler->supports('User'));
    }

    /**
     * Test getRequiredFields returns name
     */
    public function testGetRequiredFields()
    {
        $required = $this->handler->getRequiredFields();

        $this->assertIsArray($required);
        $this->assertCount(1, $required);
        $this->assertContains('name', $required);
    }

    /**
     * Test getDefaultValues returns correct defaults
     */
    public function testGetDefaultValues()
    {
        $defaults = $this->handler->getDefaultValues();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('computertypes_id', $defaults);
        $this->assertArrayHasKey('states_id', $defaults);
        $this->assertEquals(0, $defaults['computertypes_id']);
        $this->assertEquals(0, $defaults['states_id']);
    }

    /**
     * Test handle method applies default values for missing fields
     */
    public function testHandleAppliesDefaults()
    {
        $data = [
            'name' => 'DESKTOP-001',
        ];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('computertypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals('DESKTOP-001', $result['name']);
        $this->assertEquals(0, $result['computertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method preserves existing values
     */
    public function testHandlePreservesExistingValues()
    {
        $data = [
            'name' => 'LAPTOP-002',
            'computertypes_id' => 5,
            'states_id' => 3,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('LAPTOP-002', $result['name']);
        $this->assertEquals(5, $result['computertypes_id']);
        $this->assertEquals(3, $result['states_id']);
    }

    /**
     * Test handle method with empty data
     */
    public function testHandleWithEmptyData()
    {
        $data = [];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('computertypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['computertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with partial data
     */
    public function testHandleWithPartialData()
    {
        $data = [
            'name' => 'SERVER-001',
            'computertypes_id' => 2,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('SERVER-001', $result['name']);
        $this->assertEquals(2, $result['computertypes_id']);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with additional fields not in defaults
     */
    public function testHandleWithAdditionalFields()
    {
        $data = [
            'name' => 'WORKSTATION-001',
            'serial' => 'SN123456',
            'comment' => 'Test computer',
            'computertypes_id' => 1,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('WORKSTATION-001', $result['name']);
        $this->assertEquals('SN123456', $result['serial']);
        $this->assertEquals('Test computer', $result['comment']);
        $this->assertEquals(1, $result['computertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method does not override existing zero values
     */
    public function testHandleDoesNotOverrideExistingZeroValues()
    {
        $data = [
            'name' => 'PC-001',
            'computertypes_id' => 0,
            'states_id' => 0,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals(0, $result['computertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }
}
