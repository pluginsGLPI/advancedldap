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

namespace GlpiPlugin\Advancedldap\Services;

use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use CronTask;
use Exception;

/**
 * Service for managing SyncFilter cron tasks
 * Extracted from SyncFilter to respect Single Responsibility Principle
 */
class SyncFilterCronService
{
    private SyncFilterRepositoryInterface $repository;
    private LdapSyncService $sync_service;

    public function __construct(
        SyncFilterRepositoryInterface $repository,
        LdapSyncService $sync_service
    ) {
        $this->repository = $repository;
        $this->sync_service = $sync_service;
    }

    /**
     * Execute automatic LDAP synchronization cron task
     *
     * @param CronTask|null $task CronTask instance for logging (null in tests)
     * @return int 0 = nothing to do, 1 = success, -1 = need to run again
     */
    public function executeSyncTask(?CronTask $task = null): int
    {
        // Get max filters to process from task parameter (0 = unlimited)
        $max_filters = 0;
        if ($task !== null && isset($task->fields['param'])) {
            $max_filters = (int) $task->fields['param'];
        }

        // Get all active sync filters with their AuthLDAP servers
        $filters_to_sync = $this->getAllActiveSyncFiltersWithAuthLdap();

        if ($filters_to_sync === []) {
            if ($task !== null) {
                $task->log(__('No active LDAP sync filters found', 'advancedldap'));
            }
            return 0; // Nothing to do
        }

        $total_filters = count($filters_to_sync);
        $processed_count = 0;
        $success_count = 0;
        $error_count = 0;
        $total_assets_synced = 0;

        if ($task !== null) {
            $msg = sprintf(__('Found %d active filter(s) to synchronize', 'advancedldap'), $total_filters);
            $task->log($msg);
        }

        // Process each filter (limit by max_filters if set)
        foreach ($filters_to_sync as $filter_data) {
            // Check if we've reached the max limit
            if ($max_filters > 0 && $processed_count >= $max_filters) {
                if ($task !== null) {
                    $msg = sprintf(__('Reached maximum limit of %d filters per execution', 'advancedldap'), $max_filters);
                    $task->log($msg);
                }
                break;
            }

            $syncfilter_id = (int) $filter_data['syncfilter_id'];
            $authldap_id = (int) $filter_data['authldap_id'];
            $filter_name = $filter_data['name'] ?? "ID {$syncfilter_id}";

            $processed_count++;

            try {
                // Synchronize using the existing LdapSyncService
                $sync_results = $this->sync_service->synchronizeFromFilter($syncfilter_id, $authldap_id);

                if ($sync_results['success']) {
                    $success_count++;
                    $assets_created = $sync_results['stats']['created'] ?? 0;
                    $assets_updated = $sync_results['stats']['updated'] ?? 0;
                    $assets_errors = $sync_results['stats']['errors'] ?? 0;
                    $total_assets_synced += ($assets_created + $assets_updated);

                    $msg = sprintf(
                        __('Filter "%s": %d created, %d updated, %d errors', 'advancedldap'),
                        mb_substr($filter_name, 0, 50),
                        $assets_created,
                        $assets_updated,
                        $assets_errors
                    );

                    if ($task !== null) {
                        $task->log($msg);
                    }
                } else {
                    $error_count++;
                    $error_msg = $sync_results['error'] ?? __('Unknown error', 'advancedldap');
                    $msg = sprintf(
                        __('Filter "%s": Error - %s', 'advancedldap'),
                        mb_substr($filter_name, 0, 50),
                        mb_substr($error_msg, 0, 100)
                    );

                    if ($task !== null) {
                        $task->log($msg);
                    }
                }
            } catch (Exception $e) {
                $error_count++;
                $msg = sprintf(
                    __('Filter "%s": Exception - %s', 'advancedldap'),
                    mb_substr($filter_name, 0, 50),
                    mb_substr($e->getMessage(), 0, 100)
                );

                if ($task !== null) {
                    $task->log($msg);
                }
            }
        }

        // Log final summary
        $summary = sprintf(
            __('Processed %d/%d filters: %d success, %d errors. Total assets: %d', 'advancedldap'),
            $processed_count,
            $total_filters,
            $success_count,
            $error_count,
            $total_assets_synced
        );

        if ($task !== null) {
            $task->log($summary);
            $task->setVolume($total_assets_synced);
        }

        // Return codes:
        // -1 = need to run again (more filters to process)
        // 0 = nothing to do
        // 1 = success
        if ($max_filters > 0 && $processed_count < $total_filters) {
            // More filters to process in next run
            return -1;
        }

        return $success_count > 0 ? 1 : 0;
    }

    /**
     * Get all active sync filters with their associated AuthLDAP servers
     * Uses existing repository methods to avoid SQL duplication
     *
     * @return array<int, array<string, mixed>> Array of filter data
     */
    private function getAllActiveSyncFiltersWithAuthLdap(): array
    {
        // Get all active sync filters from repository
        $active_filters = $this->repository->getActiveSyncFilters();

        if ($active_filters === []) {
            return [];
        }

        // Get active AuthLDAP servers
        $active_authldap_servers = $this->repository->getActiveAuthLdapServers();
        $active_authldap_ids = array_column($active_authldap_servers, 'id');

        $results = [];

        // For each active filter, get its associated AuthLDAP servers
        foreach ($active_filters as $filter) {
            $syncfilter_id = (int) $filter['id'];

            // Get associated AuthLDAP IDs for this filter (already filtered by is_active)
            $authldap_ids = $this->repository->getAssociatedAuthLdaps($syncfilter_id);

            if ($authldap_ids === []) {
                continue;
            }

            // Only include filters that have at least one active AuthLDAP server
            foreach ($authldap_ids as $authldap_id) {
                if (in_array($authldap_id, $active_authldap_ids, true)) {
                    $results[] = [
                        'syncfilter_id' => $syncfilter_id,
                        'name' => $filter['name'] ?? '',
                        'base_dn' => $filter['base_dn'] ?? '',
                        'ldap_filter' => $filter['ldap_filter'] ?? '',
                        'asset_type' => $filter['asset_type'] ?? '',
                        'authldap_id' => $authldap_id,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get cron task information
     *
     * @param string $name Task name
     * @return array<string, string> Task information
     */
    public static function getCronInfo(string $name): array
    {
        return [
            'description' => __('Automatically synchronize active LDAP filters with GLPI assets', 'advancedldap'),
            'parameter'   => __('Maximum number of filters to process per execution (0 = unlimited)', 'advancedldap'),
        ];
    }
}
