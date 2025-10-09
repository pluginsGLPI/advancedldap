<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Repositories\AuthLdapSyncFilterRepository;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Models\AuthLdapSyncFilter;
use DbTestCase;

class AuthLdapSyncFilterRepositoryTest extends DbTestCase
{
    private $repository;
    private $database;

    public function setUp(): void
    {
        parent::setUp();

        // Create mock for database dependency
        $this->database = $this->createMock(DatabaseInterface::class);

        // Create repository instance with mocked database
        $this->repository = new AuthLdapSyncFilterRepository($this->database);
    }

    /**
     * Test addSyncFilterToAuthLdap method with success
     */
    public function testAddSyncFilterToAuthLdapWithSuccess()
    {
        // Arrange
        $authldapId = 123;
        $syncfilterId = 456;
        $isActive = true;
        $expectedId = 789;

        $this->database
            ->expects($this->once())
            ->method('insert')
            ->with(
                AuthLdapSyncFilter::$table,
                $this->callback(fn($data) => $data['authldap_id'] === $authldapId
                       && $data['syncfilter_id'] === $syncfilterId
                       && $data['is_active'] === 1
                       && isset($data['date_creation'])
                       && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['date_creation'])),
            )
            ->willReturn($expectedId);

        // Act
        $result = $this->repository->addSyncFilterToAuthLdap($authldapId, $syncfilterId, $isActive);

        // Assert
        $this->assertEquals($expectedId, $result);
    }

    /**
     * Test addSyncFilterToAuthLdap method with inactive status
     */
    public function testAddSyncFilterToAuthLdapWithInactiveStatus()
    {
        // Arrange
        $authldapId = 111;
        $syncfilterId = 222;
        $isActive = false;
        $expectedId = 333;

        $this->database
            ->expects($this->once())
            ->method('insert')
            ->with(
                AuthLdapSyncFilter::$table,
                $this->callback(fn($data) => $data['is_active'] === 0),
            )
            ->willReturn($expectedId);

        // Act
        $result = $this->repository->addSyncFilterToAuthLdap($authldapId, $syncfilterId, $isActive);

        // Assert
        $this->assertEquals($expectedId, $result);
    }

    /**
     * Test addSyncFilterToAuthLdap method with default active status
     */
    public function testAddSyncFilterToAuthLdapWithDefaultActiveStatus()
    {
        // Arrange
        $authldapId = 777;
        $syncfilterId = 888;
        $expectedId = 999;

        $this->database
            ->expects($this->once())
            ->method('insert')
            ->with(
                AuthLdapSyncFilter::$table,
                $this->callback(function ($data) {
                    return $data['is_active'] === 1; // Default should be active
                }),
            )
            ->willReturn($expectedId);

        // Act
        $result = $this->repository->addSyncFilterToAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertEquals($expectedId, $result);
    }

    /**
     * Test addSyncFilterToAuthLdap method with failure
     */
    public function testAddSyncFilterToAuthLdapWithFailure()
    {
        // Arrange
        $authldapId = 100;
        $syncfilterId = 200;

        $this->database
            ->expects($this->once())
            ->method('insert')
            ->willReturn(false);

        // Act
        $result = $this->repository->addSyncFilterToAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test removeSyncFilterFromAuthLdap method with success
     */
    public function testRemoveSyncFilterFromAuthLdapWithSuccess()
    {
        // Arrange
        $authldapId = 123;
        $syncfilterId = 456;

        $this->database
            ->expects($this->once())
            ->method('delete')
            ->with(
                AuthLdapSyncFilter::$table,
                [
                    'authldap_id' => $authldapId,
                    'syncfilter_id' => $syncfilterId,
                ],
            )
            ->willReturn(true);

        // Act
        $result = $this->repository->removeSyncFilterFromAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test removeSyncFilterFromAuthLdap method with failure
     */
    public function testRemoveSyncFilterFromAuthLdapWithFailure()
    {
        // Arrange
        $authldapId = 999;
        $syncfilterId = 888;

        $this->database
            ->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        // Act
        $result = $this->repository->removeSyncFilterFromAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test toggleSyncFilterForAuthLdap method with activation
     */
    public function testToggleSyncFilterForAuthLdapWithActivation()
    {
        // Arrange
        $authldapId = 111;
        $syncfilterId = 222;
        $isActive = true;

        $this->database
            ->expects($this->once())
            ->method('update')
            ->with(
                AuthLdapSyncFilter::$table,
                ['is_active' => 1],
                [
                    'authldap_id' => $authldapId,
                    'syncfilter_id' => $syncfilterId,
                ],
            )
            ->willReturn(true);

        // Act
        $result = $this->repository->toggleSyncFilterForAuthLdap($authldapId, $syncfilterId, $isActive);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test toggleSyncFilterForAuthLdap method with deactivation
     */
    public function testToggleSyncFilterForAuthLdapWithDeactivation()
    {
        // Arrange
        $authldapId = 333;
        $syncfilterId = 444;
        $isActive = false;

        $this->database
            ->expects($this->once())
            ->method('update')
            ->with(
                AuthLdapSyncFilter::$table,
                ['is_active' => 0],
                [
                    'authldap_id' => $authldapId,
                    'syncfilter_id' => $syncfilterId,
                ],
            )
            ->willReturn(true);

        // Act
        $result = $this->repository->toggleSyncFilterForAuthLdap($authldapId, $syncfilterId, $isActive);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test toggleSyncFilterForAuthLdap method with failure
     */
    public function testToggleSyncFilterForAuthLdapWithFailure()
    {
        // Arrange
        $authldapId = 555;
        $syncfilterId = 666;
        $isActive = true;

        $this->database
            ->expects($this->once())
            ->method('update')
            ->willReturn(false);

        // Act
        $result = $this->repository->toggleSyncFilterForAuthLdap($authldapId, $syncfilterId, $isActive);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getAuthLdapsForSyncFilter method with active only (default)
     */
    public function testGetAuthLdapsForSyncFilterWithActiveOnly()
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
                'FROM'   => AuthLdapSyncFilter::$table,
                'WHERE'  => [
                    'syncfilter_id' => $syncfilterId,
                    'is_active' => 1,
                ],
            ])
            ->willReturn($expectedResults);

        // Act
        $result = $this->repository->getAuthLdapsForSyncFilter($syncfilterId);

        // Assert
        $this->assertEquals($expectedAuthLdapIds, $result);
    }

    /**
     * Test getAuthLdapsForSyncFilter method with all relations
     */
    public function testGetAuthLdapsForSyncFilterWithAllRelations()
    {
        // Arrange
        $syncfilterId = 456;
        $activeOnly = false;
        $expectedResults = [
            ['authldap_id' => 40],
            ['authldap_id' => 50],
        ];
        $expectedAuthLdapIds = [40, 50];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'SELECT' => ['authldap_id'],
                'FROM'   => AuthLdapSyncFilter::$table,
                'WHERE'  => [
                    'syncfilter_id' => $syncfilterId,
                ],
            ])
            ->willReturn($expectedResults);

        // Act
        $result = $this->repository->getAuthLdapsForSyncFilter($syncfilterId, $activeOnly);

        // Assert
        $this->assertEquals($expectedAuthLdapIds, $result);
    }

    /**
     * Test getAuthLdapsForSyncFilter method with no results
     */
    public function testGetAuthLdapsForSyncFilterWithNoResults()
    {
        // Arrange
        $syncfilterId = 789;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->getAuthLdapsForSyncFilter($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test getAuthLdapsForSyncFilter method with non-array return
     */
    public function testGetAuthLdapsForSyncFilterWithNonArrayReturn()
    {
        // Arrange
        $syncfilterId = 999;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->getAuthLdapsForSyncFilter($syncfilterId);

        // Assert
        $this->assertEquals([], $result);
    }

    /**
     * Test isSyncFilterAssignedToAuthLdap method with active relation found
     */
    public function testIsSyncFilterAssignedToAuthLdapWithActiveRelationFound()
    {
        // Arrange
        $authldapId = 111;
        $syncfilterId = 222;
        $expectedResults = [
            ['id' => 1, 'authldap_id' => $authldapId, 'syncfilter_id' => $syncfilterId, 'is_active' => 1],
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM'  => AuthLdapSyncFilter::$table,
                'WHERE' => [
                    'authldap_id' => $authldapId,
                    'syncfilter_id' => $syncfilterId,
                    'is_active' => 1,
                ],
                'LIMIT' => 1,
            ])
            ->willReturn($expectedResults);

        // Act
        $result = $this->repository->isSyncFilterAssignedToAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isSyncFilterAssignedToAuthLdap method with any relation found
     */
    public function testIsSyncFilterAssignedToAuthLdapWithAnyRelationFound()
    {
        // Arrange
        $authldapId = 333;
        $syncfilterId = 444;
        $activeOnly = false;
        $expectedResults = [
            ['id' => 2, 'authldap_id' => $authldapId, 'syncfilter_id' => $syncfilterId, 'is_active' => 0],
        ];

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM'  => AuthLdapSyncFilter::$table,
                'WHERE' => [
                    'authldap_id' => $authldapId,
                    'syncfilter_id' => $syncfilterId,
                ],
                'LIMIT' => 1,
            ])
            ->willReturn($expectedResults);

        // Act
        $result = $this->repository->isSyncFilterAssignedToAuthLdap($authldapId, $syncfilterId, $activeOnly);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test isSyncFilterAssignedToAuthLdap method with no relation found
     */
    public function testIsSyncFilterAssignedToAuthLdapWithNoRelationFound()
    {
        // Arrange
        $authldapId = 555;
        $syncfilterId = 666;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn([]);

        // Act
        $result = $this->repository->isSyncFilterAssignedToAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test isSyncFilterAssignedToAuthLdap method with non-array return
     */
    public function testIsSyncFilterAssignedToAuthLdapWithNonArrayReturn()
    {
        // Arrange
        $authldapId = 777;
        $syncfilterId = 888;

        $this->database
            ->expects($this->once())
            ->method('request')
            ->willReturn(false);

        // Act
        $result = $this->repository->isSyncFilterAssignedToAuthLdap($authldapId, $syncfilterId);

        // Assert
        $this->assertFalse($result);
    }
}
