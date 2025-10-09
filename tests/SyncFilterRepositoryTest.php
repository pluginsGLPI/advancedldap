<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Repositories\SyncFilterRepository;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use GlpiPlugin\Advancedldap\Models\AuthLdapSyncFilter;
use DbTestCase;

class SyncFilterRepositoryTest extends DbTestCase
{
    private $repository;
    private $database;
    private $relationRepository;

    public function setUp(): void
    {
        parent::setUp();

        // Create mocks for dependencies
        $this->database = $this->createMock(DatabaseInterface::class);
        $this->relationRepository = $this->createMock(AuthLdapSyncFilterRepositoryInterface::class);

        // Create repository instance with mocked dependencies
        $this->repository = new SyncFilterRepository(
            $this->database,
            $this->relationRepository,
        );
    }

    /**
     * Test getActiveSyncFilters method with results
     */
    public function testGetActiveSyncFiltersWithResults()
    {
        // Arrange
        $expectedFilters = [
            ['id' => 1, 'name' => 'Filter A', 'is_active' => 1, 'ldap_filter' => '(objectClass=computer)'],
            ['id' => 2, 'name' => 'Filter B', 'is_active' => 1, 'ldap_filter' => '(objectClass=printer)'],
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM'   => SyncFilter::$table,
                'WHERE'  => ['is_active' => 1],
                'ORDER'  => 'name',
            ])
            ->willReturn($expectedFilters);

        // Act
        $result = $this->repository->getActiveSyncFilters();

        // Assert
        $this->assertEquals($expectedFilters, $result);
    }

    /**
     * Test getActiveSyncFilters method with no results
     */
    public function testGetActiveSyncFiltersWithNoResults()
    {
        // Arrange
        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM'   => SyncFilter::$table,
                'WHERE'  => ['is_active' => 1],
                'ORDER'  => 'name',
            ])
            ->willReturn([]);

        // Act
        $result = $this->repository->getActiveSyncFilters();

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getActiveSyncFilters method with non-array return
     */
    public function testGetActiveSyncFiltersWithNonArrayReturn()
    {
        // Arrange
        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->getActiveSyncFilters();

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getSyncFiltersForAuthLdap method
     */
    public function testGetSyncFiltersForAuthLdap()
    {
        // Arrange
        $authldapId = 123;
        $expectedFilters = [
            [
                'id' => 1,
                'name' => 'Computer Filter',
                'ldap_filter' => '(objectClass=computer)',
                'is_active' => 1,
            ],
        ];

        $syncTable = SyncFilter::$table;
        $relationTable = AuthLdapSyncFilter::$table;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'SELECT' => [
                    $syncTable . '.*',
                ],
                'FROM'   => $syncTable,
                'INNER JOIN' => [
                    $relationTable => [
                        'ON' => [
                            $relationTable => 'syncfilter_id',
                            $syncTable => 'id',
                        ],
                    ],
                ],
                'WHERE'  => [
                    $relationTable . '.authldap_id' => $authldapId,
                    $relationTable . '.is_active' => 1,
                    $syncTable . '.is_active' => 1,
                ],
                'ORDER'  => $syncTable . '.name',
            ])
            ->willReturn($expectedFilters);

        // Act
        $result = $this->repository->getSyncFiltersForAuthLdap($authldapId);

        // Assert
        $this->assertEquals($expectedFilters, $result);
    }

    /**
     * Test getSyncFiltersForAuthLdap method with no results
     */
    public function testGetSyncFiltersForAuthLdapWithNoResults()
    {
        // Arrange
        $authldapId = 999;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->getSyncFiltersForAuthLdap($authldapId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test findById method with existing filter
     */
    public function testFindByIdWithExistingFilter()
    {
        // Arrange
        $filterId = 42;
        $expectedFilter = [
            'id' => $filterId,
            'name' => 'Test Filter',
            'ldap_filter' => '(objectClass=computer)',
            'is_active' => 1,
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM'  => SyncFilter::$table,
                'WHERE' => ['id' => $filterId],
                'LIMIT' => 1,
            ])
            ->willReturn([$expectedFilter]);

        // Act
        $result = $this->repository->findById($filterId);

        // Assert
        $this->assertEquals($expectedFilter, $result);
    }

    /**
     * Test findById method with non-existent filter
     */
    public function testFindByIdWithNonExistentFilter()
    {
        // Arrange
        $filterId = 999;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM'  => SyncFilter::$table,
                'WHERE' => ['id' => $filterId],
                'LIMIT' => 1,
            ])
            ->willReturn([]);

        // Act
        $result = $this->repository->findById($filterId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test findById method with non-array return
     */
    public function testFindByIdWithNonArrayReturn()
    {
        // Arrange
        $filterId = 123;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->findById($filterId);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test create method with success
     */
    public function testCreateWithSuccess()
    {
        // Arrange
        $inputData = [
            'name' => 'New Filter',
            'ldap_filter' => '(objectClass=printer)',
            'base_dn' => 'OU=Printers,DC=example,DC=com',
            'asset_type' => 'Printer',
            'field_mappings' => ['name' => 'cn'],
            'is_active' => 1,
        ];

        $expectedId = 100;

        $this->database
            ->expects($this->once())
            ->method('insert')
            ->with(
                SyncFilter::$table,
                $this->callback(fn($data) => $data['name'] === $inputData['name']
                       && $data['ldap_filter'] === $inputData['ldap_filter']
                       && $data['base_dn'] === $inputData['base_dn']
                       && $data['asset_type'] === $inputData['asset_type']
                       && $data['field_mappings'] === json_encode($inputData['field_mappings'])
                       && $data['is_active'] === $inputData['is_active']
                       && isset($data['date_creation'])
                       && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['date_creation'])),
            )
            ->willReturn($expectedId);

        // Act
        $result = $this->repository->create($inputData);

        // Assert
        $this->assertEquals($expectedId, $result);
    }

    /**
     * Test create method with array field_mappings
     */
    public function testCreateWithArrayFieldMappings()
    {
        // Arrange
        $inputData = [
            'name' => 'Array Mappings Filter',
            'field_mappings' => ['name' => 'cn', 'serial' => 'serialNumber'],
        ];

        $expectedId = 101;

        $this->database
            ->expects($this->once())
            ->method('insert')
            ->with(
                SyncFilter::$table,
                $this->callback(fn($data) => isset($data['field_mappings'])
                       && $data['field_mappings'] === json_encode(['name' => 'cn', 'serial' => 'serialNumber'])
                       && isset($data['date_creation'])),
            )
            ->willReturn($expectedId);

        // Act
        $result = $this->repository->create($inputData);

        // Assert
        $this->assertEquals($expectedId, $result);
    }

    /**
     * Test create method with failure
     */
    public function testCreateWithFailure()
    {
        // Arrange
        $inputData = ['name' => 'Failed Filter'];

        $this->database
            ->expects($this->once())
            ->method('insert')
            ->willReturn(false);

        // Act
        $result = $this->repository->create($inputData);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test update method with success
     */
    public function testUpdateWithSuccess()
    {
        // Arrange
        $filterId = 50;
        $updateData = [
            'name' => 'Updated Filter',
            'is_active' => 0,
            'field_mappings' => ['name' => 'displayName'],
        ];

        $expectedData = [
            'name' => 'Updated Filter',
            'is_active' => 0,
            'field_mappings' => json_encode(['name' => 'displayName']),
            'date_mod' => date('Y-m-d H:i:s'),
        ];

        $this->database
            ->expects($this->once())
            ->method('update')
            ->with(
                SyncFilter::$table,
                $this->callback(fn($data) => $data['name'] === $expectedData['name']
                       && $data['is_active'] === $expectedData['is_active']
                       && $data['field_mappings'] === $expectedData['field_mappings']
                       && isset($data['date_mod'])),
                ['id' => $filterId],
            )
            ->willReturn(true);

        // Act
        $result = $this->repository->update($filterId, $updateData);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test update method with failure
     */
    public function testUpdateWithFailure()
    {
        // Arrange
        $filterId = 60;
        $updateData = ['name' => 'Failed Update'];

        $this->database
            ->expects($this->once())
            ->method('update')
            ->willReturn(false);

        // Act
        $result = $this->repository->update($filterId, $updateData);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test delete method with success
     */
    public function testDeleteWithSuccess()
    {
        // Arrange
        $filterId = 70;

        $this->database
            ->expects($this->once())
            ->method('delete')
            ->with(SyncFilter::$table, ['id' => $filterId])
            ->willReturn(true);

        // Act
        $result = $this->repository->delete($filterId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test delete method with failure
     */
    public function testDeleteWithFailure()
    {
        // Arrange
        $filterId = 80;

        $this->database
            ->expects($this->once())
            ->method('delete')
            ->with(SyncFilter::$table, ['id' => $filterId])
            ->willReturn(false);

        // Act
        $result = $this->repository->delete($filterId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getAssociatedAuthLdaps method with results
     */
    public function testGetAssociatedAuthLdapsWithResults()
    {
        // Arrange
        $syncfilterId = 123;
        $expectedResults = [
            ['authldap_id' => 10],
            ['authldap_id' => 20],
            ['authldap_id' => 30],
        ];
        $expectedAuthLdapIds = [10, 20, 30];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'SELECT' => ['authldap_id'],
                'FROM'   => AuthLdapSyncFilter::getTable(),
                'WHERE'  => [
                    'syncfilter_id' => $syncfilterId,
                    'is_active' => 1,
                ],
            ])
            ->willReturn($expectedResults);

        // Act
        $result = $this->repository->getAssociatedAuthLdaps($syncfilterId);

        // Assert
        $this->assertEquals($expectedAuthLdapIds, $result);
    }

    /**
     * Test getAssociatedAuthLdaps method with no results
     */
    public function testGetAssociatedAuthLdapsWithNoResults()
    {
        // Arrange
        $syncfilterId = 456;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->getAssociatedAuthLdaps($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getAssociatedAuthLdaps method with non-iterable return
     */
    public function testGetAssociatedAuthLdapsWithNonIterableReturn()
    {
        // Arrange
        $syncfilterId = 789;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->getAssociatedAuthLdaps($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test deleteAuthLdapRelations method with success
     */
    public function testDeleteAuthLdapRelationsWithSuccess()
    {
        // Arrange
        $syncfilterId = 789;

        $this->database
            ->expects($this->once())
            ->method('delete')
            ->with(
                AuthLdapSyncFilter::getTable(),
                ['syncfilter_id' => $syncfilterId]
            )
            ->willReturn(true);

        // Act
        $result = $this->repository->deleteAuthLdapRelations($syncfilterId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test deleteAuthLdapRelations method with failure
     */
    public function testDeleteAuthLdapRelationsWithFailure()
    {
        // Arrange
        $syncfilterId = 999;

        $this->database
            ->expects($this->once())
            ->method('delete')
            ->with(
                AuthLdapSyncFilter::getTable(),
                ['syncfilter_id' => $syncfilterId]
            )
            ->willReturn(false);

        // Act
        $result = $this->repository->deleteAuthLdapRelations($syncfilterId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getAuthLdapRelations method with results
     */
    public function testGetAuthLdapRelationsWithResults()
    {
        // Arrange
        $syncfilterId = 111;
        $expectedResults = [
            ['authldap_id' => 10, 'is_active' => 1],
            ['authldap_id' => 20, 'is_active' => 0],
            ['authldap_id' => 30, 'is_active' => 1],
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'SELECT' => ['authldap_id', 'is_active'],
                'FROM'   => AuthLdapSyncFilter::getTable(),
                'WHERE'  => ['syncfilter_id' => $syncfilterId],
            ])
            ->willReturn($expectedResults);

        // Act
        $result = $this->repository->getAuthLdapRelations($syncfilterId);

        // Assert
        $this->assertEquals($expectedResults, $result);
    }

    /**
     * Test getAuthLdapRelations method with no results
     */
    public function testGetAuthLdapRelationsWithNoResults()
    {
        // Arrange
        $syncfilterId = 222;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->getAuthLdapRelations($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getAuthLdapRelations method with non-iterable return
     */
    public function testGetAuthLdapRelationsWithNonIterableReturn()
    {
        // Arrange
        $syncfilterId = 333;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->getAuthLdapRelations($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getActiveAuthLdapServers method with results
     */
    public function testGetActiveAuthLdapServersWithResults()
    {
        // Arrange
        $expectedServers = [
            ['id' => 1, 'name' => 'LDAP Server 1'],
            ['id' => 2, 'name' => 'LDAP Server 2'],
            ['id' => 3, 'name' => 'LDAP Server 3'],
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'SELECT' => ['id', 'name'],
                'FROM'   => 'glpi_authldaps',
                'WHERE'  => ['is_active' => 1],
                'ORDER'  => 'name',
            ])
            ->willReturn($expectedServers);

        // Act
        $result = $this->repository->getActiveAuthLdapServers();

        // Assert
        $this->assertEquals($expectedServers, $result);
    }

    /**
     * Test getActiveAuthLdapServers method with no results
     */
    public function testGetActiveAuthLdapServersWithNoResults()
    {
        // Arrange
        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->getActiveAuthLdapServers();

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getActiveAuthLdapServers method with non-iterable return
     */
    public function testGetActiveAuthLdapServersWithNonIterableReturn()
    {
        // Arrange
        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->getActiveAuthLdapServers();

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getFirstActiveAuthLdapId method with result found
     */
    public function testGetFirstActiveAuthLdapIdWithResultFound()
    {
        // Arrange
        $expectedResults = [
            ['id' => 42],
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_authldaps',
                'WHERE'  => ['is_active' => 1],
                'LIMIT'  => 1,
            ])
            ->willReturn($expectedResults);

        // Act
        $result = $this->repository->getFirstActiveAuthLdapId();

        // Assert
        $this->assertEquals(42, $result);
    }

    /**
     * Test getFirstActiveAuthLdapId method with no result
     */
    public function testGetFirstActiveAuthLdapIdWithNoResult()
    {
        // Arrange
        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->getFirstActiveAuthLdapId();

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test getFirstActiveAuthLdapId method with non-iterable return
     */
    public function testGetFirstActiveAuthLdapIdWithNonIterableReturn()
    {
        // Arrange
        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->getFirstActiveAuthLdapId();

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test getSyncFiltersForAuthLdapDetailed method with results
     */
    public function testGetSyncFiltersForAuthLdapDetailedWithResults()
    {
        // Arrange
        $authldapId = 555;
        $syncTable = SyncFilter::$table;
        $relationTable = AuthLdapSyncFilter::$table;

        $expectedFilters = [
            [
                'id' => 1,
                'name' => 'Computer Filter',
                'ldap_filter' => '(objectClass=computer)',
                'base_dn' => 'OU=Computers,DC=example,DC=com',
                'asset_type' => 'Computer',
                'field_mappings' => '{"name":"cn"}',
                'is_active' => 1,
                'date_creation' => '2025-01-15 10:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Printer Filter',
                'ldap_filter' => '(objectClass=printer)',
                'base_dn' => 'OU=Printers,DC=example,DC=com',
                'asset_type' => 'Printer',
                'field_mappings' => '{"name":"cn","serial":"serialNumber"}',
                'is_active' => 1,
                'date_creation' => '2025-01-16 11:30:00',
            ],
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'SELECT' => [
                    'sf.id',
                    'sf.name',
                    'sf.ldap_filter',
                    'sf.base_dn',
                    'sf.asset_type',
                    'sf.field_mappings',
                    'sf.is_active',
                    'sf.date_creation',
                ],
                'FROM'   => "$syncTable AS sf",
                'INNER JOIN' => [
                    "$relationTable AS rel" => [
                        'ON' => [
                            'sf' => 'id',
                            'rel' => 'syncfilter_id',
                        ],
                    ],
                ],
                'WHERE'  => [
                    'rel.authldap_id' => $authldapId,
                ],
                'ORDER'  => 'sf.name',
            ])
            ->willReturn($expectedFilters);

        // Act
        $result = $this->repository->getSyncFiltersForAuthLdapDetailed($authldapId);

        // Assert
        $this->assertEquals($expectedFilters, $result);
    }

    /**
     * Test getSyncFiltersForAuthLdapDetailed method with no results
     */
    public function testGetSyncFiltersForAuthLdapDetailedWithNoResults()
    {
        // Arrange
        $authldapId = 666;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->getSyncFiltersForAuthLdapDetailed($authldapId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getSyncFiltersForAuthLdapDetailed method with non-iterable return
     */
    public function testGetSyncFiltersForAuthLdapDetailedWithNonIterableReturn()
    {
        // Arrange
        $authldapId = 777;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->getSyncFiltersForAuthLdapDetailed($authldapId);

        // Assert
        $this->assertEquals([], $result);
    }

}
