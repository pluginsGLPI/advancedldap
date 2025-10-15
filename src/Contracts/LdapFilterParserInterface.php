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
 * @copyright Copyright (C) 2025 by the advancedldap plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Contracts;

/**
 * Interface for LDAP filter parsing operations
 *
 * Provides methods to extract and analyze LDAP filter attributes
 */
interface LdapFilterParserInterface
{
    /**
     * Parse LDAP filter to extract available attributes
     *
     * Extracts all attribute names found in LDAP filter patterns like:
     * - (attribute=*)
     * - (attribute=value)
     * - (attribute>=value)
     * - (attribute<=value)
     *
     * @param string $ldap_filter LDAP filter string (RFC 4515 format)
     * @return array<string> List of unique LDAP attributes found in the filter
     *
     * @example
     * Input: "(&(objectClass=device)(serialNumber=*)(cn=*))"
     * Output: ["objectClass", "serialNumber", "cn"]
     */
    public function parseFilterAttributes(string $ldap_filter): array;

    /**
     * Validate LDAP filter syntax
     *
     * @param string $ldap_filter LDAP filter string
     * @return bool True if the filter syntax is valid
     */
    public function isValidFilter(string $ldap_filter): bool;

    /**
     * Extract objectClass values from LDAP filter
     *
     * @param string $ldap_filter LDAP filter string
     * @return array<string> List of objectClass values found
     *
     * @example
     * Input: "(&(objectClass=device)(objectClass=computer))"
     * Output: ["device", "computer"]
     */
    public function extractObjectClasses(string $ldap_filter): array;
}