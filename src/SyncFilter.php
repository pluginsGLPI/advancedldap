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
use Computer;
use DBConnection;
use DisplayPreference;
use Glpi\Application\View\TemplateRenderer;
use GLPIKey;
use Html;
use Migration;
use Toolbox;

use function Safe\json_encode;
use function Safe\ldap_get_entries;

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

        // Add builder mapping fields (polymorphic relation)
        $migration->addField($table, 'builder_itemtype', 'string', ['after' => 'itemtype']);
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
            || ($builder_itemtype === '' || $builder_itemtype === '0')
            || !is_numeric($builder_items_id)
            || (int) $builder_items_id <= 0
            || !class_exists($builder_itemtype)
            || !is_subclass_of($builder_itemtype, AbstractBuilderMapping::class)
        ) {
            echo '<div class="alert alert-warning">';
            echo __s('No Builder Mapping associated with this SyncFilter.', 'advancedldap');
            echo '</div>';
            return;
        }

        /** @var class-string<AbstractBuilderMapping> $builder_itemtype */
        $builder = new $builder_itemtype();
        if (!$builder->getFromDB((int) $builder_items_id)) {
            echo '<div class="alert alert-danger">';
            echo __s('Failed to load Builder Mapping.', 'advancedldap');
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
            if (empty($content)) {
                $content = $builder_itemtype::loadDefaultTemplate($section_name);
            }

            $sections[] = [
                'name'    => $section_name,
                'content' => json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ];
        }

        TemplateRenderer::getInstance()->display('@advancedldap/builder_mapping.html.twig', [
            'builder'          => $builder,
            'builder_itemtype' => $builder_itemtype,
            'sections'         => $sections,
            'completions'      => $this->getLdapCompletions($this->getID()),
            'authldap_status'  => $this->getAuthLdapStatus(),
        ]);
    }

    public function post_addItem()
    {
        parent::post_addItem();

        $this->createBuilderMapping();
    }

    /**
     * Create the appropriate BuilderMapping based on itemtype.
     */
    private function createBuilderMapping(): void
    {
        $itemtype = $this->fields['itemtype'] ?? null;

        if (!is_string($itemtype) || ($itemtype === '' || $itemtype === '0')) {
            return;
        }

        $builder = $this->createBuilderForItemtype($itemtype);
        if (!$builder instanceof AbstractBuilderMapping) {
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
            || ($builder_itemtype === '' || $builder_itemtype === '0')
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
     * @param int|null $syncfilter_id SyncFilter ID for dynamic attribute retrieval
     * @return array<array{name: string, type: string, detail: string}>
     */
    private function getLdapCompletions(?int $syncfilter_id = null): array
    {
        // Base attributes (fallback)
        $base_attributes = [
            'ldap.cn'                      => ' Common Name',
            'ldap.name'                    => ' Name',
            'ldap.distinguishedName'       => ' Distinguished Name (DN)',
            'ldap.objectGUID'              => ' Object GUID',
            'ldap.objectSid'               => ' Object SID',
            'ldap.sAMAccountName'          => ' SAM Account Name',
            'ldap.dNSHostName'             => ' DNS Host Name',
            'ldap.operatingSystem'         => ' Operating System',
            'ldap.operatingSystemVersion'  => ' OS Version',
            'ldap.description'             => ' Description',
            'ldap.location'                => ' Location',
            'ldap.whenCreated'             => ' Creation Date',
            'ldap.whenChanged'             => ' Last Modified Date',
            'ldap.lastLogonTimestamp'      => ' Last Logon',
            'ldap.memberOf'                => ' Group Membership',
            'ldap.managedBy'               => ' Managed By',
            'ldap.serialNumber'            => ' Serial Number',
            'ldap.domain'                  => ' Domain',
        ];

        if ($syncfilter_id === null) {
            return $this->formatCompletions($base_attributes);
        }

        // Retrieve dynamic attributes from LDAP
        $dynamic_attributes = $this->fetchLdapAttributesForSyncFilter($syncfilter_id);

        // Merge: dynamic first, then base (array_merge overwrites duplicates)
        $all_attributes = array_merge($dynamic_attributes, $base_attributes);

        return $this->formatCompletions($all_attributes);
    }

    /**
     * Fetch LDAP attributes for a SyncFilter from its unique linked AuthLDAP.
     *
     * @param int $syncfilter_id SyncFilter ID
     * @return array<string, string> Attributes as [name => description]
     */
    private function fetchLdapAttributesForSyncFilter(int $syncfilter_id): array
    {
        global $DB;

        $authldap_fk = getForeignKeyFieldForItemType(AuthLDAP::class);
        $syncfilter_fk = getForeignKeyFieldForItemType(self::class);

        // Retrieve the single linked AuthLDAP (uniqueness constraint)
        $iterator = $DB->request([
            'SELECT' => [$authldap_fk],
            'FROM'   => AuthLdapSyncFilter::getTable(),
            'WHERE'  => [$syncfilter_fk => $syncfilter_id],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 0) {
            return [];
        }

        $syncfilter = new self();
        if (!$syncfilter->getFromDB($syncfilter_id)) {
            return [];
        }

        $row = $iterator->current();
        if (!is_array($row)) {
            return [];
        }

        $authldap_id = $row[$authldap_fk] ?? 0;
        if (!is_numeric($authldap_id)) {
            return [];
        }

        $authldap = new AuthLDAP();
        if (!$authldap->getFromDB((int) $authldap_id)) {
            return [];
        }

        return $this->fetchAttributesFromLdap($authldap, $syncfilter);
    }

    /**
     * Fetch attributes from LDAP by querying a sample object.
     *
     * @param AuthLDAP   $authldap   AuthLDAP connection
     * @param SyncFilter $syncfilter SyncFilter with basedn and filter
     * @return array<string, string> Attributes as [name => description]
     */
    private function fetchAttributesFromLdap(AuthLDAP $authldap, SyncFilter $syncfilter): array
    {
        // Extract connection parameters with type validation
        $host = $authldap->fields['host'] ?? '';
        $port = $authldap->fields['port'] ?? '389';
        $rootdn = $authldap->fields['rootdn'] ?? '';
        $rootdn_passwd = $authldap->fields['rootdn_passwd'] ?? '';
        $use_tls = $authldap->fields['use_tls'] ?? false;
        $deref = $authldap->fields['deref'] ?? 0;

        $decrypted_passwd = (new GLPIKey())->decrypt(is_string($rootdn_passwd) ? $rootdn_passwd : '');

        // Connect to LDAP
        $ds = AuthLDAP::connectToServer(
            is_string($host) ? $host : '',
            is_string($port) ? $port : '389',
            is_string($rootdn) ? $rootdn : '',
            is_string($decrypted_passwd) ? $decrypted_passwd : '',
            (bool) $use_tls,
            is_int($deref) ? $deref : 0,
        );

        if ($ds === false) {
            return [];
        }

        // Search for ONE object with ALL its attributes
        $syncfilter_basedn = $syncfilter->fields['basedn'] ?? '';
        $authldap_basedn = $authldap->fields['basedn'] ?? '';
        $connection_filter = $syncfilter->fields['connection_filter'] ?? '';

        $basedn = is_string($syncfilter_basedn) && ($syncfilter_basedn !== '' && $syncfilter_basedn !== '0')
            ? $syncfilter_basedn
            : (is_string($authldap_basedn) ? $authldap_basedn : '');
        $filter = is_string($connection_filter) && ($connection_filter !== '' && $connection_filter !== '0')
            ? $connection_filter
            : '(objectClass=*)';

        $sr = @ldap_search($ds, $basedn, $filter, ['*'], 0, 1);

        if ($sr === false || is_array($sr)) {
            @ldap_close($ds);
            return [];
        }

        $entries = ldap_get_entries($ds, $sr);
        @ldap_close($ds);

        if ($entries['count'] === 0) {
            return [];
        }

        // Extract attribute names from the object
        $attributes = [];
        $entry = $entries[0];

        if (!is_array($entry)) {
            return [];
        }

        $count = $entry['count'] ?? 0;
        $entry_count = is_int($count) ? $count : 0;

        for ($i = 0; $i < $entry_count; $i++) {
            $attr_name = $entry[$i] ?? null;
            if (is_string($attr_name)) {
                $attributes['ldap.' . $attr_name] = $attr_name;
            }
        }

        return $attributes;
    }

    /**
     * Format attributes array into completions array for Monaco.
     *
     * @param array<string, string> $attributes Attributes as [name => description]
     * @return array<array{name: string, type: string, detail: string}>
     */
    private function formatCompletions(array $attributes): array
    {
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

    /**
     * Get the unique AuthLDAP linked to this SyncFilter.
     *
     * @return AuthLDAP|null The linked AuthLDAP or null if none
     */
    public function getLinkedAuthLdap(): ?AuthLDAP
    {
        global $DB;

        $authldap_fk = getForeignKeyFieldForItemType(AuthLDAP::class);
        $syncfilter_fk = getForeignKeyFieldForItemType(self::class);

        $iterator = $DB->request([
            'SELECT' => [$authldap_fk],
            'FROM'   => AuthLdapSyncFilter::getTable(),
            'WHERE'  => [$syncfilter_fk => $this->getID()],
            'LIMIT'  => 1,
        ]);

        if (count($iterator) === 0) {
            return null;
        }

        $row = $iterator->current();
        if (!is_array($row)) {
            return null;
        }

        $authldap_id = $row[$authldap_fk] ?? 0;
        if (!is_numeric($authldap_id)) {
            return null;
        }

        $authldap = new AuthLDAP();
        if ($authldap->getFromDB((int) $authldap_id)) {
            return $authldap;
        }

        return null;
    }

    /**
     * Get the status of the linked AuthLDAP connection.
     *
     * @return array{has_authldap: bool, is_active: bool, can_connect: bool, error_message: string|null, authldap_name: string|null}
     */
    public function getAuthLdapStatus(): array
    {
        $status = [
            'has_authldap'   => false,
            'is_active'      => false,
            'can_connect'    => false,
            'error_message'  => null,
            'authldap_name'  => null,
        ];

        $authldap = $this->getLinkedAuthLdap();
        if (!$authldap instanceof AuthLDAP) {
            return $status;
        }

        $status['has_authldap'] = true;
        $name = $authldap->fields['name'] ?? '';
        $status['authldap_name'] = is_string($name) ? $name : '';
        $status['is_active'] = (bool) ($authldap->fields['is_active'] ?? false);

        if (!$status['is_active']) {
            return $status;
        }

        // Extract connection parameters with type validation
        $host = $authldap->fields['host'] ?? '';
        $port = $authldap->fields['port'] ?? '389';
        $rootdn = $authldap->fields['rootdn'] ?? '';
        $rootdn_passwd = $authldap->fields['rootdn_passwd'] ?? '';
        $use_tls = $authldap->fields['use_tls'] ?? false;
        $deref = $authldap->fields['deref'] ?? 0;

        $decrypted_passwd = (new GLPIKey())->decrypt(is_string($rootdn_passwd) ? $rootdn_passwd : '');

        // Test LDAP connection
        $ds = AuthLDAP::connectToServer(
            is_string($host) ? $host : '',
            is_string($port) ? $port : '389',
            is_string($rootdn) ? $rootdn : '',
            is_string($decrypted_passwd) ? $decrypted_passwd : '',
            (bool) $use_tls,
            is_int($deref) ? $deref : 0,
        );

        if ($ds === false) {
            $status['error_message'] = __('Unable to connect to LDAP server', 'advancedldap');
            return $status;
        }

        @ldap_close($ds);
        $status['can_connect'] = true;

        return $status;
    }
}
