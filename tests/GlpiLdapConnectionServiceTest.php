<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\GlpiLdapConnectionService;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapFilterSanitizerInterface;
use PHPUnit\Framework\TestCase;

class GlpiLdapConnectionServiceTest extends TestCase
{
    private $service;
    private $repositoryMock;
    private $sanitizerMock;

    public function setUp(): void
    {
        parent::setUp();

        // Create a mock of SyncFilterRepositoryInterface
        $this->repositoryMock = $this->createMock(SyncFilterRepositoryInterface::class);

        // Configure the mock to return null for getFirstActiveAuthLdapId by default
        $this->repositoryMock->method('getFirstActiveAuthLdapId')
            ->willReturn(null);

        // Create a mock of LdapFilterSanitizerInterface
        $this->sanitizerMock = $this->createMock(LdapFilterSanitizerInterface::class);

        // Configure the sanitizer mock with default behavior (valid inputs)
        $this->sanitizerMock->method('isValidDN')
            ->willReturn(true);
        $this->sanitizerMock->method('sanitizeFilter')
            ->willReturnArgument(0); // Return the filter as-is by default

        $this->service = new GlpiLdapConnectionService($this->repositoryMock, $this->sanitizerMock);
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
        $this->assertNotNull($result['error']);

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
            'checkConnection',
            'searchWithErrorHandling'
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
     * Test searchWithErrorHandling rejects invalid Base DN
     */
    public function testSearchWithErrorHandlingRejectsInvalidBaseDN()
    {
        // Arrange - Create a new sanitizer mock for this test
        $sanitizerMock = $this->createMock(LdapFilterSanitizerInterface::class);
        $sanitizerMock->method('isValidDN')->willReturn(false); // Invalid DN

        $service = new GlpiLdapConnectionService($this->repositoryMock, $sanitizerMock);

        // Act
        $result = $service->searchWithErrorHandling(1, 'invalid)(dn=injection', '(objectClass=*)');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('entries', $result);
        $this->assertStringContainsString('Invalid LDAP Base DN', $result['error']);
    }

    /**
     * Test searchWithErrorHandling rejects invalid LDAP filter
     */
    public function testSearchWithErrorHandlingRejectsInvalidFilter()
    {
        // Arrange - Create a new sanitizer mock for this test
        $sanitizerMock = $this->createMock(LdapFilterSanitizerInterface::class);
        $sanitizerMock->method('isValidDN')->willReturn(true);  // Valid DN
        $sanitizerMock->method('sanitizeFilter')->willReturn(null); // Invalid filter

        $service = new GlpiLdapConnectionService($this->repositoryMock, $sanitizerMock);

        // Act
        $result = $service->searchWithErrorHandling(1, 'ou=users,dc=test,dc=com', '(uid=admin))(objectClass=*');

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('entries', $result);
        $this->assertStringContainsString('Invalid LDAP filter', $result['error']);
    }

    /**
     * Test searchWithErrorHandling sanitizes filter before search
     */
    public function testSearchWithErrorHandlingSanitizesFilter()
    {
        // Arrange - Create a new sanitizer mock for this test
        $sanitizerMock = $this->createMock(LdapFilterSanitizerInterface::class);
        $sanitizerMock->method('isValidDN')->willReturn(true);
        $sanitizerMock->expects($this->once())
            ->method('sanitizeFilter')
            ->with('(uid=test*)')
            ->willReturn('(uid=test\2a)'); // Escaped wildcard

        $service = new GlpiLdapConnectionService($this->repositoryMock, $sanitizerMock);

        // Act - This will fail to connect (no real LDAP), but we verify sanitizeFilter was called
        $result = $service->searchWithErrorHandling(999, 'ou=users,dc=test', '(uid=test*)');

        // Assert - Verify sanitizeFilter was called with the correct parameter
        // The result will be an error (cannot connect), but that's expected in unit test
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test searchWithErrorHandling validates DN before filter
     */
    public function testSearchWithErrorHandlingValidatesDNBeforeFilter()
    {
        // Arrange
        $sanitizerMock = $this->createMock(LdapFilterSanitizerInterface::class);
        $sanitizerMock->expects($this->once())
            ->method('isValidDN')
            ->with('ou=users,dc=test')
            ->willReturn(false);

        // sanitizeFilter should NOT be called if DN is invalid (early return)
        $sanitizerMock->expects($this->never())
            ->method('sanitizeFilter');

        $service = new GlpiLdapConnectionService($this->repositoryMock, $sanitizerMock);

        // Act
        $result = $service->searchWithErrorHandling(1, 'ou=users,dc=test', '(objectClass=*)');

        // Assert
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Invalid LDAP Base DN', $result['error']);
    }

    /**
     * Test searchWithErrorHandling returns proper structure on validation failure
     */
    public function testSearchWithErrorHandlingReturnStructureOnValidationFailure()
    {
        // Arrange
        $sanitizerMock = $this->createMock(LdapFilterSanitizerInterface::class);
        $sanitizerMock->method('isValidDN')->willReturn(false);

        $service = new GlpiLdapConnectionService($this->repositoryMock, $sanitizerMock);

        // Act
        $result = $service->searchWithErrorHandling(1, 'invalid', '(objectClass=*)');

        // Assert - Verify return structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayNotHasKey('entries', $result);
        $this->assertIsString($result['error']);
    }

    /**
     * Test constructor with sanitizer dependency
     */
    public function testConstructorWithSanitizerDependency()
    {
        // Arrange
        $repositoryMock = $this->createMock(SyncFilterRepositoryInterface::class);
        $sanitizerMock = $this->createMock(LdapFilterSanitizerInterface::class);

        // Act
        $service = new GlpiLdapConnectionService($repositoryMock, $sanitizerMock);

        // Assert
        $this->assertInstanceOf(GlpiLdapConnectionService::class, $service);
        $this->assertInstanceOf(
            'GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface',
            $service
        );
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

        // Test searchWithErrorHandling method
        $searchWithErrorHandlingMethod = $reflection->getMethod('searchWithErrorHandling');
        $this->assertEquals(3, $searchWithErrorHandlingMethod->getNumberOfRequiredParameters());
        $this->assertEquals(3, $searchWithErrorHandlingMethod->getNumberOfParameters());
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