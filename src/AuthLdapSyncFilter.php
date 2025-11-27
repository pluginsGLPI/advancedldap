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

use RuntimeException;
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

            $authldap_fk = getForeignKeyFieldForItemType(AuthLDAP::class);
            $syncfilter_fk = getForeignKeyFieldForItemType(SyncFilter::class);

            $query = "CREATE TABLE `{$table}` (
                `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `{$authldap_fk}` int {$default_key_sign} NOT NULL DEFAULT '0',
                `{$syncfilter_fk}` int {$default_key_sign} NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`{$authldap_fk}`, `{$syncfilter_fk}`),
                KEY `{$authldap_fk}` (`{$authldap_fk}`),
                KEY `{$syncfilter_fk}` (`{$syncfilter_fk}`),
                KEY `date_creation` (`date_creation`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC";

            $DB->doQuery($query);
        }
    }

    public function showRelationsForItem(CommonGLPI $item): void
    {
        global $DB;

        // Get configuration for this item type
        $config = $this->getRelationConfigForItem($item);
        if ($config === null) {
            return;
        }

        if (!($item instanceof CommonDBTM)) {
            return;
        }

        $parent_id = $item->getID();
        $itemclass = $item::class;

        // get available items id=>name for select
        $child_class = $config['child_class'];
        $child_obj = match ($child_class) {
            SyncFilter::class => new SyncFilter(),
            AuthLDAP::class => new AuthLDAP(),
            default => throw new RuntimeException('Invalid child class'),
        };
        /** @var array<array<string, mixed>> $found_items */
        $found_items = $child_obj->find(
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
                ],
            ),
            ['name'],
        );
        $available_items = array_column($found_items, 'name', 'id');

        // Add form configuration
        $form_config = [
            'title'               => $config['title'],
            'parent_field_name'   => $config['parent_field_name'],
            'parent_id'           => $parent_id,
            'dropdown_field_name' => $config['child_field_name'],
            'available_items'     => $available_items,
            'display_emptychoice' => $config['display_emptychoice'],
            'form_url'            => self::getFormURL(),
        ];

        // Query existing relations
        $child_alias = $config['child_alias'];
        $child_table = $config['child_table'];
        $child_field_name = $config['child_field_name'];
        $parent_field_name = $config['parent_field_name'];

        $iterator = $DB->request([
            'SELECT' => [$child_alias . '.*', 'rel.id as link_id'],
            'FROM'   => $child_table . ' AS ' . $child_alias,
            'INNER JOIN' => [
                self::getTable() . ' AS rel' => [
                    'ON' => [
                        'rel' => $child_field_name,
                        $child_alias => 'id',
                    ],
                ],
            ],
            'WHERE' => ['rel.' . $parent_field_name => $parent_id],
            'ORDER' => $child_alias . '.name',
        ]);

        // Build datatable entries
        $entries = [];
        foreach ($iterator as $data) {
            /** @var array{id: int, link_id: int} $data */
            // Get name with link (common to both cases)
            $child_obj = match ($child_class) {
                SyncFilter::class => new SyncFilter(),
                AuthLDAP::class => new AuthLDAP(),
                default => throw new RuntimeException('Invalid child class'),
            };
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

    /**
     * @return array{
     *     parent_field_name: string,
     *     child_field_name: string,
     *     child_class: class-string<CommonDBTM>,
     *     child_table: string,
     *     child_alias: string,
     *     child_criteria: array<string, mixed>,
     *     title: string,
     *     datatable_id: string,
     *     columns: array<string, string>,
     *     formatters: array<string, string>,
     *     display_emptychoice: bool
     * }|null
     */
    private function getRelationConfigForItem(CommonGLPI $item): ?array
    {
        switch ($item::class) {
            case AuthLDAP::class:
                /** @var class-string<SyncFilter> $childClass */
                $childClass       = SyncFilter::class;
                $title            = __('Syncfilters associated', 'advancedldap');
                $datatableId      = 'authldap_syncfilters';
                $childAlias       = 'sf';
                /** @var array<string, mixed> $childCriteria */
                $childCriteria    = [];
                $columns = [
                    'name'              => __('Name'),
                    'basedn'            => __('Base DN', 'advancedldap'),
                    'connection_filter' => __('Connection filter', 'advancedldap'),
                    'itemtype_display'  => __('Asset type', 'advancedldap'),
                ];
                $formatters = [
                    'name'              => 'raw_html',
                    'basedn'            => 'raw_html',
                    'connection_filter' => 'raw_html',
                ];
                break;

            case SyncFilter::class:
                /** @var class-string<AuthLDAP> $childClass */
                $childClass       = AuthLDAP::class;
                $title            = __('LDAP Connections associated', 'advancedldap');
                $datatableId      = 'syncfilter_ldap_connections';
                $childAlias       = 'al';
                /** @var array<string, mixed> $childCriteria */
                $childCriteria    = ['is_active' => 1];
                $columns = [
                    'name' => __('Name'),
                    'host' => __('Server'),
                    'port' => __('Port'),
                ];
                $formatters = [
                    'name' => 'raw_html',
                ];
                break;

            default:
                return null;
        }

        return [
            'parent_field_name'   => getForeignKeyFieldForItemType($item::class),
            'child_field_name'    => getForeignKeyFieldForItemType($childClass),
            'child_class'         => $childClass,
            'child_table'         => $childClass::getTable(),
            'child_alias'         => $childAlias,
            'child_criteria'      => $childCriteria,
            'title'               => $title,
            'datatable_id'        => $datatableId,
            'columns'             => $columns,
            'formatters'          => $formatters,
            'display_emptychoice' => true,
        ];
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
                __('AuthLDAP', 'advancedldap'),
                0,
                $item::class,
                AuthLDAP::getIcon(),
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
        if (!$this->validateRelationInput($input)) {
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        if (!$this->validateRelationInput($input)) {
            return false;
        }

        return parent::prepareInputForUpdate($input);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function validateRelationInput(array $input): bool
    {
        $authldap_fk = getForeignKeyFieldForItemType(AuthLDAP::class);
        $syncfilter_fk = getForeignKeyFieldForItemType(SyncFilter::class);

        $authldap_value = $input[$authldap_fk] ?? null;
        $authldaps_id = is_numeric($authldap_value) ? (int) $authldap_value : 0;

        $syncfilter_value = $input[$syncfilter_fk] ?? null;
        $syncfilters_id = is_numeric($syncfilter_value) ? (int) $syncfilter_value : 0;

        if ($authldaps_id <= 0 || $syncfilters_id <= 0) {
            Session::addMessageAfterRedirect(
                __s('Please select a valid item', 'advancedldap'),
                false,
                ERROR,
            );
            return false;
        }

        if ($this->alreadyExists($input)) {
            Session::addMessageAfterRedirect(
                __s('Relationship already exists', 'advancedldap'),
                false,
                ERROR,
            );
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function alreadyExists(array $input): bool
    {
        $authldap_fk = getForeignKeyFieldForItemType(AuthLDAP::class);
        $syncfilter_fk = getForeignKeyFieldForItemType(SyncFilter::class);

        return countElementsInTable(self::getTable(), [
            $authldap_fk => $input[$authldap_fk] ?? null,
            $syncfilter_fk => $input[$syncfilter_fk] ?? null,
        ]) > 0;
    }

    public static function cleanRelationsForItem(string $itemtype, int $items_id): void
    {
        global $DB;

        if ($itemtype === AuthLDAP::class || $itemtype === SyncFilter::class) {
            /** @var class-string<CommonDBTM> $itemtype */
            $field_name = getForeignKeyFieldForItemType($itemtype);
            if ($field_name) {
                $DB->delete(self::getTable(), [$field_name => $items_id]);
            }
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
