<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of advancedldap.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * AdvancedLDAP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdvancedLDAP. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2023 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Tests;

use Computer;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Advancedldap\Inventory\LdapSyncExecutor;
use GlpiPlugin\Advancedldap\SyncFilter;

use function Safe\hex2bin;

class TestableLdapSyncExecutor extends LdapSyncExecutor
{
    /**
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    public function callRemoveEmptyKeys(array $data): array
    {
        return $this->removeEmptyKeys($data);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $ldap_entry
     * @return array<string, mixed>
     */
    public function callReplacePlaceholders(array $data, array $ldap_entry): array
    {
        return $this->replacePlaceholders($data, $ldap_entry);
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     * @return array<string>
     */
    public function callExtractLdapAttributes(array $sections): array
    {
        return $this->extractLdapAttributes($sections);
    }

    /**
     * @param array<string, array<string, mixed>> $sections
     * @param array<string, mixed> $ldap_entry
     * @return array<string, mixed>|null
     */
    public function callBuildInventoryJson(array $sections, array $ldap_entry, SyncFilter $syncfilter): ?array
    {
        return $this->buildInventoryJson($sections, $ldap_entry, $syncfilter);
    }

    public function callConvertGuidToString(string $binary_guid): string
    {
        return $this->convertGuidToString($binary_guid);
    }

    /**
     * @param array<string, mixed> $ldap_entry
     */
    public function callGenerateDeviceId(SyncFilter $syncfilter, array $ldap_entry): string
    {
        return $this->generateDeviceId($syncfilter, $ldap_entry);
    }

    /**
     * @param array<string, mixed> $ldap_entry
     */
    public function callGetLdapValue(array $ldap_entry, string $attr_name): string
    {
        return $this->getLdapValue($ldap_entry, $attr_name);
    }
}

/**
 * @method void assertTrue($condition, string $message = '')
 * @method void assertFalse($condition, string $message = '')
 * @method void assertEquals($expected, $actual, string $message = '')
 * @method void assertNull($actual, string $message = '')
 * @method void assertNotEmpty($actual, string $message = '')
 * @method void assertIsArray($actual, string $message = '')
 * @method void assertIsString($actual, string $message = '')
 * @method void assertArrayHasKey($key, $array, string $message = '')
 * @method void assertArrayNotHasKey($key, $array, string $message = '')
 * @method void assertContains($needle, $haystack, string $message = '')
 * @method void assertCount($expected, $haystack, string $message = '')
 * @method void assertStringStartsWith($prefix, $string, string $message = '')
 * @method void assertStringEndsWith($suffix, $string, string $message = '')
 * @method void assertMatchesRegularExpression($pattern, $string, string $message = '')
 */
final class LdapSyncExecutorTest extends DbTestCase
{
    private TestableLdapSyncExecutor $executor;

    public function setUp(): void
    {
        parent::setUp();
        $this->executor = new TestableLdapSyncExecutor();
    }

    // --- removeEmptyKeys ---

    public function testRemoveEmptyKeysRemovesFlatEmptyString(): void
    {
        $result = $this->executor->callRemoveEmptyKeys(['name' => 'PC001', 'empty' => '']);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('empty', $result);
    }

    public function testRemoveEmptyKeysRecursesIntoNestedArrays(): void
    {
        $result = $this->executor->callRemoveEmptyKeys(['bios' => ['ssn' => 'ABC', 'smodel' => '']]);
        $this->assertEquals(['bios' => ['ssn' => 'ABC']], $result);
    }

    public function testRemoveEmptyKeysPreservesNonEmptyValues(): void
    {
        $result = $this->executor->callRemoveEmptyKeys(['name' => 'PC001', 'count' => 0, 'active' => false]);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('active', $result);
    }

    public function testRemoveEmptyKeysPreservesEmptyArrays(): void
    {
        $result = $this->executor->callRemoveEmptyKeys(['content' => []]);
        $this->assertArrayHasKey('content', $result);
    }

    // --- getLdapValue ---

    public function testGetLdapValueExtractsFirstValueFromLdapArray(): void
    {
        $entry = ['cn' => ['count' => 1, 0 => 'PC001']];
        $this->assertEquals('PC001', $this->executor->callGetLdapValue($entry, 'cn'));
    }

    public function testGetLdapValueReturnsEmptyStringForMissingAttribute(): void
    {
        $this->assertEquals('', $this->executor->callGetLdapValue([], 'cn'));
    }

    public function testGetLdapValueReturnsEmptyStringForEmptyLdapArray(): void
    {
        $entry = ['cn' => ['count' => 0]];
        $this->assertEquals('', $this->executor->callGetLdapValue($entry, 'cn'));
    }

    public function testGetLdapValueHandlesPlainStringValue(): void
    {
        $entry = ['cn' => 'PC001'];
        $this->assertEquals('PC001', $this->executor->callGetLdapValue($entry, 'cn'));
    }

    // --- replacePlaceholders ---

    public function testReplacePlaceholdersSubstitutesKnownAttribute(): void
    {
        $data  = ['name' => '{{ ldap.cn }}'];
        $entry = ['cn' => ['count' => 1, 0 => 'PC001']];
        $result = $this->executor->callReplacePlaceholders($data, $entry);
        $this->assertEquals('PC001', $result['name']);
    }

    public function testReplacePlaceholdersReturnsEmptyStringForMissingAttribute(): void
    {
        $data   = ['name' => '{{ ldap.missing }}'];
        $result = $this->executor->callReplacePlaceholders($data, []);
        $this->assertEquals('', $result['name']);
    }

    public function testReplacePlaceholdersHandlesMultiplePlaceholders(): void
    {
        $data  = ['name' => '{{ ldap.cn }}', 'workgroup' => '{{ ldap.domain }}'];
        $entry = [
            'cn'     => ['count' => 1, 0 => 'PC001'],
            'domain' => ['count' => 1, 0 => 'CORP'],
        ];
        $result = $this->executor->callReplacePlaceholders($data, $entry);
        $this->assertEquals('PC001', $result['name']);
        $this->assertEquals('CORP', $result['workgroup']);
    }

    public function testReplacePlaceholdersLeavesStaticValuesUnchanged(): void
    {
        $data   = ['action' => 'inventory'];
        $result = $this->executor->callReplacePlaceholders($data, []);
        $this->assertEquals('inventory', $result['action']);
    }

    public function testReplacePlaceholdersEscapesJsonSpecialCharacters(): void
    {
        $data  = ['name' => '{{ ldap.cn }}'];
        $entry = ['cn' => ['count' => 1, 0 => 'PC "test"']];
        $result = $this->executor->callReplacePlaceholders($data, $entry);
        $this->assertEquals('PC "test"', $result['name']);
    }

    // --- extractLdapAttributes ---

    public function testExtractLdapAttributesFindsPlaceholders(): void
    {
        $sections = ['hardware' => ['name' => '{{ ldap.cn }}', 'uuid' => '{{ ldap.objectGUID }}']];
        $attrs    = $this->executor->callExtractLdapAttributes($sections);
        $this->assertContains('cn', $attrs);
        $this->assertContains('objectGUID', $attrs);
    }

    public function testExtractLdapAttributesAlwaysIncludesDnAndObjectGuid(): void
    {
        $attrs = $this->executor->callExtractLdapAttributes([]);
        $this->assertContains('dn', $attrs);
        $this->assertContains('objectGUID', $attrs);
    }

    public function testExtractLdapAttributesDeduplicatesAcrossSections(): void
    {
        $sections = [
            's1' => ['name' => '{{ ldap.cn }}'],
            's2' => ['title' => '{{ ldap.cn }}'],
        ];
        $attrs = $this->executor->callExtractLdapAttributes($sections);
        $this->assertCount(1, array_filter($attrs, fn($a) => $a === 'cn'));
    }

    public function testExtractLdapAttributesHandlesMultipleSections(): void
    {
        $sections = [
            'hardware' => ['name' => '{{ ldap.cn }}'],
            'bios'     => ['ssn' => '{{ ldap.serialNumber }}'],
        ];
        $attrs = $this->executor->callExtractLdapAttributes($sections);
        $this->assertContains('cn', $attrs);
        $this->assertContains('serialNumber', $attrs);
    }

    // --- convertGuidToString ---

    public function testConvertGuidToStringProducesUuidFormat(): void
    {
        // Active Directory returns objectGUID as raw binary (16 bytes). hex2bin() converts
        // a readable hex string into that binary representation, simulating what the PHP
        // LDAP extension would return from a real directory entry.
        $binary = hex2bin('F0E1D2C3B4A5968778695A4B3C2D1E0F');
        $result = $this->executor->callConvertGuidToString($binary);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $result,
        );
    }

    public function testConvertGuidToStringFallsBackToHexForInvalidLength(): void
    {
        $input  = 'short';
        $result = $this->executor->callConvertGuidToString($input);
        $this->assertEquals(bin2hex($input), $result);
    }

    // --- generateDeviceId ---

    public function testGenerateDeviceIdUsesObjectGuidWhenPresent(): void
    {
        $syncfilter = $this->createSyncFilter();
        // Same binary GUID as above: reproduces what the PHP LDAP extension returns for objectGUID.
        $binary     = hex2bin('F0E1D2C3B4A5968778695A4B3C2D1E0F');
        $entry      = ['objectguid' => ['count' => 1, 0 => $binary]];
        $result     = $this->executor->callGenerateDeviceId($syncfilter, $entry);
        $this->assertStringStartsWith('advancedldap-' . $syncfilter->getID() . '-', $result);
    }

    public function testGenerateDeviceIdFallsBackToMd5OfDn(): void
    {
        $syncfilter = $this->createSyncFilter();
        $dn         = 'CN=PC001,DC=test,DC=local';
        $entry      = ['dn' => $dn];
        $result     = $this->executor->callGenerateDeviceId($syncfilter, $entry);
        $this->assertStringStartsWith('advancedldap-' . $syncfilter->getID() . '-', $result);
        $this->assertStringEndsWith(md5($dn), $result);
    }

    // --- buildInventoryJson ---

    public function testBuildInventoryJsonReturnsNullWithoutMainSection(): void
    {
        $syncfilter = $this->createSyncFilter();
        $result     = $this->executor->callBuildInventoryJson([], [], $syncfilter);
        $this->hasPhpLogRecordThatContains('AdvancedLDAP: Missing main section in BuilderMapping', 'Debug');
        $this->assertNull($result);
    }

    public function testBuildInventoryJsonAlwaysSetsPartialTrue(): void
    {
        $syncfilter = $this->createSyncFilter();
        $sections   = ['main' => ['action' => 'inventory', 'partial' => false, 'content' => []]];
        $result     = $this->executor->callBuildInventoryJson($sections, [], $syncfilter);
        $this->assertIsArray($result);
        assert(is_array($result));
        $this->assertTrue($result['partial']);
    }

    public function testBuildInventoryJsonMergesSectionsIntoContent(): void
    {
        $syncfilter = $this->createSyncFilter();
        $sections   = [
            'main'     => [
                'action'  => 'inventory',
                'partial' => true,
                'content' => ['versionclient' => 'test'],
            ],
            'hardware' => ['name' => '{{ ldap.cn }}'],
        ];
        $entry  = ['cn' => ['count' => 1, 0 => 'PC001']];
        $result = $this->executor->callBuildInventoryJson($sections, $entry, $syncfilter);
        $this->assertIsArray($result);
        assert(is_array($result));
        assert(is_array($result['content']));
        $this->assertArrayHasKey('hardware', $result['content']);
        assert(is_array($result['content']['hardware']));
        $this->assertEquals('PC001', $result['content']['hardware']['name']);
    }

    public function testBuildInventoryJsonStripsEmptyValuesFromPayload(): void
    {
        $syncfilter = $this->createSyncFilter();
        $sections   = [
            'main' => ['action' => 'inventory', 'tag' => '', 'content' => []],
        ];
        $result = $this->executor->callBuildInventoryJson($sections, [], $syncfilter);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('tag', $result);
    }

    // --- helpers ---

    private function createSyncFilter(): SyncFilter
    {
        /** @var SyncFilter $syncfilter */
        $syncfilter = $this->createItem(SyncFilter::class, [
            'name'              => 'Test filter',
            'connection_filter' => '(objectClass=computer)',
            'basedn'            => 'DC=test,DC=local',
            'itemtype'          => Computer::class,
        ]);
        return $syncfilter;
    }
}
