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

namespace GlpiPlugin\Advancedldap\Presenters;

use GlpiPlugin\Advancedldap\Models\SyncFilter;
use GlpiPlugin\Advancedldap\Models\AuthLdapSyncFilter;
use AuthLDAP;

/**
 * Presenter for SyncFilter form
 * Handles all data preparation and transformation logic for the form view
 */
class SyncFilterFormPresenter
{
    /**
     * Prepare all data needed for the form view
     *
     * @param SyncFilter $filter The sync filter instance
     * @param int $ID The sync filter ID
     * @param array<string, mixed> $options Form options
     * @return array<string, mixed> Prepared view model data
     */
    public function prepareViewModel(SyncFilter $filter, int $ID, array $options): array
    {
        return [
            'item' => $filter,
            'params' => $options,
            'authldap_context' => $this->resolveParentAuthLdap($ID, $options),
            'form_data' => $this->prepareFormData($filter),
            'available_fields' => $this->getAvailableFields($filter),
        ];
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
            // Get the first associated AuthLDAP
            $relation = new AuthLdapSyncFilter();
            $relations = $relation->find(['syncfilter_id' => $ID], [], 1);
            if ($relation_data = reset($relations)) {
                $current_authldap_id = $relation_data['authldap_id'];
                $current_authldap = new AuthLDAP();
                if (!$current_authldap->getFromDB($current_authldap_id)) {
                    $current_authldap = null;
                }
            }
        }

        // If we have a parent from options but no current relation, use the parent
        if ($parent_authldap && !$current_authldap) {
            $current_authldap = $parent_authldap;
            $current_authldap_id = $parent_authldap->getID();
        }

        return [
            'id' => $current_authldap_id,
            'authldap' => $current_authldap,
            'name' => $current_authldap instanceof AuthLDAP ? $current_authldap->getName() : null,
            'entity' => $current_authldap instanceof AuthLDAP ? $current_authldap->getEntityID() : null,
        ];
    }

    /**
     * Prepare form data from the filter
     *
     * @param SyncFilter $filter The sync filter instance
     * @return array<string, mixed> Form data
     */
    private function prepareFormData(SyncFilter $filter): array
    {
        return [
            'ldap_filter' => $filter->fields['ldap_filter'] ?? '',
            'base_dn' => $filter->fields['base_dn'] ?? '',
            'asset_type' => $filter->fields['asset_type'] ?? '',
            'mappings' => $filter->getFieldMappings(),
            'is_active' => $filter->fields['is_active'] ?? 1,
            'is_recursive' => $filter->fields['is_recursive'] ?? 0,
            'comment' => $filter->fields['comment'] ?? '',
        ];
    }

    /**
     * Get available fields for the current asset type
     *
     * @param SyncFilter $filter The sync filter instance
     * @return array<string, string> Available fields
     */
    private function getAvailableFields(SyncFilter $filter): array
    {
        // This would normally come from a service, but for now return basic fields
        // Asset type specific fields could be loaded here based on:
        // $asset_type = $filter->fields['asset_type'] ?? '';

        $common_fields = [
            'name' => __('Name'),
            'serial' => __('Serial number'),
            'otherserial' => __('Inventory number'),
            'comment' => __('Comments'),
            'locations_id' => __('Location'),
            'manufacturers_id' => __('Manufacturer'),
            'states_id' => __('Status'),
        ];

        return $common_fields;
    }
}
