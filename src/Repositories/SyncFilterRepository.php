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
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use GlpiPlugin\Advancedldap\Models\AuthLdapSyncFilter;

use function Safe\json_encode;

/**
 * Repository for SyncFilter data operations
 */
class SyncFilterRepository implements SyncFilterRepositoryInterface
{
    private DatabaseInterface $database;

    /**
     * @param DatabaseInterface $database
     */
    public function __construct(
        DatabaseInterface $database,
    ) {
        $this->database = $database;
    }

    /**
     * Get all active sync filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveSyncFilters(): array
    {
        $iterator = $this->database->request([
            'FROM'   => SyncFilter::$table,
            'WHERE'  => ['is_active' => 1],
            'ORDER'  => 'name',
        ]);

        if (!is_iterable($iterator)) {
            return [];
        }

        $filters = [];
        foreach ($iterator as $data) {
            $filters[] = $data;
        }

        return $filters;
    }

    /**
     * Get sync filters for a specific AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array<int, array<string, mixed>>
     */
    public function getSyncFiltersForAuthLdap(int $authldap_id): array
    {
        $sync_table = SyncFilter::$table;
        $relation_table = AuthLdapSyncFilter::$table;

        $iterator = $this->database->request([
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

        if (!is_iterable($iterator)) {
            return [];
        }

        $filters = [];
        foreach ($iterator as $data) {
            $filters[] = $data;
        }

        return $filters;
    }

    /**
     * Find sync filter by ID
     *
     * @param int $id Filter ID
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $iterator = $this->database->request([
            'FROM'  => SyncFilter::$table,
            'WHERE' => ['id' => $id],
            'LIMIT' => 1,
        ]);

        if (!is_iterable($iterator)) {
            return null;
        }

        foreach ($iterator as $data) {
            /** @var array<string, mixed> */
            return $data;
        }

        return null;
    }

    /**
     * Create a new sync filter
     *
     * @param array<string, mixed> $data Filter data
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
     * @param array<string, mixed> $data Filter data
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
     * @param array<string, mixed> $data Raw data
     * @return array<string, mixed> Prepared data
     */
    private function prepareData(array $data): array
    {
        if (isset($data['field_mappings']) && is_array($data['field_mappings'])) {
            $data['field_mappings'] = json_encode($data['field_mappings']);
        }

        /** @var array<string, mixed> */
        return $data;
    }

    /**
     * Get associated AuthLDAP IDs for a sync filter
     *
     * @param int $syncfilter_id Sync filter ID
     * @return array<int, int> Array of AuthLDAP IDs
     */
    public function getAssociatedAuthLdaps(int $syncfilter_id): array
    {
        $results = $this->database->request([
            'SELECT' => ['authldap_id'],
            'FROM'   => AuthLdapSyncFilter::getTable(),
            'WHERE'  => [
                'syncfilter_id' => $syncfilter_id,
                'is_active' => 1,
            ],
        ]);

        if (!is_iterable($results)) {
            return [];
        }

        $authldaps = [];
        foreach ($results as $data) {
            $authldaps[] = (int) $data['authldap_id'];
        }

        return $authldaps;
    }

    /**
     * Delete all AuthLDAP relations for a sync filter
     *
     * @param int $syncfilter_id Sync filter ID
     * @return bool Success status
     */
    public function deleteAuthLdapRelations(int $syncfilter_id): bool
    {
        return $this->database->delete(
            AuthLdapSyncFilter::getTable(),
            ['syncfilter_id' => $syncfilter_id],
        );
    }

    /**
     * Get all AuthLDAP relations for a sync filter
     *
     * @param int $syncfilter_id Sync filter ID
     * @return array<int, array<string, mixed>> Array of relations
     */
    public function getAuthLdapRelations(int $syncfilter_id): array
    {
        $iterator = $this->database->request([
            'SELECT' => ['authldap_id', 'is_active'],
            'FROM'   => AuthLdapSyncFilter::getTable(),
            'WHERE'  => ['syncfilter_id' => $syncfilter_id],
        ]);

        if (!is_iterable($iterator)) {
            return [];
        }

        $relations = [];
        foreach ($iterator as $data) {
            $relations[] = $data;
        }

        return $relations;
    }

    /**
     * Get all active AuthLDAP servers
     *
     * @return array<int, array{id: int, name: string}> Array of active servers
     */
    public function getActiveAuthLdapServers(): array
    {
        $iterator = $this->database->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_authldaps',
            'WHERE'  => ['is_active' => 1],
            'ORDER'  => 'name',
        ]);

        if (!is_iterable($iterator)) {
            return [];
        }

        $servers = [];
        foreach ($iterator as $data) {
            $servers[] = $data;
        }

        return $servers;
    }

    /**
     * Get the first active AuthLDAP ID
     *
     * @return int|null First active AuthLDAP ID or null if none found
     */
    public function getFirstActiveAuthLdapId(): ?int
    {
        $iterator = $this->database->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_authldaps',
            'WHERE'  => ['is_active' => 1],
            'LIMIT'  => 1,
        ]);

        if (!is_iterable($iterator)) {
            return null;
        }

        foreach ($iterator as $data) {
            return (int) $data['id'];
        }

        return null;
    }

    /**
     * Get sync filters for a specific AuthLDAP with full details
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array<int, array<string, mixed>> Array of sync filters with full details
     */
    public function getSyncFiltersForAuthLdapDetailed(int $authldap_id): array
    {
        $sync_table = SyncFilter::$table;
        $relation_table = AuthLdapSyncFilter::$table;

        $iterator = $this->database->request([
            'SELECT' => [
                'sf.id',
                'sf.name',
                'sf.ldap_filter',
                'sf.base_dn',
                'sf.asset_type',
                'sf.field_mappings',
                'sf.is_active',
                'sf.date_creation',
            ],
            'FROM'   => "$sync_table AS sf",
            'INNER JOIN' => [
                "$relation_table AS rel" => [
                    'ON' => [
                        'sf' => 'id',
                        'rel' => 'syncfilter_id',
                    ],
                ],
            ],
            'WHERE'  => [
                'rel.authldap_id' => $authldap_id,
            ],
            'ORDER'  => 'sf.name',
        ]);

        if (!is_iterable($iterator)) {
            return [];
        }

        $filters = [];
        foreach ($iterator as $data) {
            $filters[] = $data;
        }

        return $filters;
    }
}
