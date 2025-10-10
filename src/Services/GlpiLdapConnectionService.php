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
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Services;

use Exception;
use Toolbox;
use AuthLDAP;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapFilterSanitizerInterface;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use function Safe\ldap_get_entries;
use function Safe\ldap_parse_result;

/**
 * GLPI LDAP Connection Service Implementation
 *
 * Wraps GLPI's AuthLDAP functionality for testability
 */
class GlpiLdapConnectionService implements LdapConnectionInterface
{
    private SyncFilterRepositoryInterface $repository;
    private LdapFilterSanitizerInterface $sanitizer;

    public function __construct(
        SyncFilterRepositoryInterface $repository,
        LdapFilterSanitizerInterface $sanitizer
    ) {
        $this->repository = $repository;
        $this->sanitizer = $sanitizer;
    }
    /**
     * Connect to LDAP server
     *
     * @param int $authldap_id The AuthLDAP configuration ID
     * @return mixed LDAP connection resource or false on failure
     */
    public function connect(int $authldap_id)
    {
        $authldap = new AuthLDAP();
        if (!$authldap->getFromDB($authldap_id)) {
            return false;
        }

        return $authldap->connect();
    }

    /**
     * Perform LDAP search
     *
     * @param mixed  $connection LDAP connection resource
     * @param string $base_dn    Base DN for search
     * @param string $filter     LDAP filter
     * @return mixed Search result or false on failure
     */
    public function search($connection, string $base_dn, string $filter)
    {
        return ldap_search($connection, $base_dn, $filter);
    }

    /**
     * Get entries from search result
     *
     * @param mixed $connection LDAP connection resource
     * @param mixed $search_result Search result from ldap_search
     * @return array<mixed> LDAP entries
     */
    public function getEntries($connection, $search_result): array
    {
        return ldap_get_entries($connection, $search_result);
    }

    /**
     * Close LDAP connection
     *
     * @param mixed $connection LDAP connection resource
     * @return bool True on success
     */
    public function close($connection): bool
    {
        return ldap_close($connection);
    }

    /**
     * Get last LDAP error
     *
     * @param mixed $connection LDAP connection resource
     * @return string Error message
     */
    public function getError($connection): string
    {
        return ldap_error($connection);
    }

    /**
     * Check LDAP connection status for warning display
     * Reuses the same connection logic but only tests connectivity
     *
     * @param int|null $authldap_id AuthLDAP server ID
     * @return array{connected: bool, error: string|null, server_name: string|null} Connection status information
     */
    public function checkConnection(?int $authldap_id): array
    {
        // If no authldap_id provided, try to get the first active one
        if (!$authldap_id) {
            $authldap_id = $this->repository->getFirstActiveAuthLdapId();

            // If still no authldap_id found, there are no active LDAP servers
            if (!$authldap_id) {
                return [
                    'connected' => false,
                    'error' => __('No active AuthLDAP server found in GLPI', 'advancedldap'),
                    'server_name' => null,
                ];
            }
        }

        // Get AuthLDAP server information
        $authldap = new AuthLDAP();
        if (!$authldap->getFromDB($authldap_id)) {
            return [
                'connected' => false,
                'error' => __('AuthLDAP server not found', 'advancedldap'),
                'server_name' => null,
            ];
        }

        $server_name = $authldap->fields['name'] ?? "ID $authldap_id";

        // Test connection using the same logic as connect method
        $connection = $this->connect($authldap_id);
        if (!$connection) {
            return [
                'connected' => false,
                'error' => __('Cannot connect to LDAP server', 'advancedldap'),
                'server_name' => $server_name,
            ];
        }

        // Close connection immediately
        $this->close($connection);

        return [
            'connected' => true,
            'error' => null,
            'server_name' => $server_name,
        ];
    }

    /**
     * Perform LDAP search with connection and error handling
     * Automatically handles pagination if enabled in AuthLDAP configuration
     *
     * @param int $authldap_id AuthLDAP configuration ID
     * @param string $base_dn Base DN for search
     * @param string $filter LDAP filter
     * @return array{entries?: array<mixed>, error?: string} Returns ['entries' => array] on success or ['error' => string] on failure
     */
    public function searchWithErrorHandling(int $authldap_id, string $base_dn, string $filter): array
    {
        // SECURITY: Validate LDAP inputs to prevent LDAP injection attacks (RFC 4515)
        // For complete DN strings, validate structure but don't escape
        if (!$this->sanitizer->isValidDN($base_dn)) {
            return ['error' => __('Invalid LDAP Base DN syntax', 'advancedldap')];
        }

        $sanitized_filter = $this->sanitizer->sanitizeFilter($filter);
        if (!$sanitized_filter) {
            return ['error' => __('Invalid LDAP filter syntax', 'advancedldap')];
        }

        // Load AuthLDAP configuration to check pagination settings
        $authldap = new AuthLDAP();
        if (!$authldap->getFromDB($authldap_id)) {
            return ['error' => __('AuthLDAP configuration not found', 'advancedldap')];
        }

        $connection = $this->connect($authldap_id);
        if (!$connection) {
            return ['error' => __('Cannot connect to LDAP server', 'advancedldap')];
        }

        // Check if pagination is enabled in AuthLDAP configuration
        if (AuthLDAP::isLdapPageSizeAvailable($authldap)) {
            return $this->searchWithPagination($connection, $authldap, $base_dn, $sanitized_filter);
        } else {
            return $this->searchSimple($connection, $base_dn, $sanitized_filter);
        }
    }

    /**
     * Perform LDAP search with pagination (RFC 2696)
     * Automatically retrieves all pages and accumulates results
     *
     * @param mixed $connection LDAP connection resource
     * @param AuthLDAP $authldap AuthLDAP configuration object
     * @param string $base_dn Base DN for search
     * @param string $filter LDAP filter (already sanitized)
     * @return array{entries?: array<mixed>, error?: string} Returns ['entries' => array] on success or ['error' => string] on failure
     */
    private function searchWithPagination($connection, AuthLDAP $authldap, string $base_dn, string $filter): array
    {
        $all_entries = ['count' => 0];
        $total_count = 0;
        $page_count = 0;
        $cookie = '';

        $pagesize = (int) $authldap->fields['pagesize'];
        $ldap_maxlimit = (int) $authldap->fields['ldap_maxlimit'];

        Toolbox::logDebug(
            "GlpiLdapConnectionService: Pagination enabled (pagesize: $pagesize, maxlimit: " .
            ($ldap_maxlimit > 0 ? $ldap_maxlimit : 'unlimited') . ")"
        );

        do {
            $page_count++;

            // Configure pagination control (RFC 2696)
            $controls = [[
                'oid' => LDAP_CONTROL_PAGEDRESULTS,
                'iscritical' => true,
                'value' => [
                    'size' => $pagesize,
                    'cookie' => $cookie,
                ],
            ]];

            // Perform LDAP search with pagination control
            $sr = @ldap_search($connection, $base_dn, $filter, [], 0, -1, -1, LDAP_DEREF_NEVER, $controls);

            if ($sr === false) {
                $error = sprintf(
                    __('LDAP search failed: %s', 'advancedldap'),
                    $this->getError($connection)
                );
                $this->close($connection);
                return ['error' => $error];
            }

            // Parse result to extract pagination cookie (Safe function throws exception on error)
            try {
                ldap_parse_result($connection, $sr, $errcode, $matcheddn, $errmsg, $referrals, $controls);
            } catch (Exception $e) {
                $error = sprintf(
                    __('LDAP parse result failed: %s', 'advancedldap'),
                    $e->getMessage()
                );
                $this->close($connection);
                return ['error' => $error];
            }

            // Extract cookie for next page
            $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';

            // Get entries from current page
            $entries = $this->getEntries($connection, $sr);
            $page_entries = $entries['count'];
            $total_count += $page_entries;

            Toolbox::logDebug("GlpiLdapConnectionService: Page $page_count retrieved $page_entries entries (total: $total_count)");

            // Accumulate entries (skip 'count' key during merge)
            for ($i = 0; $i < $page_entries; $i++) {
                $all_entries[] = $entries[$i];
            }

            // Check if we've reached ldap_maxlimit
            if ($ldap_maxlimit > 0 && $total_count >= $ldap_maxlimit) {
                Toolbox::logDebug("GlpiLdapConnectionService: Reached ldap_maxlimit ($ldap_maxlimit), stopping pagination");
                break;
            }
        } while ($cookie !== '');

        $all_entries['count'] = $total_count;
        $this->close($connection);

        Toolbox::logDebug("GlpiLdapConnectionService: Pagination completed - $total_count entries retrieved in $page_count page(s)");

        return ['entries' => $all_entries];
    }

    /**
     * Perform simple LDAP search without pagination
     *
     * @param mixed $connection LDAP connection resource
     * @param string $base_dn Base DN for search
     * @param string $filter LDAP filter (already sanitized)
     * @return array{entries?: array<mixed>, error?: string} Returns ['entries' => array] on success or ['error' => string] on failure
     */
    private function searchSimple($connection, string $base_dn, string $filter): array
    {
        Toolbox::logDebug("GlpiLdapConnectionService: Pagination disabled, performing simple search");

        $search = $this->search($connection, $base_dn, $filter);
        if (!$search) {
            $error = sprintf(
                __('LDAP search failed: %s', 'advancedldap'),
                $this->getError($connection)
            );
            $this->close($connection);
            return ['error' => $error];
        }

        $entries = $this->getEntries($connection, $search);
        $this->close($connection);

        Toolbox::logDebug("GlpiLdapConnectionService: Simple search retrieved {$entries['count']} entries");

        return ['entries' => $entries];
    }
}
