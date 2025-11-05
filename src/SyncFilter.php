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

use CommonDropdown;
use AuthLDAP;
use CommonGLPI;
use Migration;
use DBConnection;
use Session;

class SyncFilter extends CommonDropdown
{
    public static $rightname = 'config';

    /** @var bool */
    public $can_be_translated = false;

    public $dohistory = true;

    public static function install(Migration $migration): void
    {
        global $DB;

        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        // Create syncfilters table
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage('Installing ' . $table);
            $query = "CREATE TABLE `{$table}` (
                `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT '',
                `connection_filter` text,
                `basedn` varchar(255) NOT NULL DEFAULT '',
                `itemtype` varchar(255) NOT NULL DEFAULT '',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `itemtype` (`itemtype`),
                KEY `date_creation` (`date_creation`),
                KEY `date_mod` (`date_mod`)
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

    public static function getTypeName($nb = 0)
    {
        return _sn('Advanced LDAP', 'Advanced LDAP', $nb, 'advancedldap');
    }

    public static function getIcon(): string
    {
        return 'ti ti-filter';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $item->can($item->getID(), \READ)) {
            $nb = 0;

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
     * @return array<array<string, mixed>>
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
                'name'  => 'basedn',
                'label' => __('Base DN', 'advancedldap'),
                'type'  => 'text',
            ],
            [
                'name'  => 'itemtype',
                'label' => __('Asset type', 'advancedldap'),
                'type'  => 'itemtypename',
                'itemtype_list' => 'inventory_types',
                'form_params' => [
                    'disabled' => !$this->isNewItem(),
                ],
            ],
        ];
    }

    public function post_getEmpty(): void
    {
        global $CFG_GLPI;

        if (!empty($CFG_GLPI['inventory_types']) && is_array($CFG_GLPI['inventory_types'])) {
            $this->fields['itemtype'] = $CFG_GLPI['inventory_types'][0];
        }
    }

    public function prepareInputForAdd($input)
    {
        $input = parent::prepareInputForAdd($input);

        if (empty($input['itemtype'])) {
            Session::addMessageAfterRedirect(
                __s('Asset type must be selected', 'advancedldap'),
                false,
                ERROR,
            );
            return false;
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['itemtype'])) {
            unset($input['itemtype']);
        }

        return parent::prepareInputForUpdate($input);
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
