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
 * LDAP Filter Sanitization Interface
 *
 * Contract for LDAP injection prevention following RFC 4515
 * (LDAP String Representation of Search Filters)
 */
interface LdapFilterSanitizerInterface
{
    /**
     * Escape LDAP filter value according to RFC 4515
     *
     * Escapes special characters that could be used for LDAP injection:
     * - Backslash (\)
     * - Asterisk (*)
     * - Parentheses ( and )
     * - Null byte (\x00)
     *
     * @param string $str String to escape
     * @param bool $forDN Escape for DN (stricter rules including comma, plus, quotes, etc.)
     * @return string Escaped string safe for use in LDAP filters
     */
    public function escapeFilterValue(string $str, bool $forDN = false): string;

    /**
     * Validate LDAP filter syntax
     *
     * Performs basic validation to ensure the filter has valid structure:
     * - Balanced parentheses
     * - Contains at least one attribute-value pair
     * - Basic structure validation
     *
     * @param string $filter LDAP filter to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidFilter(string $filter): bool;

    /**
     * Sanitize and validate LDAP filter before use
     *
     * Validates the filter structure and returns it if valid.
     * Note: Individual dynamic values should still be escaped using escapeFilterValue()
     * when building filters programmatically.
     *
     * @param string $filter User-provided filter
     * @return string|null Sanitized filter or null if invalid
     */
    public function sanitizeFilter(string $filter): ?string;

    /**
     * Sanitize LDAP Distinguished Name (DN) component value
     *
     * USE THIS FOR: Escaping dynamic values to insert into DN components
     * Example: $value = "John, Doe"; $dn = "CN=" . $sanitizer->sanitizeDN($value);
     *
     * DO NOT USE FOR: Complete DN strings from forms (use validateDN instead)
     *
     * Applies stricter escaping rules for DN components, including:
     * - All filter metacharacters
     * - DN-specific characters (comma, plus, quotes, less-than, greater-than, semicolon, equals, hash)
     *
     * @param string $dn Distinguished Name component value to sanitize
     * @return string Sanitized DN component
     */
    public function sanitizeDN(string $dn): string;

    /**
     * Validate LDAP Distinguished Name (DN) structure
     *
     * USE THIS FOR: Validating complete DN strings from user input/forms
     * Example: ou=users,dc=example,dc=com
     *
     * Performs basic validation of DN structure without modifying it.
     * This is for validating complete DNs, not escaping dynamic values.
     *
     * @param string $dn Distinguished Name to validate
     * @return bool True if valid DN structure, false otherwise
     */
    public function isValidDN(string $dn): bool;
}

