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
 * @author    GLPI-Project
 * @copyright Copyright (C) 2018-2023 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap;

use AuthLDAP;
use GLPIKey;
use LDAP\Connection;
use RuntimeException;
use Toolbox;

/**
 * Single entry point for opening an LDAP connection from an AuthLDAP entry:
 * a partial parameter list silently downgrades the configured TLS hardening.
 */
final class LdapConnection
{
    /** Seconds, when the AuthLDAP entry has no timeout of its own. */
    private const DEFAULT_TIMEOUT = 10;

    /**
     * Open a connection, logging every failure so callers need not.
     *
     * @param AuthLDAP $authldap The directory to connect to
     * @return Connection|false The connection, or false on failure
     */
    public static function connect(AuthLDAP $authldap)
    {
        $rootdn_passwd = $authldap->fields['rootdn_passwd'] ?? '';
        $decrypted_passwd = (new GLPIKey())->decrypt(is_string($rootdn_passwd) ? $rootdn_passwd : '');

        $parameters = self::buildConnectionParameters(
            $authldap->fields,
            is_string($decrypted_passwd) ? $decrypted_passwd : '',
        );

        // connectToServer() keeps its $last_error private, so a plain false
        // return gives us no detail; it throws only on a missing host or port.
        $reason = 'the server refused the connection';

        try {
            $ds = AuthLDAP::connectToServer(...$parameters);
        } catch (RuntimeException $e) {
            $ds = false;
            $reason = $e->getMessage();
        }

        if ($ds === false) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Cannot connect to AuthLDAP %d: %s',
                $authldap->getID(),
                $reason,
            ));
            return false;
        }

        return $ds;
    }

    /**
     * Build the ordered argument list for AuthLDAP::connectToServer().
     *
     * Kept separate from connect() so it can be asserted without a directory.
     *
     * @param array<string, mixed> $fields           AuthLDAP fields
     * @param string               $decrypted_passwd Already-decrypted bind password
     * @return array{string, string, string, string, bool, int, string, string, bool, int, string}
     */
    public static function buildConnectionParameters(array $fields, string $decrypted_passwd): array
    {
        $deref_raw   = $fields['deref_option'] ?? 0;
        $timeout_raw = $fields['timeout'] ?? self::DEFAULT_TIMEOUT;

        return [
            is_string($fields['host'] ?? null) ? $fields['host'] : '',
            // `port` is an int column: is_string() would always fall back to 389.
            is_numeric($fields['port'] ?? null) ? (string) $fields['port'] : '389',
            is_string($fields['rootdn'] ?? null) ? $fields['rootdn'] : '',
            $decrypted_passwd,
            !empty($fields['use_tls']),
            is_numeric($deref_raw) ? (int) $deref_raw : 0,
            is_string($fields['tls_certfile'] ?? null) ? $fields['tls_certfile'] : '',
            is_string($fields['tls_keyfile'] ?? null) ? $fields['tls_keyfile'] : '',
            !isset($fields['use_bind']) || !empty($fields['use_bind']),
            is_numeric($timeout_raw) ? (int) $timeout_raw : self::DEFAULT_TIMEOUT,
            is_string($fields['tls_version'] ?? null) ? $fields['tls_version'] : '',
        ];
    }
}
