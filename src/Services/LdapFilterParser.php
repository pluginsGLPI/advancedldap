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

use GlpiPlugin\Advancedldap\Contracts\LdapFilterParserInterface;
use Toolbox;

/**
 * Service for parsing and analyzing LDAP filters
 *
 * Extracts attributes, validates syntax, and analyzes LDAP filter strings
 * according to RFC 4515 specifications.
 */
class LdapFilterParser implements LdapFilterParserInterface
{
    /**
     * Parse LDAP filter to extract available attributes
     *
     * @param string $ldap_filter LDAP filter string
     * @return array<string> List of unique LDAP attributes
     */
    public function parseFilterAttributes(string $ldap_filter): array
    {
        $attributes = [];

        // Pattern to match LDAP attribute patterns like (attribute=*), (attribute=value),
        // (attribute>=value), (attribute<=value), etc.
        if (preg_match_all('/\(([a-zA-Z][a-zA-Z0-9]*)\s*[=<>~]/', $ldap_filter, $matches)) {
            $attributes = array_unique($matches[1]);
            sort($attributes);
        }

        Toolbox::logDebug('LdapFilterParser: Extracted attributes from filter', [
            'filter' => $ldap_filter,
            'attributes' => $attributes,
        ]);

        return $attributes;
    }

    /**
     * Validate LDAP filter syntax
     *
     * Performs basic validation of LDAP filter structure:
     * - Balanced parentheses
     * - Valid operators (&, |, !)
     * - Proper attribute syntax
     *
     * @param string $ldap_filter LDAP filter string
     * @return bool True if the filter syntax appears valid
     */
    public function isValidFilter(string $ldap_filter): bool
    {
        // Check if filter is empty
        if (empty(trim($ldap_filter))) {
            Toolbox::logDebug('LdapFilterParser: Filter is empty');
            return false;
        }

        // Check for balanced parentheses
        $open_count = substr_count($ldap_filter, '(');
        $close_count = substr_count($ldap_filter, ')');
        if ($open_count !== $close_count) {
            Toolbox::logDebug('LdapFilterParser: Unbalanced parentheses', [
                'open' => $open_count,
                'close' => $close_count,
            ]);
            return false;
        }

        // Check for at least one attribute-value pair pattern
        if (!preg_match('/\([a-zA-Z][a-zA-Z0-9]*\s*[=<>~]/', $ldap_filter)) {
            Toolbox::logDebug('LdapFilterParser: No valid attribute-value pattern found');
            return false;
        }

        // Check for invalid characters (basic check)
        if (preg_match('/[^\w\s()\[\]&|!=<>~*\-:.,;@\\\]/', $ldap_filter)) {
            Toolbox::logDebug('LdapFilterParser: Invalid characters detected');
            return false;
        }

        Toolbox::logDebug('LdapFilterParser: Filter validation passed', [
            'filter' => $ldap_filter,
        ]);

        return true;
    }

    /**
     * Extract objectClass values from LDAP filter
     *
     * @param string $ldap_filter LDAP filter string
     * @return array<string> List of objectClass values
     */
    public function extractObjectClasses(string $ldap_filter): array
    {
        $object_classes = [];

        // Pattern to match (objectClass=value) or (objectClass=*)
        if (preg_match_all('/\(objectClass\s*=\s*([^)]+)\)/', $ldap_filter, $matches)) {
            // Filter out wildcards and get unique values
            $object_classes = array_filter(
                array_unique($matches[1]),
                fn($value) => $value !== '*' && !empty(trim($value))
            );
            $object_classes = array_values($object_classes); // Re-index array
        }

        Toolbox::logDebug('LdapFilterParser: Extracted objectClasses', [
            'filter' => $ldap_filter,
            'objectClasses' => $object_classes,
        ]);

        return $object_classes;
    }
}