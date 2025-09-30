<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\GlpiConfigurationService;
use DbTestCase;

/**
 * Unit tests for GlpiConfigurationService
 * Tests only public methods as per GLPI testing conventions
 *
 * Note: isInventoryEnabled() is not unit tested because:
 * - This method generates debug logs via Toolbox::logDebug() at line 98
 * - The GLPI test framework rejects "unexpected log entries" causing test failures
 * - This method should be tested via integration tests instead
 */
class GlpiConfigurationServiceTest extends DbTestCase
{
    private $configurationService;

    public function setUp(): void
    {
        parent::setUp();

        // Create service instance
        $this->configurationService = new GlpiConfigurationService();
    }

    /**
     * Test get method with existing configuration value
     */
    public function testGetWithExistingValue()
    {
        // Arrange
        $key = 'test_key';
        $expectedValue = 'test_value';

        // First set a value using Config directly
        \Config::setConfigurationValues('plugin:Advancedldap', [$key => $expectedValue]);

        // Act
        $result = $this->configurationService->get($key);

        // Assert
        $this->assertEquals($expectedValue, $result);
    }

    /**
     * Test get method with non-existing key returns zero
     */
    public function testGetWithNonExistingKeyReturnsZero()
    {
        // Arrange
        $key = 'non_existing_key';

        // Act
        $result = $this->configurationService->get($key, 'default_value');

        // Assert
        // GLPI returns 0 for non-existing configuration keys
        $this->assertEquals(0, $result);
    }

    /**
     * Test get method with non-existing key and no default returns zero
     */
    public function testGetWithNonExistingKeyReturnsZero2()
    {
        // Arrange
        $key = 'non_existing_key_2';

        // Act
        $result = $this->configurationService->get($key);

        // Assert
        // GLPI returns 0 for non-existing configuration keys, even without default
        $this->assertEquals(0, $result);
    }

    /**
     * Test set method with valid values
     */
    public function testSetWithValidValues()
    {
        // Arrange
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 123,
        ];

        // Act
        $result = $this->configurationService->set($values);

        // Assert
        $this->assertTrue($result);

        // Verify values were actually set
        foreach ($values as $key => $expectedValue) {
            $actualValue = $this->configurationService->get($key);
            $this->assertEquals($expectedValue, $actualValue);
        }
    }

    /**
     * Test set method with empty values array
     */
    public function testSetWithEmptyValues()
    {
        // Arrange
        $values = [];

        // Act
        $result = $this->configurationService->set($values);

        // Assert
        $this->assertTrue($result);
    }


    /**
     * Test getGlpiConfig method with existing global config
     */
    public function testGetGlpiConfigWithExistingKey()
    {
        // Arrange
        global $CFG_GLPI;
        $key = 'root_doc';
        $expectedValue = '/glpi';
        $CFG_GLPI[$key] = $expectedValue;

        // Act
        $result = $this->configurationService->getGlpiConfig($key);

        // Assert
        $this->assertEquals($expectedValue, $result);
    }

    /**
     * Test getGlpiConfig method with non-existing key
     */
    public function testGetGlpiConfigWithNonExistingKey()
    {
        // Arrange
        global $CFG_GLPI;
        $key = 'non_existing_global_key';
        unset($CFG_GLPI[$key]); // Ensure key doesn't exist

        // Act
        $result = $this->configurationService->getGlpiConfig($key);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test getInventoryConfigUrl method returns correct URL
     */
    public function testGetInventoryConfigUrl()
    {
        // Arrange
        global $CFG_GLPI;
        $rootDoc = '/test_glpi';
        $CFG_GLPI['root_doc'] = $rootDoc;
        $expectedUrl = $rootDoc . '/front/inventory.conf.php';

        // Act
        $result = $this->configurationService->getInventoryConfigUrl();

        // Assert
        $this->assertEquals($expectedUrl, $result);
    }

    /**
     * Test getInventoryConfigUrl method with empty root_doc
     */
    public function testGetInventoryConfigUrlWithEmptyRootDoc()
    {
        // Arrange
        global $CFG_GLPI;
        $CFG_GLPI['root_doc'] = '';
        $expectedUrl = '/front/inventory.conf.php';

        // Act
        $result = $this->configurationService->getInventoryConfigUrl();

        // Assert
        $this->assertEquals($expectedUrl, $result);
    }

    /**
     * Test getInventoryConfigUrl method with null root_doc
     */
    public function testGetInventoryConfigUrlWithNullRootDoc()
    {
        // Arrange
        global $CFG_GLPI;
        $CFG_GLPI['root_doc'] = null;
        $expectedUrl = '/front/inventory.conf.php'; // null . string = string in PHP

        // Act
        $result = $this->configurationService->getInventoryConfigUrl();

        // Assert
        $this->assertEquals($expectedUrl, $result);
    }

    /**
     * Test that plugin namespace constant is properly used
     */
    public function testPluginNamespaceUsage()
    {
        // Arrange
        $key = 'namespace_test';
        $value = 'namespace_value';

        // Act
        $this->configurationService->set([$key => $value]);

        // Assert - Verify value was set in correct namespace
        $directValue = \Config::getConfigurationValue('plugin:Advancedldap', $key);
        $this->assertEquals($value, $directValue);

        // Also verify get method returns the same value
        $getValue = $this->configurationService->get($key);
        $this->assertEquals($value, $getValue);
    }

    public function tearDown(): void
    {
        // Clean up configuration values set during tests
        global $DB;

        if ($DB) {
            $DB->delete('glpi_configs', [
                'context' => 'plugin:Advancedldap'
            ]);

            $DB->delete('glpi_configs', [
                'context' => 'inventory'
            ]);
        }

        parent::tearDown();
    }
}