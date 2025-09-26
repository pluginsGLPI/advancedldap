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
                $this->callback(function ($data) use ($inputData) {
                    return $data['name'] === $inputData['name']
                           && $data['ldap_filter'] === $inputData['ldap_filter']
                           && $data['base_dn'] === $inputData['base_dn']
                           && $data['asset_type'] === $inputData['asset_type']
                           && $data['field_mappings'] === json_encode($inputData['field_mappings'])
                           && $data['is_active'] === $inputData['is_active']
                           && isset($data['date_creation'])
                           && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['date_creation']);
                }),
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
                $this->callback(function ($data) {
                    return isset($data['field_mappings'])
                           && $data['field_mappings'] === json_encode(['name' => 'cn', 'serial' => 'serialNumber'])
                           && isset($data['date_creation']);
                }),
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
                $this->callback(function ($data) use ($expectedData) {
                    return $data['name'] === $expectedData['name']
                           && $data['is_active'] === $expectedData['is_active']
                           && $data['field_mappings'] === $expectedData['field_mappings']
                           && isset($data['date_mod']);
                }),
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

}
