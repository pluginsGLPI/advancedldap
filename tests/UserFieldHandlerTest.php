<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\AssetFieldHandlers\UserFieldHandler;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;
use DbTestCase;

/**
 * Unit tests for UserFieldHandler
 */
class UserFieldHandlerTest extends DbTestCase
{
    private UserFieldHandler $handler;

    public function setUp(): void
    {
        parent::setUp();
        $this->handler = new UserFieldHandler();
    }

    /**
     * Test that handler implements AssetFieldHandlerInterface
     */
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(AssetFieldHandlerInterface::class, $this->handler);
    }

    /**
     * Test supports method returns true for User
     */
    public function testSupportsUser()
    {
        $this->assertTrue($this->handler->supports('User'));
    }

    /**
     * Test supports method returns false for other asset types
     */
    public function testDoesNotSupportOtherAssets()
    {
        $this->assertFalse($this->handler->supports('Computer'));
        $this->assertFalse($this->handler->supports('Printer'));
        $this->assertFalse($this->handler->supports('NetworkEquipment'));
        $this->assertFalse($this->handler->supports('Phone'));
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
     * Test getDefaultValues returns correct defaults for User
     * User has different defaults than hardware assets
     */
    public function testGetDefaultValues()
    {
        $defaults = $this->handler->getDefaultValues();

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('is_active', $defaults);
        $this->assertArrayHasKey('is_deleted', $defaults);
        $this->assertEquals(1, $defaults['is_active']);
        $this->assertEquals(0, $defaults['is_deleted']);
    }

    /**
     * Test handle method applies default values for missing fields
     */
    public function testHandleAppliesDefaults()
    {
        $data = [
            'name' => 'john.doe',
        ];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('is_active', $result);
        $this->assertArrayHasKey('is_deleted', $result);
        $this->assertEquals('john.doe', $result['name']);
        $this->assertEquals(1, $result['is_active']);
        $this->assertEquals(0, $result['is_deleted']);
    }

    /**
     * Test handle method preserves existing values
     */
    public function testHandlePreservesExistingValues()
    {
        $data = [
            'name' => 'jane.smith',
            'is_active' => 0,
            'is_deleted' => 1,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('jane.smith', $result['name']);
        $this->assertEquals(0, $result['is_active']);
        $this->assertEquals(1, $result['is_deleted']);
    }

    /**
     * Test handle method with empty data
     */
    public function testHandleWithEmptyData()
    {
        $data = [];

        $result = $this->handler->handle($data);

        $this->assertArrayHasKey('is_active', $result);
        $this->assertArrayHasKey('is_deleted', $result);
        $this->assertEquals(1, $result['is_active']);
        $this->assertEquals(0, $result['is_deleted']);
    }

    /**
     * Test handle method with partial data
     */
    public function testHandleWithPartialData()
    {
        $data = [
            'name' => 'bob.wilson',
            'is_active' => 1,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('bob.wilson', $result['name']);
        $this->assertEquals(1, $result['is_active']);
        $this->assertArrayHasKey('is_deleted', $result);
        $this->assertEquals(0, $result['is_deleted']);
    }

    /**
     * Test handle method with additional user fields
     */
    public function testHandleWithAdditionalFields()
    {
        $data = [
            'name' => 'alice.jones',
            'firstname' => 'Alice',
            'realname' => 'Jones',
            'email' => 'alice.jones@example.com',
            'phone' => '+1234567890',
            'is_active' => 1,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('alice.jones', $result['name']);
        $this->assertEquals('Alice', $result['firstname']);
        $this->assertEquals('Jones', $result['realname']);
        $this->assertEquals('alice.jones@example.com', $result['email']);
        $this->assertEquals('+1234567890', $result['phone']);
        $this->assertEquals(1, $result['is_active']);
        $this->assertEquals(0, $result['is_deleted']);
    }

    /**
     * Test handle method does not override existing zero/false values
     */
    public function testHandleDoesNotOverrideExistingZeroValues()
    {
        $data = [
            'name' => 'inactive.user',
            'is_active' => 0,
            'is_deleted' => 0,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals(0, $result['is_active']);
        $this->assertEquals(0, $result['is_deleted']);
    }

    /**
     * Test handle method with user being marked as deleted
     */
    public function testHandleWithDeletedUser()
    {
        $data = [
            'name' => 'deleted.user',
            'is_active' => 0,
            'is_deleted' => 1,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('deleted.user', $result['name']);
        $this->assertEquals(0, $result['is_active']);
        $this->assertEquals(1, $result['is_deleted']);
    }

    /**
     * Test handle method with complete user profile
     */
    public function testHandleWithCompleteUserProfile()
    {
        $data = [
            'name' => 'complete.user',
            'firstname' => 'Complete',
            'realname' => 'User',
            'email' => 'complete@example.com',
            'phone' => '+9876543210',
            'mobile' => '+1111111111',
            'locations_id' => 5,
            'usertitles_id' => 2,
            'is_active' => 1,
            'is_deleted' => 0,
        ];

        $result = $this->handler->handle($data);

        $this->assertEquals('complete.user', $result['name']);
        $this->assertEquals('Complete', $result['firstname']);
        $this->assertEquals('User', $result['realname']);
        $this->assertEquals('complete@example.com', $result['email']);
        $this->assertEquals('+9876543210', $result['phone']);
        $this->assertEquals('+1111111111', $result['mobile']);
        $this->assertEquals(5, $result['locations_id']);
        $this->assertEquals(2, $result['usertitles_id']);
        $this->assertEquals(1, $result['is_active']);
        $this->assertEquals(0, $result['is_deleted']);
    }

    /**
     * Test that User defaults differ from hardware assets
     * Users use is_active/is_deleted while hardware uses *types_id/states_id
     */
    public function testUserDefaultsDifferFromHardwareAssets()
    {
        $defaults = $this->handler->getDefaultValues();

        // User should NOT have hardware-specific fields
        $this->assertArrayNotHasKey('computertypes_id', $defaults);
        $this->assertArrayNotHasKey('states_id', $defaults);
        $this->assertArrayNotHasKey('printertypes_id', $defaults);

        // User should have user-specific fields
        $this->assertArrayHasKey('is_active', $defaults);
        $this->assertArrayHasKey('is_deleted', $defaults);
    }
}
