<?php

namespace GlpiPlugin\Advancedldap\Tests;

use GlpiPlugin\Advancedldap\Services\LdapParameterValidator;
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use DbTestCase;

class LdapParameterValidatorTest extends DbTestCase
{
    private $validator;

    public function setUp(): void
    {
        parent::setUp();
        $this->validator = new LdapParameterValidator();
    }

    /**
     * Test validateBasicParameters with valid parameters
     */
    public function testValidateBasicParametersWithValidData()
    {
        // Arrange
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';

        // Act
        $result = $this->validator->validateBasicParameters($baseDn, $filter, $assetType);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateBasicParameters with empty base DN
     */
    public function testValidateBasicParametersWithEmptyBaseDn()
    {
        // Arrange
        $baseDn = '';
        $filter = '(objectClass=computer)';
        $assetType = 'Computer';

        // Act
        $result = $this->validator->validateBasicParameters($baseDn, $filter, $assetType);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateBasicParameters with empty filter
     */
    public function testValidateBasicParametersWithEmptyFilter()
    {
        // Arrange
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '';
        $assetType = 'Computer';

        // Act
        $result = $this->validator->validateBasicParameters($baseDn, $filter, $assetType);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateBasicParameters with empty asset type
     */
    public function testValidateBasicParametersWithEmptyAssetType()
    {
        // Arrange
        $baseDn = 'OU=Computers,DC=example,DC=com';
        $filter = '(objectClass=computer)';
        $assetType = '';

        // Act
        $result = $this->validator->validateBasicParameters($baseDn, $filter, $assetType);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateBasicParameters with all empty parameters
     */
    public function testValidateBasicParametersWithAllEmpty()
    {
        // Arrange
        $baseDn = '';
        $filter = '';
        $assetType = '';

        // Act
        $result = $this->validator->validateBasicParameters($baseDn, $filter, $assetType);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateAssetTypeExists with existing class
     */
    public function testValidateAssetTypeExistsWithExistingClass()
    {
        // Arrange
        $assetType = 'Computer'; // Standard GLPI class

        // Act
        $result = $this->validator->validateAssetTypeExists($assetType);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateAssetTypeExists with non-existing class
     */
    public function testValidateAssetTypeExistsWithNonExistingClass()
    {
        // Arrange
        $assetType = 'NonExistentAssetClass123';

        // Act
        $result = $this->validator->validateAssetTypeExists($assetType);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateAssetTypeExists with standard GLPI classes
     */
    public function testValidateAssetTypeExistsWithStandardClasses()
    {
        // Arrange & Act & Assert
        $validClasses = ['Computer', 'Monitor', 'Printer', 'NetworkEquipment', 'Phone'];

        foreach ($validClasses as $className) {
            $result = $this->validator->validateAssetTypeExists($className);
            $this->assertNull($result, "Class $className should be valid");
        }
    }

    /**
     * Test validateSyncFilter with valid SyncFilter object
     */
    public function testValidateSyncFilterWithValidData()
    {
        // Arrange
        $syncFilter = $this->createMock(SyncFilter::class);

        $syncFilter->method('getField')
            ->willReturnCallback(function ($field) {
                $fields = [
                    'base_dn' => 'OU=Computers,DC=example,DC=com',
                    'ldap_filter' => '(objectClass=computer)',
                    'asset_type' => 'Computer',
                ];
                return $fields[$field] ?? null;
            });

        $syncFilter->method('getFieldMappings')
            ->willReturn(['name' => 'cn', 'serial' => 'serialNumber']);

        // Act
        $result = $this->validator->validateSyncFilter($syncFilter);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateSyncFilter with empty base DN
     */
    public function testValidateSyncFilterWithEmptyBaseDn()
    {
        // Arrange
        $syncFilter = $this->createMock(SyncFilter::class);

        $syncFilter->method('getField')
            ->willReturnCallback(function ($field) {
                $fields = [
                    'base_dn' => '',
                    'ldap_filter' => '(objectClass=computer)',
                    'asset_type' => 'Computer',
                ];
                return $fields[$field] ?? null;
            });

        $syncFilter->method('getFieldMappings')
            ->willReturn(['name' => 'cn']);

        // Act
        $result = $this->validator->validateSyncFilter($syncFilter);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateSyncFilter with empty field mappings
     */
    public function testValidateSyncFilterWithEmptyFieldMappings()
    {
        // Arrange
        $syncFilter = $this->createMock(SyncFilter::class);

        $syncFilter->method('getField')
            ->willReturnCallback(function ($field) {
                $fields = [
                    'base_dn' => 'OU=Computers,DC=example,DC=com',
                    'ldap_filter' => '(objectClass=computer)',
                    'asset_type' => 'Computer',
                ];
                return $fields[$field] ?? null;
            });

        $syncFilter->method('getFieldMappings')
            ->willReturn([]);

        // Act
        $result = $this->validator->validateSyncFilter($syncFilter);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateSyncFilter with invalid asset type
     */
    public function testValidateSyncFilterWithInvalidAssetType()
    {
        // Arrange
        $syncFilter = $this->createMock(SyncFilter::class);

        $syncFilter->method('getField')
            ->willReturnCallback(function ($field) {
                $fields = [
                    'base_dn' => 'OU=Computers,DC=example,DC=com',
                    'ldap_filter' => '(objectClass=computer)',
                    'asset_type' => 'InvalidAssetType',
                ];
                return $fields[$field] ?? null;
            });

        $syncFilter->method('getFieldMappings')
            ->willReturn(['name' => 'cn']);

        // Act
        $result = $this->validator->validateSyncFilter($syncFilter);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateConnectionParameters with valid parameters
     */
    public function testValidateConnectionParametersWithValidData()
    {
        // Arrange
        $host = 'ldap.example.com';
        $port = 389;
        $baseDn = 'DC=example,DC=com';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateConnectionParameters with LDAPS port
     */
    public function testValidateConnectionParametersWithLdapsPort()
    {
        // Arrange
        $host = 'ldaps.example.com';
        $port = 636;
        $baseDn = 'DC=example,DC=com';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateConnectionParameters with empty host
     */
    public function testValidateConnectionParametersWithEmptyHost()
    {
        // Arrange
        $host = '';
        $port = 389;
        $baseDn = 'DC=example,DC=com';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateConnectionParameters with invalid port (zero)
     */
    public function testValidateConnectionParametersWithZeroPort()
    {
        // Arrange
        $host = 'ldap.example.com';
        $port = 0;
        $baseDn = 'DC=example,DC=com';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateConnectionParameters with invalid port (negative)
     */
    public function testValidateConnectionParametersWithNegativePort()
    {
        // Arrange
        $host = 'ldap.example.com';
        $port = -1;
        $baseDn = 'DC=example,DC=com';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateConnectionParameters with invalid port (too high)
     */
    public function testValidateConnectionParametersWithTooHighPort()
    {
        // Arrange
        $host = 'ldap.example.com';
        $port = 65536;
        $baseDn = 'DC=example,DC=com';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateConnectionParameters with maximum valid port
     */
    public function testValidateConnectionParametersWithMaximumPort()
    {
        // Arrange
        $host = 'ldap.example.com';
        $port = 65535;
        $baseDn = 'DC=example,DC=com';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateConnectionParameters with empty base DN
     */
    public function testValidateConnectionParametersWithEmptyBaseDn()
    {
        // Arrange
        $host = 'ldap.example.com';
        $port = 389;
        $baseDn = '';

        // Act
        $result = $this->validator->validateConnectionParameters($host, $port, $baseDn);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateFieldMappings with valid mappings
     */
    public function testValidateFieldMappingsWithValidData()
    {
        // Arrange
        $fieldMappings = [
            ['ldap_field' => 'cn', 'glpi_field' => 'name'],
            ['ldap_field' => 'serialNumber', 'glpi_field' => 'serial'],
            ['ldap_field' => 'description', 'glpi_field' => 'comment'],
        ];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateFieldMappings with empty array
     */
    public function testValidateFieldMappingsWithEmptyArray()
    {
        // Arrange
        $fieldMappings = [];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateFieldMappings with missing LDAP field
     */
    public function testValidateFieldMappingsWithMissingLdapField()
    {
        // Arrange
        $fieldMappings = [
            ['ldap_field' => 'cn', 'glpi_field' => 'name'],
            ['ldap_field' => '', 'glpi_field' => 'serial'], // Missing LDAP field
        ];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateFieldMappings with missing GLPI field
     */
    public function testValidateFieldMappingsWithMissingGlpiField()
    {
        // Arrange
        $fieldMappings = [
            ['ldap_field' => 'cn', 'glpi_field' => 'name'],
            ['ldap_field' => 'serialNumber', 'glpi_field' => ''], // Missing GLPI field
        ];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateFieldMappings with missing both fields
     */
    public function testValidateFieldMappingsWithMissingBothFields()
    {
        // Arrange
        $fieldMappings = [
            ['ldap_field' => '', 'glpi_field' => ''], // Both missing
        ];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateFieldMappings with incomplete mapping structure
     */
    public function testValidateFieldMappingsWithIncompleteStructure()
    {
        // Arrange
        $fieldMappings = [
            ['ldap_field' => 'cn', 'glpi_field' => 'name'],
            ['ldap_field' => 'serialNumber'], // Missing glpi_field key
        ];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    /**
     * Test validateFieldMappings with single valid mapping
     */
    public function testValidateFieldMappingsWithSingleMapping()
    {
        // Arrange
        $fieldMappings = [
            ['ldap_field' => 'cn', 'glpi_field' => 'name'],
        ];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateFieldMappings with multiple valid mappings
     */
    public function testValidateFieldMappingsWithMultipleMappings()
    {
        // Arrange
        $fieldMappings = [
            ['ldap_field' => 'cn', 'glpi_field' => 'name'],
            ['ldap_field' => 'serialNumber', 'glpi_field' => 'serial'],
            ['ldap_field' => 'description', 'glpi_field' => 'comment'],
            ['ldap_field' => 'physicalDeliveryOfficeName', 'glpi_field' => 'location'],
            ['ldap_field' => 'operatingSystem', 'glpi_field' => 'os'],
        ];

        // Act
        $result = $this->validator->validateFieldMappings($fieldMappings);

        // Assert
        $this->assertNull($result);
    }
}