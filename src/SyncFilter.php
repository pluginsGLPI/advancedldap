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
use Computer;
use DBConnection;
use DisplayPreference;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Toolbox;

use function Safe\json_encode;

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

        // Add builder mapping fields (polymorphic relation)
        $migration->addField($table, 'builder_itemtype', 'string', ['after' => 'field_mappings']);
        $migration->addField($table, 'builder_items_id', 'int', ['after' => 'builder_itemtype']);
        $migration->addKey($table, ['builder_itemtype', 'builder_items_id'], 'builder');

        $migration->executeMigration();

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
        $tabs = parent::defineTabs($options);
        $this->addStandardTab(self::class, $tabs, $options);
        /** @var array<string, string> $tabs */
        return $tabs;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof self)) {
            return '';
        }

        // Only show tab if a BuilderMapping is associated
        $builder_itemtype = $item->fields['builder_itemtype'] ?? null;
        $builder_items_id = $item->fields['builder_items_id'] ?? 0;

        if (empty($builder_itemtype) || $builder_items_id <= 0) {
            return '';
        }

        return self::createTabEntry(
            __('Builder Mapping', 'advancedldap'),
            0,
            $item::class,
            'ti ti-code',
        );
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof self) {
            $item->showBuilderMappingTab();
            return true;
        }
        return false;
    }

    /**
     * Display the Builder Mapping tab content.
     */
    public function showBuilderMappingTab(): void
    {
        $builder_itemtype = $this->fields['builder_itemtype'] ?? null;
        $builder_items_id = $this->fields['builder_items_id'] ?? 0;

        if (
            !is_string($builder_itemtype)
            || empty($builder_itemtype)
            || !is_numeric($builder_items_id)
            || (int) $builder_items_id <= 0
            || !class_exists($builder_itemtype)
            || !is_subclass_of($builder_itemtype, AbstractBuilderMapping::class)
        ) {
            echo '<div class="alert alert-warning">';
            echo __('No Builder Mapping associated with this SyncFilter.', 'advancedldap');
            echo '</div>';
            return;
        }

        /** @var class-string<AbstractBuilderMapping> $builder_itemtype */
        $builder = new $builder_itemtype();
        if (!$builder->getFromDB((int) $builder_items_id)) {
            echo '<div class="alert alert-danger">';
            echo __('Failed to load Builder Mapping.', 'advancedldap');
            echo '</div>';
            return;
        }

        // Load Monaco CSS (required for AJAX-loaded content)
        echo Html::css("lib/monaco.css");

        // Prepare sections data for template
        $sections = [];
        $section_names = $builder_itemtype::getSectionNames();
        foreach ($section_names as $section_name) {
            if (!is_string($section_name)) {
                continue;
            }
            $content = $builder->getSection($section_name);
            $sections[] = [
                'name'    => $section_name,
                'content' => json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ];
        }

        TemplateRenderer::getInstance()->display('@advancedldap/builder_mapping.html.twig', [
            'builder'          => $builder,
            'builder_itemtype' => $builder_itemtype,
            'sections'         => $sections,
            'completions'      => self::getLdapCompletions(),
        ]);
    }

    public function post_addItem()
    {
        parent::post_addItem();

        $this->createBuilderMapping();
    }

    /**
     * Create the appropriate BuilderMapping based on itemtype.
     *
     * @return void
     */
    private function createBuilderMapping(): void
    {
        $itemtype = $this->fields['itemtype'] ?? null;

        if (!is_string($itemtype) || empty($itemtype)) {
            return;
        }

        $builder = $this->createBuilderForItemtype($itemtype);
        if ($builder === null) {
            return;
        }

        $builder_id = $builder->createWithDefaults();
        $builder_class = $builder::class;

        if ($builder_id === false) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Failed to create BuilderMapping for SyncFilter %d',
                $this->getID(),
            ));
            return;
        }

        $this->update([
            'id'               => $this->getID(),
            'builder_itemtype' => $builder_class,
            'builder_items_id' => $builder_id,
        ]);
    }

    /**
     * Create a BuilderMapping instance for a given itemtype.
     *
     * @param string $itemtype GLPI itemtype (e.g., 'Computer')
     * @return AbstractBuilderMapping|null Builder instance or null if unsupported
     */
    private function createBuilderForItemtype(string $itemtype): ?AbstractBuilderMapping
    {
        return match ($itemtype) {
            Computer::class => new ComputerBuilderMapping(),
            default => null,
        };
    }

    public function cleanDBonPurge()
    {
        parent::cleanDBonPurge();

        // Delete associated BuilderMapping
        $builder_itemtype = $this->fields['builder_itemtype'] ?? null;
        $builder_items_id = $this->fields['builder_items_id'] ?? 0;

        if (
            !is_string($builder_itemtype)
            || empty($builder_itemtype)
            || !is_numeric($builder_items_id)
            || (int) $builder_items_id <= 0
            || !class_exists($builder_itemtype)
            || !is_subclass_of($builder_itemtype, AbstractBuilderMapping::class)
        ) {
            return;
        }

        /** @var class-string<AbstractBuilderMapping> $builder_itemtype */
        $builder = new $builder_itemtype();
        if ($builder->getFromDB((int) $builder_items_id)) {
            $builder->delete(['id' => (int) $builder_items_id], true);
        }
    }

    /**
     * Get LDAP attribute completions for Monaco editor.
     *
     * @return array<array{name: string, type: string, detail?: string}>
     */
    private static function getLdapCompletions(): array
    {
        // Common LDAP attributes for Computer objects
        $attributes = [
            'ldap.cn'                      => 'Common Name',
            'ldap.name'                    => 'Name',
            'ldap.distinguishedName'       => 'Distinguished Name (DN)',
            'ldap.objectGUID'              => 'Object GUID',
            'ldap.objectSid'               => 'Object SID',
            'ldap.sAMAccountName'          => 'SAM Account Name',
            'ldap.dNSHostName'             => 'DNS Host Name',
            'ldap.operatingSystem'         => 'Operating System',
            'ldap.operatingSystemVersion'  => 'OS Version',
            'ldap.description'             => 'Description',
            'ldap.location'                => 'Location',
            'ldap.whenCreated'             => 'Creation Date',
            'ldap.whenChanged'             => 'Last Modified Date',
            'ldap.lastLogonTimestamp'      => 'Last Logon',
            'ldap.memberOf'                => 'Group Membership',
            'ldap.managedBy'               => 'Managed By',
            'ldap.serialNumber'            => 'Serial Number',
            'ldap.domain'                  => 'Domain',
        ];

        $completions = [];
        foreach ($attributes as $name => $detail) {
            $completions[] = [
                'name'   => $name,
                'type'   => 'Variable',
                'detail' => $detail,
            ];
        }

        return $completions;
    }
}
