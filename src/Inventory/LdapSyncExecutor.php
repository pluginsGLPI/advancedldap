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
 * @author    GLPI-Project
 * @copyright Copyright (C) 2018-2023 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Inventory;

use Throwable;
use LDAP\Result;
use AuthLDAP;
use Glpi\Inventory\Inventory;
use GlpiPlugin\Advancedldap\AbstractBuilderMapping;
use GlpiPlugin\Advancedldap\AuthLdapSyncFilter;
use GlpiPlugin\Advancedldap\LdapConnection;
use GlpiPlugin\Advancedldap\SyncFilter;
use Toolbox;

use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\ldap_get_entries;
use function Safe\preg_match_all;
use function Safe\preg_replace_callback;

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
     * Regex pattern to match LDAP placeholders in JSON templates.
     * Matches: {{ ldap.attributeName }}
     */
    private const PLACEHOLDER_PATTERN = '/\{\{\s*ldap\.(\w+)\s*\}\}/';

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
     */
    public function executeSyncFilter(AuthLDAP $authldap, SyncFilter $syncfilter): void
    {
        // 1. Load BuilderMapping from SyncFilter
        $builder = $this->loadBuilderMapping($syncfilter);
        if (!$builder instanceof AbstractBuilderMapping) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: No BuilderMapping for SyncFilter %d',
                $syncfilter->getID(),
            ));
            $this->results['skipped']++;
            return;
        }

        // 2. Get all JSON sections and extract LDAP attributes to request
        $sections = $builder->getAllSections();
        $ldap_attrs = $this->extractLdapAttributes($sections);

        if ($ldap_attrs === []) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: No LDAP attributes found in BuilderMapping for SyncFilter %d',
                $syncfilter->getID(),
            ));
            $this->results['skipped']++;
            return;
        }

        // 3. Perform LDAP search
        $ldap_entries = $this->performLdapSearch($authldap, $syncfilter, $ldap_attrs);

        if ($ldap_entries === false) {
            $this->results['errors']++;
            return;
        }

        if ($ldap_entries === []) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: No LDAP entries found for SyncFilter %d',
                $syncfilter->getID(),
            ));
            return;
        }

        // 4. Process each LDAP entry
        foreach ($ldap_entries as $ldap_entry) {
            $this->processLdapEntry($sections, $ldap_entry, $syncfilter);
        }
    }

    /**
     * Load the BuilderMapping associated with a SyncFilter.
     *
     * @param SyncFilter $syncfilter The sync filter
     *
     * @return AbstractBuilderMapping|null The builder mapping or null if not found
     */
    private function loadBuilderMapping(SyncFilter $syncfilter): ?AbstractBuilderMapping
    {
        $builder_itemtype = $syncfilter->fields['builder_itemtype'] ?? null;
        $builder_items_id = $syncfilter->fields['builder_items_id'] ?? 0;

        if (!is_string($builder_itemtype) || ($builder_itemtype === '' || $builder_itemtype === '0') || !is_numeric($builder_items_id) || (int) $builder_items_id <= 0) {
            return null;
        }

        if (!class_exists($builder_itemtype) || !is_subclass_of($builder_itemtype, AbstractBuilderMapping::class)) {
            return null;
        }

        /** @var AbstractBuilderMapping $builder */
        $builder = new $builder_itemtype();
        if (!$builder->getFromDB((int) $builder_items_id)) {
            return null;
        }

        return $builder;
    }

    /**
     * Extract LDAP attribute names from JSON sections.
     *
     * Parses all {{ ldap.attributeName }} placeholders and returns unique attribute names.
     *
     * @param array<string, array<string, mixed>> $sections JSON sections from BuilderMapping
     *
     * @return array<string> List of LDAP attribute names to request
     */
    protected function extractLdapAttributes(array $sections): array
    {
        $attributes = [];

        foreach ($sections as $section_content) {
            $json_string = json_encode($section_content);
            if (preg_match_all(self::PLACEHOLDER_PATTERN, $json_string, $matches) !== 0) {
                $attributes = array_merge($attributes, $matches[1]);
            }
        }

        // Always include essential attributes
        $attributes[] = 'dn';
        $attributes[] = 'objectGUID';

        return array_unique(array_filter($attributes));
    }

    /**
     * Process a single LDAP entry and inject into GLPI inventory.
     *
     * @param array<string, array<string, mixed>> $sections    JSON sections from BuilderMapping
     * @param array<string, mixed>                $ldap_entry  The LDAP entry data
     * @param SyncFilter                          $syncfilter  The sync filter
     */
    private function processLdapEntry(array $sections, array $ldap_entry, SyncFilter $syncfilter): void
    {
        try {
            // Build the inventory JSON by replacing placeholders
            $inventory_data = $this->buildInventoryJson($sections, $ldap_entry, $syncfilter);

            if ($inventory_data === null) {
                $this->results['errors']++;
                return;
            }

            // Inject into GLPI inventory system
            $this->injectInventory($inventory_data);
        } catch (Throwable $throwable) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Error processing LDAP entry: %s',
                $throwable->getMessage(),
            ));
            $this->results['errors']++;
        }
    }

    /**
     * Build the complete inventory JSON from sections and LDAP data.
     *
     * @param array<string, array<string, mixed>> $sections    JSON sections from BuilderMapping
     * @param array<string, mixed>                $ldap_entry  The LDAP entry data
     * @param SyncFilter                          $syncfilter  The sync filter
     *
     * @return array<string, mixed>|null The complete inventory JSON or null on error
     */
    protected function buildInventoryJson(array $sections, array $ldap_entry, SyncFilter $syncfilter): ?array
    {
        // Get the main section (base structure)
        $main = $sections['main'] ?? [];
        if (empty($main)) {
            Toolbox::logDebug('AdvancedLDAP: Missing main section in BuilderMapping');
            return null;
        }

        // Replace placeholders in main section
        $inventory = $this->replacePlaceholders($main, $ldap_entry);

        // Ensure content exists
        if (!isset($inventory['content']) || !is_array($inventory['content'])) {
            $inventory['content'] = [];
        }

        /** @var array<string, mixed> $content */
        $content = $inventory['content'];

        // Process other sections (hardware, etc.) and merge into content
        foreach ($sections as $section_name => $section_content) {
            if ($section_name === 'main') {
                continue;
            }

            $processed_section = $this->replacePlaceholders($section_content, $ldap_entry);
            $content[$section_name] = $processed_section;
        }

        $inventory['content'] = $content;

        // Generate unique device ID if not set or is a placeholder
        $deviceid = $inventory['deviceid'] ?? '';
        if (!is_string($deviceid) || ($deviceid === '' || $deviceid === '0') || str_contains($deviceid, '{{')) {
            $inventory['deviceid'] = $this->generateDeviceId($syncfilter, $ldap_entry);
        }

        // Always set as partial inventory
        $inventory['partial'] = true;

        // Remove any empty keys (e.g. tag: "" should be stripped)
        /** @var array<string, mixed> $inventory */
        $inventory = $this->removeEmptyKeys($inventory);

        return $inventory;
    }

    /**
     * Recursively remove keys with empty string values from an array.
     *
     * @param array<mixed, mixed> $data The data to clean
     *
     * @return array<mixed, mixed> The cleaned data
     */
    protected function removeEmptyKeys(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->removeEmptyKeys($value);
            } elseif ($value === '') {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Replace {{ ldap.xxx }} placeholders with actual LDAP values.
     *
     * @param array<string, mixed> $data       The data structure with placeholders
     * @param array<string, mixed> $ldap_entry The LDAP entry data
     *
     * @return array<string, mixed> The data with placeholders replaced
     */
    protected function replacePlaceholders(array $data, array $ldap_entry): array
    {
        $json_string = json_encode($data, JSON_UNESCAPED_UNICODE);

        $json_string = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function ($matches) use ($ldap_entry) {
                $attr_raw = $matches[1] ?? '';
                $attr_name = strtolower(is_string($attr_raw) ? $attr_raw : '');
                $value = $this->getLdapValue($ldap_entry, $attr_name);
                // Escape JSON special characters to prevent invalid JSON
                return addcslashes($value, "\"\\/\n\r\t");
            },
            $json_string,
        );

        $decoded = json_decode($json_string, true);

        /** @var array<string, mixed> $result */
        $result = is_array($decoded) ? $decoded : [];
        return $result;
    }

    /**
     * Get a value from LDAP entry, handling the LDAP array structure.
     *
     * @param array<string, mixed> $ldap_entry The LDAP entry
     * @param string               $attr_name  The attribute name (lowercase)
     *
     * @return string The attribute value or empty string
     */
    protected function getLdapValue(array $ldap_entry, string $attr_name): string
    {
        // LDAP attributes are lowercase in the entry array
        if (!isset($ldap_entry[$attr_name])) {
            return '';
        }

        $value = $ldap_entry[$attr_name];

        // LDAP returns arrays with 'count' and indexed values
        if (is_array($value)) {
            if (isset($value[0])) {
                $value = $value[0];
            } else {
                return '';
            }
        }

        // Handle binary values (like objectGUID)
        if ($attr_name === 'objectguid' && is_string($value) && ($value !== '' && $value !== '0')) {
            return $this->convertGuidToString($value);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Convert binary GUID to string format.
     *
     * @param string $binary_guid The binary GUID
     *
     * @return string The GUID as string
     */
    protected function convertGuidToString(string $binary_guid): string
    {
        $hex = bin2hex($binary_guid);
        if (strlen($hex) !== 32) {
            return $hex;
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 6, 2) . substr($hex, 4, 2) . substr($hex, 2, 2) . substr($hex, 0, 2),
            substr($hex, 10, 2) . substr($hex, 8, 2),
            substr($hex, 14, 2) . substr($hex, 12, 2),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    /**
     * Generate a unique device ID for the inventory.
     *
     * @param SyncFilter           $syncfilter The sync filter
     * @param array<string, mixed> $ldap_entry The LDAP entry
     *
     * @return string The device ID
     */
    protected function generateDeviceId(SyncFilter $syncfilter, array $ldap_entry): string
    {
        // Try to use objectGUID
        $guid = $this->getLdapValue($ldap_entry, 'objectguid');
        if ($guid !== '' && $guid !== '0') {
            return 'advancedldap-' . $syncfilter->getID() . '-' . $guid;
        }

        // Fallback to DN hash
        $dn = isset($ldap_entry['dn']) && is_string($ldap_entry['dn']) ? $ldap_entry['dn'] : '';
        return 'advancedldap-' . $syncfilter->getID() . '-' . md5($dn);
    }

    /**
     * Perform LDAP search using filter criteria.
     *
     * @param AuthLDAP      $authldap   The LDAP connection
     * @param SyncFilter    $syncfilter The sync filter with search criteria
     * @param array<string> $ldap_attrs LDAP attributes to fetch
     *
     * @return array<int, array<string, mixed>>|false Array of LDAP entries or false on error
     */
    private function performLdapSearch(AuthLDAP $authldap, SyncFilter $syncfilter, array $ldap_attrs): array|false
    {
        $connection_filter = $syncfilter->fields['connection_filter'] ?? '';
        $basedn = $syncfilter->fields['basedn'] ?? '';

        if (!is_string($connection_filter) || ($connection_filter === '' || $connection_filter === '0') || !is_string($basedn) || ($basedn === '' || $basedn === '0')) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Missing filter or basedn for SyncFilter %d',
                $syncfilter->getID(),
            ));
            return false;
        }

        Toolbox::logDebug(sprintf(
            'AdvancedLDAP: Searching LDAP - Filter: "%s", BaseDN: "%s", Attrs: [%s]',
            $connection_filter,
            $basedn,
            implode(', ', $ldap_attrs),
        ));

        // Connect to LDAP using AuthLDAP credentials
        $ds = LdapConnection::connect($authldap);

        if ($ds === false) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Failed to connect to LDAP server for AuthLDAP %d',
                $authldap->getID(),
            ));
            return false;
        }

        // Perform the LDAP search
        $sr = @ldap_search($ds, $basedn, $connection_filter, $ldap_attrs);

        if ($sr === false) {
            $errno = ldap_errno($ds);
            // 32 = LDAP_NO_SUCH_OBJECT (no results, not an error)
            if ($errno !== 32) {
                Toolbox::logDebug(sprintf(
                    'AdvancedLDAP: LDAP search failed - Error %d: %s',
                    $errno,
                    ldap_error($ds),
                ));
                return false;
            }

            Toolbox::logDebug('AdvancedLDAP: LDAP search returned no results (LDAP_NO_SUCH_OBJECT)');
            return [];
        }

        // Get entries - $sr is guaranteed to be LDAP\Result after the false check above
        if (!$sr instanceof Result) {
            Toolbox::logDebug('AdvancedLDAP: Unexpected LDAP search result type');
            return false;
        }

        try {
            $entries = ldap_get_entries($ds, $sr);
        } catch (Throwable $throwable) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Failed to get LDAP entries - Error: %s',
                $throwable->getMessage(),
            ));
            return false;
        }

        $count = isset($entries['count']) && is_int($entries['count']) ? $entries['count'] : 0;
        Toolbox::logDebug(sprintf(
            'AdvancedLDAP: LDAP search found %d entries',
            $count,
        ));

        // Convert LDAP entries to clean array (remove 'count' key and numeric indexes)
        /** @var array<int, array<string, mixed>> $results */
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            if (isset($entries[$i]) && is_array($entries[$i])) {
                /** @var array<string, mixed> $entry */
                $entry = $entries[$i];
                $results[] = $entry;
            }
        }

        return $results;
    }

    /**
     * Inject inventory data into GLPI.
     *
     * @param array<string, mixed> $inventory_data The inventory JSON structure
     */
    private function injectInventory(array $inventory_data): void
    {
        // Convert array to stdClass (required by Inventory schema validation)
        $json_data = json_decode(json_encode($inventory_data));

        $inventory = new Inventory();
        $inventory->setData($json_data);

        $deviceid = isset($inventory_data['deviceid']) && is_string($inventory_data['deviceid'])
            ? $inventory_data['deviceid']
            : 'unknown';

        if ($inventory->inError()) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Inventory validation error for device %s',
                $deviceid,
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
            ],
        ]);

        foreach ($iterator as $row) {
            if (!is_array($row)) {
                continue;
            }

            $syncfilter = new SyncFilter();
            $syncfilter->getFromResultSet($row);
            $syncfilters[] = $syncfilter;
        }

        return $syncfilters;
    }

    /**
     * Reset sync results counters.
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

}
