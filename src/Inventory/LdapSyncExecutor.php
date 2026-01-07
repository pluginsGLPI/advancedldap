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
use GLPIKey;
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
     * TODO: remove - test purpose only
     * Execute synchronization for a single SyncFilter across all linked AuthLDAP connections.
     *
     * @param SyncFilter $syncfilter The sync filter to execute
     *
     * @return array{created: int, updated: int, errors: int, skipped: int} Sync results
     */
    public function executeForSyncFilter(SyncFilter $syncfilter): array
    {
        $this->resetResults();

        // TODO: remove - test purpose only
        $authldaps = $this->getAuthLdapsForSyncFilter($syncfilter);

        if (empty($authldaps)) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: No AuthLDAP connections linked to SyncFilter %d',
                $syncfilter->getID()
            ));
            return $this->results;
        }

        foreach ($authldaps as $authldap) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: Testing SyncFilter %d with AuthLDAP %d (%s)',
                $syncfilter->getID(),
                $authldap->getID(),
                $authldap->fields['name']
            ));
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
        // Remove duplicates and empty values
        $ldap_attrs = array_unique(array_filter($ldap_attrs));

        Toolbox::debug(sprintf(
            'AdvancedLDAP: Searching LDAP - Filter: "%s", BaseDN: "%s", Attrs: [%s]',
            $connection_filter,
            $basedn,
            implode(', ', $ldap_attrs)
        ));

        // Connect to LDAP using AuthLDAP credentials
        $ds = AuthLDAP::connectToServer(
            $authldap->fields['host'],
            $authldap->fields['port'],
            $authldap->fields['rootdn'],
            (new GLPIKey())->decrypt($authldap->fields['rootdn_passwd']),
            $authldap->fields['use_tls'],
            $authldap->fields['deref_option'],
            $authldap->fields['tls_certfile'] ?? '',
            $authldap->fields['tls_keyfile'] ?? '',
            $authldap->fields['use_bind'],
            $authldap->fields['timeout'],
            $authldap->fields['tls_version'] ?? ''
        );

        if ($ds === false) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: Failed to connect to LDAP server for AuthLDAP %d',
                $authldap->getID()
            ));
            return false;
        }

        // Perform the LDAP search
        $sr = @ldap_search($ds, $basedn, $connection_filter, $ldap_attrs);

        if ($sr === false) {
            $errno = ldap_errno($ds);
            // 32 = LDAP_NO_SUCH_OBJECT (no results, not an error)
            if ($errno !== 32) {
                Toolbox::debug(sprintf(
                    'AdvancedLDAP: LDAP search failed - Error %d: %s',
                    $errno,
                    ldap_error($ds)
                ));
                return false;
            }
            Toolbox::debug('AdvancedLDAP: LDAP search returned no results (LDAP_NO_SUCH_OBJECT)');
            return [];
        }

        // Get entries
        $entries = @ldap_get_entries($ds, $sr);

        if ($entries === false) {
            Toolbox::debug(sprintf(
                'AdvancedLDAP: Failed to get LDAP entries - Error: %s',
                ldap_error($ds)
            ));
            return false;
        }

        $count = $entries['count'] ?? 0;
        Toolbox::debug(sprintf(
            'AdvancedLDAP: LDAP search found %d entries',
            $count
        ));

        // Debug: dump first entry structure
        if ($count > 0) {
            Toolbox::debug('AdvancedLDAP: First entry structure:');
            Toolbox::debug($entries[0]);
        }

        // Convert LDAP entries to clean array (remove 'count' key and numeric indexes)
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $results[] = $entries[$i];
        }

        return $results;
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
        // Convert array to stdClass (required by Inventory schema validation)
        $json_data = json_decode(json_encode($inventory_data));

        $inventory = new Inventory();
        $inventory->setData($json_data);

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
     * TODO: remove - test purpose only
     * Get AuthLDAP connections linked to a SyncFilter.
     *
     * @param SyncFilter $syncfilter The sync filter
     *
     * @return array<AuthLDAP> Array of AuthLDAP objects
     */
    private function getAuthLdapsForSyncFilter(SyncFilter $syncfilter): array
    {
        global $DB;

        $authldaps = [];
        $relation_table = AuthLdapSyncFilter::getTable();
        $authldap_table = AuthLDAP::getTable();
        $authldap_fk = getForeignKeyFieldForItemType(AuthLDAP::class);
        $syncfilter_fk = getForeignKeyFieldForItemType(SyncFilter::class);

        $iterator = $DB->request([
            'SELECT' => ['al.*'],
            'FROM'   => $authldap_table . ' AS al',
            'INNER JOIN' => [
                $relation_table . ' AS rel' => [
                    'ON' => [
                        'rel' => $authldap_fk,
                        'al'  => 'id',
                    ],
                ],
            ],
            'WHERE' => [
                'rel.' . $syncfilter_fk => $syncfilter->getID(),
                'al.is_active'          => 1,
            ],
        ]);

        foreach ($iterator as $row) {
            $authldap = new AuthLDAP();
            $authldap->getFromResultSet($row);
            $authldaps[] = $authldap;
        }

        return $authldaps;
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
