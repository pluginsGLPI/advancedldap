<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetFieldHandlers\PrinterFieldHandler;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;
use DbTestCase;

/**
 * Unit tests for PrinterFieldHandler
 */
class PrinterFieldHandlerTest extends DbTestCase
{
    private PrinterFieldHandler $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new PrinterFieldHandler();
    }

    /**
     * Test that handler implements AssetFieldHandlerInterface
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(AssetFieldHandlerInterface::class, $this->handler);
    }

    /**
     * Test supports method returns true for Printer
     */
    public function testSupportsPrinter()
    {
        $this->assertTrue($this->handler->supports('Printer'));
    }

    /**
     * Test supports method returns false for other asset types
     */
    public function testDoesNotSupportOtherAssets()
    {
        $this->assertFalse($this->handler->supports('Computer'));
        $this->assertFalse($this->handler->supports('Phone'));
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
        $this->assertArrayHasKey('printertypes_id', $defaults);
        $this->assertArrayHasKey('states_id', $defaults);
        $this->assertEquals(0, $defaults['printertypes_id']);
        $this->assertEquals(0, $defaults['states_id']);
    }

    /**
     * Test handle method applies default values for missing fields
     */
    public function testHandleAppliesDefaults()
    {
        $data = [
            'name' => 'HP LaserJet Pro',
        ];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('printertypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals('HP LaserJet Pro', $result['name']);
        $this->assertEquals(0, $result['printertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method preserves existing values
     */
    public function testHandlePreservesExistingValues()
    {
        $data = [
            'name' => 'Canon Pixma',
            'printertypes_id' => 5,
            'states_id' => 3,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Canon Pixma', $result['name']);
        $this->assertEquals(5, $result['printertypes_id']);
        $this->assertEquals(3, $result['states_id']);
    }

    /**
     * Test handle method with empty data
     */
    public function testHandleWithEmptyData()
    {
        $data = [];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('printertypes_id', $result);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['printertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with partial data
     */
    public function testHandleWithPartialData()
    {
        $data = [
            'name' => 'Epson EcoTank',
            'printertypes_id' => 2,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Epson EcoTank', $result['name']);
        $this->assertEquals(2, $result['printertypes_id']);
        $this->assertArrayHasKey('states_id', $result);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with additional fields not in defaults
     */
    public function testHandleWithAdditionalFields()
    {
        $data = [
            'name' => 'Xerox WorkCentre',
            'serial' => 'XRX123456',
            'comment' => 'Multifunction printer',
            'locations_id' => 3,
            'printertypes_id' => 1,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('Xerox WorkCentre', $result['name']);
        $this->assertEquals('XRX123456', $result['serial']);
        $this->assertEquals('Multifunction printer', $result['comment']);
        $this->assertEquals(3, $result['locations_id']);
        $this->assertEquals(1, $result['printertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method does not override existing zero values
     */
    public function testHandleDoesNotOverrideExistingZeroValues()
    {
        $data = [
            'name' => 'Brother HL-L2350DW',
            'printertypes_id' => 0,
            'states_id' => 0,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals(0, $result['printertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }

    /**
     * Test handle method with printer-specific fields
     */
    public function testHandleWithPrinterSpecificFields()
    {
        $data = [
            'name' => 'HP Color LaserJet',
            'serial' => 'HPCLJ789',
            'have_ethernet' => 1,
            'have_wifi' => 1,
            'printertypes_id' => 2,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('HP Color LaserJet', $result['name']);
        $this->assertEquals('HPCLJ789', $result['serial']);
        $this->assertEquals(1, $result['have_ethernet']);
        $this->assertEquals(1, $result['have_wifi']);
        $this->assertEquals(2, $result['printertypes_id']);
        $this->assertEquals(0, $result['states_id']);
    }
}
