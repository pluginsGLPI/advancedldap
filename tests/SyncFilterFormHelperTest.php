<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\SyncFilterFormHelper;
use GlpiPlugin\Advancedldap\Services\AssetFieldService;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
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
 */
class SyncFilterFormHelperTest extends DbTestCase
{
    private $formHelper;
    private $repository;
    private $ldapConnectionService;

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

}