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

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Advancedldap\LdapConnection;

/**
 * @method void assertSame($expected, $actual, string $message = '')
 * @method void assertCount(int $expectedCount, $haystack, string $message = '')
 */
final class LdapConnectionTest extends DbTestCase
{
    /**
     * Types mirror the glpi_authldaps schema: only varchar columns are strings.
     *
     * @return array<string, mixed>
     */
    private function fullyConfiguredFields(): array
    {
        return [
            'host'         => 'ldap.example.org',
            'port'         => 636,
            'rootdn'       => 'cn=admin,dc=example,dc=org',
            'use_tls'      => 1,
            'deref_option' => 3,
            'tls_certfile' => '/etc/ssl/client.crt',
            'tls_keyfile'  => '/etc/ssl/client.key',
            'use_bind'     => 1,
            'timeout'      => 25,
            'tls_version'  => '1.3',
        ];
    }

    public function testBuildConnectionParametersForwardsFullTlsConfiguration(): void
    {
        $parameters = LdapConnection::buildConnectionParameters($this->fullyConfiguredFields(), 's3cret');

        $this->assertSame([
            'ldap.example.org',
            '636',
            'cn=admin,dc=example,dc=org',
            's3cret',
            true,
            3,
            '/etc/ssl/client.crt',
            '/etc/ssl/client.key',
            true,
            25,
            '1.3',
        ], $parameters);
    }

    public function testBuildConnectionParametersMatchesConnectToServerArity(): void
    {
        // A shorter list would fall back to core defaults, dropping the hardening.
        $this->assertCount(11, LdapConnection::buildConnectionParameters($this->fullyConfiguredFields(), ''));
    }

    public function testBuildConnectionParametersPreservesNonDefaultPort(): void
    {
        // Regression: an is_string() check sent every LDAPS connection to 389.
        $parameters = LdapConnection::buildConnectionParameters(['port' => 636], '');
        $this->assertSame('636', $parameters[1]);
    }

    public function testBuildConnectionParametersReadsDerefOptionAsInt(): void
    {
        $parameters = LdapConnection::buildConnectionParameters(['deref_option' => 3], '');
        $this->assertSame(3, $parameters[5]);
    }

    public function testBuildConnectionParametersIgnoresMisspelledDerefField(): void
    {
        // Regression: the column is `deref_option`; `deref` does not exist.
        $parameters = LdapConnection::buildConnectionParameters(['deref' => 3], '');
        $this->assertSame(0, $parameters[5]);
    }

    public function testBuildConnectionParametersAppliesDefaultsForBareEntry(): void
    {
        $parameters = LdapConnection::buildConnectionParameters(['host' => 'ldap.example.org'], '');

        $this->assertSame('389', $parameters[1]);
        $this->assertSame(false, $parameters[4]);
        $this->assertSame(0, $parameters[5]);
        $this->assertSame('', $parameters[6]);
        $this->assertSame('', $parameters[7]);
        $this->assertSame(true, $parameters[8]);
        $this->assertSame(10, $parameters[9]);
        $this->assertSame('', $parameters[10]);
    }

    public function testBuildConnectionParametersHonoursDisabledBind(): void
    {
        $parameters = LdapConnection::buildConnectionParameters(['use_bind' => '0'], '');
        $this->assertSame(false, $parameters[8]);
    }

    public function testBuildConnectionParametersCoercesUnexpectedTypes(): void
    {
        $parameters = LdapConnection::buildConnectionParameters(
            ['host' => ['unexpected'], 'timeout' => 'not-a-number'],
            '',
        );

        $this->assertSame('', $parameters[0]);
        $this->assertSame(10, $parameters[9]);
    }
}
