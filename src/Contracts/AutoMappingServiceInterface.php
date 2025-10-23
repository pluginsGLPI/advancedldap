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
 * Interface for automatic field mapping service
 *
 * Provides automatic mapping suggestions between GLPI fields and LDAP attributes
 * based on RFC 4519 standards and asset-specific requirements.
 */
interface AutoMappingServiceInterface
{
    /**
     * Get default field mappings for a given itemtype
     *
     * Returns a mapping array of GLPI field => LDAP attribute
     * based on required fields and RFC 4519 standards.
     *
     * @param string $itemtype GLPI itemtype (Computer, Phone, Printer, NetworkEquipment, etc.)
     * @return array<string, string> Array of [glpi_field => ldap_attribute]
     */
    public function getDefaultMappings(string $itemtype): array;

    /**
     * Get required fields for a given itemtype
     *
     * @param string $itemtype GLPI itemtype
     * @return array<int, string> Array of required GLPI field names
     */
    public function getRequiredFieldsForItemtype(string $itemtype): array;

    /**
     * Suggest an LDAP attribute for a given GLPI field
     *
     * @param string $glpiField GLPI field name
     * @param array<string> $availableLdapAttributes Optional list of available LDAP attributes to check
     * @return string|null Suggested LDAP attribute or null if no suggestion available
     */
    public function suggestLdapAttribute(string $glpiField, array $availableLdapAttributes = []): ?string;
}
