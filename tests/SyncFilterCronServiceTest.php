<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\SyncFilterCronService;
use GlpiPlugin\Advancedldap\Services\LdapSyncService;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use CronTask;
use DbTestCase;

/**
 * Unit tests for SyncFilterCronService
 * Tests cron task execution and synchronization management
 */
class SyncFilterCronServiceTest extends DbTestCase
{
    private $cronService;
    private $repository;
    private $syncService;

    public function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->repository = $this->createMock(SyncFilterRepositoryInterface::class);
        $this->syncService = $this->createMock(LdapSyncService::class);

        // Create service instance with mocked dependencies
        $this->cronService = new SyncFilterCronService(
            $this->repository,
            $this->syncService
        );
    }

    /**
     * Test constructor properly initializes the service
     */
    public function testConstructor()
    {
        $this->assertInstanceOf(SyncFilterCronService::class, $this->cronService);
    }

    /**
     * Test executeSyncTask with no active filters
     * Should return 0 (nothing to do)
     */
    public function testExecuteSyncTaskWithNoActiveFilters()
    {
        // Arrange
        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn([]);

        // Act
        $result = $this->cronService->executeSyncTask(null);

        // Assert
        $this->assertEquals(0, $result, 'Should return 0 when no active filters found');
    }

    /**
     * Test executeSyncTask with one successful filter
     * Should return 1 (success)
     */
    public function testExecuteSyncTaskWithOneSuccessfulFilter()
    {
        // Arrange
        $activeFilters = [
            [
                'id' => 1,
                'name' => 'Test Filter',
                'base_dn' => 'ou=users,dc=test,dc=com',
                'ldap_filter' => '(objectClass=person)',
                'asset_type' => 'Computer',
            ],
        ];

        $activeAuthLdapServers = [
            ['id' => 1],
        ];

        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($activeFilters);

        $this->repository
            ->expects($this->once())
            ->method('getActiveAuthLdapServers')
            ->willReturn($activeAuthLdapServers);

        $this->repository
            ->expects($this->once())
            ->method('getAssociatedAuthLdaps')
            ->with(1)
            ->willReturn([1]);

        $syncResult = [
            'success' => true,
            'stats' => [
                'created' => 5,
                'updated' => 3,
                'errors' => 0,
                'processed' => 8,
            ],
        ];

        $this->syncService
            ->expects($this->once())
            ->method('synchronizeFromFilter')
            ->with(1, 1)
            ->willReturn($syncResult);

        // Act
        $result = $this->cronService->executeSyncTask(null);

        // Assert
        $this->assertEquals(1, $result, 'Should return 1 on successful synchronization');
    }

    /**
     * Test executeSyncTask with multiple filters
     * Should process all filters and return 1
     */
    public function testExecuteSyncTaskWithMultipleFilters()
    {
        // Arrange
        $activeFilters = [
            [
                'id' => 1,
                'name' => 'Filter 1',
                'base_dn' => 'ou=users,dc=test,dc=com',
                'ldap_filter' => '(objectClass=person)',
                'asset_type' => 'Computer',
            ],
            [
                'id' => 2,
                'name' => 'Filter 2',
                'base_dn' => 'ou=printers,dc=test,dc=com',
                'ldap_filter' => '(objectClass=printer)',
                'asset_type' => 'Printer',
            ],
        ];

        $activeAuthLdapServers = [
            ['id' => 1],
        ];

        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($activeFilters);

        $this->repository
            ->expects($this->once())
            ->method('getActiveAuthLdapServers')
            ->willReturn($activeAuthLdapServers);

        $this->repository
            ->expects($this->exactly(2))
            ->method('getAssociatedAuthLdaps')
            ->willReturnOnConsecutiveCalls([1], [1]);

        $syncResult1 = [
            'success' => true,
            'stats' => ['created' => 2, 'updated' => 1, 'errors' => 0, 'processed' => 3],
        ];

        $syncResult2 = [
            'success' => true,
            'stats' => ['created' => 3, 'updated' => 2, 'errors' => 0, 'processed' => 5],
        ];

        $this->syncService
            ->expects($this->exactly(2))
            ->method('synchronizeFromFilter')
            ->willReturnOnConsecutiveCalls($syncResult1, $syncResult2);

        // Act
        $result = $this->cronService->executeSyncTask(null);

        // Assert
        $this->assertEquals(1, $result, 'Should return 1 when all filters succeed');
    }

    /**
     * Test executeSyncTask with failed synchronization
     * Should handle errors gracefully and return 0
     */
    public function testExecuteSyncTaskWithFailedSync()
    {
        // Arrange
        $activeFilters = [
            [
                'id' => 1,
                'name' => 'Test Filter',
                'base_dn' => 'ou=users,dc=test,dc=com',
                'ldap_filter' => '(objectClass=person)',
                'asset_type' => 'Computer',
            ],
        ];

        $activeAuthLdapServers = [
            ['id' => 1],
        ];

        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($activeFilters);

        $this->repository
            ->expects($this->once())
            ->method('getActiveAuthLdapServers')
            ->willReturn($activeAuthLdapServers);

        $this->repository
            ->expects($this->once())
            ->method('getAssociatedAuthLdaps')
            ->with(1)
            ->willReturn([1]);

        $syncResult = [
            'success' => false,
            'error' => 'LDAP connection failed',
            'stats' => ['created' => 0, 'updated' => 0, 'errors' => 1, 'processed' => 0],
        ];

        $this->syncService
            ->expects($this->once())
            ->method('synchronizeFromFilter')
            ->with(1, 1)
            ->willReturn($syncResult);

        // Act
        $result = $this->cronService->executeSyncTask(null);

        // Assert
        $this->assertEquals(0, $result, 'Should return 0 when all syncs fail');
    }

    /**
     * Test executeSyncTask with exception during sync
     * Should catch exception and continue processing
     */
    public function testExecuteSyncTaskWithException()
    {
        // Arrange
        $activeFilters = [
            [
                'id' => 1,
                'name' => 'Test Filter',
                'base_dn' => 'ou=users,dc=test,dc=com',
                'ldap_filter' => '(objectClass=person)',
                'asset_type' => 'Computer',
            ],
        ];

        $activeAuthLdapServers = [
            ['id' => 1],
        ];

        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($activeFilters);

        $this->repository
            ->expects($this->once())
            ->method('getActiveAuthLdapServers')
            ->willReturn($activeAuthLdapServers);

        $this->repository
            ->expects($this->once())
            ->method('getAssociatedAuthLdaps')
            ->with(1)
            ->willReturn([1]);

        $this->syncService
            ->expects($this->once())
            ->method('synchronizeFromFilter')
            ->willThrowException(new \Exception('Unexpected error'));

        // Act
        $result = $this->cronService->executeSyncTask(null);

        // Assert
        $this->assertEquals(0, $result, 'Should return 0 when exception occurs');
    }

    /**
     * Test executeSyncTask with max_filters limit
     * Should process only the specified number of filters and return -1
     */
    public function testExecuteSyncTaskWithMaxFiltersLimit()
    {
        // Arrange
        $activeFilters = [
            [
                'id' => 1,
                'name' => 'Filter 1',
                'base_dn' => 'ou=users,dc=test,dc=com',
                'ldap_filter' => '(objectClass=person)',
                'asset_type' => 'Computer',
            ],
            [
                'id' => 2,
                'name' => 'Filter 2',
                'base_dn' => 'ou=printers,dc=test,dc=com',
                'ldap_filter' => '(objectClass=printer)',
                'asset_type' => 'Printer',
            ],
        ];

        $activeAuthLdapServers = [
            ['id' => 1],
        ];

        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($activeFilters);

        $this->repository
            ->expects($this->once())
            ->method('getActiveAuthLdapServers')
            ->willReturn($activeAuthLdapServers);

        // getAllActiveSyncFiltersWithAuthLdap loops through ALL filters to build the list
        // So getAssociatedAuthLdaps will be called for both filters (not limited by max_filters)
        $this->repository
            ->expects($this->exactly(2))
            ->method('getAssociatedAuthLdaps')
            ->willReturnOnConsecutiveCalls([1], [1]);

        $syncResult = [
            'success' => true,
            'stats' => ['created' => 2, 'updated' => 1, 'errors' => 0, 'processed' => 3],
        ];

        // Only the first filter will be synchronized (due to max_filters = 1)
        $this->syncService
            ->expects($this->once())
            ->method('synchronizeFromFilter')
            ->willReturn($syncResult);

        // Create mock CronTask with max_filters = 1
        $mockTask = $this->createMock(CronTask::class);
        $mockTask->fields = ['param' => 1];
        $mockTask->expects($this->atLeastOnce())->method('log');

        // Act
        $result = $this->cronService->executeSyncTask($mockTask);

        // Assert
        $this->assertEquals(-1, $result, 'Should return -1 when more filters remain');
    }

    /**
     * Test executeSyncTask filters out inactive AuthLDAP servers
     */
    public function testExecuteSyncTaskFiltersInactiveAuthLdap()
    {
        // Arrange
        $activeFilters = [
            [
                'id' => 1,
                'name' => 'Test Filter',
                'base_dn' => 'ou=users,dc=test,dc=com',
                'ldap_filter' => '(objectClass=person)',
                'asset_type' => 'Computer',
            ],
        ];

        // No active AuthLDAP servers
        $activeAuthLdapServers = [];

        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($activeFilters);

        $this->repository
            ->expects($this->once())
            ->method('getActiveAuthLdapServers')
            ->willReturn($activeAuthLdapServers);

        $this->repository
            ->expects($this->once())
            ->method('getAssociatedAuthLdaps')
            ->with(1)
            ->willReturn([1]); // Returns AuthLDAP ID but it's not active

        // syncService should NOT be called because no active AuthLDAP found
        $this->syncService
            ->expects($this->never())
            ->method('synchronizeFromFilter');

        // Act
        $result = $this->cronService->executeSyncTask(null);

        // Assert
        $this->assertEquals(0, $result, 'Should return 0 when no active AuthLDAP servers');
    }

    /**
     * Test executeSyncTask with mixed success and failure
     * Should return 1 if at least one succeeds
     */
    public function testExecuteSyncTaskWithMixedResults()
    {
        // Arrange
        $activeFilters = [
            [
                'id' => 1,
                'name' => 'Filter 1',
                'base_dn' => 'ou=users,dc=test,dc=com',
                'ldap_filter' => '(objectClass=person)',
                'asset_type' => 'Computer',
            ],
            [
                'id' => 2,
                'name' => 'Filter 2',
                'base_dn' => 'ou=printers,dc=test,dc=com',
                'ldap_filter' => '(objectClass=printer)',
                'asset_type' => 'Printer',
            ],
        ];

        $activeAuthLdapServers = [
            ['id' => 1],
        ];

        $this->repository
            ->expects($this->once())
            ->method('getActiveSyncFilters')
            ->willReturn($activeFilters);

        $this->repository
            ->expects($this->once())
            ->method('getActiveAuthLdapServers')
            ->willReturn($activeAuthLdapServers);

        $this->repository
            ->expects($this->exactly(2))
            ->method('getAssociatedAuthLdaps')
            ->willReturnOnConsecutiveCalls([1], [1]);

        // First sync succeeds, second fails
        $syncResultSuccess = [
            'success' => true,
            'stats' => ['created' => 2, 'updated' => 1, 'errors' => 0, 'processed' => 3],
        ];

        $syncResultFailure = [
            'success' => false,
            'error' => 'Connection timeout',
            'stats' => ['created' => 0, 'updated' => 0, 'errors' => 1, 'processed' => 0],
        ];

        $this->syncService
            ->expects($this->exactly(2))
            ->method('synchronizeFromFilter')
            ->willReturnOnConsecutiveCalls($syncResultSuccess, $syncResultFailure);

        // Act
        $result = $this->cronService->executeSyncTask(null);

        // Assert
        $this->assertEquals(1, $result, 'Should return 1 if at least one sync succeeds');
    }

    /**
     * Test getCronInfo returns correct information
     */
    public function testGetCronInfo()
    {
        // Act
        $info = SyncFilterCronService::getCronInfo('test');

        // Assert
        $this->assertIsArray($info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('parameter', $info);
        $this->assertNotEmpty($info['description']);
        $this->assertNotEmpty($info['parameter']);
    }
}
