<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetFieldHandlers\NetworkEquipmentFieldHandler;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;
use DbTestCase;

/**
 * Unit tests for NetworkEquipmentFieldHandler
 */
class NetworkEquipmentFieldHandlerTest extends DbTestCase
{
    private NetworkEquipmentFieldHandler $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new NetworkEquipmentFieldHandler();
    }

    /**
     * Test that handler implements AssetFieldHandlerInterface
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(AssetFieldHandlerInterface::class, $this->handler);
    }

    /**
     * Test supports method returns true for NetworkEquipment
     */
    public function testSupportsNetworkEquipment()
    {
        $this->assertTrue($this->handler->supports('NetworkEquipment'));
    }

    /**
     * Test supports method returns false for other asset types
     */
    public function testDoesNotSupportOtherAssets()
    {
        $this->assertFalse($this->handler->supports('Computer'));
        $this->assertFalse($this->handler->supports('Printer'));
        $this->assertFalse($this->handler->supports('Phone'));
        $this->assertFalse($this->handler->supports('User'));
    }

    /**
     * Test getRequiredFields returns name
     * Note: NetworkEquipment also needs serial OR mac for unique identification
     * but only 'name' is strictly required by the handler
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
        $this->assertArrayHasKey('networkequipmenttypes_id', $defaults);
        $this->assertArrayHasKey('states_id', $defaults);
        $this->assertEquals(0, $defaults['networkequipmenttypes_id']);
        $this->assertEquals(0, $defaults['states_id']);
    }

    /**
     * Test handle method applies default values for missing fields
     */
    public function testHandleAppliesDefaults()
    {
        $data = [
            'name' => 'Switch-01',
        ];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('networkequipmenttypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals('Switch-01', $result['name']);
        $this->assertEquals(0, $result['networkequipmenttypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method preserves existing values
     */
    public function testHandlePreservesExistingValues()
    {
        $data = [
            'name' => 'Router-01',
            'networkequipmenttypes_id' => 5,
            'states_id' => 3,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Router-01', $result['name']);
        $this->assertEquals(5, $result['networkequipmenttypes_id']);
        $this->assertEquals(3, $result['states_id']);
    }

    /**
     * Test handle method with empty data
     */
    public function testHandleWithEmptyData()
    {
        $data = [];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('networkequipmenttypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['networkequipmenttypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with partial data
     */
    public function testHandleWithPartialData()
    {
        $data = [
            'name' => 'Firewall-01',
            'networkequipmenttypes_id' => 2,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Firewall-01', $result['name']);
        $this->assertEquals(2, $result['networkequipmenttypes_id']);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with additional fields (serial and mac)
     * These are important for NetworkEquipment unique identification
     */
    public function testHandleWithSerialAndMac()
    {
        $data = [
            'name' => 'Switch-Core-01',
            'serial' => 'SN987654321',
            'mac' => '00:1A:2B:3C:4D:5E',
            'networkequipmenttypes_id' => 1,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Switch-Core-01', $result['name']);
        $this->assertEquals('SN987654321', $result['serial']);
        $this->assertEquals('00:1A:2B:3C:4D:5E', $result['mac']);
        $this->assertEquals(1, $result['networkequipmenttypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with additional fields not in defaults
     */
    public function testHandleWithAdditionalFields()
    {
        $data = [
            'name' => 'AP-Office-01',
            'comment' => 'Wireless Access Point',
            'locations_id' => 5,
            'networkequipmenttypes_id' => 3,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('AP-Office-01', $result['name']);
        $this->assertEquals('Wireless Access Point', $result['comment']);
        $this->assertEquals(5, $result['locations_id']);
        $this->assertEquals(3, $result['networkequipmenttypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method does not override existing zero values
     */
    public function testHandleDoesNotOverrideExistingZeroValues()
    {
        $data = [
            'name' => 'Switch-01',
            'networkequipmenttypes_id' => 0,
            'states_id' => 0,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals(0, $result['networkequipmenttypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }
}
