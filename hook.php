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

use GlpiPlugin\AdvancedLdap\SyncFilter;

/**
 * Plugin install process
 */
function plugin_advancedldap_install(): bool
{
    global $DB;

    // Create syncfilters table
    if (!$DB->tableExists('glpi_plugin_advancedldap_syncfilters')) {
        $query = "CREATE TABLE `glpi_plugin_advancedldap_syncfilters` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `connection_filter` text,
            `base_dn` varchar(255) NOT NULL DEFAULT '',
            `asset_type` varchar(255) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `asset_type` (`asset_type`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";

        if (!$DB->doQuery($query)) {
            Toolbox::logDebug("Advanced LDAP - Error creating table glpi_plugin_advancedldap_syncfilters: " . $DB->error());
            return false;
        }
    }

    return true;
}

/**
 * Plugin uninstall process
 */
function plugin_advancedldap_uninstall(): bool
{
    global $DB;

    // Drop tables
    $tables = [
        'glpi_plugin_advancedldap_syncfilters',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery(sprintf('DROP TABLE `%s`', $table));
        }
    }

    return true;
}

/**
 * @return array<class-string, string>
 */
function plugin_advancedldap_getDropdown(): array
{
    return [SyncFilter::class => __s('Sync Filter', 'advancedldap')];
}
