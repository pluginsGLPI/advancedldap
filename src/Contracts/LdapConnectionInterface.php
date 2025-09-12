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

namespace GlpiPlugin\Advancedldap\Contracts;

/**
 * LDAP connection contract
 */
interface LdapConnectionInterface
{
    /**
     * Connect to LDAP server
     *
     * @param int $authldap_id The AuthLDAP configuration ID
     * @return mixed LDAP connection resource or false on failure
     */
    public function connect(int $authldap_id);

    /**
     * Perform LDAP search
     *
     * @param mixed  $connection LDAP connection resource
     * @param string $base_dn    Base DN for search
     * @param string $filter     LDAP filter
     * @return mixed Search result or false on failure
     */
    public function search($connection, string $base_dn, string $filter);

    /**
     * Get entries from search result
     *
     * @param mixed $connection LDAP connection resource
     * @param mixed $search_result Search result from ldap_search
     * @return array LDAP entries
     */
    public function getEntries($connection, $search_result): array;

    /**
     * Close LDAP connection
     *
     * @param mixed $connection LDAP connection resource
     * @return bool True on success
     */
    public function close($connection): bool;

    /**
     * Get last LDAP error
     *
     * @param mixed $connection LDAP connection resource
     * @return string Error message
     */
    public function getError($connection): string;
}
