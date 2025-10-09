<?php

namespace GlpiPlugin\Advancedldap\Tests;

use AuthLDAP;
use GlpiPlugin\Advancedldap\Services\SyncFilterFormHelper;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use DbTestCase;

/**
 * Unit tests for SyncFilterFormHelper
 * Tests only public methods as per GLPI testing conventions
 *
 * Note: The following methods are not unit tested because they generate debug logs:
 * - buildAssetDropdown() - generates Toolbox::logDebug() at line 97
 * - getCurrentConfiguration() - generates Toolbox::logDebug() at line 151
 * - getAvailableAuthLdapServers() - generates Toolbox::logDebug() at line 190
 * - getFirstActiveAuthLdapId() - generates Toolbox::logDebug() at line 206
 *
 * The GLPI test framework rejects "unexpected log entries" causing test failures.
 * These methods should be tested via integration tests instead.
 *
 * Tested methods:
 * - checkLdapConnectionStatus() - delegates to LdapConnectionInterface without logging
 * - checkAuthLdapActiveStatus() - checks if AuthLDAP server is active
 */
class SyncFilterFormHelperTest extends DbTestCase
{
    private $formHelper;
    private $repository;
    private $ldapConnectionService;
    private $authldap;

    public function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->repository = $this->createMock(SyncFilterRepositoryInterface::class);
        $this->ldapConnectionService = $this->createMock(LdapConnectionInterface::class);

        // Create service instance with mocked dependencies
        $this->formHelper = new SyncFilterFormHelper(
            $this->repository,
            $this->ldapConnectionService,
        );

        // Create AuthLDAP instance for tests
        $this->authldap = new AuthLDAP();
    }


    /**
     * Test checkLdapConnectionStatus delegates to LdapConnectionInterface
     */
    public function testCheckLdapConnectionStatus()
    {
        // Arrange
        $authldapId = 25;
        $expectedStatus = [
            'connected' => true,
            'message' => 'Connection successful',
        ];

        $this->ldapConnectionService
            ->expects($this->once())
            ->method('checkConnection')
            ->with($authldapId)
            ->willReturn($expectedStatus);

        // Act
        $result = $this->formHelper->checkLdapConnectionStatus($authldapId);

        // Assert
        $this->assertEquals($expectedStatus, $result);
    }

    /**
     * Test checkLdapConnectionStatus with null authldap_id
     */
    public function testCheckLdapConnectionStatusWithNullId()
    {
        // Arrange
        $authldapId = null;
        $expectedStatus = [
            'connected' => false,
            'message' => 'No AuthLDAP ID provided',
        ];

        $this->ldapConnectionService
            ->expects($this->once())
            ->method('checkConnection')
            ->with($authldapId)
            ->willReturn($expectedStatus);

        // Act
        $result = $this->formHelper->checkLdapConnectionStatus($authldapId);

        // Assert
        $this->assertEquals($expectedStatus, $result);
    }

    /**
     * Test checkAuthLdapActiveStatus with null authldap_id
     * Should return is_active=true and server_name=null when no ID provided
     */
    public function testCheckAuthLdapActiveStatusWithNullId()
    {
        // Act
        $result = $this->formHelper->checkAuthLdapActiveStatus(null);

        // Assert
        $this->assertTrue($result['is_active']);
        $this->assertNull($result['server_name']);
    }

    /**
     * Test checkAuthLdapActiveStatus with active AuthLDAP server
     * Should return is_active=true with server name
     */
    public function testCheckAuthLdapActiveStatusWithActiveServer()
    {
        // Arrange - Create an active AuthLDAP server
        $authldap_id = $this->authldap->add([
            'name' => 'Test Active Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 1,
        ]);

        // Act
        $result = $this->formHelper->checkAuthLdapActiveStatus($authldap_id);

        // Assert
        $this->assertTrue($result['is_active']);
        $this->assertEquals('Test Active Server', $result['server_name']);
    }

    /**
     * Test checkAuthLdapActiveStatus with inactive AuthLDAP server
     * Should return is_active=false with server name
     */
    public function testCheckAuthLdapActiveStatusWithInactiveServer()
    {
        // Arrange - Create an inactive AuthLDAP server
        $authldap_id = $this->authldap->add([
            'name' => 'Test Inactive Server',
            'host' => 'ldap.example.com',
            'basedn' => 'dc=example,dc=com',
            'is_active' => 0,
        ]);

        // Act
        $result = $this->formHelper->checkAuthLdapActiveStatus($authldap_id);

        // Assert
        $this->assertFalse($result['is_active']);
        $this->assertEquals('Test Inactive Server', $result['server_name']);
    }

    /**
     * Test checkAuthLdapActiveStatus with non-existent AuthLDAP ID
     * Should return is_active=true and server_name=null when server doesn't exist
     */
    public function testCheckAuthLdapActiveStatusWithNonExistentId()
    {
        // Arrange - Use a non-existent ID
        $nonExistentId = 99999;

        // Act
        $result = $this->formHelper->checkAuthLdapActiveStatus($nonExistentId);

        // Assert
        $this->assertTrue($result['is_active']);
        $this->assertNull($result['server_name']);
    }

}