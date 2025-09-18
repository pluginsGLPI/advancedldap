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
            `ldap_filter` text,
            `base_dn` varchar(255) NOT NULL DEFAULT '',
            `asset_type` varchar(255) NOT NULL DEFAULT '',
            `field_mappings` longtext,
            `is_active` tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`),
            KEY `asset_type` (`asset_type`),
            KEY `date_creation` (`date_creation`),
            KEY `date_mod` (`date_mod`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";

        if (!$DB->doQuery($query)) {
            Toolbox::logDebug("Advanced LDAP - Error creating table glpi_plugin_advancedldap_syncfilters: " . $DB->error());
            return false;
        }
    }

    // Create authldap_syncfilters relation table
    if (!$DB->tableExists('glpi_plugin_advancedldap_authldap_syncfilters')) {
        $query = "CREATE TABLE `glpi_plugin_advancedldap_authldap_syncfilters` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `authldap_id` int unsigned NOT NULL DEFAULT '0',
            `syncfilter_id` int unsigned NOT NULL DEFAULT '0',
            `is_active` tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`authldap_id`, `syncfilter_id`),
            KEY `authldap_id` (`authldap_id`),
            KEY `syncfilter_id` (`syncfilter_id`),
            KEY `is_active` (`is_active`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";

        if (!$DB->doQuery($query)) {
            Toolbox::logDebug("Advanced LDAP - Error creating table glpi_plugin_advancedldap_authldap_syncfilters: " . $DB->error());
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
        'glpi_plugin_advancedldap_authldap_syncfilters',
        'glpi_plugin_advancedldap_syncfilters',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->query("DROP TABLE `$table`");
        }
    }

    return true;
}


/**
 * Hook to add massive actions for plugin items
 *
 * @param string $type The itemtype for which to return massive actions
 * @return array Array of massive actions
 */
function plugin_advancedldap_MassiveActions($type): array
{
    $actions = [];

    switch ($type) {
        case 'PluginAdvancedldapSyncFilter':
        case 'GlpiPlugin\\Advancedldap\\Models\\SyncFilter':
            $actions['PluginAdvancedldapSyncFilter' . MassiveAction::CLASS_ACTION_SEPARATOR . 'duplicate']
                = _x('button', 'Duplicate');
            break;
    }

    return $actions;
}
