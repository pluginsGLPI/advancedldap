<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\LdapSyncService;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use GlpiPlugin\Advancedldap\Services\AssetCreationService;
use GlpiPlugin\Advancedldap\Services\AssetTypeClassifier;
use GlpiPlugin\Advancedldap\Services\LdapInventoryService;
use DbTestCase;

/**
 * Unit tests for LdapSyncService
 * Tests only public methods as per GLPI testing conventions
 */
class LdapSyncServiceTest extends DbTestCase
{
    private $ldapSyncService;
    private $ldapConnection;
    private $assetCreationService;
    private $assetTypeClassifier;

    public function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->ldapConnection = $this->createMock(LdapConnectionInterface::class);
        $this->assetCreationService = $this->createMock(AssetCreationService::class);
        $this->assetTypeClassifier = $this->createMock(AssetTypeClassifier::class);

        // Create service instance with mocked dependencies
        $this->ldapSyncService = new LdapSyncService(
            $this->ldapConnection,
            $this->assetCreationService,
            $this->assetTypeClassifier,
        );
    }

    /**
     * Test constructor properly initializes the service
     */
    public function testConstructor()
    {
        // Assert - verify instance is created properly
        $this->assertInstanceOf(LdapSyncService::class, $this->ldapSyncService);
    }

    /**
     * Test synchronizeFromFilter with non-existent sync filter
     * Should return error structure with sync filter not found message
     */
    public function testSynchronizeFromFilterWithInvalidSyncFilter()
    {
        // Arrange
        $syncfilterId = 999999; // Non-existent ID
        $authldapId = 1;

        // Act
        $result = $this->ldapSyncService->synchronizeFromFilter($syncfilterId, $authldapId);

        // Assert - verify error structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('details', $result);

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertIsArray($result['stats']);
        $this->assertEquals(0, $result['stats']['processed']);
    }

    /**
     * Test synchronizeFromFilter with non-existent AuthLDAP
     * Should return error structure with AuthLDAP not found message
     */
    public function testSynchronizeFromFilterWithInvalidAuthLdap()
    {
        // Arrange
        $syncfilterId = 1;
        $authldapId = 999999; // Non-existent ID

        // Act
        $result = $this->ldapSyncService->synchronizeFromFilter($syncfilterId, $authldapId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
    }

}