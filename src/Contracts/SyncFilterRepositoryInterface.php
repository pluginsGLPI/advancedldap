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
 * Interface for SyncFilter repository operations
 */
interface SyncFilterRepositoryInterface
{
    /**
     * Get all active sync filters
     *
     * @return array
     */
    public function getActiveSyncFilters(): array;

    /**
     * Get sync filters for a specific AuthLDAP
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array
     */
    public function getSyncFiltersForAuthLdap(int $authldap_id): array;

    /**
     * Find sync filter by ID
     *
     * @param int $id Filter ID
     * @return array|null
     */
    public function findById(int $id): ?array;

    /**
     * Create a new sync filter
     *
     * @param array $data Filter data
     * @return int|false Created filter ID or false on failure
     */
    public function create(array $data);

    /**
     * Update a sync filter
     *
     * @param int $id Filter ID
     * @param array $data Filter data
     * @return bool Success status
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a sync filter
     *
     * @param int $id Filter ID
     * @return bool Success status
     */
    public function delete(int $id): bool;
}
