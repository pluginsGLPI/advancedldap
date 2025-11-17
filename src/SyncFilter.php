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

use AuthLDAP;
use CommonDropdown;
use CommonGLPI;
use DBConnection;
use DisplayPreference;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QuerySubQuery;
use GlpiPlugin\Advancedldap\Service\FieldMappingService;
use Migration;
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
                `field_mappings` longtext,
                `is_active` tinyint NOT NULL DEFAULT '1',
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

        $migration->updateDisplayPrefs(
            [
                self::getType() => [3401, 3567, 3087],
            ],
        );
    }

    public static function uninstall(Migration $migration): void
    {
        $table = self::getTable();
        $migration->displayMessage('Uninstalling ' . $table);
        $migration->dropTable($table);

        $display_preferences = new DisplayPreference();
        $display_preferences->deleteByCriteria(['itemtype' => self::getType()]);
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

        if ($item instanceof self && $item->can($item->getID(), \READ)) {
            $tabs = [];

            // Configuration tab
            $tabs[1] = self::createTabEntry(
                __('Configuration', 'advancedldap'),
                0,
                $item::class,
                'ti ti-settings',
            );

            // Field Mapping tab
            $tabs[2] = self::createTabEntry(
                __('Field Mapping', 'advancedldap'),
                0,
                $item::class,
                'ti ti-arrows-exchange',
            );
            

            return $tabs;
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP) {
            $instance = new AuthLdapSyncFilter();
            $instance->showSyncFiltersList($item);
        }

        if ($item instanceof self) {
            switch ($tabnum) {
                case 1:
                    $item->showLdapConf();
                    break;
                case 2:
                    $item->showFieldMappingTab();
                    break;
            }
        }

        return true;
    }

    private function showLdapConf(): void
    {
        global $DB;

        $syncfilter_id = $this->getID();

        $authldap = new AuthLDAP();
        $available_authldaps = array_column(
            $authldap->find([
                'is_active' => 1,
                'NOT' => [
                    'id' => new QuerySubQuery([
                        'SELECT' => 'authldap_id',
                        'FROM'   => AuthLdapSyncFilter::getTable(),
                        'WHERE'  => ['syncfilter_id' => $syncfilter_id],
                    ]),
                ],
            ], ['name']),
            'name',
            'id',
        );

        TemplateRenderer::getInstance()->display('@advancedldap/relation_add_form.html.twig', [
            'title'               => __('LDAP Connections associated', 'advancedldap'),
            'parent_field_name'   => 'syncfilter_id',
            'parent_id'           => $syncfilter_id,
            'dropdown_field_name' => 'authldap_id',
            'available_items'     => $available_authldaps,
            'empty_message'       => __('No LDAP connections available', 'advancedldap'),
            'empty_label'         => __('Select an LDAP'),
        ]);

        $entries = [];
        $iterator = $DB->request([
            'SELECT' => ['al.*', 'rel.id as link_id'],
            'FROM' => 'glpi_authldaps AS al',
            'INNER JOIN' => [
                AuthLdapSyncFilter::getTable() . ' AS rel' => [
                    'ON' => [
                        'rel' => 'authldap_id',
                        'al' => 'id',
                    ],
                ],
            ],
            'WHERE' => ['rel.syncfilter_id' => $syncfilter_id],
            'ORDER' => 'al.name',
        ]);

        foreach ($iterator as $row) {
            /** @var array{id: int, name: string, host: string, port: int|string, basedn: string, link_id: int} $row */

            $authldap = new AuthLDAP();
            $name = htmlescape(NOT_AVAILABLE);
            if ($authldap->getFromDB($row['id'])) {
                $name = $authldap->getLink();
            }

            $entries[] = [
                'id'       => $row['link_id'],
                'itemtype' => AuthLdapSyncFilter::class,
                'name'     => $name,
                'host'     => $row['host'],
                'port'     => $row['port'],
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'is_tab' => true,
            'datatable_id' => 'syncfilter_ldap_connections',
            'nofilter' => true,
            'columns' => [
                'name' => __('Name'),
                'host' => __('Server'),
                'port' => __('Port'),
            ],
            'formatters' => [
                'name' => 'raw_html',
            ],
            'entries' => $entries,
            'total_number' => count($entries),
            'filtered_number' => count($entries),
            'showmassiveactions' => true,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                'container'     => 'massAuthLdapSyncFilter' . mt_rand(),
            ],
        ]);
    }

    /**
     * Display the Field Mapping tab content
     */
    private function showFieldMappingTab(): void
    {
        $service = new FieldMappingService();
        $itemtype = $this->fields['itemtype'] ?? null;

        // Get current mappings
        $current_mappings = $service->getMapping($this);

        // Get available fields for the itemtype (empty array if no itemtype)
        $available_fields = !empty($itemtype) ? $service->getAvailableFields($itemtype) : [];

        // Convert mappings to indexed array for display
        $indexed_mappings = $service->mappingsToIndexedArray($current_mappings);

        // Display the form using Twig template - the template handles the itemtype warning
        TemplateRenderer::getInstance()->display('@advancedldap/field_mapping.html.twig', [
            'syncfilter_id' => $this->getID(),
            'itemtype' => $itemtype,
            'current_mappings' => $indexed_mappings,
            'available_fields' => $available_fields,
            '_glpi_csrf_token' => Session::getNewCSRFToken(),
        ]);
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
                'form_params' => [
                    'required' => true,
                ],
            ],
            [
                'name'  => 'basedn',
                'label' => __('Base DN', 'advancedldap'),
                'type'  => 'text',
                'form_params' => [
                    'required' => true,
                ],
            ],
            [
                'name'  => 'itemtype',
                'label' => __('Asset type', 'advancedldap'),
                'type'  => 'itemtypename',
                'itemtype_list' => 'inventory_types',
                'form_params' => [
                    'disabled' => !$this->isNewItem(),
                    'display_emptychoice' => false,
                    'required' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string|int, array<string, mixed>>
     */
    public function rawSearchOptions(): array
    {
        /** @var array<string|int, array<string, mixed>> */
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '3401',
            'table'              => self::getTable(),
            'field'              => 'connection_filter',
            'name'               => __s('Connection filter', 'advancedldap'),
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '3567',
            'table'              => self::getTable(),
            'field'              => 'basedn',
            'name'               => __s('Base DN', 'advancedldap'),
            'searchtype'         => ['equals', 'notequals'],
            'datatype'           => 'string',
        ];

        $tab[] = [
            'id'                 => '3087',
            'table'              => self::getTable(),
            'field'              => 'itemtype',
            'name'               => __s('Asset type', 'advancedldap'),
            'datatype'           => 'itemtypename',
        ];


        return $tab;
    }

    public static function canCreate(): bool
    {
        return static::canUpdate();
    }

    public static function canPurge(): bool
    {
        return static::canUpdate();
    }

    /**
     * Prepare input data for update
     *
     * @param array $input Input data
     * @return array|false Modified input data or false if invalid
     */
    public function prepareInputForUpdate($input)
    {
        // Check if we're updating field mappings from array format
        if (isset($input['mappings'])) {
            $service = new FieldMappingService();

            // Clean and convert mappings to JSON
            $cleaned_mappings = $service->cleanMappings($input['mappings']);
            $input['field_mappings'] = json_encode($cleaned_mappings);

            // Remove the raw mappings array from input
            unset($input['mappings']);
        }

        // Check if we're receiving field_mappings as JSON string already
        // Validate it's valid JSON
        if (isset($input['field_mappings']) && is_string($input['field_mappings'])) {
            $decoded = json_decode($input['field_mappings'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Invalid JSON, set to empty object
                $input['field_mappings'] = '{}';
            }
            // If valid JSON, keep it as is
        }

        return parent::prepareInputForUpdate($input);
    }
}
