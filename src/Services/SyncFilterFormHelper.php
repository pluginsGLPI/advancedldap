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

use GlpiPlugin\Advancedldap\Contracts\SyncFilterFormHelperInterface;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use Safe\Exceptions\JsonException;
use Toolbox;
use AuthLDAP;

use function Safe\json_decode;

/**
 * Service for building SyncFilter form components
 *
 * Handles form generation, configuration retrieval, and AuthLDAP server management
 * for the SyncFilter interface.
 */
class SyncFilterFormHelper implements SyncFilterFormHelperInterface
{
    private SyncFilterRepositoryInterface $repository;
    private LdapConnectionInterface $ldap_connection_service;

    public function __construct(
        SyncFilterRepositoryInterface $repository,
        LdapConnectionInterface $ldap_connection_service
    ) {
        $this->repository = $repository;
        $this->ldap_connection_service = $ldap_connection_service;
    }

    /**
     * Build asset dropdown options organized by type
     *
     * @param AssetFieldProviderInterface $asset_field_provider Asset field provider service
     * @return array<string, string> Dropdown options
     */
    public function buildAssetDropdown(AssetFieldProviderInterface $asset_field_provider): array
    {
        $available_assets = [];

        // Get all asset types
        $all_asset_types = $asset_field_provider instanceof AssetFieldService
            ? $asset_field_provider->getAllAssetTypes()
            : [];

        // Separate assets by type
        $native_assets = array_filter($all_asset_types, fn($info) => $info['type'] === 'native');
        $generic_assets = array_filter($all_asset_types, fn($info) => $info['type'] === 'generic');

        // Add native assets section
        if (!empty($native_assets)) {
            $available_assets['native_separator'] = '--- ' . __('Native Assets') . ' ---';
            foreach ($native_assets as $itemtype => $info) {
                $available_assets[$itemtype] = $info['name'];
            }
        }

        // Add generic assets section
        if (!empty($generic_assets)) {
            $available_assets['generic_separator'] = '--- ' . __('Generic Assets') . ' ---';
            foreach ($generic_assets as $itemtype => $info) {
                $available_assets[$itemtype] = $info['name'];
            }
        }

        return $available_assets;
    }

    /**
     * Get current filter configuration
     *
     * @param int $filter_id SyncFilter ID (0 for new)
     * @param int|null $authldap_id Specific AuthLDAP ID
     * @param array<string, mixed> $filter_data Filter data from database
     * @return array<string, mixed> Configuration array
     */
    public function getCurrentConfiguration(int $filter_id, ?int $authldap_id, array $filter_data): array
    {
        // Use provided authldap_id, or fall back to GET parameter, or find the first active one
        $default_authldap_id = $authldap_id ?? $_GET['authldap_id'] ?? '';
        if (empty($default_authldap_id)) {
            $default_authldap_id = $this->getFirstActiveAuthLdapId();
        }

        // Default configuration structure
        $config = [
            'authldap_id' => $default_authldap_id,
            'filter_name' => '',
            'ldap_connection_filter' => '',
            'ldap_base_dn' => '',
            'asset_type' => '',
            'asset_field' => '',
            'is_active' => 1,
        ];

        // Populate with existing filter data
        if ($filter_id > 0 && !empty($filter_data)) {
            $field_mappings = [];
            if (isset($filter_data['field_mappings']) && is_string($filter_data['field_mappings'])) {
                try {
                    $field_mappings = json_decode($filter_data['field_mappings'], true) ?? [];
                } catch (JsonException $e) {
                    Toolbox::logDebug("SyncFilterFormHelper: Failed to decode field_mappings for filter $filter_id: " . $e->getMessage());
                    $field_mappings = [];
                }
            }

            $asset_field = !empty($field_mappings) ? array_key_first($field_mappings) : '';

            $config = [
                'authldap_id' => $default_authldap_id,
                'filter_name' => $filter_data['name'] ?? '',
                'ldap_connection_filter' => $filter_data['ldap_filter'] ?? '',
                'ldap_base_dn' => $filter_data['base_dn'] ?? '',
                'asset_type' => $filter_data['asset_type'] ?? '',
                'asset_field' => $asset_field,
                'is_active' => $filter_data['is_active'] ?? 1,
            ];
        }

        return $config;
    }

    /**
     * Check LDAP connection status for an AuthLDAP server
     *
     * @param int|null $authldap_id AuthLDAP server ID
     * @return array<string, mixed> Status information
     */
    public function checkLdapConnectionStatus(?int $authldap_id): array
    {
        return $this->ldap_connection_service->checkConnection($authldap_id);
    }

    /**
     * Get all available AuthLDAP servers
     *
     * @return string[] Associative array [authldap_id => server_name]
     */
    public function getAvailableAuthLdapServers(): array
    {
        global $DB;

        $servers = [];
        $iterator = $DB->request([
            'FROM' => AuthLDAP::getTable(),
            'WHERE' => ['is_active' => 1],
            'ORDER' => ['name ASC'],
        ]);

        foreach ($iterator as $row) {
            $servers[(int) $row['id']] = $row['name'];
        }

        /** @var string[] */
        return $servers;
    }

    /**
     * Get first active AuthLDAP ID
     *
     * @return int|null First active AuthLDAP ID or null
     */
    public function getFirstActiveAuthLdapId(): ?int
    {
        $first_id = $this->repository->getFirstActiveAuthLdapId();

        return $first_id;
    }
}