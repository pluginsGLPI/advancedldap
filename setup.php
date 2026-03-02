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
use GlpiPlugin\Advancedldap\SyncFilter;
use Glpi\Plugin\Hooks;

use function Safe\define;

define('PLUGIN_ADVANCEDLDAP_VERSION', '0.0.1');
define('PLUGIN_ADVANCEDLDAP_DIR', __DIR__);

// Minimal GLPI version, inclusive
define("PLUGIN_ADVANCEDLDAP_MIN_GLPI_VERSION", "11.0.0");

// Maximum GLPI version, exclusive
define("PLUGIN_ADVANCEDLDAP_MAX_GLPI_VERSION", "11.0.99");

function plugin_init_advancedldap(): void
{
    /** @var array<string, array<string, string|array<int|string, string>>> $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['advancedldap'] = 'front/syncfilter.php';
    $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['advancedldap'] = [
        AuthLDAP::class => 'plugin_advancedldap_item_purge',
        SyncFilter::class => 'plugin_advancedldap_item_purge',
    ];

    /** @var string $request_uri */
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($request_uri, '/plugins/advancedldap/') || str_contains($request_uri, '/authldap.form.php')) {
        $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['advancedldap'] = [
            'js/field_mapping.js',
        ];
    }

    Plugin::registerClass(AuthLdapSyncFilter::class, [
        'addtabon' => ['AuthLDAP', SyncFilter::class],
    ]);
}

/**
 * Plugin declaration
 *
 * @return array<string, mixed>
 */
function plugin_version_advancedldap(): array
{
    return [
        'name'           => 'Advanced LDAP',
        'version'        => PLUGIN_ADVANCEDLDAP_VERSION,
        'author'         => '<a href="http://www.teclib.com">Teclib\'</a>',
        'license'        => 'GPL v3+',
        'homepage'       => 'https://www.teclib.com',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_ADVANCEDLDAP_MIN_GLPI_VERSION,
                'max' => PLUGIN_ADVANCEDLDAP_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install
 */
function plugin_advancedldap_check_prerequisites(): bool
{
    if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
        echo "Run composer install --no-dev in the plugin directory<br>";
        return false;
    }

    return true;
}

/**
 * Check configuration process
 * OPTIONAL
 *
 * @param bool $verbose Whether to display message on failure. Defaults to false.
 */
function plugin_advancedldap_check_config(bool $verbose = false): bool
{
    // Your configuration check
    return true;

    // Example:
    // if ($verbose) {
    //    echo __('Installed / not configured', 'advancedldap');
    // }
    // return false;
}

/**
 * Hook called when an item is purged (deleted permanently)
 * Clean all AuthLdapSyncFilter relations when an AuthLDAP or SyncFilter is purged
 *
 * @param CommonDBTM $item The item being purged
 */
function plugin_advancedldap_item_purge(CommonDBTM $item): void
{
    AuthLdapSyncFilter::cleanRelationsForItem($item->getType(), $item->getID());
}
