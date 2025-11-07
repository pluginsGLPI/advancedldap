<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of advancedldap.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * AdvancedLDAP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdvancedLDAP. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2023 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap;

use CommonDBTM;
use Migration;
use DBConnection;
use Session;

class AuthLdapSyncFilter extends CommonDBTM
{
    public static $rightname = 'config';

    public static function install(Migration $migration): void
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        // Create authldap_syncfilters relation table
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage('Installing ' . $table);
            $query = "CREATE TABLE `{$table}` (
                `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `authldap_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                `syncfilter_id` int {$default_key_sign} NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`authldap_id`, `syncfilter_id`),
                KEY `authldap_id` (`authldap_id`),
                KEY `syncfilter_id` (`syncfilter_id`),
                KEY `date_creation` (`date_creation`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC";

            $DB->doQuery($query);
        }
    }

    public static function uninstall(Migration $migration): void
    {
        $table = self::getTable();
        $migration->displayMessage('Uninstalling ' . $table);
        $migration->dropTable($table);
    }

    public function prepareInputForAdd($input)
    {
        if ($this->alreadyExists($input)) {
            Session::addMessageAfterRedirect(
                __s('Relationship already exists', 'advancedldap'),
                false,
                ERROR,
            );
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function alreadyExists(array $input): bool
    {
        return countElementsInTable(self::getTable(), [
            'authldap_id' => $input['authldap_id'],
            'syncfilter_id' => $input['syncfilter_id'],
        ]) > 0;
    }

    public static function canCreate(): bool
    {
        return static::canUpdate();
    }

    public static function canPurge(): bool
    {
        return static::canUpdate();
    }
}
