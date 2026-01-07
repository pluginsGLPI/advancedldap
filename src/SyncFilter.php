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
use CommonGLPI;
use DBConnection;
use DisplayPreference;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Advancedldap\Service\FieldMappingService;
use Html;
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
     * @return array<string, string>
     */
    public function defineTabs($options = [])
    {
        $ong = parent::defineTabs($options);
        $this->addStandardTab(self::class, $ong, $options);
        /** @var array<string, string> $ong */
        return $ong;
    }

    /**
     * Prepare mapping input data for update
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed>|false Modified input data or false if invalid
     */
    public function prepareInputForUpdate($input)
    {
        $service = FieldMappingService::getInstance();
        $input = $service->prepareMappingsForStorage($input);

        return parent::prepareInputForUpdate($input);
    }

    // TODO: remove - test purpose only (entire method override)
    public function showForm($ID, array $options = [])
    {
        // TODO: remove - test button
        if (!$this->isNewItem()) {
            $options['addbuttons'] = [
                'test_sync' => [
                    'text'       => __('Test Sync', 'advancedldap'),
                    'icon'       => 'ti ti-refresh',
                    'type'       => 'button',
                    'btn_class'  => 'btn-outline-secondary',
                    'add_attribs' => [
                        'id'                  => 'test-sync-btn',
                        'data-syncfilter-id'  => $ID,
                    ],
                ],
            ];
        }

        $result = parent::showForm($ID, $options);

        // TODO: remove - test button JS
        if (!$this->isNewItem()) {
            $ajax_url = Html::getPrefixedUrl('/plugins/advancedldap/ajax/testSync.php');
            $csrf_token = Session::getNewCSRFToken();

            $js = <<<JAVASCRIPT
            $(function() {
                const testSyncBtn = document.getElementById('test-sync-btn');
                if (testSyncBtn) {
                    testSyncBtn.addEventListener('click', function() {
                        const syncfilterId = this.dataset.syncfilterId;
                        const btn = this;

                        btn.disabled = true;
                        const originalContent = btn.innerHTML;
                        btn.innerHTML = '<i class="ti ti-loader ti-spin"></i> <span>Testing...</span>';

                        fetch('{$ajax_url}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: '_glpi_csrf_token=' + encodeURIComponent('{$csrf_token}') + '&syncfilters_id=' + syncfilterId
                        })
                        .then(response => response.json())
                        .then(data => {
                            btn.disabled = false;
                            btn.innerHTML = originalContent;

                            if (data.success) {
                                glpi_toast_info('Test completed. Check php-errors.log for debug output.');
                            } else {
                                glpi_toast_error(data.message || 'Test failed');
                            }
                        })
                        .catch(error => {
                            btn.disabled = false;
                            btn.innerHTML = originalContent;
                            glpi_toast_error('Request failed');
                            console.error('Test sync error:', error);
                        });
                    });
                }
            });
JAVASCRIPT;

            echo Html::scriptBlock($js);
        }

        return $result;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof self && $item->can($item->getID(), \READ)) {
            return self::createTabEntry(
                __('Field Mapping', 'advancedldap'),
                0,
                $item::class,
                'ti ti-arrows-exchange',
            );
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof self) {
            $item->showFieldMappingTab();
        }

        return true;
    }

    public function showFieldMappingTab(): void
    {
        $service = FieldMappingService::getInstance();
        $itemtype = $this->fields['itemtype'] ?? null;

        $current_mappings = $service->getMapping($this);
        $available_fields = (empty($itemtype) || !is_string($itemtype)) ? [] : $service->getAvailableFields($itemtype);
        $indexed_mappings = $service->mappingsToIndexedArray($current_mappings);

        TemplateRenderer::getInstance()->display('@advancedldap/field_mapping.html.twig', [
            'syncfilter_id' => $this->getID(),
            'itemtype' => $itemtype,
            'current_mappings' => $indexed_mappings,
            'available_fields' => $available_fields,
            '_glpi_csrf_token' => Session::getNewCSRFToken(),
        ]);
    }
}
