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

namespace GlpiPlugin\AdvancedLdap;

use CommonDropdown;
use AuthLDAP;
use CommonGLPI;
use Session;

class SyncFilter extends CommonDropdown
{
    public static $rightname = 'config';

    public $dohistory = true;

    public static function getTypeName($nb = 0)
    {
        return _sn('Advanced LDAP', 'Advanced LDAP', $nb, 'advancedldap');
    }

    public static function getIcon(): string
    {
        return 'ti ti-filter';
    }

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_advancedldap_syncfilters';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $item->can($item->getID(), \READ)) {
            $nb = 0;

            //only for admin users ?
            if (Session::getCurrentInterface() !== 'helpdesk') {
                //to set later when filters can be created
                $nb = 0;
            }

            return self::createTabEntry(
                __('Advanced sync', 'advancedldap'),
                $nb,
                $item::class,
                static::getIcon(),
            );
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item instanceof AuthLDAP) {
            $instance = new self();
            $instance->showSyncFiltersList($item);
        }

        return true;
    }

    public function showSyncFiltersList(AuthLDAP $item): void
    {
        //to set later when filters can be created : here will be displayed actions like test / sync / etc...
        echo "<div class='center'>";
        echo "<h2>Hello World</h2>";
        echo "<p>Advanced LDAP Sync Configuration</p>";
        echo "<p>LDAP Server ID: " . $item->getID() . "</p>";
        echo "</div>";
    }

    /**
     * @return array<array<string, string>>
     */
    public function getAdditionalFields(): array
    {
        return [
            [
                'name'  => 'connection_filter',
                'label' => __('Connection filter', 'advancedldap'),
                'type'  => 'text',
            ],
            [
                'name'  => 'base_dn',
                'label' => __('Base DN', 'advancedldap'),
                'type'  => 'text',
            ],
            [
                'name'  => 'asset_type',
                'label' => __('Asset type', 'advancedldap'),
                'type'  => 'text', //select later
            ],
        ];
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
