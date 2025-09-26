<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\SyncFilterService;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface;
use DbTestCase;

class SyncFilterServiceTest extends DbTestCase
{
    private $syncFilterService;
    private $syncFilterRepository;
    private $relationRepository;

    public function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->syncFilterRepository = $this->createMock(SyncFilterRepositoryInterface::class);
        $this->relationRepository = $this->createMock(AuthLdapSyncFilterRepositoryInterface::class);

        // Create service instance with mocked dependencies
        $this->syncFilterService = new SyncFilterService(
            $this->syncFilterRepository,
            $this->relationRepository
        );
    }

    /**
     * Test getAvailableSyncFilters method
     */
    public function testGetAvailableSyncFilters()
    {
        // Arrange
        $expectedFilters = [
            ['id' => 1, 'name' => 'Filter 1', 'is_active' => 1],
            ['id' => 2, 'name' => 'Filter 2', 'is_active' => 1]
        ];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($expectedFilters);

        // Act
        $result = $this->syncFilterService->getAvailableSyncFilters();

        // Assert
        $this->assertEquals($expectedFilters, $result);
    }

    /**
     * Test getSyncFiltersForAuthLdap method
     */
    public function testGetSyncFiltersForAuthLdap()
    {
        // Arrange
        $authldapId = 123;
        $expectedFilters = [
            ['id' => 1, 'name' => 'Filter for AuthLDAP 123', 'authldap_id' => 123]
        ];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('getSyncFiltersForAuthLdap')
            ->with($authldapId)
            ->willReturn($expectedFilters);

        // Act
        $result = $this->syncFilterService->getSyncFiltersForAuthLdap($authldapId);

        // Assert
        $this->assertEquals($expectedFilters, $result);
    }

    /**
     * Test createSyncFilter method with default parameters
     */
    public function testCreateSyncFilterWithDefaults()
    {
        // Arrange
        $name = 'Test Filter';
        $ldapFilter = '(objectClass=computer)';
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $assetType = 'Computer';
        $expectedId = 42;

        $expectedData = [
            'name' => $name,
            'ldap_filter' => $ldapFilter,
            'base_dn' => $baseDn,
            'asset_type' => $assetType,
            'field_mappings' => [],
            'is_active' => 1,
        ];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('create')
            ->with($expectedData)
            ->willReturn($expectedId);

        // Act
        $result = $this->syncFilterService->createSyncFilter($name, $ldapFilter, $baseDn, $assetType);

        // Assert
        $this->assertEquals($expectedId, $result);
    }

    /**
     * Test createSyncFilter method with custom parameters
     */
    public function testCreateSyncFilterWithCustomParameters()
    {
        // Arrange
        $name = 'Custom Filter';
        $ldapFilter = '(objectClass=printer)';
        $baseDn = 'OU=Printers,DC=example,DC=com';
        $assetType = 'Printer';
        $fieldMappings = ['name' => 'cn', 'location' => 'physicalDeliveryOfficeName'];
        $isActive = false;
        $expectedId = 43;

        $expectedData = [
            'name' => $name,
            'ldap_filter' => $ldapFilter,
            'base_dn' => $baseDn,
            'asset_type' => $assetType,
            'field_mappings' => $fieldMappings,
            'is_active' => 0,
        ];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('create')
            ->with($expectedData)
            ->willReturn($expectedId);

        // Act
        $result = $this->syncFilterService->createSyncFilter(
            $name,
            $ldapFilter,
            $baseDn,
            $assetType,
            $fieldMappings,
            $isActive
        );

        // Assert
        $this->assertEquals($expectedId, $result);
    }

    /**
     * Test updateSyncFilter method
     */
    public function testUpdateSyncFilter()
    {
        // Arrange
        $filterId = 10;
        $updateData = ['name' => 'Updated Filter Name', 'is_active' => 0];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('update')
            ->with($filterId, $updateData)
            ->willReturn(true);

        // Act
        $result = $this->syncFilterService->updateSyncFilter($filterId, $updateData);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test deleteSyncFilter method
     */
    public function testDeleteSyncFilter()
    {
        // Arrange
        $filterId = 15;
        $authldapIds = [100, 200, 300];

        // Mock getting AuthLDAPs using this filter
        $this->relationRepository
            ->expects($this->once())
            ->method('getAuthLdapsForSyncFilter')
            ->with($filterId, false)
            ->willReturn($authldapIds);

        // Mock removing relations for each AuthLDAP
        $this->relationRepository
            ->expects($this->exactly(3))
            ->method('removeSyncFilterFromAuthLdap')
            ->willReturnCallback(function($authldapId, $syncfilterId) use ($filterId) {
                static $expectedIds = [100, 200, 300];
                static $callIndex = 0;

                // Verify parameters are correct
                $this->assertContains($authldapId, $expectedIds, "Unexpected authldap_id: $authldapId");
                $this->assertEquals($filterId, $syncfilterId, "Expected syncfilter_id to be $filterId");

                $callIndex++;
                return true;
            });

        // Mock deleting the filter itself
        $this->syncFilterRepository
            ->expects($this->once())
            ->method('delete')
            ->with($filterId)
            ->willReturn(true);

        // Act
        $result = $this->syncFilterService->deleteSyncFilter($filterId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test deleteSyncFilter method with mixed data types
     */
    public function testDeleteSyncFilterWithMixedDataTypes()
    {
        // Arrange
        $filterId = 20;
        // Mix of integers and non-integers (as can happen with some DB drivers)
        $authldapIds = [100, '200', null, 300, 'invalid'];

        // Mock getting AuthLDAPs using this filter
        $this->relationRepository
            ->expects($this->once())
            ->method('getAuthLdapsForSyncFilter')
            ->with($filterId, false)
            ->willReturn($authldapIds);

        // Mock removing relations - should only be called for integer IDs (100, 300)
        $this->relationRepository
            ->expects($this->exactly(2))
            ->method('removeSyncFilterFromAuthLdap')
            ->willReturnCallback(function($authldapId, $syncfilterId) use ($filterId) {
                // Only integer IDs should reach this method
                $this->assertIsInt($authldapId, "Expected integer authldap_id, got: " . gettype($authldapId));
                $this->assertContains($authldapId, [100, 300], "Unexpected authldap_id: $authldapId");
                $this->assertEquals($filterId, $syncfilterId);
                return true;
            });

        // Mock deleting the filter itself
        $this->syncFilterRepository
            ->expects($this->once())
            ->method('delete')
            ->with($filterId)
            ->willReturn(true);

        // Act
        $result = $this->syncFilterService->deleteSyncFilter($filterId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test assignSyncFilterToAuthLdap method for new relation
     */
    public function testAssignSyncFilterToAuthLdapNewRelation()
    {
        // Arrange
        $authldapId = 50;
        $syncfilterId = 25;
        $isActive = true;
        $expectedRelationId = 99;

        // Mock checking if relation already exists (returns false)
        $this->relationRepository
            ->expects($this->once())
            ->method('isSyncFilterAssignedToAuthLdap')
            ->with($authldapId, $syncfilterId, false)
            ->willReturn(false);

        // Mock creating new relation
        $this->relationRepository
            ->expects($this->once())
            ->method('addSyncFilterToAuthLdap')
            ->with($authldapId, $syncfilterId, $isActive)
            ->willReturn($expectedRelationId);

        // Act
        $result = $this->syncFilterService->assignSyncFilterToAuthLdap($authldapId, $syncfilterId, $isActive);

        // Assert
        $this->assertEquals($expectedRelationId, $result);
    }

    /**
     * Test assignSyncFilterToAuthLdap method for existing relation
     */
    public function testAssignSyncFilterToAuthLdapExistingRelation()
    {
        // Arrange
        $authldapId = 60;
        $syncfilterId = 35;
        $isActive = false;

        // Mock checking if relation already exists (returns true)
        $this->relationRepository
            ->expects($this->once())
            ->method('isSyncFilterAssignedToAuthLdap')
            ->with($authldapId, $syncfilterId, false)
            ->willReturn(true);

        // Mock updating existing relation
        $this->relationRepository
            ->expects($this->once())
            ->method('toggleSyncFilterForAuthLdap')
            ->with($authldapId, $syncfilterId, $isActive)
            ->willReturn(true);

        // Act
        $result = $this->syncFilterService->assignSyncFilterToAuthLdap($authldapId, $syncfilterId, $isActive);

        // Assert
        $this->assertEquals(1, $result); // Returns 1 as pseudo-ID for existing relation
    }

    /**
     * Test unassignSyncFilterFromAuthLdap method
     */
    public function testUnassignSyncFilterFromAuthLdap()
    {
        // Arrange
        $authldapId = 70;
        $syncfilterId = 45;

        $this->relationRepository
            ->expects($this->once())
            ->method('removeSyncFilterFromAuthLdap')
            ->with($authldapId, $syncfilterId)
            ->willReturn(true);

        // Act
        $result = $this->syncFilterService->unassignSyncFilterFromAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test getFieldMappings method with valid data
     */
    public function testGetFieldMappingsWithValidData()
    {
        // Arrange
        $syncfilterId = 80;
        $mappings = ['name' => 'cn', 'description' => 'description'];
        $filterData = [
            'id' => $syncfilterId,
            'field_mappings' => json_encode($mappings)
        ];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('findById')
            ->with($syncfilterId)
            ->willReturn($filterData);

        // Act
        $result = $this->syncFilterService->getFieldMappings($syncfilterId);

        // Assert
        $this->assertEquals($mappings, $result);
    }

    /**
     * Test getFieldMappings method with empty/invalid data
     */
    public function testGetFieldMappingsWithEmptyData()
    {
        // Arrange
        $syncfilterId = 90;
        $filterData = [
            'id' => $syncfilterId,
            'field_mappings' => ''
        ];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('findById')
            ->with($syncfilterId)
            ->willReturn($filterData);

        // Act
        $result = $this->syncFilterService->getFieldMappings($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getFieldMappings method with non-existent filter
     */
    public function testGetFieldMappingsWithNonExistentFilter()
    {
        // Arrange
        $syncfilterId = 999;

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('findById')
            ->with($syncfilterId)
            ->willReturn(null);

        // Act
        $result = $this->syncFilterService->getFieldMappings($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test updateFieldMappings method
     */
    public function testUpdateFieldMappings()
    {
        // Arrange
        $syncfilterId = 100;
        $mappings = ['name' => 'cn', 'serial' => 'serialNumber'];

        $this->syncFilterRepository
            ->expects($this->once())
            ->method('update')
            ->with($syncfilterId, ['field_mappings' => $mappings])
            ->willReturn(true);

        // Act
        $result = $this->syncFilterService->updateFieldMappings($syncfilterId, $mappings);

        // Assert
        $this->assertTrue($result);
    }
}