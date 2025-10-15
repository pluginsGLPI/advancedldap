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
 * Interface for SyncFilter form generation helpers
 *
 * Provides methods to build form components for SyncFilter configuration
 */
interface SyncFilterFormHelperInterface
{
    /**
     * Build asset dropdown options organized by type
     *
     * Groups assets into native and generic categories with separators.
     *
     * @param AssetFieldProviderInterface $asset_field_provider Asset field provider service
     * @return array<string, string> Dropdown options [itemtype => label]
     *
     * @example
     * Output: [
     *     'native_separator' => '--- Native Assets ---',
     *     'Computer' => 'Computer',
     *     'Monitor' => 'Monitor',
     *     'generic_separator' => '--- Generic Assets ---',
     *     'Asset_CustomDevice' => 'Custom Device'
     * ]
     */
    public function buildAssetDropdown(AssetFieldProviderInterface $asset_field_provider): array;

    /**
     * Get current filter configuration
     *
     * Retrieves existing configuration for a filter or returns default values
     * for new filters. Handles AuthLDAP association.
     *
     * @param int $filter_id SyncFilter ID (0 for new filter)
     * @param int|null $authldap_id Specific AuthLDAP ID to associate
     * @param array<string, mixed> $filter_data Filter data from database
     * @return array<string, mixed> Configuration array with keys:
     *     - authldap_id: int
     *     - filter_name: string
     *     - ldap_connection_filter: string
     *     - ldap_base_dn: string
     *     - asset_type: string
     *     - asset_field: string
     *     - is_active: int (0 or 1)
     */
    public function getCurrentConfiguration(int $filter_id, ?int $authldap_id, array $filter_data): array;

    /**
     * Check LDAP connection status for an AuthLDAP server
     *
     * @param int|null $authldap_id AuthLDAP server ID
     * @return array<string, mixed> Status information:
     *     - connected: bool
     *     - message: string
     *     - server_name: string|null
     */
    public function checkLdapConnectionStatus(?int $authldap_id): array;

    /**
     * Get all available AuthLDAP servers
     *
     * @return array<int, string> Associative array [authldap_id => server_name]
     */
    public function getAvailableAuthLdapServers(): array;

    /**
     * Get first active AuthLDAP ID
     *
     * @return int|null First active AuthLDAP ID or null if none exists
     */
    public function getFirstActiveAuthLdapId(): ?int;
}