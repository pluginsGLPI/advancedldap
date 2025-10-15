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
 * Interface for LDAP attribute mapping operations
 *
 * Provides RFC 4519 standard mappings between GLPI fields and LDAP attributes
 */
interface LdapAttributeMapperInterface
{
    /**
     * Find matching LDAP attribute for a GLPI field based on RFC 4519 conventions
     *
     * Uses standard RFC 4519 mappings to suggest appropriate LDAP attributes
     * for common GLPI field names. Falls back to exact field name if no standard
     * mapping exists.
     *
     * @param string $glpi_field GLPI field name (e.g., 'serial', 'name', 'comment')
     * @param array<string> $available_ldap_attributes Available LDAP attributes from filter
     * @return string Best matching LDAP attribute or same name as fallback
     *
     * @example
     * Input: glpi_field='serial', available=['serialNumber', 'cn']
     * Output: 'serialNumber'
     *
     * @example
     * Input: glpi_field='name', available=['cn', 'description']
     * Output: 'cn'
     */
    public function findMatchingAttribute(string $glpi_field, array $available_ldap_attributes): string;

    /**
     * Get all standard RFC 4519 mappings
     *
     * Returns complete mapping table between GLPI fields and LDAP attributes
     * according to RFC 4519 standard.
     *
     * @return array<string, string> Associative array [glpi_field => ldap_attribute]
     *
     * @example
     * Output: [
     *     'serial' => 'serialNumber',
     *     'name' => 'cn',
     *     'comment' => 'description',
     *     'location' => 'l',
     *     'phone' => 'telephoneNumber',
     *     ...
     * ]
     */
    public function getStandardMappings(): array;

    /**
     * Get reverse mapping (LDAP attribute to GLPI field)
     *
     * @param string $ldap_attribute LDAP attribute name
     * @return string|null Corresponding GLPI field or null if no mapping exists
     *
     * @example
     * Input: 'serialNumber'
     * Output: 'serial'
     */
    public function getGlpiFieldForAttribute(string $ldap_attribute): ?string;

    /**
     * Validate if an LDAP attribute exists in available attributes
     *
     * @param string $ldap_attribute Attribute to check
     * @param array<string> $available_attributes List of available attributes
     * @return bool True if attribute is available
     */
    public function isAttributeAvailable(string $ldap_attribute, array $available_attributes): bool;
}