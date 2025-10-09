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
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Services;

use DBmysqlIterator;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;

/**
 * GLPI database wrapper
 */
class GlpiDatabaseService implements DatabaseInterface
{
    /**
     * Execute a database request
     *
     * @param array<string, mixed> $criteria Database query criteria
     * @return DBmysqlIterator Query result iterator
     */
    public function request(array $criteria)
    {
        global $DB;
        return $DB->request($criteria);
    }

    /**
     * Get table name for a specific itemtype
     *
     * @param string $itemtype The itemtype
     * @return string|null Table name or null if not found
     */
    public function getTableForItemType(string $itemtype): ?string
    {
        return getTableForItemType($itemtype) ?: null;
    }

    /**
     * Insert data into a table
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Data to insert
     * @return int|false Inserted ID or false on failure
     */
    public function insert(string $table, array $data): int|false
    {
        global $DB;
        $result = $DB->insert($table, $data);

        if ($result) {
            // DB->insert() returns true on success, get the last inserted ID
            $insertedId = $DB->insertId();
            return $insertedId > 0 ? (int) $insertedId : false;
        }

        return false;
    }

    /**
     * Update data in a table
     *
     * @param string $table Table name
     * @param array<string, mixed> $data Data to update
     * @param array<string, mixed> $where Where conditions
     * @return bool Success status
     */
    public function update(string $table, array $data, array $where): bool
    {
        global $DB;
        return $DB->update($table, $data, $where);
    }

    /**
     * Delete data from a table
     *
     * @param string $table Table name
     * @param array<string, mixed> $where Where conditions
     * @return bool Success status
     */
    public function delete(string $table, array $where): bool
    {
        global $DB;
        return $DB->delete($table, $where);
    }
}
