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
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\SyncFilter;
use GlpiPlugin\Advancedldap\AuthLdapSyncFilter;

/**
 * Repository for SyncFilter data operations
 */
class SyncFilterRepository implements SyncFilterRepositoryInterface
{
    private DatabaseInterface $database;
    private AuthLdapSyncFilterRepositoryInterface $relation_repository;

    /**
     * @param DatabaseInterface $database
     * @param AuthLdapSyncFilterRepositoryInterface $relation_repository
     */
    public function __construct(
        DatabaseInterface $database,
        AuthLdapSyncFilterRepositoryInterface $relation_repository,
    ) {
        $this->database = $database;
        $this->relation_repository = $relation_repository;
    }

    /**
     * Get all active sync filters
     *
     * @return array
     */
    public function getActiveSyncFilters(): array
    {
        $results = $this->database->request([
            'FROM'   => SyncFilter::$table,
            'WHERE'  => ['is_active' => 1],
            'ORDER'  => 'name',
        ]);

        return is_array($results) ? $results : [];
    }

    /**
     * Get sync filters for a specific AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array
     */
    public function getSyncFiltersForAuthLdap(int $authldap_id): array
    {
        $sync_table = SyncFilter::$table;
        $relation_table = AuthLdapSyncFilter::$table;

        $results = $this->database->request([
            'SELECT' => [
                $sync_table . '.*',
            ],
            'FROM'   => $sync_table,
            'INNER JOIN' => [
                $relation_table => [
                    'ON' => [
                        $relation_table => 'syncfilter_id',
                        $sync_table => 'id',
                    ],
                ],
            ],
            'WHERE'  => [
                $relation_table . '.authldap_id' => $authldap_id,
                $relation_table . '.is_active' => 1,
                $sync_table . '.is_active' => 1,
            ],
            'ORDER'  => $sync_table . '.name',
        ]);

        return is_array($results) ? $results : [];
    }

    /**
     * Find sync filter by ID
     *
     * @param int $id Filter ID
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $results = $this->database->request([
            'FROM'  => SyncFilter::$table,
            'WHERE' => ['id' => $id],
            'LIMIT' => 1,
        ]);

        if (!is_array($results) || count($results) === 0) {
            return null;
        }

        return $results[0];
    }

    /**
     * Create a new sync filter
     *
     * @param array $data Filter data
     * @return int|false Created filter ID or false on failure
     */
    public function create(array $data)
    {
        $prepared_data = $this->prepareData($data);
        $prepared_data['date_creation'] = date('Y-m-d H:i:s');

        return $this->database->insert(SyncFilter::$table, $prepared_data);
    }

    /**
     * Update a sync filter
     *
     * @param int $id Filter ID
     * @param array $data Filter data
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        $prepared_data = $this->prepareData($data);
        $prepared_data['date_mod'] = date('Y-m-d H:i:s');

        return $this->database->update(
            SyncFilter::$table,
            $prepared_data,
            ['id' => $id],
        );
    }

    /**
     * Delete a sync filter
     *
     * @param int $id Filter ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        return $this->database->delete(
            SyncFilter::$table,
            ['id' => $id],
        );
    }

    /**
     * Prepare data for database operations
     *
     * @param array $data Raw data
     * @return array Prepared data
     */
    private function prepareData(array $data): array
    {
        if (isset($data['field_mappings']) && is_array($data['field_mappings'])) {
            $data['field_mappings'] = json_encode($data['field_mappings']);
        }

        return $data;
    }
}
