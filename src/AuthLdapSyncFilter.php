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
use AuthLDAP;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QuerySubQuery;

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

    public function showSyncFiltersList(AuthLDAP $authldap): void
    {
        global $DB;

        $authldap_id = $authldap->getField('id');

        $syncfilter = new SyncFilter();
        $available_syncfilters = array_column(
            $syncfilter->find([
                'NOT' => [
                    'id' => new QuerySubQuery([
                        'SELECT' => 'syncfilter_id',
                        'FROM'   => self::getTable(),
                        'WHERE'  => ['authldap_id' => $authldap_id]
                    ])
                ]
            ], ['name']),
            'name',
            'id'
        );

        TemplateRenderer::getInstance()->display('@advancedldap/relation_add_form.html.twig', [
            'title'               => __('Syncfilters associated', 'advancedldap'),
            'parent_field_name'   => 'authldap_id',
            'parent_id'           => $authldap_id,
            'dropdown_field_name' => 'syncfilter_id',
            'available_items'     => $available_syncfilters,
            'empty_message'       => __('No sync filters available', 'advancedldap'),
            'empty_label'         => __('Select a filter'),
        ]);

        $entries = [];
        $iterator = $DB->request([
            'SELECT' => ['sf.*', 'rel.id as link_id'],
            'FROM' => SyncFilter::getTable() . ' AS sf',
            'INNER JOIN' => [
                self::getTable() . ' AS rel' => [
                    'ON' => [
                        'rel' => 'syncfilter_id',
                        'sf'  => 'id',
                    ],
                ],
            ],
            'WHERE' => ['rel.authldap_id' => $authldap_id],
            'ORDER' => 'sf.name',
        ]);

        $syncfilter = new SyncFilter();
        foreach ($iterator as $row) {
            /** @var array{id: int, name: string, basedn: string, connection_filter: string, itemtype: string, link_id: int} $row */

            $name = htmlescape(NOT_AVAILABLE);
            if ($syncfilter->getFromDB($row['id'])) {
                $name = $syncfilter->getLink();
            }

            $itemtype_name = $row['itemtype'];
            if (class_exists($row['itemtype'])) {
                $itemtype_name = $row['itemtype']::getTypeName(1);
            }

            $entries[] = [
                'id'                => $row['link_id'],
                'itemtype'          => self::class,
                'name'              => $name,
                'basedn'            => '<code>' . htmlspecialchars($row['basedn']) . '</code>',
                'connection_filter' => '<code>' . htmlspecialchars($row['connection_filter']) . '</code>',
                'itemtype_display'  => $itemtype_name,
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'is_tab' => true,
            'datatable_id' => 'authldap_syncfilters',
            'nofilter' => true,
            'columns' => [
                'name' => __('Name'),
                'basedn' => __('Base DN', 'advancedldap'),
                'connection_filter' => __('Connection filter', 'advancedldap'),
                'itemtype_display' => __('Asset type', 'advancedldap'),
            ],
            'formatters' => [
                'name' => 'raw_html',
                'basedn' => 'raw_html',
                'connection_filter' => 'raw_html',
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

        $field = null;
        if ($itemtype === 'AuthLDAP') {
            $field = 'authldap_id';
        } elseif ($itemtype === SyncFilter::class) {
            $field = 'syncfilter_id';
        }

        if ($field !== null) {
            $DB->delete(self::getTable(), [$field => $items_id]);
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
