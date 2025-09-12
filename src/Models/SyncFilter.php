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
use MassiveAction;
use Session;

/**
 * SyncFilter class for managing LDAP synchronization filters
 */
class SyncFilter extends CommonDBTM
{
    public static $rightname = 'config';
    public static $table = 'glpi_plugin_advancedldap_syncfilters';

    /**
     * Get the table name for this class
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
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($isadmin) {
            $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'enable'] = __('Enable');
            $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'disable'] = __('Disable');
            $actions[__CLASS__ . MassiveAction::CLASS_ACTION_SEPARATOR . 'duplicate'] = _x('button', 'Duplicate');
        }

        return $actions;
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
            case 'enable':
                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        if ($item->update(['id' => $id, 'is_active' => 1])) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                break;

            case 'disable':
                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        if ($item->update(['id' => $id, 'is_active' => 0])) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                break;

            case 'duplicate':
                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        $input = $item->fields;
                        unset($input['id']);
                        $input['name'] = sprintf(__('Copy of %s'), $input['name']);

                        if ($item->add($input)) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                break;

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

        // Get AuthLDAP servers for dropdown
        $authldap_servers = [];
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => 'glpi_authldaps',
            'WHERE' => ['is_active' => 1],
            'ORDER' => 'name'
        ]);
        foreach ($iterator as $data) {
            $authldap_servers[$data['id']] = $data['name'];
        }

        // Get available assets and current configuration
        $container = \GlpiPlugin\Advancedldap\Bootstrap::getContainer();
        $asset_field_provider = $container->get(\GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface::class);
        
        $available_assets = $this->buildAssetDropdown($asset_field_provider);
        $current_config = $this->getCurrentConfiguration($ID);
        $test_results = $this->handleTestRequest($current_config);

        // Render template
        \Glpi\Application\View\TemplateRenderer::getInstance()->display('@advancedldap/syncfilter_form.html.twig', [
            'item' => $this,
            'params' => $options,
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
     * @return array
     */
    private function getCurrentConfiguration(int $ID): array
    {
        $config = [
            'authldap_id' => $_GET['authldap_id'] ?? '',
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

            $config = [
                'authldap_id' => '', // Will be set by relation if exists
                'filter_name' => $this->fields['name'],
                'ldap_connection_filter' => $this->fields['ldap_filter'],
                'ldap_base_dn' => $this->fields['base_dn'],
                'asset_type' => $this->fields['asset_type'],
                'asset_field' => $asset_field,
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
                'LIMIT' => 1
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
}
