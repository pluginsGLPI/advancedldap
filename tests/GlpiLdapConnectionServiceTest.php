<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\GlpiLdapConnectionService;
use PHPUnit\Framework\TestCase;

class GlpiLdapConnectionServiceTest extends TestCase
{
    private $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new GlpiLdapConnectionService();
    }

    /**
     * Test checkConnection method with null authldap_id
     */
    public function testCheckConnectionWithNullAuthldapId()
    {
        // Act
        $result = $this->service->checkConnection(null);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('connected', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('server_name', $result);

        $this->assertFalse($result['connected']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['server_name']);
        $this->assertStringContainsString('No AuthLDAP server selected', $result['error']);
    }

    /**
     * Test checkConnection method with zero authldap_id
     */
    public function testCheckConnectionWithZeroAuthldapId()
    {
        // Act
        $result = $this->service->checkConnection(0);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['connected']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['server_name']);
        $this->assertStringContainsString('No AuthLDAP server selected', $result['error']);
    }

    /**
     * Test checkConnection method with negative authldap_id
     */
    public function testCheckConnectionWithNegativeAuthldapId()
    {
        // Arrange
        $negativeId = -1;

        // Act
        $result = $this->service->checkConnection($negativeId);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('connected', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('server_name', $result);

        // With negative ID, it should try to load AuthLDAP and fail
        $this->assertFalse($result['connected']);
        $this->assertNotNull($result['error']);
        $this->assertNull($result['server_name']);
    }

    /**
     * Test that checkConnection returns proper structure for any input
     */
    public function testCheckConnectionReturnsProperStructure()
    {
        // Test with various inputs
        $testCases = [null, 0, -1, 999999];

        foreach ($testCases as $testCase) {
            $result = $this->service->checkConnection($testCase);

            // Assert structure is always consistent
            $this->assertIsArray($result, "Result should be array for input: $testCase");
            $this->assertArrayHasKey('connected', $result);
            $this->assertArrayHasKey('error', $result);
            $this->assertArrayHasKey('server_name', $result);

            $this->assertIsBool($result['connected']);
            $this->assertTrue(is_string($result['error']) || is_null($result['error']));
            $this->assertTrue(is_string($result['server_name']) || is_null($result['server_name']));
        }
    }

    /**
     * Test that error messages are properly localized
     */
    public function testCheckConnectionErrorMessagesAreLocalized()
    {
        // Test null case
        $result = $this->service->checkConnection(null);
        $this->assertStringContainsString('No AuthLDAP server selected', $result['error']);

        // Test non-existent ID case
        $result = $this->service->checkConnection(999999);
        $this->assertNotNull($result['error']);
        // Error should contain meaningful message
        $this->assertTrue(
            strpos($result['error'], 'AuthLDAP server not found') !== false ||
            strpos($result['error'], 'Cannot connect') !== false
        );
    }

    /**
     * Test checkConnection handles boundary values correctly
     */
    public function testCheckConnectionBoundaryValues()
    {
        // Test with maximum integer
        $result = $this->service->checkConnection(PHP_INT_MAX);
        $this->assertFalse($result['connected']);
        $this->assertNotNull($result['error']);

        // Test with minimum integer
        $result = $this->service->checkConnection(PHP_INT_MIN);
        $this->assertFalse($result['connected']);
        $this->assertNotNull($result['error']);
    }

    /**
     * Test that service implements LdapConnectionInterface
     */
    public function testServiceImplementsInterface()
    {
        $this->assertInstanceOf(
            'GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface',
            $this->service
        );
    }

    /**
     * Test that all required public methods exist
     */
    public function testPublicMethodsExist()
    {
        $requiredMethods = [
            'connect',
            'search',
            'getEntries',
            'close',
            'getError',
            'checkConnection'
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "Method $method should exist"
            );

            $reflection = new \ReflectionMethod($this->service, $method);
            $this->assertTrue(
                $reflection->isPublic(),
                "Method $method should be public"
            );
        }
    }

    /**
     * Test method signatures are correct
     */
    public function testMethodSignatures()
    {
        $reflection = new \ReflectionClass($this->service);

        // Test connect method
        $connectMethod = $reflection->getMethod('connect');
        $this->assertEquals(1, $connectMethod->getNumberOfRequiredParameters());

        // Test search method
        $searchMethod = $reflection->getMethod('search');
        $this->assertEquals(3, $searchMethod->getNumberOfRequiredParameters());

        // Test getEntries method
        $getEntriesMethod = $reflection->getMethod('getEntries');
        $this->assertEquals(2, $getEntriesMethod->getNumberOfRequiredParameters());

        // Test close method
        $closeMethod = $reflection->getMethod('close');
        $this->assertEquals(1, $closeMethod->getNumberOfRequiredParameters());

        // Test getError method
        $getErrorMethod = $reflection->getMethod('getError');
        $this->assertEquals(1, $getErrorMethod->getNumberOfRequiredParameters());

        // Test checkConnection method
        $checkConnectionMethod = $reflection->getMethod('checkConnection');
        $this->assertEquals(1, $checkConnectionMethod->getNumberOfRequiredParameters());
        $this->assertEquals(1, $checkConnectionMethod->getNumberOfParameters());
    }

    /**
     * Test checkConnection return types
     */
    public function testCheckConnectionReturnTypes()
    {
        $result = $this->service->checkConnection(null);

        // Verify return type hints are respected
        $reflection = new \ReflectionMethod($this->service, 'checkConnection');
        $returnType = $reflection->getReturnType();

        if ($returnType) {
            $this->assertEquals('array', $returnType->getName());
        }

        // Verify actual return matches expected type
        $this->assertIsArray($result);
    }
}