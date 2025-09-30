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

use GlpiPlugin\Advancedldap\Contracts\LdapAttributeMapperInterface;
use Toolbox;

/**
 * Service for mapping GLPI fields to LDAP attributes
 *
 * Provides RFC 4519 standard mappings between GLPI field names
 * and LDAP attribute names.
 */
class LdapAttributeMapper implements LdapAttributeMapperInterface
{
    /**
     * RFC 4519 standard mappings between GLPI fields and LDAP attributes
     *
     * @var array<string, string>
     */
    private array $standard_mappings = [
        'serial' => 'serialNumber',
        'name' => 'cn',
        'comment' => 'description',
        'location' => 'l',
        'phone' => 'telephoneNumber',
        'mail' => 'mail',
        'email' => 'mail',
        'emails' => 'mail',
        'firstname' => 'givenName',
        'realname' => 'sn',
        'mobile' => 'mobile',
        'title' => 'title',
        'uuid' => 'entryUUID',
        'dn' => 'distinguishedName',
        'address' => 'street',
        'street' => 'street',
        'city' => 'l',
        'postalcode' => 'postalCode',
        'country' => 'c',
        'fax' => 'facsimileTelephoneNumber',
    ];

    /**
     * Find matching LDAP attribute for a GLPI field
     *
     * @param string $glpi_field GLPI field name
     * @param array<string> $available_ldap_attributes Available LDAP attributes
     * @return string Best matching LDAP attribute
     */
    public function findMatchingAttribute(string $glpi_field, array $available_ldap_attributes): string
    {
        // Normalize field name to lowercase for comparison
        $normalized_field = strtolower($glpi_field);

        // Check if we have a standard mapping and the LDAP attribute exists
        if (isset($this->standard_mappings[$normalized_field])) {
            $mapped_attribute = $this->standard_mappings[$normalized_field];
            if (in_array($mapped_attribute, $available_ldap_attributes)) {
                return $mapped_attribute;
            }
        }

        // Fallback: if the exact GLPI field name exists as LDAP attribute, use it
        if (in_array($glpi_field, $available_ldap_attributes)) {
            return $glpi_field;
        }

        // Final fallback: use the GLPI field name (may not exist in LDAP)
        return $glpi_field;
    }

    /**
     * Get all standard RFC 4519 mappings
     *
     * @return array<string, string> Mapping array [glpi_field => ldap_attribute]
     */
    public function getStandardMappings(): array
    {
        return $this->standard_mappings;
    }

    /**
     * Get reverse mapping (LDAP attribute to GLPI field)
     *
     * @param string $ldap_attribute LDAP attribute name
     * @return string|null Corresponding GLPI field or null
     */
    public function getGlpiFieldForAttribute(string $ldap_attribute): ?string
    {
        $reverse_mapping = array_flip($this->standard_mappings);

        if (isset($reverse_mapping[$ldap_attribute])) {
            return $reverse_mapping[$ldap_attribute];
        }

        return null;
    }

    /**
     * Validate if an LDAP attribute exists in available attributes
     *
     * @param string $ldap_attribute Attribute to check
     * @param array<string> $available_attributes List of available attributes
     * @return bool True if attribute is available
     */
    public function isAttributeAvailable(string $ldap_attribute, array $available_attributes): bool
    {
        return in_array($ldap_attribute, $available_attributes);
    }
}