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
use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QuerySubQuery;
use Migration;
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

    public function showRelationsForItem(CommonGLPI $item): void
    {
        global $DB;

        // Configuration per item type
        $configs = [
            AuthLDAP::class => [
                'parent_field_name' => 'authldap_id',
                'child_field_name'  => 'syncfilter_id',
                'child_class'       => SyncFilter::class,
                'child_table'       => SyncFilter::getTable(),
                'child_alias'       => 'sf',
                'child_criteria'    => [],
                'title'             => __('Syncfilters associated', 'advancedldap'),
                'empty_message'     => __('No sync filters available (only active and unlinked filters are displayed here).', 'advancedldap'),
                'empty_label'       => __('Select a filter'),
                'datatable_id'      => 'authldap_syncfilters',
                'columns'           => [
                    'name'              => __('Name'),
                    'basedn'            => __('Base DN', 'advancedldap'),
                    'connection_filter' => __('Connection filter', 'advancedldap'),
                    'itemtype_display'  => __('Asset type', 'advancedldap'),
                ],
                'formatters'        => [
                    'name'              => 'raw_html',
                    'basedn'            => 'raw_html',
                    'connection_filter' => 'raw_html',
                ],
            ],
            SyncFilter::class => [
                'parent_field_name' => 'syncfilter_id',
                'child_field_name'  => 'authldap_id',
                'child_class'       => AuthLDAP::class,
                'child_table'       => 'glpi_authldaps',
                'child_alias'       => 'al',
                'child_criteria'    => ['is_active' => 1],
                'title'             => __('LDAP Connections associated', 'advancedldap'),
                'empty_message'     => __('No LDAP connections available (only active and unlinked LDAP directories are displayed here).', 'advancedldap'),
                'empty_label'       => __('Select an LDAP'),
                'datatable_id'      => 'syncfilter_ldap_connections',
                'columns'           => [
                    'name' => __('Name'),
                    'host' => __('Server'),
                    'port' => __('Port'),
                ],
                'formatters'        => [
                    'name' => 'raw_html',
                ],
            ],
        ];

        // Select config based on item type
        $itemclass = $item::class;
        if (!isset($configs[$itemclass])) {
            return;
        }

        $config = $configs[$itemclass];

        if (!($item instanceof CommonDBTM)) {
            return;
        }

        $parent_id = $item->getID();

        // get available items id=>name for select
        $child_obj = new $config['child_class']();
        $available_items = array_column(
            $child_obj->find(
                array_merge(
                    $config['child_criteria'],
                    [
                        'NOT' => [
                            'id' => new QuerySubQuery([
                                'SELECT' => $config['child_field_name'],
                                'FROM'   => self::getTable(),
                                'WHERE'  => [$config['parent_field_name'] => $parent_id],
                            ]),
                        ],
                    ]
                ),
                ['name']
            ),
            'name',
            'id'
        );

        // Add form configuration
        $form_config = [
            'title'               => $config['title'],
            'parent_field_name'   => $config['parent_field_name'],
            'parent_id'           => $parent_id,
            'dropdown_field_name' => $config['child_field_name'],
            'available_items'     => $available_items,
            'empty_message'       => $config['empty_message'],
            'empty_label'         => $config['empty_label'],
        ];

        // Query existing relations
        $iterator = $DB->request([
            'SELECT' => [$config['child_alias'] . '.*', 'rel.id as link_id'],
            'FROM'   => $config['child_table'] . ' AS ' . $config['child_alias'],
            'INNER JOIN' => [
                self::getTable() . ' AS rel' => [
                    'ON' => [
                        'rel' => $config['child_field_name'],
                        $config['child_alias'] => 'id',
                    ],
                ],
            ],
            'WHERE' => ['rel.' . $config['parent_field_name'] => $parent_id],
            'ORDER' => $config['child_alias'] . '.name',
        ]);

        // Build datatable entries
        $entries = [];
        foreach ($iterator as $data) {
            /** @var array{id: int, link_id: int} $data */
            // Get name with link (common to both cases)
            $child_obj = new $config['child_class']();
            $name = htmlescape(NOT_AVAILABLE);
            if ($child_obj->getFromDB($data['id'])) {
                $name = $child_obj->getLink();
            }

            // Build base entry
            $entry = [
                'id'       => $data['link_id'],
                'itemtype' => self::class,
                'name'     => $name,
            ];

            // Add specific fields based on parent item type
            if ($itemclass === AuthLDAP::class) {
                /** @var array{id: int, name: string, basedn: string, connection_filter: string, itemtype: string, link_id: int} $data */
                $itemtype_name = $data['itemtype'];
                if (class_exists($data['itemtype'])) {
                    $itemtype_name = $data['itemtype']::getTypeName(1);
                }

                $entry['basedn']            = '<code>' . htmlspecialchars($data['basedn']) . '</code>';
                $entry['connection_filter'] = '<code>' . htmlspecialchars($data['connection_filter']) . '</code>';
                $entry['itemtype_display']  = $itemtype_name;
            } elseif ($itemclass === SyncFilter::class) {
                /** @var array{id: int, name: string, host: string, port: int|string, basedn: string, link_id: int} $data */
                $entry['host'] = $data['host'];
                $entry['port'] = $data['port'];
            }

            $entries[] = $entry;
        }

        // Datatable configuration
        $datatable_config = [
            'is_tab'             => true,
            'datatable_id'       => $config['datatable_id'],
            'nofilter'           => true,
            'columns'            => $config['columns'],
            'formatters'         => $config['formatters'],
            'entries'            => $entries,
            'total_number'       => count($entries),
            'filtered_number'    => count($entries),
            'showmassiveactions' => true,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                'container'     => 'massAuthLdapSyncFilter' . mt_rand(),
            ],
        ];

        TemplateRenderer::getInstance()->display('@advancedldap/relation_add_form.html.twig', $form_config);
        TemplateRenderer::getInstance()->display('components/datatable.html.twig', $datatable_config);
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP || $item instanceof SyncFilter) {
            $instance = new self();
            $instance->showRelationsForItem($item);
        }

        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $item->can($item->getID(), \READ)) {
            return self::createTabEntry(
                __('Advanced sync', 'advancedldap'),
                0,
                $item::class,
                SyncFilter::getIcon(),
            );
        }

        if ($item instanceof SyncFilter && $item->can($item->getID(), \READ)) {
            return self::createTabEntry(
                __('Configuration', 'advancedldap'),
                0,
                $item::class,
                'ti ti-settings',
            );
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    public function getForbiddenStandardMassiveAction()
    {
        /** @var array<int, string> $forbidden */
        $forbidden   = parent::getForbiddenStandardMassiveAction();
        $forbidden[] = 'update';
        $forbidden[] = 'clone';
        return $forbidden;
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

    public static function cleanRelationsForItem(string $itemtype, int $items_id): void
    {
        global $DB;

        $map = [
            AuthLDAP::class => 'authldap_id',
            SyncFilter::class => 'syncfilter_id',
        ];

        if (isset($map[$itemtype])) {
            $DB->delete(self::getTable(), [$map[$itemtype] => $items_id]);
        }
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
