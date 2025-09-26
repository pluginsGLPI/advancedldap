<?php

namespace GlpiPlugin\Advancedldap\Tests;

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
            'macaddress' => ['00:1B:63:84:45:E6']
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
            'location' => ['Server Room A']
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
            'location' => ['Office Floor 1']
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
            'macaddress' => ['AA:BB:CC:DD:EE:FF']
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
        $this->expectException(\InvalidArgumentException::class);
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
     * Test convertToInventoryFormat with SyncFilter configuration
     */
    public function testConvertToInventoryFormatWithSyncFilterConfig()
    {
        // Arrange
        $ldapData = [
            'cn' => ['CONFIG-TEST'],
            'operatingsystem' => ['Ubuntu 20.04']
        ];

        $syncFilterConfig = [
            'field_mappings' => json_encode([
                'name' => 'cn',
                'os' => 'operatingsystem'
            ])
        ];

        // Act
        $result = $this->converter->convertToInventoryFormat($ldapData, Computer::class, $syncFilterConfig);

        // Assert
        $this->assertEquals('CONFIG-TEST', $result['content']['hardware']['name']);
        $this->assertArrayHasKey('operatingsystem', $result['content']);
    }
}