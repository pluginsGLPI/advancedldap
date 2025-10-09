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

use GlpiPlugin\Advancedldap\Services\GlpiConfigurationService;
use GlpiPlugin\Advancedldap\Services\SyncFilterFormHelper;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapFilterSanitizerInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapFilterParserInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapAttributeMapperInterface;
use AuthLDAP;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterFormHelperInterface;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use GlpiPlugin\Advancedldap\Services\LdapTestService;
use GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface;
use CommonDBTM;
use CommonGLPI;
use Html;
use MassiveAction;
use Session;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Advancedldap\Bootstrap;
use Safe\Exceptions\JsonException;
use Toolbox;

use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\class_alias;

/**
 * SyncFilter class for managing LDAP synchronization filters
 */
class SyncFilter extends CommonDBTM
{
    public static $rightname = 'config';
    /** @var string */
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
     * @phpstan-ignore-next-line method.parentMethodFinalByPhpDoc
     */
    public static function getType(): string
    {
        // For massive actions, use the namespaced class name to match with $ma->remainings keys
        // which come from HTML checkboxes generated with SyncFilter::class
        // Otherwise, return legacy name for hooks and setup compatibility
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        // Check if called from MassiveAction context
        foreach ($backtrace as $trace) {
            if (isset($trace['class']) && $trace['class'] === 'MassiveAction') {
                return self::class;
            }
        }

        // Return legacy name for GLPI compatibility (hooks, setup, etc.)
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
     * Get form URL for this class
     * Override to use the correct front file instead of namespace-generated path
     *
     * @param bool $full Include GLPI_ROOT or not
     * @return string
     */
    public static function getFormURL($full = true): string
    {
        $base = $full ? GLPI_ROOT : '';
        return $base . '/plugins/advancedldap/front/syncfilter.form.php';
    }

    /**
     * Redirect to list after an action
     * Override to redirect to parent AuthLDAP instead of generic list
     *
     * @return void
     * @phpstan-ignore-next-line method.parentMethodFinalByPhpDoc
     */
    public function redirectToList(): void
    {
        $container = Bootstrap::getContainer();
        $configService = $container->get(GlpiConfigurationService::class);

        // Try to get authldap_id from current request parameters
        $authldap_id = SyncFilterFormHelper::getAuthLdapIdFromRequest();

        // If we have a parent AuthLDAP from URL, redirect there
        if ($authldap_id !== 0) {
            Html::redirect($configService->getGlpiConfig('root_doc') . "/front/authldap.form.php?id=" . intval($authldap_id));
        }

        // Fallback to default behavior (redirect to plugin search page)
        Html::redirect($configService->getGlpiConfig('root_doc') . "/plugins/advancedldap/front/syncfilter.php");
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
     * @return array<int|string, mixed>
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
            'table'             => static::getTable(),
            'field'             => 'name',
            'name'              => __('Name'),
            'datatype'          => 'itemlink',
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '2',
            'table'             => static::getTable(),
            'field'             => 'ldap_filter',
            'name'              => __('LDAP Filter', 'advancedldap'),
            'datatype'          => 'text',
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '3',
            'table'             => static::getTable(),
            'field'             => 'base_dn',
            'name'              => __('Base DN', 'advancedldap'),
            'datatype'          => 'string',
        ];

        $tab[] = [
            'id'                => '4',
            'table'             => static::getTable(),
            'field'             => 'asset_type',
            'name'              => __('Asset Type', 'advancedldap'),
            'datatype'          => 'string',
        ];

        $tab[] = [
            'id'                => '5',
            'table'             => static::getTable(),
            'field'             => 'is_active',
            'name'              => __('Active'),
            'datatype'          => 'bool',
        ];

        $tab[] = [
            'id'                => '19',
            'table'             => static::getTable(),
            'field'             => 'date_mod',
            'name'              => __('Last update'),
            'datatype'          => 'datetime',
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '121',
            'table'             => static::getTable(),
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
     * @return array<string, mixed>
     */
    public function getFieldMappings(): array
    {
        if (empty($this->fields['field_mappings'])) {
            return [];
        }

        try {
            $mappings = json_decode($this->fields['field_mappings'], true);
            return is_array($mappings) ? $mappings : [];
        } catch (JsonException $e) {
            // Only log for valid IDs (not during tests where ID is 0)
            if ($this->getID() > 0) {
                Toolbox::logDebug("SyncFilter: Failed to decode field_mappings for filter " . $this->getID() . ": " . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Get associated AuthLDAP servers
     *
     * @return array<int, int> Array of AuthLDAP IDs
     */
    public function getAssociatedAuthLDAPs(): array
    {
        if (!$this->getID()) {
            return [];
        }

        $container = Bootstrap::getContainer();
        $repository = $container->get(SyncFilterRepositoryInterface::class);

        return $repository->getAssociatedAuthLdaps($this->getID());
    }

    /**
     * Set field mappings from array
     *
     * @param array<string, mixed> $mappings Field mappings
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
     * Validate LDAP inputs (Base DN and filter) before saving
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed>|false Validated input or false on validation error
     */
    private function validateLdapInputs(array $input)
    {
        $container = Bootstrap::getContainer();
        $sanitizer = $container->get(LdapFilterSanitizerInterface::class);

        // Validate Base DN if present
        if (isset($input['base_dn']) && !empty($input['base_dn']) && !$sanitizer->isValidDN($input['base_dn'])) {
            Session::addMessageAfterRedirect(
                __('Invalid LDAP Base DN syntax. Please check the format (example: ou=users,dc=example,dc=com)', 'advancedldap'),
                false,
                ERROR
            );
            return false;
        }

        // Validate LDAP filter if present
        if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
            $validatedFilter = $sanitizer->sanitizeFilter($input['ldap_filter']);
            if ($validatedFilter === null) {
                Session::addMessageAfterRedirect(
                    __('Invalid LDAP filter syntax. Please check your filter format (example: (objectClass=person))', 'advancedldap'),
                    false,
                    ERROR
                );
                return false;
            }
            // Replace with validated filter
            $input['ldap_filter'] = $validatedFilter;
        }

        return $input;
    }

    /**
     * Prepare field mappings from input data
     * Delegates to LdapFilterParser and LdapAttributeMapper services
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed> Prepared input with field_mappings
     */
    private function prepareMappingsInput(array $input): array
    {
        // Handle field_mappings array conversion
        if (isset($input['field_mappings']) && is_array($input['field_mappings'])) {
            $input['field_mappings'] = json_encode($input['field_mappings']);
        }

        // Get services from container
        $container = Bootstrap::getContainer();
        $filter_parser = $container->get(LdapFilterParserInterface::class);
        $attribute_mapper = $container->get(LdapAttributeMapperInterface::class);

        // Create intelligent field mapping from asset_fields using LDAP filter analysis
        if (isset($input['asset_fields']) && is_array($input['asset_fields']) && !empty($input['asset_fields'])) {
            $field_mappings = [];

            // Parse LDAP filter to get available attributes
            $available_attributes = [];
            if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
                $available_attributes = $filter_parser->parseFilterAttributes($input['ldap_filter']);
            }

            // Map each selected asset field to corresponding LDAP attribute
            foreach ($input['asset_fields'] as $glpi_field) {
                if (!empty($glpi_field)) {
                    $ldap_attribute = $attribute_mapper->findMatchingAttribute($glpi_field, $available_attributes);
                    $field_mappings[$glpi_field] = $ldap_attribute;
                }
            }

            if ($field_mappings !== []) {
                $input['field_mappings'] = json_encode($field_mappings);
            }
        }

        // Backward compatibility: Handle single asset_field (legacy)
        elseif (isset($input['asset_field']) && !empty($input['asset_field']) && empty($input['field_mappings'])) {
            $ldap_attribute = $input['asset_field']; // Fallback to same name

            // If we have an LDAP filter, parse it to find the best matching attribute
            if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
                $available_attributes = $filter_parser->parseFilterAttributes($input['ldap_filter']);
                $ldap_attribute = $attribute_mapper->findMatchingAttribute($input['asset_field'], $available_attributes);
            }

            $field_mappings = [$input['asset_field'] => $ldap_attribute];
            $input['field_mappings'] = json_encode($field_mappings);
        }

        return $input;
    }

    /**
     * Prepare input data for add operation
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed>|false Prepared input or false on error
     */
    public function prepareInputForAdd($input)
    {
        // Validate LDAP inputs before saving
        $input = $this->validateLdapInputs($input);
        if ($input === false) {
            return false;
        }

        return $this->prepareMappingsInput($input);
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
        $container = Bootstrap::getContainer();
        $repository = $container->get(SyncFilterRepositoryInterface::class);
        $repository->deleteAuthLdapRelations($this->getID());

        return true;
    }

    /**
     * Prepare input data for update operation
     *
     * @param array<string, mixed> $input Input data
     * @return array<string, mixed>|false Prepared input or false on error
     */
    public function prepareInputForUpdate($input)
    {
        // Validate LDAP inputs before saving
        $input = $this->validateLdapInputs($input);
        if ($input === false) {
            return false;
        }

        return $this->prepareMappingsInput($input);
    }

    /**
     * Get forbidden massive actions for this itemtype
     *
     * @return array<int, string> Array of forbidden massive actions
     */
    public function getForbiddenStandardMassiveAction(): array
    {
        $forbidden = parent::getForbiddenStandardMassiveAction();

        // Remove update action from massive actions
        $forbidden[] = 'MassiveAction:update';

        return $forbidden;
    }

    /**
     * Get massive actions available for this itemtype
     *
     * @param object|null $checkitem link item to check right
     * @return array<string, string> Array of massive actions
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
                     . "&nbsp;" . htmlspecialchars(__('Create duplicates of selected filters', 'advancedldap'), ENT_QUOTES, 'UTF-8');
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
     * @param array<int, int> $ids IDs of the items
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

                        // Create the new item
                        $new_item = new static();
                        if ($new_item->add($input)) {
                            // Duplicate ALL AuthLDAP relations
                            $container = Bootstrap::getContainer();
                            $repository = $container->get(SyncFilterRepositoryInterface::class);
                            $relations = $repository->getAuthLdapRelations($id);

                            foreach ($relations as $relation_data) {
                                $relation = new AuthLdapSyncFilter();
                                $relation->add([
                                    'authldap_id' => $relation_data['authldap_id'],
                                    'syncfilter_id' => $new_item->getID(),
                                    'is_active' => $relation_data['is_active'],
                                ]);
                            }
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
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
     * @param array<string, mixed> $options Parameters
     * @return array<string, string>
     */
    public function defineTabs($options = []): array
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(self::class, $ong, $options);
        $this->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    /**
     * Get tab name for this item
     *
     * @param CommonGLPI $item Item for which the tab is displayed
     * @param int $withtemplate Template mode
     * @return string|array Tab name
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof self) {
            return self::createTabEntry(
                text: __('Fields mapping', 'advancedldap'),
                icon: 'ti ti-line'
            );
        }
        return '';
    }

    /**
     * Display tab content for this item
     *
     * @param CommonGLPI $item Item for which the tab is displayed
     * @param int $tabnum Tab number
     * @param int $withtemplate Template mode
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof self) {
            $item->showFieldsMappingForm();
            return true;
        }
        return false;
    }

    /**
     * Show fields mapping form
     *
     * @return void
     */
    private function showFieldsMappingForm(): void
    {
        // Get current asset type from the filter
        $asset_type = $this->fields['asset_type'] ?? '';

        // Use existing getFieldMappings method to respect DRY principle
        $current_mappings = $this->getFieldMappings();

        // Render the template using GLPI's TemplateRenderer - UPDATED NAME
        TemplateRenderer::getInstance()->display('@advancedldap/syncfilter_mapping.form.twig', [
            'item'             => $this,
            'asset_type'       => $asset_type,
            'current_mappings' => $current_mappings,
            'params'          => [
                'candel'  => false,
                'canedit' => $this->canUpdateItem(),
            ],
        ]);
    }

    /**
     * Update field mappings from form submission
     * Following Single Responsibility Principle - separate method for mapping updates
     *
     * @param array<string, mixed> $mappings_data Raw mappings data from form
     * @return bool Success status
     */
    public function updateFieldMappings(array $mappings_data): bool
    {
        // Transform indexed array to key-value pairs
        $field_mappings = [];

        if (isset($mappings_data['mappings']) && is_array($mappings_data['mappings'])) {
            foreach ($mappings_data['mappings'] as $mapping) {
                if (!empty($mapping['glpi_field']) && !empty($mapping['ldap_attribute'])) {
                    $field_mappings[$mapping['glpi_field']] = $mapping['ldap_attribute'];
                }
            }
        }

        // Use existing setFieldMappings method to respect DRY principle
        return $this->setFieldMappings($field_mappings);
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
     * @param array<string, mixed> $options Options array
     * @return bool
     */
    public function showForm($ID, array $options = []): bool
    {
        $this->initForm($ID, $options);

        // Resolve parent AuthLDAP from options or existing relations
        $authldap_context = $this->resolveParentAuthLdap($ID, $options);

        // Prepare form data (services, dropdowns, configuration)
        $form_data = $this->prepareFormData($ID, $authldap_context['current_authldap_id']);

        // Handle test request if present
        $test_results = $this->handleTestRequest($form_data['current_config']);

        // Collect form metadata (inventory status, LDAP connection)
        $metadata = $this->collectFormMetadata(
            $authldap_context['current_authldap_id'],
            $form_data['form_helper'],
            $form_data['config_service']
        );

        // Render template with all collected data
        TemplateRenderer::getInstance()->display('@advancedldap/syncfilter_form.html.twig', [
            'item' => $this,
            'params' => $options,
            'parent_authldap' => $authldap_context['parent_authldap'],
            'current_authldap_id' => $authldap_context['current_authldap_id'],
            'authldap_servers' => $form_data['authldap_servers'],
            'available_assets' => $form_data['available_assets'],
            'current_config' => $form_data['current_config'],
            'test_results' => $test_results,
            'inventory_enabled' => $metadata['inventory_enabled'],
            'inventory_config_url' => $metadata['inventory_config_url'],
            'ldap_connection_status' => $metadata['ldap_connection_status'],
            'authldap_active_status' => $metadata['authldap_active_status'],
        ]);

        return true;
    }



    /**
     * Resolve the parent AuthLDAP from options or existing relations
     *
     * @param int $ID The sync filter ID
     * @param array<string, mixed> $options Options array
     * @return array<string, mixed> Array with 'authldap' and 'authldap_id' keys
     */
    private function resolveParentAuthLdap(int $ID, array $options): array
    {
        // Handle parent AuthLDAP if provided (following GLPI conventions)
        $parent_authldap = null;
        if (isset($options['parent']) && $options['parent'] instanceof AuthLDAP) {
            $parent_authldap = $options['parent'];
        } elseif (!empty($options['authldap_id'])) {
            $authldap = new AuthLDAP();
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

        return [
            'parent_authldap' => $current_authldap,
            'current_authldap_id' => $current_authldap_id,
        ];
    }

    /**
     * Prepare all form data (services, dropdowns, configuration)
     *
     * @param int $ID The sync filter ID
     * @param int|null $authldap_id Current AuthLDAP ID
     * @return array<string, mixed> Array with form data and services
     */
    private function prepareFormData(int $ID, ?int $authldap_id): array
    {
        // Get container for dependency injection
        $container = Bootstrap::getContainer();

        // Get services
        $form_helper = $container->get(SyncFilterFormHelperInterface::class);
        $asset_field_provider = $container->get(AssetFieldProviderInterface::class);
        $config_service = $container->get(GlpiConfigurationService::class);

        // Get AuthLDAP servers for dropdown
        $authldap_servers = $form_helper->getAvailableAuthLdapServers();

        // Get available assets using form helper
        $available_assets = $form_helper->buildAssetDropdown($asset_field_provider);

        // Get current configuration
        $filter_data = $ID > 0 && $this->getFromDB($ID) ? $this->fields : [];
        $current_config = $form_helper->getCurrentConfiguration($ID, $authldap_id, $filter_data);

        return [
            'form_helper' => $form_helper,
            'config_service' => $config_service,
            'authldap_servers' => $authldap_servers,
            'available_assets' => $available_assets,
            'current_config' => $current_config,
        ];
    }

    /**
     * Collect form metadata (inventory status, LDAP connection)
     *
     * @param int|null $authldap_id Current AuthLDAP ID
     * @param mixed $form_helper Form helper service
     * @param mixed $config_service Configuration service
     * @return array<string, mixed> Array with metadata
     */
    private function collectFormMetadata(?int $authldap_id, $form_helper, $config_service): array
    {
        // Check if GLPI inventory is enabled
        $inventory_enabled = $config_service->isInventoryEnabled();
        $inventory_config_url = $config_service->getInventoryConfigUrl();

        // Check LDAP connection status
        $ldap_connection_status = $form_helper->checkLdapConnectionStatus($authldap_id);

        // Check AuthLDAP active status
        $authldap_active_status = $form_helper->checkAuthLdapActiveStatus($authldap_id);

        return [
            'inventory_enabled' => $inventory_enabled,
            'inventory_config_url' => $inventory_config_url,
            'ldap_connection_status' => $ldap_connection_status,
            'authldap_active_status' => $authldap_active_status,
        ];
    }

    /**
     * Handle test request
     * Uses only data from database (filter must be saved before testing)
     *
     * @param array<string, mixed> $current_config
     * @return array<string, mixed>|null
     */
    private function handleTestRequest(array &$current_config): ?array
    {
        if (!isset($_GET['test_ldap']) || $_GET['test_ldap'] !== '1') {
            return null;
        }

        // Filter must be saved (ID > 0) to run tests
        if ($this->getID() <= 0) {
            Toolbox::logDebug("SyncFilter: Cannot test unsaved filter");
            Session::addMessageAfterRedirect(__('Please save the filter before testing', 'advancedldap'), false, WARNING);
            return null;
        }

        // Get all data from database
        $test_base_dn = $this->fields['base_dn'] ?? '';
        $test_filter = $this->fields['ldap_filter'] ?? '';
        $test_asset_type = $this->fields['asset_type'] ?? '';
        $test_authldap_id = $this->getParentAuthLdapId();

        // Validate required fields
        if (empty($test_base_dn) || empty($test_filter)) {
            Toolbox::logDebug("SyncFilter: Missing base_dn or ldap_filter in database");
            Session::addMessageAfterRedirect(__('Base DN and LDAP filter are required for testing', 'advancedldap'), false, ERROR);
            return null;
        }

        if (!$test_authldap_id) {
            Toolbox::logDebug("SyncFilter: No AuthLDAP server associated with this filter");
            Session::addMessageAfterRedirect(__('No LDAP server associated with this filter', 'advancedldap'), false, ERROR);
            return null;
        }

        $container = Bootstrap::getContainer();
        $ldap_test_service = $container->get(LdapTestService::class);

        // Get field mappings from database
        $field_mappings = $this->getFieldMappings();

        // Run test with database values
        $test_results = $ldap_test_service->testLdapFilter(
            $test_authldap_id,
            $test_base_dn,
            $test_filter,
            $test_asset_type,
            '', // asset_field deprecated, using field_mappings instead
            $field_mappings,
        );

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

        $container = Bootstrap::getContainer();
        $repository = $container->get(AuthLdapSyncFilterRepositoryInterface::class);

        $authldap_ids = $repository->getAuthLdapsForSyncFilter($this->getID(), true);

        return !empty($authldap_ids) ? (int) $authldap_ids[0] : null;
    }

    /**
     * Get the parent AuthLDAP object for this filter
     *
     * @return AuthLDAP|null
     */
    public function getParentAuthLdap(): ?AuthLDAP
    {
        $authldap_id = $this->getParentAuthLdapId();
        if (!$authldap_id) {
            return null;
        }

        $authldap = new AuthLDAP();
        if ($authldap->getFromDB($authldap_id)) {
            return $authldap;
        }

        return null;
    }

}

// Legacy compatibility for GLPI 11 Search engine
// This alias ensures that the old PluginAdvancedldapSyncFilter naming still works
if (!class_exists('PluginAdvancedldapSyncFilter', false)) {
    class_alias(\GlpiPlugin\Advancedldap\Models\SyncFilter::class, 'PluginAdvancedldapSyncFilter');
}
