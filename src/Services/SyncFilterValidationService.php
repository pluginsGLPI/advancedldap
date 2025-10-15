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
use GlpiPlugin\Advancedldap\Contracts\LdapFilterParserInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapAttributeMapperInterface;
use Session;

use function Safe\json_encode;

/**
 * Service for validating and preparing SyncFilter inputs
 * Extracted from SyncFilter to respect Single Responsibility Principle
 */
class SyncFilterValidationService
{
    private LdapFilterSanitizerInterface $sanitizer;
    private LdapFilterParserInterface $filter_parser;
    private LdapAttributeMapperInterface $attribute_mapper;

    public function __construct(
        LdapFilterSanitizerInterface $sanitizer,
        LdapFilterParserInterface $filter_parser,
        LdapAttributeMapperInterface $attribute_mapper
    ) {
        $this->sanitizer = $sanitizer;
        $this->filter_parser = $filter_parser;
        $this->attribute_mapper = $attribute_mapper;
    }

    /**
     * Validate LDAP inputs (Base DN and filter) before saving
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed>|false Validated input or false on validation error
     */
    public function validateLdapInputs(array $input)
    {
        // Validate Base DN if present
        if (isset($input['base_dn']) && !empty($input['base_dn']) && !$this->sanitizer->isValidDN($input['base_dn'])) {
            Session::addMessageAfterRedirect(
                __('Invalid LDAP Base DN syntax. Please check the format (example: ou=users,dc=example,dc=com)', 'advancedldap'),
                false,
                ERROR
            );
            return false;
        }

        // Validate LDAP filter if present
        if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
            $validatedFilter = $this->sanitizer->sanitizeFilter($input['ldap_filter']);
            if ($validatedFilter === null) {
                Session::addMessageAfterRedirect(
                    __('Invalid LDAP filter syntax. Please check your filter format (example: (objectClass=person))', 'advancedldap'),
                    false,
                    ERROR
                );
                return false;
            }
            // Replace with validated filter
            $input['ldap_filter'] = $validatedFilter;
        }

        return $input;
    }

    /**
     * Prepare field mappings from input data
     * Delegates to LdapFilterParser and LdapAttributeMapper services
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed> Prepared input with field_mappings
     */
    public function prepareMappingsInput(array $input): array
    {
        // Handle field_mappings array conversion
        if (isset($input['field_mappings']) && is_array($input['field_mappings'])) {
            $input['field_mappings'] = json_encode($input['field_mappings']);
        }

        // Create intelligent field mapping from asset_fields using LDAP filter analysis
        if (isset($input['asset_fields']) && is_array($input['asset_fields']) && !empty($input['asset_fields'])) {
            $field_mappings = [];

            // Parse LDAP filter to get available attributes
            $available_attributes = [];
            if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
                $available_attributes = $this->filter_parser->parseFilterAttributes($input['ldap_filter']);
            }

            // Map each selected asset field to corresponding LDAP attribute
            foreach ($input['asset_fields'] as $glpi_field) {
                if (!empty($glpi_field)) {
                    $ldap_attribute = $this->attribute_mapper->findMatchingAttribute($glpi_field, $available_attributes);
                    $field_mappings[$glpi_field] = $ldap_attribute;
                }
            }

            if ($field_mappings !== []) {
                $input['field_mappings'] = json_encode($field_mappings);
            }
        }

        // Backward compatibility: Handle single asset_field (legacy)
        elseif (isset($input['asset_field']) && !empty($input['asset_field']) && empty($input['field_mappings'])) {
            $ldap_attribute = $input['asset_field']; // Fallback to same name

            // If we have an LDAP filter, parse it to find the best matching attribute
            if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
                $available_attributes = $this->filter_parser->parseFilterAttributes($input['ldap_filter']);
                $ldap_attribute = $this->attribute_mapper->findMatchingAttribute($input['asset_field'], $available_attributes);
            }

            $field_mappings = [$input['asset_field'] => $ldap_attribute];
            $input['field_mappings'] = json_encode($field_mappings);
        }

        return $input;
    }

    /**
     * Validate all inputs for a SyncFilter
     * Combines LDAP validation and mappings preparation
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed>|false Prepared input or false on error
     */
    public function validateAndPrepare(array $input)
    {
        // Validate LDAP inputs first
        $input = $this->validateLdapInputs($input);
        if ($input === false) {
            return false;
        }

        // Prepare mappings
        return $this->prepareMappingsInput($input);
    }
}
