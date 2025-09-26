<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\LdapTestService;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use AuthLDAP;
use Exception;
use DbTestCase;

class LdapTestServiceTest extends DbTestCase
{
    private $ldapTestService;
    private $ldapConnection;
    private $database;
    private $assetFieldProvider;
    private $validAuthLdapId;

    public function setUp(): void
    {
        parent::setUp();

        // Create a valid AuthLDAP for testing
        $authLdap = new AuthLDAP();
        $this->validAuthLdapId = $authLdap->add([
            'name' => 'Test LDAP Server',
            'host' => 'ldap.example.com',
            'basedn' => 'DC=example,DC=com',
            'port' => 389,
            'is_active' => 1,
        ]);

        // Create mocks for dependencies
        $this->ldapConnection = $this->createMock(LdapConnectionInterface::class);
        $this->database = $this->createMock(DatabaseInterface::class);
        $this->assetFieldProvider = $this->createMock(AssetFieldProviderInterface::class);

        // Create service instance with mocked dependencies
        $this->ldapTestService = new LdapTestService(
            $this->ldapConnection,
            $this->database,
            $this->assetFieldProvider,
        );
    }

    /**
     * Test testLdapFilter method with invalid AuthLDAP ID
     */
    public function testTestLdapFilterWithInvalidAuthLdapId()
    {
        // Arrange
        $authldapId = 999; // Non-existent ID
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';

        // Note: AuthLDAP is instantiated directly in the method, so it will try to load from DB
        // This test relies on the fact that ID 999 won't exist in the test database

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('AuthLDAP configuration not found', $result['error']);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('entries', $result);
        $this->assertEquals([], $result['entries']);
    }

    /**
     * Test testLdapFilter method with empty base DN
     */
    public function testTestLdapFilterWithEmptyBaseDn()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = ''; // Empty base DN
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Base DN is required', $result['error']);
    }

    /**
     * Test testLdapFilter method with empty LDAP filter
     */
    public function testTestLdapFilterWithEmptyFilter()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = ''; // Empty filter
        $assetType = 'Computer';

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('LDAP filter is required', $result['error']);
    }

    /**
     * Test testLdapFilter method with empty asset type
     */
    public function testTestLdapFilterWithEmptyAssetType()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = ''; // Empty asset type

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Asset type is required', $result['error']);
    }

    /**
     * Test testLdapFilter method with LDAP connection failure
     */
    public function testTestLdapFilterWithConnectionFailure()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';

        // Mock AuthLDAP to exist but connection to fail
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->willReturn(false); // Connection failure

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Cannot connect to LDAP server', $result['error']);
    }

    /**
     * Test testLdapFilter method with LDAP search failure
     */
    public function testTestLdapFilterWithSearchFailure()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';
        $mockConnection = 'mock_connection_resource';

        // Mock successful connection but failed search
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->willReturn($mockConnection);

        $this->ldapConnection
            ->expects($this->once())
            ->method('search')
            ->with($mockConnection, $baseDn, $filter)
            ->willReturn(false); // Search failure

        $this->ldapConnection
            ->expects($this->once())
            ->method('getError')
            ->with($mockConnection)
            ->willReturn('Invalid DN syntax');

        $this->ldapConnection
            ->expects($this->once())
            ->method('close')
            ->with($mockConnection);

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('LDAP search failed', $result['error']);
        $this->assertStringContainsString('Invalid DN syntax', $result['error']);
    }

    /**
     * Test testLdapFilter method with successful LDAP search and no entries
     */
    public function testTestLdapFilterWithSuccessfulSearchNoEntries()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';
        $mockConnection = 'mock_connection_resource';
        $mockSearchResult = 'mock_search_result';

        // Mock successful connection and search but no entries
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->willReturn($mockConnection);

        $this->ldapConnection
            ->expects($this->once())
            ->method('search')
            ->with($mockConnection, $baseDn, $filter)
            ->willReturn($mockSearchResult);

        $this->ldapConnection
            ->expects($this->once())
            ->method('getEntries')
            ->with($mockConnection, $mockSearchResult)
            ->willReturn(['count' => 0]);

        $this->ldapConnection
            ->expects($this->once())
            ->method('close')
            ->with($mockConnection);

        $this->assetFieldProvider
            ->expects($this->once())
            ->method('getItemTypeFields')
            ->with($assetType)
            ->willReturn(['name' => 'Name', 'serial' => 'Serial Number']);

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertNull($result['error']);
        $this->assertArrayHasKey('config', $result);
        $this->assertEquals($baseDn, $result['config']['base_dn']);
        $this->assertEquals($filter, $result['config']['filter']);
        $this->assertEquals($assetType, $result['config']['asset_type']);
        $this->assertArrayHasKey('entries', $result);
        $this->assertEquals([], $result['entries']);
    }

    /**
     * Test testLdapFilter method with successful LDAP search and entries
     */
    public function testTestLdapFilterWithSuccessfulSearchWithEntries()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(cn=*)';
        $assetType = 'Computer';
        $mockConnection = 'mock_connection_resource';
        $mockSearchResult = 'mock_search_result';

        $mockEntries = [
            'count' => 1,
            0 => [
                'dn' => 'CN=computer1,OU=Computers,DC=example,DC=com',
                'cn' => ['count' => 1, 0 => 'computer1'],
                'name' => ['count' => 1, 0 => 'computer1'],
                'count' => 2,
                0 => 'cn',
                1 => 'name',
            ],
        ];

        // Mock successful connection, search and entries
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->willReturn($mockConnection);

        $this->ldapConnection
            ->expects($this->once())
            ->method('search')
            ->with($mockConnection, $baseDn, $filter)
            ->willReturn($mockSearchResult);

        $this->ldapConnection
            ->expects($this->once())
            ->method('getEntries')
            ->with($mockConnection, $mockSearchResult)
            ->willReturn($mockEntries);

        $this->ldapConnection
            ->expects($this->once())
            ->method('close')
            ->with($mockConnection);

        $this->assetFieldProvider
            ->expects($this->once())
            ->method('getItemTypeFields')
            ->with($assetType)
            ->willReturn(['name' => 'Name', 'serial' => 'Serial Number']);

        // Mock database for GLPI impact analysis
        $this->database
            ->expects($this->once())
            ->method('getTableForItemType')
            ->with($assetType)
            ->willReturn('glpi_computers');

        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM' => 'glpi_computers',
                'WHERE' => ['name' => 'computer1'],
                'LIMIT' => 1,
            ])
            ->willReturn([]); // Asset doesn't exist

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertNull($result['error']);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('entries', $result);
        $this->assertCount(1, $result['entries']);

        $entry = $result['entries'][0];
        $this->assertArrayHasKey('dn', $entry);
        $this->assertArrayHasKey('attributes', $entry);
        $this->assertArrayHasKey('glpi_impact', $entry);
        $this->assertEquals('CN=computer1,OU=Computers,DC=example,DC=com', $entry['dn']);
        $this->assertEquals('computer1', $entry['attributes']['cn']);
        $this->assertEquals('computer1', $entry['attributes']['name']);
        $this->assertFalse($entry['glpi_impact']['exists']);
    }

    /**
     * Test testLdapFilter method with legacy asset field parameter
     */
    public function testTestLdapFilterWithLegacyAssetField()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';
        $assetField = 'serial'; // Legacy parameter
        $mockConnection = 'mock_connection_resource';
        $mockSearchResult = 'mock_search_result';

        // Mock successful connection and search but no entries for simplicity
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->willReturn($mockConnection);

        $this->ldapConnection
            ->expects($this->once())
            ->method('search')
            ->with($mockConnection, $baseDn, $filter)
            ->willReturn($mockSearchResult);

        $this->ldapConnection
            ->expects($this->once())
            ->method('getEntries')
            ->with($mockConnection, $mockSearchResult)
            ->willReturn(['count' => 0]);

        $this->ldapConnection
            ->expects($this->once())
            ->method('close')
            ->with($mockConnection);

        $this->assetFieldProvider
            ->expects($this->once())
            ->method('getItemTypeFields')
            ->with($assetType)
            ->willReturn(['name' => 'Name', 'serial' => 'Serial Number']);

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType, $assetField);

        // Assert
        $this->assertIsArray($result);
        $this->assertNull($result['error']);
        $this->assertArrayHasKey('config', $result);
        $this->assertEquals('Serial Number', $result['config']['asset_field']); // Should be translated
    }

    /**
     * Test testLdapFilter method with field mappings parameter
     */
    public function testTestLdapFilterWithFieldMappings()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';
        $fieldMappings = ['name' => 'cn', 'serial' => 'serialNumber'];
        $mockConnection = 'mock_connection_resource';
        $mockSearchResult = 'mock_search_result';

        // Mock successful connection and search but no entries for simplicity
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->willReturn($mockConnection);

        $this->ldapConnection
            ->expects($this->once())
            ->method('search')
            ->with($mockConnection, $baseDn, $filter)
            ->willReturn($mockSearchResult);

        $this->ldapConnection
            ->expects($this->once())
            ->method('getEntries')
            ->with($mockConnection, $mockSearchResult)
            ->willReturn(['count' => 0]);

        $this->ldapConnection
            ->expects($this->once())
            ->method('close')
            ->with($mockConnection);

        $this->assetFieldProvider
            ->expects($this->once())
            ->method('getItemTypeFields')
            ->with($assetType)
            ->willReturn(['name' => 'Name', 'serial' => 'Serial Number']);

        // Act
        $result = $this->ldapTestService->testLdapFilter(
            $authldapId,
            $baseDn,
            $filter,
            $assetType,
            '',
            $fieldMappings,
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertNull($result['error']);
        $this->assertArrayHasKey('config', $result);
        $this->assertEquals($fieldMappings, $result['config']['field_mappings']);
        $this->assertArrayHasKey('field_mappings_readable', $result['config']);
        $this->assertEquals(['name' => 'Name', 'serial' => 'Serial Number'], $result['config']['field_mappings_readable']);
    }

    /**
     * Test testLdapFilter method with existing asset in GLPI
     */
    public function testTestLdapFilterWithExistingAssetInGlpi()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(cn=computer1)';
        $assetType = 'Computer';
        $mockConnection = 'mock_connection_resource';
        $mockSearchResult = 'mock_search_result';

        $mockEntries = [
            'count' => 1,
            0 => [
                'dn' => 'CN=computer1,OU=Computers,DC=example,DC=com',
                'cn' => ['count' => 1, 0 => 'computer1'],
                'name' => ['count' => 1, 0 => 'computer1'],
                'count' => 2,
                0 => 'cn',
                1 => 'name',
            ],
        ];

        // Mock successful connection, search and entries
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->willReturn($mockConnection);

        $this->ldapConnection
            ->expects($this->once())
            ->method('search')
            ->with($mockConnection, $baseDn, $filter)
            ->willReturn($mockSearchResult);

        $this->ldapConnection
            ->expects($this->once())
            ->method('getEntries')
            ->with($mockConnection, $mockSearchResult)
            ->willReturn($mockEntries);

        $this->ldapConnection
            ->expects($this->once())
            ->method('close')
            ->with($mockConnection);

        $this->assetFieldProvider
            ->expects($this->once())
            ->method('getItemTypeFields')
            ->with($assetType)
            ->willReturn(['name' => 'Name']);

        // Mock database to return existing asset
        $this->database
            ->expects($this->once())
            ->method('getTableForItemType')
            ->with($assetType)
            ->willReturn('glpi_computers');

        $mockIterator = [['id' => 1, 'name' => 'computer1']];
        $this->database
            ->expects($this->once())
            ->method('request')
            ->with([
                'FROM' => 'glpi_computers',
                'WHERE' => ['name' => 'computer1'],
                'LIMIT' => 1,
            ])
            ->willReturn($mockIterator);

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertNull($result['error']);
        $this->assertCount(1, $result['entries']);

        $entry = $result['entries'][0];
        $this->assertTrue($entry['glpi_impact']['exists']);
        $this->assertStringContainsString('exists, fields will be updated', $entry['glpi_impact']['message']);
    }

    /**
     * Test testLdapFilter method with exception thrown during processing
     */
    public function testTestLdapFilterWithException()
    {
        // Arrange
        $authldapId = $this->validAuthLdapId;
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';

        // Mock connection to throw exception
        $this->ldapConnection
            ->expects($this->once())
            ->method('connect')
            ->with($authldapId)
            ->will($this->throwException(new Exception('Connection timeout')));

        // Act
        $result = $this->ldapTestService->testLdapFilter($authldapId, $baseDn, $filter, $assetType);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('Error: Connection timeout', $result['error']);
    }
}
