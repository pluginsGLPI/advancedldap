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

namespace GlpiPlugin\Advancedldap;

use AuthLDAP;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Advancedldap\Container\ServiceContainer;

/**
 * Advanced LDAP synchronization functionality
 */
class AdvancedLdapSync extends CommonGLPI
{
    public static $rightname = 'config';

    private ServiceContainer $container;

    /**
     * @param ServiceContainer|null $container Optional service container
     */
    public function __construct(?ServiceContainer $container = null)
    {
        parent::__construct();

        $this->container = $container ?? ServiceContainer::getInstance();
    }

    /**
     * Get tab name for AuthLDAP item
     *
     * @param CommonGLPI $item         Item for which tab is displayed
     * @param int        $withtemplate Template mode
     * @return array<int, string>|string Tab names or empty string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $item->can($item->getID(), \READ)) {
            $nb = 0;
            if ($_SESSION['glpishow_count_on_tabs']) {
                $nb = $this->countSyncFiltersForAuthLdap($item->getID());
            }

            return self::createTabEntry(
                __('Advanced sync', 'advancedldap'),
                $nb,
                $item::class,
                "ti ti-filter",
            );
        }
        return '';
    }

    /**
     * Display tab content for AuthLDAP item
     *
     * @param CommonGLPI $item      Item for which tab is displayed
     * @param int        $tabnum    Tab number
     * @param int        $withtemplate Template mode
     * @return bool Success status
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof AuthLDAP) {
            $instance = new self();
            $instance->showSyncFiltersList($item);
        }
        return true;
    }

    /**
     * Show sync filters list for AuthLDAP
     *
     * @param AuthLDAP $authldap AuthLDAP instance
     * @return void
     */
    public function showSyncFiltersList(AuthLDAP $authldap): void
    {
        $id = $authldap->getField('id');

        if (!$authldap->can($id, \READ)) {
            return;
        }

        // Get sync filters for this AuthLDAP
        $sync_filters = $this->getSyncFiltersForAuthLdap($id);

        TemplateRenderer::getInstance()->display('@advancedldap/syncfilters_list.html.twig', [
            'authldap' => $authldap,
            'sync_filters' => $sync_filters,
            'can_edit' => $authldap->can($id, \UPDATE),
        ]);
    }



    /**
     * Count sync filters for this AuthLDAP instance
     *
     * @param int $authldap_id AuthLDAP ID
     * @return int Number of filters
     */
    public function countSyncFiltersForAuthLdap(int $authldap_id): int
    {
        return countElementsInTable(
            'glpi_plugin_advancedldap_authldap_syncfilters',
            [
                'authldap_id' => $authldap_id,
                // Removed is_active filter to count ALL relations (active and inactive)
            ],
        );
    }

    /**
     * Get sync filters for this AuthLDAP instance
     *
     * @param int $authldap_id AuthLDAP ID
     * @return array
     */
    public function getSyncFiltersForAuthLdap(int $authldap_id): array
    {
        $repository = $this->container->get(\GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface::class);
        return $repository->getSyncFiltersForAuthLdapDetailed($authldap_id);
    }

    /**
     * Get all available sync filters
     *
     * @return array
     */
    public function getAvailableSyncFilters(): array
    {
        $sync_filter_service = $this->container->get(\GlpiPlugin\Advancedldap\Services\SyncFilterService::class);
        return $sync_filter_service->getAvailableSyncFilters();
    }



}
