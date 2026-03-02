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

use GlpiPlugin\Advancedldap\AuthLdapSyncFilter;
use GlpiPlugin\Advancedldap\ComputerBuilderMapping;
use GlpiPlugin\Advancedldap\SyncFilter;

/**
 * Plugin install process
 */
function plugin_advancedldap_install(): bool
{
    $migration = new Migration(PLUGIN_ADVANCEDLDAP_VERSION);
    SyncFilter::install($migration);
    AuthLdapSyncFilter::install($migration);
    ComputerBuilderMapping::install($migration);

    return true;
}

/**
 * Plugin uninstall process
 */
function plugin_advancedldap_uninstall(): bool
{
    $migration = new Migration(PLUGIN_ADVANCEDLDAP_VERSION);
    ComputerBuilderMapping::uninstall($migration);
    AuthLdapSyncFilter::uninstall($migration);
    SyncFilter::uninstall($migration);

    return true;
}

/**
 * @return array<class-string, string>
 */
function plugin_advancedldap_getDropdown(): array
{
    return [SyncFilter::class => __s('Sync Filter', 'advancedldap')];
}

/**
 * @param string $itemtype
 * @param int $search_option_id
 * @param array<int, array<int, array<string, string>>> $data
 * @param int $id
 */
function plugin_advancedldap_giveItem($itemtype, $search_option_id, $data, $id): string
{
    /** @var array<int, array<string, string>> */
    $searchopt = Search::getOptions($itemtype);
    /** @var string */
    $table = $searchopt[$search_option_id]['table'];
    /** @var string */
    $field = $searchopt[$search_option_id]['field'];

    switch ($table . '.' . $field) {
        case SyncFilter::getTable() . '.connection_filter':
        case SyncFilter::getTable() . '.basedn':
            /** @var string */
            $value = $data[$id][0]['name'] ?? '';
            return "<code>" . htmlspecialchars($value) . "</code>";
    }

    return '';
}
