<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by the advancedldap plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Models;

use CommonDBTM;
use Html;
use MassiveAction;
use Session;

/**
 * SyncFilter class for managing LDAP synchronization filters
 */
class SyncFilter extends CommonDBTM
{
    public static $rightname = 'config';
    public static $table = 'glpi_plugin_advancedldap_syncfilters';
    public $dohistory = true;

    /**
     * Get the table name for this class
     * Override needed because GLPI auto-generates incorrectly from namespace
     *
     * @param string|null $classname Class name
     * @return string
     */
    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_advancedldap_syncfilters';
    }

    /**
     * Get the type name for this class
     *
     * @param int $nb Number of items (for plural)
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return _n('Sync Filter', 'Sync Filters', $nb, 'advancedldap');
    }

    /**
     * Get the type identifier for this class (for massive actions compatibility)
     *
     * @return string
     */
    public static function getType(): string
    {
        // Return legacy name for GLPI compatibility
        return 'PluginAdvancedldapSyncFilter';
    }

    /**
     * Get search URL for this class
     * Override to use the correct front file instead of namespace-generated path
     *
     * @param bool $full Include GLPI_ROOT or not
     * @return string
     */
    public static function getSearchURL($full = true): string
    {
        $base = $full ? GLPI_ROOT : '';
        return $base . '/plugins/advancedldap/front/syncfilter.php';
    }

    /**
     * Redirect to list after an action
     * Override to redirect to parent AuthLDAP instead of generic list
     *
     * @return void
     */
    public function redirectToList(): void
    {
        global $CFG_GLPI;
        
        // Try to get authldap_id from current URL parameters (after deletion context)
        $authldap_id = $_GET['authldap_id'] ?? null;

        // If we have a parent AuthLDAP from URL, redirect there
        if ($authldap_id) {
            Html::redirect($CFG_GLPI['root_doc'] . "/front/authldap.form.php?id=" . intval($authldap_id));
            return;
        }

        // Fallback to default behavior (redirect to plugin search page)
        Html::redirect($CFG_GLPI['root_doc'] . "/plugins/advancedldap/front/syncfilter.php");
    }

    /**
     * Get the icon for this itemtype
     *
     * @return string
     */
    public static function getIcon(): string
    {
        return 'ti ti-filter';
    }

    /**
     * Get search options for this class
     *
     * @return array
     */
    public function rawSearchOptions(): array
    {
        $tab = [];

        $tab[] = [
            'id'                => 'common',
            'name'              => __('Characteristics'),
        ];

        $tab[] = [
            'id'                => '1',
            'table'             => $this->getTable(),
            'field'             => 'name',
            'name'              => __('Name'),
            'datatype'          => 'itemlink',
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '2',
            'table'             => $this->getTable(),
            'field'             => 'ldap_filter',
            'name'              => __('LDAP Filter', 'advancedldap'),
            'datatype'          => 'text',
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '3',
            'table'             => $this->getTable(),
            'field'             => 'base_dn',
            'name'              => __('Base DN', 'advancedldap'),
            'datatype'          => 'string',
        ];

        $tab[] = [
            'id'                => '4',
            'table'             => $this->getTable(),
            'field'             => 'asset_type',
            'name'              => __('Asset Type', 'advancedldap'),
            'datatype'          => 'string',
        ];

        $tab[] = [
            'id'                => '5',
            'table'             => $this->getTable(),
            'field'             => 'is_active',
            'name'              => __('Active'),
            'datatype'          => 'bool',
        ];

        $tab[] = [
            'id'                => '19',
            'table'             => $this->getTable(),
            'field'             => 'date_mod',
            'name'              => __('Last update'),
            'datatype'          => 'datetime',
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '121',
            'table'             => $this->getTable(),
            'field'             => 'date_creation',
            'name'              => __('Creation date'),
            'datatype'          => 'datetime',
            'massiveaction'     => false,
        ];

        return $tab;
    }

    /**
     * Get field mappings as array
     *
     * @return array
     */
    public function getFieldMappings(): array
    {
        if (empty($this->fields['field_mappings'])) {
            return [];
        }

        $mappings = json_decode($this->fields['field_mappings'], true);
        return is_array($mappings) ? $mappings : [];
    }

    /**
     * Get associated AuthLDAP servers
     *
     * @return array Array of AuthLDAP IDs
     */
    public function getAssociatedAuthLDAPs(): array
    {
        global $DB;

        if (!$this->getID()) {
            return [];
        }

        $iterator = $DB->request([
            'SELECT' => ['authldap_id'],
            'FROM' => AuthLdapSyncFilter::getTable(),
            'WHERE' => [
                'syncfilter_id' => $this->getID(),
                'is_active' => 1,
            ],
        ]);

        $authldaps = [];
        foreach ($iterator as $data) {
            $authldaps[] = $data['authldap_id'];
        }

        return $authldaps;
    }

    /**
     * Set field mappings from array
     *
     * @param array $mappings Field mappings
     * @return bool
     */
    public function setFieldMappings(array $mappings): bool
    {
        return $this->update([
            'id' => $this->getID(),
            'field_mappings' => json_encode($mappings),
        ]);
    }

    /**
     * Prepare input data for add operation
     *
     * @param array $input Input data
     * @return array|false Prepared input or false on error
     */
    public function prepareInputForAdd($input)
    {
        if (isset($input['field_mappings']) && is_array($input['field_mappings'])) {
            $input['field_mappings'] = json_encode($input['field_mappings']);
        }

        return $input;
    }

    /**
     * Actions after item was added to database
     *
     * @return void
     */
    public function post_addItem(): void
    {
        parent::post_addItem();

        // Create relation with AuthLDAP if authldap_id is provided
        if (isset($this->input['authldap_id']) && $this->input['authldap_id'] > 0) {
            $relation = new AuthLdapSyncFilter();
            $relation->add([
                'authldap_id' => $this->input['authldap_id'],
                'syncfilter_id' => $this->getID(),
                'is_active' => 1,
            ]);
        }
    }

    /**
     * Actions before item deletion
     * Clean up related AuthLDAP relations
     *
     * @return bool
     */
    public function pre_deleteItem(): bool
    {
        if (!parent::pre_deleteItem()) {
            return false;
        }

        // Delete all related AuthLDAP relations before deleting the sync filter
        global $DB;
        $DB->delete(
            'glpi_plugin_advancedldap_authldap_syncfilters',
            ['syncfilter_id' => $this->getID()],
        );

        return true;
    }

    /**
     * Prepare input data for update operation
     *
     * @param array $input Input data
     * @return array|false Prepared input or false on error
     */
    public function prepareInputForUpdate($input)
    {
        if (isset($input['field_mappings']) && is_array($input['field_mappings'])) {
            $input['field_mappings'] = json_encode($input['field_mappings']);
        }

        return $input;
    }

    /**
     * Get massive actions available for this itemtype
     *
     * @param object|null $checkitem link item to check right
     * @return array Array of massive actions
     */
    public function getSpecificMassiveActions($checkitem = null): array
    {
        $actions = parent::getSpecificMassiveActions($checkitem);

        // Ensure $actions is always an array
        if (!is_array($actions)) {
            $actions = [];
        }

        // Custom massive actions are now handled via hook in hook.php

        return $actions;
    }

    /**
     * Show massive actions sub form for specific actions
     *
     * @param MassiveAction $ma MassiveAction instance
     * @return bool
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma): bool
    {
        switch ($ma->getAction()) {
            case 'duplicate':
                echo "&nbsp;" . Html::submit(_x('button', 'Duplicate'), ['name' => 'massiveaction'])
                     . "&nbsp;" . __('Create duplicates of selected filters', 'advancedldap');
                return true;

            default:
                return parent::showMassiveActionsSubForm($ma);
        }
    }

    /**
     * Process massive actions
     *
     * @param MassiveAction $ma MassiveAction instance
     * @param CommonDBTM    $item Item on which the action is performed
     * @param array         $ids IDs of the items
     * @return void
     */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids): void
    {
        switch ($ma->getAction()) {
            case 'duplicate':
                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        $input = $item->fields;
                        unset($input['id']);
                        $input['name'] = sprintf(__('Copy of %s'), $input['name']);

                        if ($item->add($input)) {
                            $ma->itemDone(static::getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone(static::getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone(static::getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                return;

            default:
                parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
                break;
        }
    }

    /**
     * Define tabs to display on SyncFilter form
     *
     * @param array $options Parameters
     * @return array
     */
    public function defineTabs($options = []): array
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    /**
     * Check if current user can view this itemtype
     *
     * @return bool
     */
    public static function canView(): bool
    {
        return Session::haveRight(static::$rightname, READ);
    }

    /**
     * Check if current user can create items of this itemtype
     *
     * @return bool
     */
    public static function canCreate(): bool
    {
        // Allow users with config rights to create sync filters
        return Session::haveRight(static::$rightname, READ);
    }

    /**
     * Check if current user can update items of this itemtype
     *
     * @return bool
     */
    public static function canUpdate(): bool
    {
        return Session::haveRight(static::$rightname, READ);
    }

    /**
     * Check if current user can delete items of this itemtype
     *
     * @return bool
     */
    public static function canDelete(): bool
    {
        return Session::haveRight(static::$rightname, READ);
    }

    /**
     * Check if current user can purge items of this itemtype
     *
     * @return bool
     */
    public static function canPurge(): bool
    {
        return Session::haveRight(static::$rightname, READ);
    }

    /**
     * Display the sync filter form
     *
     * @param int   $ID      ID of the sync filter
     * @param array $options Options array
     * @return bool
     */
    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);

        // Handle parent AuthLDAP if provided (following GLPI conventions)
        $parent_authldap = null;
        if (isset($options['parent']) && $options['parent'] instanceof \AuthLDAP) {
            $parent_authldap = $options['parent'];
        } elseif (!empty($options['authldap_id'])) {
            $authldap = new \AuthLDAP();
            if ($authldap->getFromDB($options['authldap_id'])) {
                $parent_authldap = $authldap;
            }
        }

        // Get current authldap_id from existing relation if editing
        $current_authldap_id = null;
        $current_authldap = null;
        if ($ID > 0) {
            $current_authldap_id = $this->getParentAuthLdapId();
            if ($current_authldap_id) {
                $current_authldap = $this->getParentAuthLdap();
            }
        }

        // If we have a parent from options but no current relation, use the parent
        if ($parent_authldap && !$current_authldap) {
            $current_authldap = $parent_authldap;
            $current_authldap_id = $parent_authldap->getID();
        }

        // Get AuthLDAP servers for dropdown
        $authldap_servers = [];
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => 'glpi_authldaps',
            'WHERE' => ['is_active' => 1],
            'ORDER' => 'name',
        ]);
        foreach ($iterator as $data) {
            $authldap_servers[$data['id']] = $data['name'];
        }

        // Get available assets and current configuration
        $container = \GlpiPlugin\Advancedldap\Bootstrap::getContainer();
        $asset_field_provider = $container->get(\GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface::class);

        $available_assets = $this->buildAssetDropdown($asset_field_provider);
        $current_config = $this->getCurrentConfiguration($ID, $current_authldap_id);
        $test_results = $this->handleTestRequest($current_config);

        // Render template
        \Glpi\Application\View\TemplateRenderer::getInstance()->display('@advancedldap/syncfilter_form.html.twig', [
            'item' => $this,
            'params' => $options,
            'parent_authldap' => $current_authldap,
            'current_authldap_id' => $current_authldap_id,
            'authldap_servers' => $authldap_servers,
            'available_assets' => $available_assets,
            'current_config' => $current_config,
            'test_results' => $test_results,
        ]);

        return true;
    }

    /**
     * Build asset dropdown options
     *
     * @param mixed $asset_field_provider
     * @return array
     */
    private function buildAssetDropdown($asset_field_provider): array
    {
        $available_assets = [];

        // Separate assets by type
        $all_asset_types = $asset_field_provider instanceof \GlpiPlugin\Advancedldap\Services\AssetFieldService
            ? $asset_field_provider->getAllAssetTypes()
            : [];

        $native_assets = array_filter($all_asset_types, fn($info) => $info['type'] === 'native');
        $generic_assets = array_filter($all_asset_types, fn($info) => $info['type'] === 'generic');

        if (!empty($native_assets)) {
            $available_assets['native_separator'] = '--- ' . __('Native Assets') . ' ---';
            foreach ($native_assets as $itemtype => $info) {
                $available_assets[$itemtype] = $info['name'];
            }
        }

        if (!empty($generic_assets)) {
            $available_assets['generic_separator'] = '--- ' . __('Generic Assets') . ' ---';
            foreach ($generic_assets as $itemtype => $info) {
                $available_assets[$itemtype] = $info['name'];
            }
        }

        return $available_assets;
    }

    /**
     * Get current configuration for the filter
     *
     * @param int $ID Filter ID
     * @param int|null $authldap_id Specific AuthLDAP ID to use
     * @return array
     */
    private function getCurrentConfiguration(int $ID, ?int $authldap_id = null): array
    {
        // Use provided authldap_id, or fall back to GET parameter, or find the first active one
        $default_authldap_id = $authldap_id ?? $_GET['authldap_id'] ?? '';
        if (empty($default_authldap_id)) {
            global $DB;
            $iterator = $DB->request([
                'SELECT' => ['id'],
                'FROM' => 'glpi_authldaps',
                'WHERE' => ['is_active' => 1],
                'LIMIT' => 1,
            ]);
            foreach ($iterator as $data) {
                $default_authldap_id = $data['id'];
                break;
            }
        }

        $config = [
            'authldap_id' => $default_authldap_id,
            'filter_name' => '',
            'ldap_connection_filter' => '',
            'ldap_base_dn' => '',
            'asset_type' => '',
            'asset_field' => '',
            'is_active' => 1,
        ];

        if ($ID > 0 && $this->getFromDB($ID)) {
            $field_mappings = $this->getFieldMappings();
            $asset_field = !empty($field_mappings) ? array_key_first($field_mappings) : '';

            // Check GET parameters for test scenarios (overrides stored data when testing)
            $test_base_dn = $_GET['test_base_dn'] ?? '';
            $test_filter = $_GET['test_filter'] ?? '';
            $test_asset_type = $_GET['test_asset_type'] ?? '';
            $test_asset_field = $_GET['test_asset_field'] ?? '';

            $config = [
                'authldap_id' => $default_authldap_id, // Use the default AuthLDAP ID
                'filter_name' => $this->fields['name'],
                'ldap_connection_filter' => !empty($test_filter) ? $test_filter : $this->fields['ldap_filter'],
                'ldap_base_dn' => !empty($test_base_dn) ? $test_base_dn : $this->fields['base_dn'],
                'asset_type' => !empty($test_asset_type) ? $test_asset_type : $this->fields['asset_type'],
                'asset_field' => !empty($test_asset_field) ? $test_asset_field : $asset_field,
                'is_active' => $this->fields['is_active'],
            ];
        }

        return $config;
    }

    /**
     * Handle test request
     *
     * @param array $current_config
     * @return array|null
     */
    private function handleTestRequest(array &$current_config): ?array
    {
        if (!isset($_GET['test_ldap']) || $_GET['test_ldap'] !== '1') {
            return null;
        }

        $test_authldap_id = intval($_GET['test_authldap_id'] ?? 0);
        $test_base_dn = $_GET['test_base_dn'] ?? '';
        $test_filter = $_GET['test_filter'] ?? '';
        $test_asset_type = $_GET['test_asset_type'] ?? '';
        $test_asset_field = $_GET['test_asset_field'] ?? '';

        if (empty($test_base_dn) || empty($test_filter)) {
            return null;
        }

        // If no authldap_id provided, use the first available one
        if (!$test_authldap_id) {
            global $DB;
            $iterator = $DB->request([
                'SELECT' => ['id'],
                'FROM' => 'glpi_authldaps',
                'WHERE' => ['is_active' => 1],
                'LIMIT' => 1,
            ]);
            foreach ($iterator as $data) {
                $test_authldap_id = $data['id'];
                break;
            }

            if (!$test_authldap_id) {
                return null;
            }
        }

        $container = \GlpiPlugin\Advancedldap\Bootstrap::getContainer();
        $ldap_test_service = $container->get(\GlpiPlugin\Advancedldap\Services\LdapTestService::class);

        $test_results = $ldap_test_service->testLdapFilter(
            $test_authldap_id,
            $test_base_dn,
            $test_filter,
            $test_asset_type,
            $test_asset_field,
        );

        $current_config['ldap_base_dn'] = $test_base_dn;
        $current_config['ldap_connection_filter'] = $test_filter;
        $current_config['authldap_id'] = $test_authldap_id;

        return $test_results;
    }

    /**
     * Get the parent AuthLDAP ID for this filter
     * Uses the repository pattern to respect SOLID principles
     *
     * @return int|null
     */
    public function getParentAuthLdapId(): ?int
    {
        if (!$this->getID()) {
            return null;
        }

        $container = \GlpiPlugin\Advancedldap\Bootstrap::getContainer();
        $repository = $container->get(\GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface::class);

        $authldap_ids = $repository->getAuthLdapsForSyncFilter($this->getID(), true);

        return !empty($authldap_ids) ? (int) $authldap_ids[0] : null;
    }

    /**
     * Get the parent AuthLDAP object for this filter
     *
     * @return \AuthLDAP|null
     */
    public function getParentAuthLdap(): ?\AuthLDAP
    {
        $authldap_id = $this->getParentAuthLdapId();
        if (!$authldap_id) {
            return null;
        }

        $authldap = new \AuthLDAP();
        if ($authldap->getFromDB($authldap_id)) {
            return $authldap;
        }

        return null;
    }
}

// Legacy compatibility for GLPI 11 Search engine
// This alias ensures that the old PluginAdvancedldapSyncFilter naming still works
if (!class_exists('PluginAdvancedldapSyncFilter', false)) {
    class_alias('GlpiPlugin\Advancedldap\Models\SyncFilter', 'PluginAdvancedldapSyncFilter');
}
