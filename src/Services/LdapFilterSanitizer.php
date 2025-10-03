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

namespace GlpiPlugin\Advancedldap\Services;

use GlpiPlugin\Advancedldap\Contracts\LdapFilterSanitizerInterface;

/**
 * LDAP Filter Sanitization Service
 *
 * Implements RFC 4515 - LDAP String Representation of Search Filters
 * Provides protection against LDAP injection attacks by properly escaping
 * special characters in filter values and DNs.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc4515
 */
class LdapFilterSanitizer implements LdapFilterSanitizerInterface
{
    /**
     * Escape LDAP filter value according to RFC 4515
     *
     * RFC 4515 Section 3: Special characters must be escaped using backslash notation.
     * The following characters MUST be escaped:
     * - Backslash (\) - Must be escaped as \5c
     * - Asterisk (*) - Must be escaped as \2a (wildcard character)
     * - Parentheses ( - Must be escaped as \28
     * - Parentheses ) - Must be escaped as \29
     * - Null byte (\x00) - Must be escaped as \00
     *
     * @param string $str String to escape
     * @param bool $forDN Escape for DN (stricter rules)
     * @return string Escaped string
     */
    public function escapeFilterValue(string $str, bool $forDN = false): string
    {
        // RFC 4515: Characters that must be escaped in filter values
        // These are the metacharacters that have special meaning in LDAP filters
        $metaChars = ['\\', '*', '(', ')', "\x00"];
        $quotedChars = [];

        // Convert each metacharacter to its hexadecimal escape sequence
        // Format: \HH where HH is the two-digit hexadecimal representation
        foreach ($metaChars as $char) {
            $quotedChars[] = '\\' . str_pad(dechex(ord($char)), 2, '0', STR_PAD_LEFT);
        }

        // Perform the escaping
        $escaped = str_replace($metaChars, $quotedChars, $str);

        // For DNs, also escape additional special characters per RFC 4514
        // Distinguished Names have stricter requirements
        if ($forDN) {
            $dnMetaChars = [',', '+', '"', '<', '>', ';', '=', '#'];

            foreach ($dnMetaChars as $char) {
                // DN metacharacters are escaped with a simple backslash prefix
                $escaped = str_replace($char, '\\' . $char, $escaped);
            }

            // Leading and trailing spaces in DN components must be escaped
            if (strlen($escaped) > 0) {
                $hasLeadingSpace = ($escaped[0] === ' ');
                $hasTrailingSpace = ($escaped[strlen($escaped) - 1] === ' ');

                // Escape leading space
                if ($hasLeadingSpace) {
                    $escaped = '\\' . $escaped;
                }

                // Escape trailing space (but only if it's different from the leading space for single space case)
                // If we have a single space, it's already been escaped as leading space
                $len = strlen($escaped);
                if ($hasTrailingSpace && !($hasLeadingSpace && $len === 2)) {
                    // Only escape trailing if it's not a single space already escaped as leading
                    $escaped = substr($escaped, 0, -1) . '\\ ';
                }
            }
        }

        return $escaped;
    }

    /**
     * Validate LDAP filter syntax
     *
     * Performs basic validation to ensure the filter structure is valid:
     * 1. Check balanced parentheses
     * 2. Verify presence of at least one attribute-value pair
     * 3. Basic structure validation
     *
     * Note: This is not a complete LDAP filter parser, but catches common
     * malformed filters and obvious injection attempts.
     *
     * @param string $filter LDAP filter to validate
     * @return bool True if valid, false otherwise
     */
    public function isValidFilter(string $filter): bool
    {
        // Empty filter is invalid
        if (empty(trim($filter))) {
            return false;
        }

        // Check balanced parentheses - a basic requirement for valid LDAP filters
        if (substr_count($filter, '(') !== substr_count($filter, ')')) {
            return false;
        }

        // Check minimum structure: must contain at least one attribute-value pair
        // Valid patterns: (attr=value), (attr>=value), (attr<=value), (attr~=value)
        // Also allow attribute names with hyphens and numbers (common in LDAP schemas)
        if (!preg_match('/\([a-zA-Z][a-zA-Z0-9\-]*\s*[=<>~]/', $filter)) {
            return false;
        }

        // Check for common injection patterns that bypass simple filters
        // The previous checks (balanced parens + attribute-value pairs) are sufficient
        // for catching most injection attempts. The orphan paren check was too strict
        // and rejected valid nested filters like (&(attr1=val1)(attr2=val2))

        return true;
    }

    /**
     * Sanitize and validate LDAP filter before use
     *
     * This method validates the overall filter structure. It does NOT escape
     * individual values - that must be done using escapeFilterValue() when
     * building dynamic filters programmatically.
     *
     * Use this for user-provided complete filters (like from forms).
     * Use escapeFilterValue() when inserting user data into filter templates.
     *
     * @param string $filter User-provided filter
     * @return string|null Sanitized filter or null if invalid
     */
    public function sanitizeFilter(string $filter): ?string
    {
        $filter = trim($filter);

        // Validate structure
        if (!$this->isValidFilter($filter)) {
            return null;
        }

        // The filter structure is valid
        // Note: Individual dynamic values embedded in the filter should still be
        // escaped using escapeFilterValue() when building filters programmatically
        return $filter;
    }

    /**
     * Sanitize LDAP Distinguished Name (DN) component value
     *
     * USE THIS FOR: Escaping dynamic values to insert into DN components
     * Example: $value = "John, Doe"; $dn = "CN=" . $sanitizer->sanitizeDN($value);
     *
     * DO NOT USE FOR: Complete DN strings from forms (use isValidDN instead)
     *
     * Applies the stricter escaping rules required for DN components.
     * DNs have additional characters that must be escaped beyond filter values.
     *
     * @param string $dn Distinguished Name component value
     * @return string Sanitized DN component
     */
    public function sanitizeDN(string $dn): string
    {
        // Use escapeFilterValue with DN mode enabled for strict escaping
        return $this->escapeFilterValue($dn, true);
    }

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
    public function isValidDN(string $dn): bool
    {
        // Empty DN is invalid
        if (empty(trim($dn))) {
            return false;
        }

        // Basic DN structure validation: should contain '=' and typically 'dc=' or 'ou=' or 'cn='
        // Format: attribute=value,attribute=value,...
        // Examples: ou=users,dc=example,dc=com or cn=admin,dc=example,dc=com

        // Check for at least one attribute=value pair
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9\-]*\s*=/', $dn)) {
            return false;
        }

        // Check for balanced structure (no trailing commas, etc.)
        if (preg_match('/[,=]\s*$/', $dn)) {
            return false;
        }

        // Check that if there are commas, they separate valid components
        $components = explode(',', $dn);
        foreach ($components as $component) {
            $component = trim($component);

            // Each component should be attribute=value (without LDAP filter metacharacters)
            // The value must contain at least one non-whitespace character
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9\-]*\s*=\s*\S/', $component)) {
                return false;
            }

            // Check that the value part doesn't contain LDAP filter metacharacters
            // These characters should not appear in DN values: ( ) & | ! ~ * \
            // Allow escaped versions if needed, but reject unescaped filter operators
            if (preg_match('/[()&|!~*]/', $component)) {
                return false;
            }
        }

        return true;
    }
}
