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

use LDAP\Connection;
use Agent;
use Throwable;
use LDAP\Result;
use AuthLDAP;
use Glpi\Inventory\Inventory;
use GLPIKey;
use GlpiPlugin\Advancedldap\AbstractBuilderMapping;
use GlpiPlugin\Advancedldap\AuthLdapSyncFilter;
use GlpiPlugin\Advancedldap\SyncFilter;
use Toolbox;

use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\ldap_get_entries;
use function Safe\ldap_parse_result;
use function Safe\preg_match_all;
use function Safe\preg_replace_callback;

/**
 * Orchestrates LDAP-to-GLPI inventory synchronization for a SyncFilter.
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
     * @var array{created: int, updated: int, errors: int, skipped: int, ldap_complete: int}
     */
    private array $results = [
        'created'       => 0,
        'updated'       => 0,
        'errors'        => 0,
        'skipped'       => 0,
        'ldap_complete' => 1,
    ];

    /**
     * Whether the last LDAP search retrieved the full result set.
     * Set to false when the search failed, was truncated (size limit exceeded)
     * or the connection could not be established.
     */
    protected bool $last_search_complete = true;

    /**
     * Whether the last LDAP search retrieved the full result set.
     */
    public function wasLastSearchComplete(): bool
    {
        return $this->last_search_complete;
    }

    /**
     * Iterate over LDAP result pages until the server returns an empty cookie.
     *
     * The page fetcher receives the pagination cookie ('' for the first page) and
     * must return ['entries' => ..., 'next_cookie' => ...] or false on error.
     * On fetcher failure, the whole collection fails and the search is flagged
     * as incomplete: partial results must never be mistaken for full ones.
     *
     * @param callable(string): (array{entries: array<int, array<string, mixed>>, next_cookie: string}|false) $page_fetcher
     *
     * @return array<int, array<string, mixed>>|false All entries, or false on error
     */
    protected function collectAllPages(callable $page_fetcher): array|false
    {
        $entries = [];
        $cookie  = '';

        do {
            $page = $page_fetcher($cookie);

            if ($page === false) {
                $this->last_search_complete = false;
                return false;
            }

            $entries = array_merge($entries, $page['entries']);
            $cookie  = $page['next_cookie'];
        } while ($cookie !== '');

        return $entries;
    }

    /**
     * Execute synchronization for all active SyncFilters linked to an LDAP connection.
     *
     * @param AuthLDAP $authldap The LDAP connection
     *
     * @return array{created: int, updated: int, errors: int, skipped: int, ldap_complete: int} Sync results
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
     * Execute synchronization for a single SyncFilter using its linked AuthLDAP.
     *
     * @param SyncFilter $syncfilter The sync filter to execute
     *
     * @return array{created: int, updated: int, errors: int, skipped: int, ldap_complete: int} Sync results
     */
    public function executeSingleFilter(SyncFilter $syncfilter): array
    {
        $this->resetResults();

        $authldap = $syncfilter->getLinkedAuthLdap();
        if (!$authldap instanceof AuthLDAP) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: SyncFilter %d has no linked AuthLDAP, nothing to synchronize',
                $syncfilter->getID(),
            ));
            $this->results['skipped']++;
            return $this->results;
        }

        $this->executeSyncFilter($authldap, $syncfilter);

        return $this->results;
    }

    /**
     * Preview synchronization for a single SyncFilter without injecting data.
     *
     * @param SyncFilter $syncfilter The sync filter to preview
     *
     * @return array{first_entry: array<string, mixed>|null, would_create: int, would_update: int, total: int}
     */
    public function previewSyncFilter(SyncFilter $syncfilter): array
    {
        $authldap = $syncfilter->getLinkedAuthLdap();
        $result = ['first_entry' => null, 'would_create' => 0, 'would_update' => 0, 'total' => 0];

        if (!$authldap instanceof AuthLDAP) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: SyncFilter %d has no linked AuthLDAP, cannot preview',
                $syncfilter->getID(),
            ));
            return $result;
        }

        $builder = $this->loadBuilderMapping($syncfilter);
        if (!$builder instanceof AbstractBuilderMapping) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: SyncFilter %d has no BuilderMapping, cannot preview',
                $syncfilter->getID(),
            ));
            return $result;
        }

        $sections   = $builder->getAllSections();
        $ldap_attrs = $this->extractLdapAttributes($sections);

        $ldap_entries = $this->performLdapSearch($authldap, $syncfilter, $ldap_attrs);

        if (!is_array($ldap_entries) || $ldap_entries === []) {
            return $result;
        }

        foreach ($ldap_entries as $index => $ldap_entry) {
            $inventory_data = $this->buildInventoryJson($sections, $ldap_entry, $syncfilter);
            if ($inventory_data === null) {
                continue;
            }

            $result['total']++;

            if ($index === 0) {
                $result['first_entry'] = $inventory_data;
            }

            $deviceid = isset($inventory_data['deviceid']) && is_string($inventory_data['deviceid'])
                ? $inventory_data['deviceid'] : '';

            $agent = new Agent();
            if ($deviceid !== '' && $agent->getFromDBByCrit(['deviceid' => $deviceid])) {
                $result['would_update']++;
            } else {
                $result['would_create']++;
            }
        }

        return $result;
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

        if (!$this->last_search_complete) {
            $this->results['ldap_complete'] = 0;
        }

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
        // Substitute directly within the PHP structure (string leaves only). LDAP values
        // never touch the JSON-string layer raw, so they cannot break out of their context
        // or inject arbitrary keys into the inventory payload.
        /** @var array<string, mixed> $result */
        $result = $this->substitutePlaceholders($data, $ldap_entry);
        return $result;
    }

    /**
     * Recursively replace {{ ldap.xxx }} placeholders inside string leaves of a structure.
     *
     * @param mixed                $value      The value to process (array, string or scalar)
     * @param array<string, mixed> $ldap_entry The LDAP entry data
     *
     * @return mixed The value with placeholders substituted
     */
    private function substitutePlaceholders(mixed $value, array $ldap_entry): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->substitutePlaceholders($item, $ldap_entry);
            }

            return $result;
        }

        if (is_string($value)) {
            return preg_replace_callback(
                self::PLACEHOLDER_PATTERN,
                function ($matches) use ($ldap_entry) {
                    $attr_raw = $matches[1] ?? '';
                    $attr_name = strtolower(is_string($attr_raw) ? $attr_raw : '');
                    return $this->getLdapValue($ldap_entry, $attr_name);
                },
                $value,
            );
        }

        return $value;
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
     * Open a connection to the LDAP server using AuthLDAP credentials.
     *
     * @param AuthLDAP $authldap The LDAP connection configuration
     *
     * @return Connection|false The LDAP link or false on failure
     */
    protected function connectToLdap(AuthLDAP $authldap): Connection|false
    {
        $host = is_string($authldap->fields['host'] ?? null) ? $authldap->fields['host'] : '';
        $port = is_string($authldap->fields['port'] ?? null) ? $authldap->fields['port'] : '389';
        $rootdn = is_string($authldap->fields['rootdn'] ?? null) ? $authldap->fields['rootdn'] : '';
        $rootdn_passwd = is_string($authldap->fields['rootdn_passwd'] ?? null) ? $authldap->fields['rootdn_passwd'] : '';
        $use_tls = !empty($authldap->fields['use_tls']);
        $deref_raw = $authldap->fields['deref_option'] ?? 0;
        $deref_option = is_numeric($deref_raw) ? (int) $deref_raw : 0;
        $tls_certfile = is_string($authldap->fields['tls_certfile'] ?? null) ? $authldap->fields['tls_certfile'] : '';
        $tls_keyfile = is_string($authldap->fields['tls_keyfile'] ?? null) ? $authldap->fields['tls_keyfile'] : '';
        $use_bind = !isset($authldap->fields['use_bind']) || !empty($authldap->fields['use_bind']);
        $timeout_raw = $authldap->fields['timeout'] ?? 10;
        $timeout = is_numeric($timeout_raw) ? (int) $timeout_raw : 10;
        $tls_version = is_string($authldap->fields['tls_version'] ?? null) ? $authldap->fields['tls_version'] : '';

        return AuthLDAP::connectToServer(
            $host,
            $port,
            $rootdn,
            (new GLPIKey())->decrypt($rootdn_passwd) ?? '',
            $use_tls,
            $deref_option,
            $tls_certfile,
            $tls_keyfile,
            $use_bind,
            $timeout,
            $tls_version,
        );
    }

    /**
     * Get the page size to use for LDAP searches on this connection.
     *
     * @param AuthLDAP $authldap The LDAP connection configuration
     *
     * @return int Page size, or 0 when pagination is not available
     */
    protected function getPageSize(AuthLDAP $authldap): int
    {
        if (!AuthLDAP::isLdapPageSizeAvailable($authldap)) {
            return 0;
        }

        $pagesize = $authldap->fields['pagesize'] ?? 0;

        return is_numeric($pagesize) ? max(0, (int) $pagesize) : 0;
    }

    /**
     * Perform a single (possibly paged) LDAP search request.
     *
     * @param Connection $ds The LDAP link
     * @param string           $basedn     Search base DN
     * @param string           $filter     LDAP filter
     * @param array<string>    $ldap_attrs Attributes to fetch
     * @param string           $cookie     Pagination cookie ('' for the first page)
     * @param int              $pagesize   Page size (0 = pagination disabled)
     *
     * @return array{entries: array<int, array<string, mixed>>, next_cookie: string}|false
     */
    protected function fetchLdapPage(
        Connection $ds,
        string $basedn,
        string $filter,
        array $ldap_attrs,
        string $cookie,
        int $pagesize,
    ): array|false {
        $controls = [];
        if ($pagesize > 0) {
            $controls = [
                [
                    'oid'        => LDAP_CONTROL_PAGEDRESULTS,
                    'iscritical' => true,
                    'value'      => [
                        'size'   => $pagesize,
                        'cookie' => $cookie,
                    ],
                ],
            ];
        }

        $sr = @ldap_search($ds, $basedn, $filter, $ldap_attrs, 0, -1, -1, LDAP_DEREF_NEVER, $controls);

        if ($sr === false) {
            $errno = ldap_errno($ds);
            // 32 = LDAP_NO_SUCH_OBJECT (no results, not an error)
            if ($errno === 32) {
                Toolbox::logDebug('AdvancedLDAP: LDAP search returned no results (LDAP_NO_SUCH_OBJECT)');
                return ['entries' => [], 'next_cookie' => ''];
            }

            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: LDAP search failed - Error %d: %s',
                $errno,
                ldap_error($ds),
            ));
            return false;
        }

        if (!$sr instanceof Result) {
            Toolbox::logDebug('AdvancedLDAP: Unexpected LDAP search result type');
            return false;
        }

        $next_cookie = '';
        if ($pagesize > 0) {
            // Read the pagination cookie from the parsed result controls (same pattern as
            // core AuthLDAP::searchForUsers()). Narrow each offset: the by-ref output is mixed.
            $errcode = null;
            $matcheddn = null;
            $errmsg = null;
            $referrals = null;
            $parsed_controls = [];
            try {
                ldap_parse_result($ds, $sr, $errcode, $matcheddn, $errmsg, $referrals, $parsed_controls);
            } catch (Throwable) {
                $parsed_controls = [];
            }

            $paged       = is_array($parsed_controls) ? ($parsed_controls[LDAP_CONTROL_PAGEDRESULTS] ?? null) : null;
            $paged_value = is_array($paged) ? ($paged['value'] ?? null) : null;
            $cookie      = is_array($paged_value) ? ($paged_value['cookie'] ?? null) : null;

            if (is_string($cookie) || is_int($cookie)) {
                $next_cookie = (string) $cookie;
            }
        }

        // 4 (openldap) / 11 = size limit exceeded: the server refused to return everything
        if (in_array(ldap_errno($ds), [4, 11], true)) {
            Toolbox::logDebug('AdvancedLDAP: LDAP size limit exceeded, result set is truncated');
            $this->last_search_complete = false;
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

        // Convert LDAP entries to clean array (remove 'count' key and numeric indexes)
        /** @var array<int, array<string, mixed>> $page_entries */
        $page_entries = [];
        for ($i = 0; $i < $count; $i++) {
            if (isset($entries[$i]) && is_array($entries[$i])) {
                /** @var array<string, mixed> $entry */
                $entry = $entries[$i];
                $page_entries[] = $entry;
            }
        }

        return ['entries' => $page_entries, 'next_cookie' => $next_cookie];
    }

    /**
     * Perform LDAP search using filter criteria, fetching all result pages.
     *
     * @param AuthLDAP      $authldap   The LDAP connection
     * @param SyncFilter    $syncfilter The sync filter with search criteria
     * @param array<string> $ldap_attrs LDAP attributes to fetch
     *
     * @return array<int, array<string, mixed>>|false Array of LDAP entries or false on error
     */
    protected function performLdapSearch(AuthLDAP $authldap, SyncFilter $syncfilter, array $ldap_attrs): array|false
    {
        $this->last_search_complete = true;

        $connection_filter = $syncfilter->fields['connection_filter'] ?? '';
        $basedn = $syncfilter->fields['basedn'] ?? '';

        if (!is_string($connection_filter) || ($connection_filter === '' || $connection_filter === '0') || !is_string($basedn) || ($basedn === '' || $basedn === '0')) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Missing filter or basedn for SyncFilter %d',
                $syncfilter->getID(),
            ));
            $this->last_search_complete = false;
            return false;
        }

        $ds = $this->connectToLdap($authldap);

        if ($ds === false) {
            Toolbox::logDebug(sprintf(
                'AdvancedLDAP: Failed to connect to LDAP server for AuthLDAP %d',
                $authldap->getID(),
            ));
            $this->last_search_complete = false;
            return false;
        }

        $pagesize = $this->getPageSize($authldap);

        Toolbox::logDebug(sprintf(
            'AdvancedLDAP: Searching LDAP - Filter: "%s", BaseDN: "%s", PageSize: %d, Attrs: [%s]',
            $connection_filter,
            $basedn,
            $pagesize,
            implode(', ', $ldap_attrs),
        ));

        $entries = $this->collectAllPages(
            fn(string $cookie): array|false => $this->fetchLdapPage($ds, $basedn, $connection_filter, $ldap_attrs, $cookie, $pagesize),
        );

        if ($entries === false) {
            return false;
        }

        Toolbox::logDebug(sprintf('AdvancedLDAP: LDAP search found %d entries', count($entries)));

        return $entries;
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

        $agent = new Agent();
        $agent_exists = $deviceid !== 'unknown' && $agent->getFromDBByCrit(['deviceid' => $deviceid]);

        $inventory->doInventory();

        if ($agent_exists) {
            $this->results['updated']++;
        } else {
            $this->results['created']++;
        }
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
            'created'       => 0,
            'updated'       => 0,
            'errors'        => 0,
            'skipped'       => 0,
            'ldap_complete' => 1,
        ];
    }

}
