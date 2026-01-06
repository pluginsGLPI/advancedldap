<?php

/**
 * -------------------------------------------------------------------------
 * AdvancedLDAP plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of AdvancedLDAP.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * @author    GLPI-Project
 * @copyright Copyright (C) GLPI-Project
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Inventory;

use AuthLDAP;
use Computer;
use Glpi\Inventory\Inventory;
use GlpiPlugin\Advancedldap\AuthLdapSyncFilter;
use GlpiPlugin\Advancedldap\Service\FieldMappingService;
use GlpiPlugin\Advancedldap\SyncFilter;
use Phone;
use Toolbox;

/**
 * Orchestrator for LDAP to GLPI inventory synchronization.
 *
 * Responsibilities:
 * 1. Retrieve active SyncFilters for a given LDAP connection
 * 2. Perform LDAP searches using filter criteria
 * 3. Instantiate the appropriate InventoryBuilder based on itemtype
 * 4. Inject built inventory JSON into Glpi\Inventory\Inventory
 * 5. Log results
 */
class LdapSyncExecutor
{
    /**
     * Mapping of itemtype to builder class.
     *
     * @var array<string, class-string<AbstractInventoryBuilder>>
     */
    private const BUILDERS = [
        Computer::class => ComputerBuilder::class,
        Phone::class    => PhoneBuilder::class,
    ];

    /**
     * Results of the last synchronization.
     *
     * @var array{created: int, updated: int, errors: int, skipped: int}
     */
    private array $results = [
        'created' => 0,
        'updated' => 0,
        'errors'  => 0,
        'skipped' => 0,
    ];

    /**
     * Execute synchronization for all active SyncFilters linked to an LDAP connection.
     *
     * @param AuthLDAP $authldap The LDAP connection
     *
     * @return array{created: int, updated: int, errors: int, skipped: int} Sync results
     */
    public function executeForConnection(AuthLDAP $authldap): array
    {
        $this->resetResults();

        $syncfilters = $this->getSyncFiltersForConnection($authldap);

        foreach ($syncfilters as $syncfilter) {
            $this->executeSyncFilter($authldap, $syncfilter);
        }

        return $this->results;
    }

    /**
     * Execute synchronization for a single SyncFilter.
     *
     * @param AuthLDAP   $authldap   The LDAP connection
     * @param SyncFilter $syncfilter The sync filter to execute
     *
     * @return void
     */
    public function executeSyncFilter(AuthLDAP $authldap, SyncFilter $syncfilter): void
    {
        $itemtype = $syncfilter->fields['itemtype'] ?? null;
        if (empty($itemtype) || !isset(self::BUILDERS[$itemtype])) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: Unsupported itemtype "%s" for SyncFilter %d',
                $itemtype ?? 'null',
                $syncfilter->getID()
            ));
            $this->results['skipped']++;
            return;
        }

        // Get field mappings
        $mapping_service = FieldMappingService::getInstance();
        $field_mappings = $mapping_service->getMapping($syncfilter);

        if (empty($field_mappings)) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: No field mappings configured for SyncFilter %d',
                $syncfilter->getID()
            ));
            $this->results['skipped']++;
            return;
        }

        // Perform LDAP search
        $ldap_entries = $this->performLdapSearch($authldap, $syncfilter, $field_mappings);

        if ($ldap_entries === false) {
            $this->results['errors']++;
            return;
        }

        // Instantiate the appropriate builder
        $builder_class = self::BUILDERS[$itemtype];
        $builder = new $builder_class();

        // Process each LDAP entry
        foreach ($ldap_entries as $ldap_entry) {
            $this->processLdapEntry($builder, $syncfilter, $ldap_entry, $field_mappings);
        }
    }

    /**
     * Perform LDAP search using filter criteria.
     *
     * @param AuthLDAP   $authldap       The LDAP connection
     * @param SyncFilter $syncfilter     The sync filter with search criteria
     * @param array      $field_mappings Field mappings to determine attributes to fetch
     *
     * @return array<int, array>|false Array of LDAP entries or false on error
     */
    private function performLdapSearch(AuthLDAP $authldap, SyncFilter $syncfilter, array $field_mappings)
    {
        // TODO: Implement actual LDAP search
        // This is a placeholder showing the expected structure

        $connection_filter = $syncfilter->fields['connection_filter'] ?? '';
        $basedn = $syncfilter->fields['basedn'] ?? '';

        if (empty($connection_filter) || empty($basedn)) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: Missing filter or basedn for SyncFilter %d',
                $syncfilter->getID()
            ));
            return false;
        }

        // Determine which LDAP attributes to request
        $ldap_attrs = array_values($field_mappings);
        // Always request DN
        $ldap_attrs[] = 'dn';
        // Request objectGUID if available (for device ID)
        $ldap_attrs[] = 'objectGUID';

        // TODO: Use AuthLDAP methods to perform the search
        // $ds = $authldap->connect();
        // $sr = ldap_search($ds, $basedn, $connection_filter, $ldap_attrs);
        // $entries = ldap_get_entries($ds, $sr);

        Toolbox::debug(sprintf(
            'AdvancedLDAP: Would search LDAP with filter "%s" on base "%s"',
            $connection_filter,
            $basedn
        ));

        // Return empty array for now (placeholder)
        return [];
    }

    /**
     * Process a single LDAP entry and inject into GLPI inventory.
     *
     * @param AbstractInventoryBuilder $builder        The builder for this itemtype
     * @param SyncFilter               $syncfilter     The sync filter
     * @param array                    $ldap_entry     The LDAP entry data
     * @param array                    $field_mappings Field mappings
     *
     * @return void
     */
    private function processLdapEntry(
        AbstractInventoryBuilder $builder,
        SyncFilter $syncfilter,
        array $ldap_entry,
        array $field_mappings
    ): void {
        try {
            // Generate device ID
            $device_id = AbstractInventoryBuilder::generateDeviceId(
                $syncfilter->getID(),
                $ldap_entry,
                'objectGUID'
            );

            // Build inventory JSON
            $inventory_data = $builder->build($device_id, $ldap_entry, $field_mappings);

            // Inject into GLPI inventory system
            $this->injectInventory($inventory_data);
        } catch (\Throwable $e) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: Error processing LDAP entry: %s',
                $e->getMessage()
            ));
            $this->results['errors']++;
        }
    }

    /**
     * Inject inventory data into GLPI.
     *
     * @param array $inventory_data The inventory JSON structure
     *
     * @return void
     */
    private function injectInventory(array $inventory_data): void
    {
        // TODO: Implement actual inventory injection
        // The Inventory class expects JSON string or stdClass

        $inventory = new Inventory();
        $inventory->setData($inventory_data);

        if ($inventory->inError()) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: Inventory validation error for device %s',
                $inventory_data['deviceid'] ?? 'unknown'
            ));
            $this->results['errors']++;
            return;
        }

        $inventory->doInventory();

        // TODO: Determine if item was created or updated based on Inventory results
        // For now, assume created
        $this->results['created']++;
    }

    /**
     * Get active SyncFilters linked to an LDAP connection.
     *
     * @param AuthLDAP $authldap The LDAP connection
     *
     * @return array<SyncFilter> Array of active SyncFilter objects
     */
    private function getSyncFiltersForConnection(AuthLDAP $authldap): array
    {
        global $DB;

        $syncfilters = [];
        $relation_table = AuthLdapSyncFilter::getTable();
        $syncfilter_table = SyncFilter::getTable();
        $authldap_fk = getForeignKeyFieldForItemType(AuthLDAP::class);
        $syncfilter_fk = getForeignKeyFieldForItemType(SyncFilter::class);

        $iterator = $DB->request([
            'SELECT' => ['sf.*'],
            'FROM'   => $syncfilter_table . ' AS sf',
            'INNER JOIN' => [
                $relation_table . ' AS rel' => [
                    'ON' => [
                        'rel' => $syncfilter_fk,
                        'sf'  => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'rel.' . $authldap_fk => $authldap->getID(),
                'sf.is_active'        => 1,
            ],
        ]);

        foreach ($iterator as $row) {
            $syncfilter = new SyncFilter();
            $syncfilter->getFromResultSet($row);
            $syncfilters[] = $syncfilter;
        }

        return $syncfilters;
    }

    /**
     * Reset sync results counters.
     *
     * @return void
     */
    private function resetResults(): void
    {
        $this->results = [
            'created' => 0,
            'updated' => 0,
            'errors'  => 0,
            'skipped' => 0,
        ];
    }

    /**
     * Get the list of supported itemtypes.
     *
     * @return array<string> List of supported itemtype class names
     */
    public static function getSupportedItemtypes(): array
    {
        return array_keys(self::BUILDERS);
    }
}
