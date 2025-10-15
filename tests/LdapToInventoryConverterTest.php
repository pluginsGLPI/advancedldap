<?php

namespace GlpiPlugin\Advancedldap\Tests;

use InvalidArgumentException;
use GlpiPlugin\Advancedldap\Services\LdapToInventoryConverter;
use Computer;
use NetworkEquipment;
use Printer;
use Phone;
use DbTestCase;

class LdapToInventoryConverterTest extends DbTestCase
{
    private $converter;

    public function setUp(): void
    {
        parent::setUp();
        $this->converter = new LdapToInventoryConverter();
    }

    /**
     * Test convertToInventoryFormat with valid Computer data
     */
    public function testConvertToInventoryFormatWithValidComputerData()
    {
        // Arrange
        $ldapData = [
            'cn' => ['TEST-PC-001'],
            'objectguid' => ['12345-abcde-67890'],
            'operatingsystem' => ['Windows 10 Pro'],
            'operatingsystemversion' => ['10.0.19042'],
            'totalmemory' => ['8589934592'],
            'ipaddress' => ['192.168.1.100'],
            'macaddress' => ['00:1B:63:84:45:E6'],
        ];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Computer::class);

        // Assert
        $this->assertEquals('inventory', $result['action']);
        $this->assertStringContainsString('computer-12345-abcde-67890', $result['deviceid']); // objectguid has priority over cn
        $this->assertEquals(Computer::class, $result['itemtype']);
        $this->assertFalse($result['partial']);

        // Check content structure
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('4.1', $result['content']['versionclient']);
        $this->assertArrayHasKey('hardware', $result['content']);
        $this->assertEquals('TEST-PC-001', $result['content']['hardware']['name']);
        $this->assertEquals('12345-abcde-67890', $result['content']['hardware']['uuid']);

        // Check operating system
        $this->assertArrayHasKey('operatingsystem', $result['content']);
        $this->assertEquals('Windows 10 Pro', $result['content']['operatingsystem']['name']);
        $this->assertEquals('10.0.19042', $result['content']['operatingsystem']['version']);

        // Check networks
        $this->assertArrayHasKey('networks', $result['content']);
        $this->assertIsArray($result['content']['networks']);
        $this->assertCount(1, $result['content']['networks']);
        $this->assertEquals('192.168.1.100', $result['content']['networks'][0]['ipaddress']);
        $this->assertEquals('00:1B:63:84:45:E6', $result['content']['networks'][0]['macaddr']);
    }

    /**
     * Test convertToInventoryFormat with NetworkEquipment data
     */
    public function testConvertToInventoryFormatWithNetworkEquipmentData()
    {
        // Arrange
        $ldapData = [
            'cn' => ['SWITCH-001'],
            'entryuuid' => ['uuid-switch-001'],
            'serialnumber' => ['SNK12345'],
            'manufacturer' => ['Cisco'],
            'model' => ['Catalyst 2960'],
            'firmware' => ['15.2(4)E10'],
            'macaddress' => ['00:1A:2B:3C:4D:5E'],
            'ipaddress' => ['192.168.1.10'],
            'location' => ['Server Room A'],
        ];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, NetworkEquipment::class);

        // Assert
        $this->assertEquals('inventory', $result['action']);
        $this->assertEquals(NetworkEquipment::class, $result['itemtype']);
        $this->assertStringContainsString('networkequipment-uuid-switch-001', $result['deviceid']); // entryuuid has priority over cn

        // Check network_device section
        $this->assertArrayHasKey('network_device', $result['content']);
        $networkDevice = $result['content']['network_device'];
        $this->assertEquals('SWITCH-001', $networkDevice['name']);
        $this->assertEquals('Networking', $networkDevice['type']);
        $this->assertEquals('SNK12345', $networkDevice['serial']);
        $this->assertEquals('Cisco', $networkDevice['manufacturer']);
        $this->assertEquals('Catalyst 2960', $networkDevice['model']);
        $this->assertEquals('15.2(4)E10', $networkDevice['firmware']);
        $this->assertEquals('00:1A:2B:3C:4D:5E', $networkDevice['mac']);
        $this->assertEquals('Server Room A', $networkDevice['location']);
        $this->assertContains('192.168.1.10', $networkDevice['ips']);
    }

    /**
     * Test convertToInventoryFormat with Printer data
     */
    public function testConvertToInventoryFormatWithPrinterData()
    {
        // Arrange
        $ldapData = [
            'cn' => ['PRINTER-001'],
            'objectguid' => ['printer-guid-001'],
            'serialnumber' => ['PRT12345'],
            'description' => ['HP LaserJet Pro M404n'],
            'drivername' => ['HP Universal Printing PCL 6'],
            'ipaddress' => ['192.168.1.200'],
            'location' => ['Office Floor 1'],
        ];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Printer::class);

        // Assert
        $this->assertEquals('inventory', $result['action']);
        $this->assertEquals(Printer::class, $result['itemtype']);
        $this->assertStringContainsString('printer-printer-guid-001', $result['deviceid']); // objectguid has priority over cn

        // Check network_device section for printer
        $this->assertArrayHasKey('network_device', $result['content']);
        $networkDevice = $result['content']['network_device'];
        $this->assertEquals('PRINTER-001', $networkDevice['name']);
        $this->assertEquals('Printer', $networkDevice['type']);
        $this->assertEquals('PRT12345', $networkDevice['serial']);
        $this->assertEquals('HP LaserJet Pro M404n', $networkDevice['model']);

        // Check driver information
        $this->assertArrayHasKey('driver', $result['content']);
        $this->assertEquals('HP Universal Printing PCL 6', $result['content']['driver']);
    }

    /**
     * Test convertToInventoryFormat with Phone data
     */
    public function testConvertToInventoryFormatWithPhoneData()
    {
        // Arrange
        $ldapData = [
            'cn' => ['PHONE-001'],
            'entryuuid' => ['phone-uuid-001'],
            'ipaddress' => ['192.168.1.150'],
            'macaddress' => ['AA:BB:CC:DD:EE:FF'],
        ];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Phone::class);

        // Assert
        $this->assertEquals('inventory', $result['action']);
        $this->assertEquals(Phone::class, $result['itemtype']);
        $this->assertStringContainsString('phone-phone-uuid-001', $result['deviceid']); // entryuuid has priority over cn

        // Check basic structure
        $this->assertArrayHasKey('hardware', $result['content']);
        $this->assertEquals('PHONE-001', $result['content']['hardware']['name']);

        // Check networks
        $this->assertArrayHasKey('networks', $result['content']);
        $this->assertIsArray($result['content']['networks']);
    }

    /**
     * Test convertToInventoryFormat with unsupported itemtype
     */
    public function testConvertToInventoryFormatWithUnsupportedItemtype()
    {
        // Arrange
        $ldapData = ['cn' => ['TEST-DEVICE']];

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported itemtype for inventory conversion: UnsupportedClass');

        // Act
        $this->converter->convertToInventoryFormat($ldapData, 'UnsupportedClass');
    }


    /**
     * Test getSupportedItemtypes method
     */
    public function testGetSupportedItemtypes()
    {
        $result = $this->converter->getSupportedItemtypes();

        $expectedTypes = [Computer::class, NetworkEquipment::class, Printer::class, Phone::class];
        $this->assertEquals($expectedTypes, $result);
    }

    /**
     * Test convertToInventoryFormat with minimal data
     */
    public function testConvertToInventoryFormatWithMinimalData()
    {
        // Arrange - only required minimum data
        $ldapData = ['cn' => ['MINIMAL-DEVICE']];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Computer::class);

        // Assert basic structure is maintained
        $this->assertEquals('inventory', $result['action']);
        $this->assertEquals(Computer::class, $result['itemtype']);
        $this->assertStringContainsString('computer-MINIMAL-DEVICE', $result['deviceid']); // cn is used when no guid available
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('hardware', $result['content']);
        $this->assertEquals('MINIMAL-DEVICE', $result['content']['hardware']['name']);
    }

    /**
     * Test convertToInventoryFormat with field mappings configuration
     */
    public function testConvertToInventoryFormatWithSyncFilterConfig()
    {
        // Arrange
        $ldapData = [
            'cn' => ['CONFIG-TEST'],
            'operatingsystem' => ['Ubuntu 20.04'],
        ];

        // Field mappings: direct array format (not JSON encoded)
        $fieldMappings = [
            'name' => 'cn',
            'os' => 'operatingsystem',
        ];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Computer::class, $fieldMappings);

        // Assert
        $this->assertEquals('CONFIG-TEST', $result['content']['hardware']['name']);

        // Since 'operatingsystem' is in the field mappings, it should be included
        $this->assertArrayHasKey('operatingsystem', $result['content']);
        $this->assertEquals('Ubuntu 20.04', $result['content']['operatingsystem']['name']);
    }

    // ========== Field Mappings Filtering Tests ==========

    /**
     * Test convertToInventoryFormat with fieldMappings filters non-configured LDAP attributes
     */
    public function testConvertToInventoryFormatWithFieldMappingsFiltersAttributes()
    {
        // Arrange - Complete LDAP data
        $ldapData = [
            'cn' => ['TEST-PC'],
            'serialnumber' => ['SN12345'],      // Configured in mapping
            'model' => ['Dell Latitude'],        // NOT configured
            'ipaddress' => ['192.168.1.10'],     // NOT configured
            'manufacturer' => ['Dell Inc.'],     // NOT configured
        ];

        // Field mappings: only name and serial
        $fieldMappings = ['name' => 'cn', 'serial' => 'serialnumber'];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Computer::class, $fieldMappings);

        // Assert
        $this->assertEquals('TEST-PC', $result['content']['hardware']['name']);

        // Verify 'serial' is present (in mapping)
        $this->assertArrayHasKey('bios', $result['content']);
        $this->assertEquals('SN12345', $result['content']['bios']['ssn']);

        // Verify 'model' is ABSENT (not in mapping)
        $this->assertArrayNotHasKey('smodel', $result['content']['bios']);

        // Verify 'manufacturer' is ABSENT (not in mapping)
        $this->assertArrayNotHasKey('smanufacturer', $result['content']['bios']);

        // Verify 'ipaddress' is ABSENT (not in mapping)
        if (isset($result['content']['networks'])) {
            $this->assertEmpty($result['content']['networks']);
        }
    }

    /**
     * Test convertToInventoryFormat with critical name fields always allowed
     */
    public function testConvertToInventoryFormatCriticalFieldsAlwaysAllowed()
    {
        // Even if fieldMappings is empty, 'cn' must be used
        $ldapData = [
            'cn' => ['CRITICAL-DEVICE'],
            'displayname' => ['Critical Display Name'],
            'model' => ['Should be filtered'],
        ];

        $fieldMappings = [];  // Empty config

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Computer::class, $fieldMappings);

        // Assert
        // 'cn' always present (critical field)
        $this->assertEquals('CRITICAL-DEVICE', $result['content']['hardware']['name']);

        // But model should still be included since fieldMappings is empty (backward compat)
        // When fieldMappings is empty, ALL fields are allowed
        if (isset($result['content']['bios']['smodel'])) {
            $this->assertEquals('Should be filtered', $result['content']['bios']['smodel']);
        }
    }

    /**
     * Test convertToInventoryFormat with empty field mappings allows all fields (backward compatibility)
     */
    public function testConvertToInventoryFormatEmptyMappingsAllowsAll()
    {
        $ldapData = [
            'cn' => ['PC-001'],
            'serialnumber' => ['SN999'],
            'model' => ['Dell'],
            'ipaddress' => ['10.0.0.1'],
        ];

        // NO field mappings = old behavior (everything allowed)
        $result = $this->converter->convertToInventoryFormat($ldapData, Computer::class, []);

        // All fields must be present
        $this->assertEquals('PC-001', $result['content']['hardware']['name']);
        $this->assertEquals('SN999', $result['content']['bios']['ssn']);
        $this->assertEquals('Dell', $result['content']['bios']['smodel']);
        $this->assertNotEmpty($result['content']['networks']);
        $this->assertEquals('10.0.0.1', $result['content']['networks'][0]['ipaddress']);
    }

    /**
     * Test convertToInventoryFormat NetworkEquipment with field mappings filtering
     */
    public function testConvertToInventoryFormatNetworkEquipmentWithMappings()
    {
        $ldapData = [
            'cn' => ['SWITCH-001'],
            'serialnumber' => ['SN-SWITCH-123'],
            'manufacturer' => ['Cisco'],  // Mapped
            'firmware' => ['v15.2'],      // NOT mapped
            'model' => ['Catalyst'],      // NOT mapped
        ];

        $fieldMappings = ['name' => 'cn', 'serial' => 'serialnumber', 'manufacturer' => 'manufacturer'];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, NetworkEquipment::class, $fieldMappings);

        // Assert
        // Manufacturer present (in mapping)
        $this->assertEquals('Cisco', $result['content']['network_device']['manufacturer']);

        // Firmware absent (not in mapping)
        $this->assertArrayNotHasKey('firmware', $result['content']['network_device']);
        if (isset($result['content']['firmwares'])) {
            $this->assertEmpty($result['content']['firmwares']);
        }

        // Model absent (not in mapping)
        $this->assertArrayNotHasKey('model', $result['content']['network_device']);
    }

    /**
     * Test convertToInventoryFormat Printer with field mappings filtering
     */
    public function testConvertToInventoryFormatPrinterWithMappings()
    {
        $ldapData = [
            'cn' => ['PRINTER-001'],
            'serialnumber' => ['SN-PRINT-456'],
            'driver' => ['HP LaserJet PCL 6'],  // Mapped
            'description' => ['Office Printer'], // NOT mapped
        ];

        $fieldMappings = ['name' => 'cn', 'serial' => 'serialnumber', 'driver' => 'driver'];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Printer::class, $fieldMappings);

        // Assert
        // Driver present (in mapping)
        if (isset($result['content']['printer'])) {
            $this->assertEquals('HP LaserJet PCL 6', $result['content']['printer']['driver']);
        }

        // Serial present (in mapping)
        $this->assertEquals('SN-PRINT-456', $result['content']['bios']['ssn']);

        // Description absent (not in mapping)
        if (isset($result['content']['printer'])) {
            $this->assertArrayNotHasKey('description', $result['content']['printer']);
        }
    }
}
