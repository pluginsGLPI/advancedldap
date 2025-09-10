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
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.fr.html
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Services;

use AuthLDAP;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;

/**
 * GLPI LDAP Connection Service Implementation
 *
 * Wraps GLPI's AuthLDAP functionality for testability
 */
class GlpiLdapConnectionService implements LdapConnectionInterface
{
    /**
     * Connect to LDAP server
     *
     * @param int $authldap_id The AuthLDAP configuration ID
     * @return mixed LDAP connection resource or false on failure
     */
    public function connect(int $authldap_id)
    {
        $authldap = new AuthLDAP();
        if (!$authldap->getFromDB($authldap_id)) {
            return false;
        }

        return $authldap->connect();
    }

    /**
     * Perform LDAP search
     *
     * @param mixed  $connection LDAP connection resource
     * @param string $base_dn    Base DN for search
     * @param string $filter     LDAP filter
     * @return mixed Search result or false on failure
     */
    public function search($connection, string $base_dn, string $filter)
    {
        return ldap_search($connection, $base_dn, $filter);
    }

    /**
     * Get entries from search result
     *
     * @param mixed $connection LDAP connection resource
     * @param mixed $search_result Search result from ldap_search
     * @return array LDAP entries
     */
    public function getEntries($connection, $search_result): array
    {
        return ldap_get_entries($connection, $search_result);
    }

    /**
     * Close LDAP connection
     *
     * @param mixed $connection LDAP connection resource
     * @return bool True on success
     */
    public function close($connection): bool
    {
        return ldap_close($connection);
    }

    /**
     * Get last LDAP error
     *
     * @param mixed $connection LDAP connection resource
     * @return string Error message
     */
    public function getError($connection): string
    {
        return ldap_error($connection);
    }
}
