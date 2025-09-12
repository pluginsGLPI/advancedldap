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

namespace GlpiPlugin\Advancedldap\Repositories;

use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Models\AuthLdapSyncFilter;

/**
 * Repository for AuthLdap-SyncFilter relation operations
 */
class AuthLdapSyncFilterRepository implements AuthLdapSyncFilterRepositoryInterface
{
    private DatabaseInterface $database;

    /**
     * @param DatabaseInterface $database
     */
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * Add a sync filter to an AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @param int $syncfilter_id SyncFilter ID
     * @param bool $is_active Active status
     * @return int|false ID of the created relation or false on failure
     */
    public function addSyncFilterToAuthLdap(int $authldap_id, int $syncfilter_id, bool $is_active = true)
    {
        return $this->database->insert(AuthLdapSyncFilter::$table, [
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
            'is_active' => $is_active ? 1 : 0,
            'date_creation' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove a sync filter from an AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @param int $syncfilter_id SyncFilter ID
     * @return bool Success status
     */
    public function removeSyncFilterFromAuthLdap(int $authldap_id, int $syncfilter_id): bool
    {
        return $this->database->delete(
            AuthLdapSyncFilter::$table,
            [
                'authldap_id' => $authldap_id,
                'syncfilter_id' => $syncfilter_id,
            ],
        );
    }

    /**
     * Toggle active status of a relation
     *
     * @param int $authldap_id AuthLDAP ID
     * @param int $syncfilter_id SyncFilter ID
     * @param bool $is_active New active status
     * @return bool Success status
     */
    public function toggleSyncFilterForAuthLdap(int $authldap_id, int $syncfilter_id, bool $is_active): bool
    {
        return $this->database->update(
            AuthLdapSyncFilter::$table,
            ['is_active' => $is_active ? 1 : 0],
            [
                'authldap_id' => $authldap_id,
                'syncfilter_id' => $syncfilter_id,
            ],
        );
    }

    /**
     * Get all AuthLDAP IDs that use a specific sync filter
     *
     * @param int $syncfilter_id SyncFilter ID
     * @param bool $active_only Only active relations
     * @return array
     */
    public function getAuthLdapsForSyncFilter(int $syncfilter_id, bool $active_only = true): array
    {
        $where = ['syncfilter_id' => $syncfilter_id];
        if ($active_only) {
            $where['is_active'] = 1;
        }

        $results = $this->database->request([
            'SELECT' => ['authldap_id'],
            'FROM'   => AuthLdapSyncFilter::$table,
            'WHERE'  => $where,
        ]);

        if (!is_array($results)) {
            return [];
        }

        return array_column($results, 'authldap_id');
    }

    /**
     * Check if a sync filter is assigned to an AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @param int $syncfilter_id SyncFilter ID
     * @param bool $active_only Check only active relations
     * @return bool
     */
    public function isSyncFilterAssignedToAuthLdap(int $authldap_id, int $syncfilter_id, bool $active_only = true): bool
    {
        $where = [
            'authldap_id' => $authldap_id,
            'syncfilter_id' => $syncfilter_id,
        ];

        if ($active_only) {
            $where['is_active'] = 1;
        }

        $results = $this->database->request([
            'FROM'  => AuthLdapSyncFilter::$table,
            'WHERE' => $where,
            'LIMIT' => 1,
        ]);

        if (!is_array($results)) {
            return false;
        }

        return count($results) > 0;
    }
}
