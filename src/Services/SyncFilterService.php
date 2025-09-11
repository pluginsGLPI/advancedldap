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

use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface;

/**
 * Service for SyncFilter business logic
 */
class SyncFilterService
{
    private SyncFilterRepositoryInterface $syncFilterRepository;
    private AuthLdapSyncFilterRepositoryInterface $relationRepository;

    /**
     * @param SyncFilterRepositoryInterface $syncFilterRepository
     * @param AuthLdapSyncFilterRepositoryInterface $relationRepository
     */
    public function __construct(
        SyncFilterRepositoryInterface $syncFilterRepository,
        AuthLdapSyncFilterRepositoryInterface $relationRepository,
    ) {
        $this->syncFilterRepository = $syncFilterRepository;
        $this->relationRepository = $relationRepository;
    }

    /**
     * Get all available sync filters
     *
     * @return array
     */
    public function getAvailableSyncFilters(): array
    {
        return $this->syncFilterRepository->getActiveSyncFilters();
    }

    /**
     * Get sync filters configured for an AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array
     */
    public function getSyncFiltersForAuthLdap(int $authldap_id): array
    {
        return $this->syncFilterRepository->getSyncFiltersForAuthLdap($authldap_id);
    }

    /**
     * Create a new sync filter
     *
     * @param string $name Filter name
     * @param string $ldap_filter LDAP filter
     * @param string $base_dn Base DN
     * @param string $asset_type Asset type
     * @param array $field_mappings Field mappings
     * @param bool $is_active Active status
     * @return int|false Created filter ID or false on failure
     */
    public function createSyncFilter(
        string $name,
        string $ldap_filter,
        string $base_dn,
        string $asset_type,
        array $field_mappings = [],
        bool $is_active = true,
    ) {
        $data = [
            'name' => $name,
            'ldap_filter' => $ldap_filter,
            'base_dn' => $base_dn,
            'asset_type' => $asset_type,
            'field_mappings' => $field_mappings,
            'is_active' => $is_active ? 1 : 0,
        ];

        return $this->syncFilterRepository->create($data);
    }

    /**
     * Update an existing sync filter
     *
     * @param int $id Filter ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updateSyncFilter(int $id, array $data): bool
    {
        return $this->syncFilterRepository->update($id, $data);
    }

    /**
     * Delete a sync filter and its relations
     *
     * @param int $id Filter ID
     * @return bool Success status
     */
    public function deleteSyncFilter(int $id): bool
    {
        // First, get all AuthLDAPs using this filter
        $authldap_ids = $this->relationRepository->getAuthLdapsForSyncFilter($id, false);

        // Remove all relations
        foreach ($authldap_ids as $authldap_id) {
            $this->relationRepository->removeSyncFilterFromAuthLdap($authldap_id, $id);
        }

        // Then delete the filter itself
        return $this->syncFilterRepository->delete($id);
    }

    /**
     * Assign a sync filter to an AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @param int $syncfilter_id SyncFilter ID
     * @param bool $is_active Active status
     * @return int|false Relation ID or false on failure
     */
    public function assignSyncFilterToAuthLdap(int $authldap_id, int $syncfilter_id, bool $is_active = true)
    {
        // Check if relation already exists
        if ($this->relationRepository->isSyncFilterAssignedToAuthLdap($authldap_id, $syncfilter_id, false)) {
            // Update existing relation
            $success = $this->relationRepository->toggleSyncFilterForAuthLdap($authldap_id, $syncfilter_id, $is_active);
            return $success ? 1 : false; // Return 1 as pseudo-ID for existing relation
        }

        // Create new relation
        return $this->relationRepository->addSyncFilterToAuthLdap($authldap_id, $syncfilter_id, $is_active);
    }

    /**
     * Unassign a sync filter from an AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @param int $syncfilter_id SyncFilter ID
     * @return bool Success status
     */
    public function unassignSyncFilterFromAuthLdap(int $authldap_id, int $syncfilter_id): bool
    {
        return $this->relationRepository->removeSyncFilterFromAuthLdap($authldap_id, $syncfilter_id);
    }

    /**
     * Get field mappings for a sync filter
     *
     * @param int $syncfilter_id SyncFilter ID
     * @return array Field mappings
     */
    public function getFieldMappings(int $syncfilter_id): array
    {
        $filter = $this->syncFilterRepository->findById($syncfilter_id);

        if (!$filter || empty($filter['field_mappings'])) {
            return [];
        }

        $mappings = json_decode($filter['field_mappings'], true);
        return is_array($mappings) ? $mappings : [];
    }

    /**
     * Update field mappings for a sync filter
     *
     * @param int $syncfilter_id SyncFilter ID
     * @param array $mappings Field mappings
     * @return bool Success status
     */
    public function updateFieldMappings(int $syncfilter_id, array $mappings): bool
    {
        return $this->syncFilterRepository->update($syncfilter_id, [
            'field_mappings' => $mappings,
        ]);
    }
}
