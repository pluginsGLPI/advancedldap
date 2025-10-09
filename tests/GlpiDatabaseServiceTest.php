<?php

namespace GlpiPlugin\Advancedldap\Tests;

use DBmysqlIterator;
use Exception;
use RuntimeException;
use GlpiPlugin\Advancedldap\Services\GlpiDatabaseService;
use DbTestCase;

class GlpiDatabaseServiceTest extends DbTestCase
{
    private $databaseService;

    public function setUp(): void
    {
        parent::setUp();

        // Create service instance
        $this->databaseService = new GlpiDatabaseService();
    }

    /**
     * Test request method with valid criteria
     */
    public function testRequestWithValidCriteria()
    {
        // Arrange
        $criteria = [
            'FROM' => 'glpi_users',
            'WHERE' => ['name' => 'glpi'],
            'LIMIT' => 1
        ];

        // Act
        $result = $this->databaseService->request($criteria);

        // Assert
        $this->assertInstanceOf(DBmysqlIterator::class, $result);
        $this->assertGreaterThanOrEqual(0, $result->count());
    }

    /**
     * Test request method with empty criteria
     */
    public function testRequestWithEmptyCriteria()
    {
        // Arrange
        $criteria = [];

        // Act & Assert
        // Empty criteria should throw exception or return empty iterator
        $this->expectException(Exception::class);
        $this->databaseService->request($criteria);
    }

    /**
     * Test getTableForItemType method with valid itemtype
     */
    public function testGetTableForItemTypeWithValidItemtype()
    {
        // Arrange
        $itemtype = 'User';
        $expectedTable = 'glpi_users';

        // Act
        $result = $this->databaseService->getTableForItemType($itemtype);

        // Assert
        $this->assertEquals($expectedTable, $result);
    }

    /**
     * Test getTableForItemType method with invalid itemtype
     */
    public function testGetTableForItemTypeWithInvalidItemtype()
    {
        // Arrange
        $itemtype = 'NonExistentItemType';

        // Act
        $result = $this->databaseService->getTableForItemType($itemtype);

        // Assert
        // GLPI generates table names even for invalid itemtypes
        $this->assertEquals('glpi_nonexistentitemtypes', $result);
    }

    /**
     * Test getTableForItemType method with empty itemtype
     */
    public function testGetTableForItemTypeWithEmptyItemtype()
    {
        // Arrange
        $itemtype = '';

        // Act
        $result = $this->databaseService->getTableForItemType($itemtype);

        // Assert
        // GLPI generates 'glpi_' for empty itemtype
        $this->assertEquals('glpi_', $result);
    }

    /**
     * Test insert method with valid data
     */
    public function testInsertWithValidData()
    {
        // Arrange
        $table = 'glpi_logs';
        $data = [
            'itemtype' => 'User',
            'items_id' => 1,
            'itemtype_link' => 'User',
            'linked_action' => 0,
            'user_name' => 'test_user',
            'date_mod' => date('Y-m-d H:i:s'),
            'id_search_option' => 1,
            'old_value' => 'old',
            'new_value' => 'new'
        ];

        // Act
        $result = $this->databaseService->insert($table, $data);

        // Assert
        // GlpiDatabaseService now correctly returns the inserted ID
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        // Verify the insert actually succeeded
        $checkResult = $this->databaseService->request([
            'FROM' => $table,
            'WHERE' => ['id' => $result]
        ]);
        $this->assertEquals(1, $checkResult->count());
    }

    /**
     * Test insert method with invalid table
     */
    public function testInsertWithInvalidTable()
    {
        // Arrange
        $table = 'non_existent_table';
        $data = ['test' => 'value'];

        // Act & Assert
        // GLPI throws RuntimeException for invalid tables
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Table .* doesn\'t exist/');
        $this->databaseService->insert($table, $data);
    }

    /**
     * Test insert method with empty data
     */
    public function testInsertWithEmptyData()
    {
        // Arrange
        $table = 'glpi_logs';
        $data = [];

        // Act
        $result = $this->databaseService->insert($table, $data);

        // Assert
        // GlpiDatabaseService now correctly returns the inserted ID even for empty data
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * Test update method with valid parameters
     */
    public function testUpdateWithValidParameters()
    {
        // Arrange - First insert a record to update
        $table = 'glpi_logs';
        $insertData = [
            'itemtype' => 'User',
            'items_id' => 1,
            'itemtype_link' => 'User',
            'linked_action' => 0,
            'user_name' => 'test_user_update',
            'date_mod' => date('Y-m-d H:i:s'),
            'id_search_option' => 1,
            'old_value' => 'old',
            'new_value' => 'new'
        ];

        // Use the corrected insert method
        $insertedId = $this->databaseService->insert($table, $insertData);
        $this->assertIsInt($insertedId);
        $this->assertGreaterThan(0, $insertedId);

        $updateData = ['new_value' => 'updated_value'];
        $where = ['id' => $insertedId];

        // Act
        $result = $this->databaseService->update($table, $updateData, $where);

        // Assert
        $this->assertTrue($result);

        // Verify the update worked
        $checkResult = $this->databaseService->request([
            'FROM' => $table,
            'WHERE' => $where
        ]);
        $row = $checkResult->current();
        $this->assertEquals('updated_value', $row['new_value']);
    }

    /**
     * Test update method with invalid table
     */
    public function testUpdateWithInvalidTable()
    {
        // Arrange
        $table = 'non_existent_table';
        $data = ['test' => 'value'];
        $where = ['id' => 1];

        // Act & Assert
        // GLPI throws RuntimeException for invalid tables
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Table .* doesn\'t exist/');
        $this->databaseService->update($table, $data, $where);
    }

    /**
     * Test update method with non-existent record
     */
    public function testUpdateWithNonExistentRecord()
    {
        // Arrange
        $table = 'glpi_logs';
        $data = ['new_value' => 'test'];
        $where = ['id' => 999999]; // ID that doesn't exist

        // Act
        $result = $this->databaseService->update($table, $data, $where);

        // Assert
        // Update of non-existent record should still return true but affect 0 rows
        $this->assertTrue($result);
    }

    /**
     * Test delete method with valid parameters
     */
    public function testDeleteWithValidParameters()
    {
        // Arrange - First insert a record to delete
        $table = 'glpi_logs';
        $insertData = [
            'itemtype' => 'User',
            'items_id' => 1,
            'itemtype_link' => 'User',
            'linked_action' => 0,
            'user_name' => 'test_user_delete',
            'date_mod' => date('Y-m-d H:i:s'),
            'id_search_option' => 1,
            'old_value' => 'old',
            'new_value' => 'new'
        ];

        // Use the corrected insert method
        $insertedId = $this->databaseService->insert($table, $insertData);
        $this->assertIsInt($insertedId);
        $this->assertGreaterThan(0, $insertedId);

        $where = ['id' => $insertedId];

        // Act
        $result = $this->databaseService->delete($table, $where);

        // Assert
        $this->assertTrue($result);

        // Verify the record was deleted
        $checkResult = $this->databaseService->request([
            'FROM' => $table,
            'WHERE' => $where
        ]);
        $this->assertEquals(0, $checkResult->count());
    }

    /**
     * Test delete method with invalid table
     */
    public function testDeleteWithInvalidTable()
    {
        // Arrange
        $table = 'non_existent_table';
        $where = ['id' => 1];

        // Act & Assert
        // GLPI throws RuntimeException for invalid tables
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Table .* doesn\'t exist/');
        $this->databaseService->delete($table, $where);
    }

    /**
     * Test delete method with non-existent record
     */
    public function testDeleteWithNonExistentRecord()
    {
        // Arrange
        $table = 'glpi_logs';
        $where = ['id' => 999999]; // ID that doesn't exist

        // Act
        $result = $this->databaseService->delete($table, $where);

        // Assert
        // Delete of non-existent record should still return true but affect 0 rows
        $this->assertTrue($result);
    }

    /**
     * Test delete method with empty where clause
     */
    public function testDeleteWithEmptyWhere()
    {
        // Arrange
        $table = 'glpi_logs';
        $where = [];

        // Act & Assert
        // GLPI prevents DELETE without WHERE clause
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot run an DELETE query without WHERE clause!');
        $this->databaseService->delete($table, $where);
    }

    public function tearDown(): void
    {
        // Clean up any test data created during tests
        global $DB;

        if ($DB) {
            // Clean up test logs that might have been created
            $DB->delete('glpi_logs', [
                'user_name' => ['LIKE', 'test_user%']
            ]);
        }

        parent::tearDown();
    }
}