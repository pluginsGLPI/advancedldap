<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Services;

use InvalidArgumentException;
use Computer;
use NetworkEquipment;
use Printer;
use Phone;

use function Safe\preg_match;

/**
 * Service for converting LDAP data to Inventory JSON format
 *
 * Converts LDAP attributes to the JSON format expected by Glpi\Inventory\Inventory
 * Supports Computer, NetworkEquipment, Printer, and Phone asset types
 */
class LdapToInventoryConverter
{
    private const SUPPORTED_ITEMTYPES = [
        Computer::class => 'Computer',
        NetworkEquipment::class => 'NetworkEquipment',
        Printer::class => 'Printer',
        Phone::class => 'Phone',
    ];

    private LdapDataExtractor $data_extractor;

    public function __construct()
    {
        $this->data_extractor = new LdapDataExtractor();
    }

    /**
     * Convert LDAP data to Inventory JSON format
     *
     * @param array<string, mixed> $ldapData LDAP attributes
     * @param string $itemtype GLPI itemtype (Computer, NetworkEquipment, etc.)
     * @param array<string, string> $fieldMappings Field mappings from sync filter (GLPI field => LDAP attribute)
     * @return array<string, mixed> JSON data compatible with Inventory.php
     */
    public function convertToInventoryFormat(array $ldapData, string $itemtype, array $fieldMappings = []): array
    {
        if (!isset(self::SUPPORTED_ITEMTYPES[$itemtype])) {
            throw new InvalidArgumentException("Unsupported itemtype for inventory conversion: $itemtype");
        }

        $deviceId = $this->generateDeviceId($ldapData, $itemtype);

        $baseInventory = [
            'action' => 'inventory',
            'deviceid' => $deviceId,
            'itemtype' => $itemtype,
            'partial' => false,
            'content' => [
                'versionclient' => '4.1',
            ],
        ];

        // Add appropriate main section based on itemtype
        if ($itemtype === NetworkEquipment::class) {
            $baseInventory['content']['network_device'] = $this->buildNetworkDeviceSection($ldapData, $fieldMappings);
        } else {
            $baseInventory['content']['hardware'] = [
                'name' => $this->extractDeviceName($ldapData),
            ];
        }

        // Add sections only if user has configured them or data exists
        $sections = $this->buildSelectiveSections($ldapData, $itemtype, $fieldMappings);
        $baseInventory['content'] = array_merge($baseInventory['content'], $sections);

        return $baseInventory;
    }

    /**
     * Build inventory sections selectively based on available LDAP data and user configuration
     *
     * @param array<string, mixed> $ldapData
     * @param string $itemtype
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildSelectiveSections(array $ldapData, string $itemtype, array $fieldMappings): array
    {
        $sections = [];

        // Hardware section - build if we have basic hardware data
        $hardware = $this->buildHardwareSection($ldapData, $itemtype, $fieldMappings);
        if ($hardware !== []) {
            $sections['hardware'] = $hardware;
        }

        // BIOS section - build if we have BIOS-related data
        $bios = $this->buildBiosSection($ldapData, $fieldMappings);
        if ($bios !== []) {
            $sections['bios'] = $bios;
        }

        // Networks section - always try to build (may be empty array)
        $sections['networks'] = $this->buildNetworkSection($ldapData, $fieldMappings);

        // Type-specific sections
        switch ($itemtype) {
            case Computer::class:
                $sections = array_merge($sections, $this->buildComputerSpecificSections($ldapData, $fieldMappings));
                break;
            case NetworkEquipment::class:
                $sections = array_merge($sections, $this->buildNetworkEquipmentSections($ldapData, $fieldMappings));
                break;
            case Printer::class:
                $sections = array_merge($sections, $this->buildPrinterSections($ldapData, $fieldMappings));
                break;
            case Phone::class:
                $sections = array_merge($sections, $this->buildPhoneSections($ldapData, $fieldMappings));
                break;
        }

        return $sections;
    }

    /**
     * Check if an LDAP field is allowed based on field mappings configuration
     * If field mappings is empty, all fields are allowed (backward compatibility)
     *
     * @param string $ldapField LDAP attribute name
     * @param array<string, string> $fieldMappings Field mappings (GLPI field => LDAP attribute)
     * @return bool
     */
    private function isFieldAllowed(string $ldapField, array $fieldMappings): bool
    {
        // If no field mappings configured, allow all fields (backward compatibility)
        if ($fieldMappings === []) {
            return true;
        }

        // Always allow critical name fields (required for asset creation)
        $criticalNameFields = ['cn', 'name', 'displayname', 'samaccountname'];
        if (in_array(strtolower($ldapField), $criticalNameFields)) {
            return true;
        }

        // Check if this LDAP field is in the configured mappings
        return in_array(strtolower($ldapField), array_map('strtolower', $fieldMappings));
    }

    /**
     * Get LDAP attribute value from field mapping
     * Handles the lowercase normalization of LDAP attributes
     *
     * @param array<string, mixed> $ldapData LDAP data array
     * @param string $glpiField GLPI field name
     * @param array<string, string> $fieldMappings Field mappings configuration
     * @return string|null The LDAP attribute value or null if not found
     */
    private function getMappedLdapValue(array $ldapData, string $glpiField, array $fieldMappings): ?string
    {
        if (empty($fieldMappings[$glpiField])) {
            return null;
        }

        // LDAP attributes are normalized to lowercase by PHP ldap_get_entries
        $ldapAttribute = strtolower($fieldMappings[$glpiField]);

        if (!empty($ldapData[$ldapAttribute][0])) {
            return $ldapData[$ldapAttribute][0];
        }

        return null;
    }

    /**
     * Get the first available value from a list of LDAP fields
     *
     * @param array<string, mixed> $ldapData LDAP data array
     * @param array<string> $fields List of field names to check
     * @param array<string, string> $fieldMappings Field mappings configuration
     * @return string|null The first non-empty value found or null
     */
    private function getFirstAvailableLdapField(array $ldapData, array $fields, array $fieldMappings): ?string
    {
        foreach ($fields as $field) {
            if ($this->isFieldAllowed($field, $fieldMappings) && !empty($ldapData[$field][0])) {
                return $ldapData[$field][0];
            }
        }
        return null;
    }

    /**
     * Find and return the first available LDAP field value from a list of fields
     * This method replaces the repetitive foreach pattern throughout the codebase
     *
     * @param array<string, mixed> $ldapData LDAP data array
     * @param array<string> $fields List of field names to check in priority order
     * @param array<string, string> $fieldMappings Field mappings configuration
     * @return array{value: string|null, field: string|null} Array with the value and the field name that was found
     */
    private function findFirstAllowedField(array $ldapData, array $fields, array $fieldMappings): array
    {
        foreach ($fields as $field) {
            if ($this->isFieldAllowed($field, $fieldMappings) && !empty($ldapData[$field][0])) {
                return [
                    'value' => $ldapData[$field][0],
                    'field' => $field,
                ];
            }
        }
        return ['value' => null, 'field' => null];
    }

    /**
     * Generate unique device ID from LDAP data
     *
     * @param array<string, mixed> $ldapData
     */
    private function generateDeviceId(array $ldapData, string $itemtype): string
    {
        // Try common unique identifiers from LDAP
        $candidates = [
            $ldapData['objectguid'][0] ?? null,
            $ldapData['entryuuid'][0] ?? null,
            $ldapData['cn'][0] ?? null,
            $ldapData['name'][0] ?? null,
            $ldapData['samaccountname'][0] ?? null,
        ];

        $identifier = null;
        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                $identifier = $candidate;
                break;
            }
        }

        if (!$identifier) {
            $identifier = 'ldap-' . md5(serialize($ldapData));
        }

        // Prefix with itemtype for uniqueness across types
        $prefix = strtolower(str_replace('\\', '-', $itemtype));
        return $prefix . '-' . $identifier;
    }

    /**
     * Build hardware section common to all itemtypes
     *
     * @param array<string, mixed> $ldapData
     * @param string $itemtype
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildHardwareSection(array $ldapData, string $itemtype, array $fieldMappings): array
    {
        $hardware = [];

        // Name mapping (required for most inventory)
        $nameFields = ['cn', 'name', 'displayname', 'samaccountname'];
        $nameResult = $this->findFirstAllowedField($ldapData, $nameFields, $fieldMappings);
        if ($nameResult['value'] !== null) {
            $hardware['name'] = $nameResult['value'];
        }

        // UUID mapping - Check field_mappings first, then defaults
        $uuid = $this->getMappedLdapValue($ldapData, 'uuid', $fieldMappings);
        if ($uuid === null) {
            // Fallback to default UUID fields if not in mappings
            $uuidFields = ['objectguid', 'entryuuid'];
            $uuid = $this->getFirstAvailableLdapField($ldapData, $uuidFields, $fieldMappings);
        }
        if ($uuid !== null) {
            $hardware['uuid'] = $uuid;
        }

        // Chassis type (for computers) - only if allowed
        if ($itemtype === Computer::class) {
            $chassisFields = ['chassistype', 'chassis_type'];
            $chassisResult = $this->findFirstAllowedField($ldapData, $chassisFields, $fieldMappings);
            if ($chassisResult['value'] !== null) {
                $hardware['chassis_type'] = $chassisResult['value'];
            } else {
                // Default chassis type if not specified
                $hardware['chassis_type'] = 'Desktop'; // Default value
            }
        }

        // Memory (total system memory) - only if allowed
        $memoryFields = ['totalmemory', 'physicalmemory', 'memory'];
        $memoryResult = $this->findFirstAllowedField($ldapData, $memoryFields, $fieldMappings);
        if ($memoryResult['value'] !== null && is_numeric($memoryResult['value'])) {
            $hardware['memory'] = (int) $memoryResult['value'];
        }

        // Virtual machine system indicator
        $hardware['vmsystem'] = 'Physical'; // Default for LDAP assets

        // Comment field - map from field_mappings configuration
        // Check if 'comment' is configured in field mappings
        if (!empty($fieldMappings['comment'])) {
            // LDAP attributes are normalized to lowercase by PHP ldap_get_entries
            $ldapAttribute = strtolower($fieldMappings['comment']);
            if (!empty($ldapData[$ldapAttribute][0])) {
                $hardware['comment'] = $ldapData[$ldapAttribute][0];
            }
        }

        return $hardware;
    }

    /**
     * Build Computer-specific sections
     *
     * @param array<string, mixed> $ldapData
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildComputerSpecificSections(array $ldapData, array $fieldMappings): array
    {
        $sections = [];

        // Operating System - only if allowed
        if (
            ($this->isFieldAllowed('operatingsystem', $fieldMappings) && !empty($ldapData['operatingsystem'][0])) ||
            ($this->isFieldAllowed('operatingsystemversion', $fieldMappings) && !empty($ldapData['operatingsystemversion'][0]))
        ) {
            $sections['operatingsystem'] = [];

            if ($this->isFieldAllowed('operatingsystem', $fieldMappings) && !empty($ldapData['operatingsystem'][0])) {
                $sections['operatingsystem']['name'] = $ldapData['operatingsystem'][0];
            }

            if ($this->isFieldAllowed('operatingsystemversion', $fieldMappings) && !empty($ldapData['operatingsystemversion'][0])) {
                $sections['operatingsystem']['version'] = $ldapData['operatingsystemversion'][0];
            }

            if ($this->isFieldAllowed('operatingsystemarchitecture', $fieldMappings) && !empty($ldapData['operatingsystemarchitecture'][0])) {
                $sections['operatingsystem']['architecture'] = $ldapData['operatingsystemarchitecture'][0];
            }
        }

        // Memory - only if allowed
        if (
            ($this->isFieldAllowed('totalmemory', $fieldMappings) && !empty($ldapData['totalmemory'][0])) ||
            ($this->isFieldAllowed('physicalmemory', $fieldMappings) && !empty($ldapData['physicalmemory'][0]))
        ) {
            $sections['memories'] = [];
            $memorySize = $ldapData['totalmemory'][0] ?? $ldapData['physicalmemory'][0];

            if (is_numeric($memorySize)) {
                $sections['memories'][] = [
                    'capacity' => (int) $memorySize,
                    'description' => 'Physical Memory',
                    'type' => 'Unknown',
                ];
            }
        }

        // Networks
        $sections['networks'] = $this->buildNetworkSection($ldapData, $fieldMappings);

        return $sections;
    }

    /**
     * Build NetworkEquipment-specific sections
     */
    /**
     * Build network_device section specifically for NetworkEquipment
     * Based on GLPI inventory format standard
     *
     * @param array<string, mixed> $ldapData
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildNetworkDeviceSection(array $ldapData, array $fieldMappings): array
    {
        $networkDevice = [
            'name' => $this->extractDeviceName($ldapData),
            'type' => 'Networking',
        ];

        // Add serial number if available and allowed
        $serialFields = ['serialnumber', 'serial', 'hardwareserial'];
        $serialResult = $this->findFirstAllowedField($ldapData, $serialFields, $fieldMappings);
        if ($serialResult['value'] !== null) {
            $networkDevice['serial'] = $serialResult['value'];
        }

        // Add manufacturer if available and allowed
        $manufacturerFields = ['manufacturer', 'vendor', 'company'];
        $manufacturerResult = $this->findFirstAllowedField($ldapData, $manufacturerFields, $fieldMappings);
        if ($manufacturerResult['value'] !== null) {
            $networkDevice['manufacturer'] = $manufacturerResult['value'];
        }

        // Add model if available and allowed
        $modelFields = ['model', 'hardwaremodel', 'description'];
        $modelResult = $this->findFirstAllowedField($ldapData, $modelFields, $fieldMappings);
        if ($modelResult['value'] !== null) {
            $networkDevice['model'] = $modelResult['value'];
        }

        // Add firmware/version if available and allowed
        $firmwareFields = ['firmware', 'version', 'softwareversion'];
        $firmwareResult = $this->findFirstAllowedField($ldapData, $firmwareFields, $fieldMappings);
        if ($firmwareResult['value'] !== null) {
            $networkDevice['firmware'] = $firmwareResult['value'];
        }

        // Add MAC address if available and allowed
        $macFields = ['macaddress', 'mac', 'physicaladdress'];
        $macResult = $this->findFirstAllowedField($ldapData, $macFields, $fieldMappings);
        if ($macResult['value'] !== null) {
            $networkDevice['mac'] = $macResult['value'];
        }

        // Add IP addresses if available and allowed
        $ipFields = ['ipaddress', 'ip', 'networkaddress'];
        foreach ($ipFields as $field) {
            if ($this->isFieldAllowed($field, $fieldMappings) && !empty($ldapData[$field])) {
                $ips = is_array($ldapData[$field]) ? array_filter($ldapData[$field], 'is_string') : [$ldapData[$field]];
                if ($ips !== []) {
                    $networkDevice['ips'] = $ips;
                    break;
                }
            }
        }

        // Add location if available and allowed
        $locationFields = ['location', 'site', 'office'];
        $locationResult = $this->findFirstAllowedField($ldapData, $locationFields, $fieldMappings);
        if ($locationResult['value'] !== null) {
            $networkDevice['location'] = $locationResult['value'];
        }

        // Add contact if available and allowed
        $contactFields = ['contact', 'owner', 'admin'];
        $contactResult = $this->findFirstAllowedField($ldapData, $contactFields, $fieldMappings);
        if ($contactResult['value'] !== null) {
            $networkDevice['contact'] = $contactResult['value'];
        }

        return $networkDevice;
    }

    /**
     * @param array<string, mixed> $ldapData
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildNetworkEquipmentSections(array $ldapData, array $fieldMappings): array
    {
        $sections = [];

        // Networks section is common
        $sections['networks'] = $this->buildNetworkSection($ldapData, $fieldMappings);

        // Network ports section (empty by default, can be extended)
        $sections['network_ports'] = [];

        // Firmware section - only if allowed
        $firmwareFields = ['firmware', 'version', 'softwareversion'];
        $firmwareResult = $this->findFirstAllowedField($ldapData, $firmwareFields, $fieldMappings);
        if ($firmwareResult['value'] !== null) {
            $sections['firmwares'] = [[
                'version' => $firmwareResult['value'],
                'type' => 'system',
            ]];
        }

        return $sections;
    }

    /**
     * Build Printer-specific sections
     *
     * @param array<string, mixed> $ldapData
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildPrinterSections(array $ldapData, array $fieldMappings): array
    {
        $sections = [];

        // Networks section
        $sections['networks'] = $this->buildNetworkSection($ldapData, $fieldMappings);

        // Driver information - only if allowed
        if (
            ($this->isFieldAllowed('drivername', $fieldMappings) && !empty($ldapData['drivername'][0])) ||
            ($this->isFieldAllowed('driver', $fieldMappings) && !empty($ldapData['driver'][0]))
        ) {
            $sections['driver'] = $ldapData['drivername'][0] ?? $ldapData['driver'][0];
        }

        // Add network_device with minimal required structure for printers
        $networkDevice = [
            'type' => 'Printer',
            'name' => $this->extractDeviceName($ldapData),
        ];

        // Add serial if available and allowed
        if ($this->isFieldAllowed('serialnumber', $fieldMappings) && !empty($ldapData['serialnumber'][0])) {
            $networkDevice['serial'] = $ldapData['serialnumber'][0];
        }

        // Add model from description if available and allowed
        if ($this->isFieldAllowed('description', $fieldMappings) && !empty($ldapData['description'][0])) {
            $networkDevice['model'] = $ldapData['description'][0];
        }

        $sections['network_device'] = $networkDevice;

        return $sections;
    }

    /**
     * Build Phone-specific sections
     *
     * @param array<string, mixed> $ldapData
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildPhoneSections(array $ldapData, array $fieldMappings): array
    {
        $sections = [];

        // Networks section
        $sections['networks'] = $this->buildNetworkSection($ldapData, $fieldMappings);

        return $sections;
    }

    /**
     * Build BIOS section for hardware information
     *
     * @param array<string, mixed> $ldapData
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildBiosSection(array $ldapData, array $fieldMappings): array
    {
        $bios = [];
        $hasData = false;

        // BIOS manufacturer - only if allowed
        $biosManufacturerFields = ['manufacturer', 'company', 'organizationname'];
        $manufacturerResult = $this->findFirstAllowedField($ldapData, $biosManufacturerFields, $fieldMappings);
        if ($manufacturerResult['value'] !== null) {
            $bios['bmanufacturer'] = $manufacturerResult['value'];
            $bios['smanufacturer'] = $manufacturerResult['value']; // Same for system
            $hasData = true;
        }

        // BIOS version - only if allowed
        $biosVersionFields = ['biosversion', 'bversion', 'firmware', 'version'];
        $versionResult = $this->findFirstAllowedField($ldapData, $biosVersionFields, $fieldMappings);
        if ($versionResult['value'] !== null) {
            $bios['bversion'] = $versionResult['value'];
            $hasData = true;
        }

        // BIOS date - only if allowed
        $biosDateFields = ['biosdate', 'bdate'];
        $dateResult = $this->findFirstAllowedField($ldapData, $biosDateFields, $fieldMappings);
        if ($dateResult['value'] !== null) {
            $bios['bdate'] = $dateResult['value'];
            $hasData = true;
        }

        // System model - only if allowed
        $modelFields = ['model', 'productname', 'smodel'];
        $modelResult = $this->findFirstAllowedField($ldapData, $modelFields, $fieldMappings);
        if ($modelResult['value'] !== null) {
            $bios['smodel'] = $modelResult['value'];
            $hasData = true;
        }

        // System serial number - only if allowed (THIS IS THE KEY FIX!)
        $serialFields = ['serialnumber', 'serialNumber', 'ssn'];
        $serialResult = $this->findFirstAllowedField($ldapData, $serialFields, $fieldMappings);
        if ($serialResult['value'] !== null) {
            $bios['ssn'] = $serialResult['value'];
            $hasData = true;
        }

        // Motherboard manufacturer (if different) - only if allowed
        if ($this->isFieldAllowed('motherboardmanufacturer', $fieldMappings) && !empty($ldapData['motherboardmanufacturer'][0])) {
            $bios['mmanufacturer'] = $ldapData['motherboardmanufacturer'][0];
            $hasData = true;
        }

        // Motherboard model - only if allowed
        if ($this->isFieldAllowed('motherboardmodel', $fieldMappings) && !empty($ldapData['motherboardmodel'][0])) {
            $bios['mmodel'] = $ldapData['motherboardmodel'][0];
            $hasData = true;
        }

        // Only return BIOS section if we have actual data
        return $hasData ? $bios : [];
    }

    /**
     * Build network section common to multiple itemtypes
     *
     * @param array<string, mixed> $ldapData
     * @param array<string, string> $fieldMappings Field mappings from sync filter
     * @return array<string, mixed>
     */
    private function buildNetworkSection(array $ldapData, array $fieldMappings): array
    {
        $networks = [];

        // IP Address - only if allowed
        $ipFields = ['ipaddress', 'networkaddress', 'ip'];
        $ipResult = $this->findFirstAllowedField($ldapData, $ipFields, $fieldMappings);
        $ipAddress = $ipResult['value'];

        // MAC Address - only if allowed
        $macFields = ['macaddress', 'physicaldeliveryofficename', 'networkaddress'];
        $macResult = $this->findFirstAllowedField($ldapData, $macFields, $fieldMappings);
        $macAddress = null;
        // Validate MAC address format
        if ($macResult['value'] !== null && preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $macResult['value'])) {
            $macAddress = $macResult['value'];
        }

        // Description - only if allowed
        $description = 'Network Interface';
        if ($this->isFieldAllowed('description', $fieldMappings) && !empty($ldapData['description'][0])) {
            $description = $ldapData['description'][0];
        }

        if ($ipAddress || $macAddress) {
            $network = ['description' => $description];

            if ($ipAddress) {
                $network['ipaddress'] = $ipAddress;
            }

            if ($macAddress) {
                $network['macaddr'] = $macAddress;
            }

            $networks[] = $network;
        }

        return $networks;
    }

    /**
     * Extract device name from LDAP data
     *
     * @param array<string, mixed> $ldapData
     */
    private function extractDeviceName(array $ldapData): string
    {
        return $this->data_extractor->extractDeviceName($ldapData);
    }

    /**
     * Get supported itemtypes for inventory conversion
     *
     * @return array<int, class-string>
     */
    public function getSupportedItemtypes(): array
    {
        return array_keys(self::SUPPORTED_ITEMTYPES);
    }
}
