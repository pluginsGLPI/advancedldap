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

include('../../../inc/includes.php');

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Advancedldap\Bootstrap;

global $CFG_GLPI;

Session::checkRight('config', UPDATE);

if (isset($_POST['save_filter'])) {
    // Save sync options (both filter and general config)
    $authldap_id = intval($_POST['authldap_id'] ?? 0);
    $syncfilter_id = intval($_POST['syncfilter_id'] ?? 0);
    $filter_name = trim($_POST['filter_name'] ?? '');
    $ldap_base_dn = trim($_POST['ldap_base_dn'] ?? '');
    $ldap_connection_filter = trim($_POST['ldap_connection_filter'] ?? '');
    $asset_type = trim($_POST['asset_type'] ?? '');
    $asset_field = trim($_POST['asset_field'] ?? '');
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    
    // Save sync filter if all required fields are provided
    if ($authldap_id && $filter_name && $ldap_base_dn && $ldap_connection_filter && $asset_type && $asset_field) {
        try {
            $container = Bootstrap::getContainer();
            $syncFilterService = $container->get(\GlpiPlugin\Advancedldap\Services\SyncFilterService::class);
            
            // Prepare field mappings - simple 1:1 mapping for now
            $field_mappings = [$asset_field => $asset_field];
            
            // Always create a new filter for now (we can add edit functionality later)
            $final_syncfilter_id = $syncFilterService->createSyncFilter(
                $filter_name,
                $ldap_connection_filter,
                $ldap_base_dn,
                $asset_type,
                $field_mappings,
                $is_active
            );
            
            if ($final_syncfilter_id) {
                // Create relation between AuthLDAP and the new SyncFilter
                $relationRepository = $container->get(\GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface::class);
                $relationRepository->addSyncFilterToAuthLdap($authldap_id, $final_syncfilter_id, $is_active);
                
                Session::addMessageAfterRedirect(__('Sync options saved successfully', 'advancedldap'), false, INFO);
            } else {
                Session::addMessageAfterRedirect(__('Error saving sync filter', 'advancedldap'), false, ERROR);
            }
        } catch (\Exception $e) {
            Session::addMessageAfterRedirect(__('Error: ', 'advancedldap') . $e->getMessage(), false, ERROR);
        }
    } else {
        // Sync filter fields are incomplete
        Session::addMessageAfterRedirect(__('Please fill all required fields to save sync filter', 'advancedldap'), false, ERROR);
    }
    
    // Redirect back to the AuthLDAP form
    Html::redirect($CFG_GLPI['root_doc'] . "/front/authldap.form.php?id=" . $authldap_id);
} elseif (isset($_POST['test_ldap_filter'])) {
    // Handle LDAP filter test
    $authldap_id = $_POST['authldap_id'] ?? null;
    $ldap_base_dn = trim($_POST['ldap_base_dn'] ?? '');
    $ldap_connection_filter = trim($_POST['ldap_connection_filter'] ?? '');
    $asset_type = $_POST['asset_type'] ?? '';
    $asset_field = $_POST['asset_field'] ?? '';

    // Redirect back to the AuthLDAP form with test parameters
    $redirect_url = $CFG_GLPI['root_doc'] . "/front/authldap.form.php?id=" . intval($authldap_id);
    $redirect_url .= "&test_ldap=1";
    $redirect_url .= "&test_base_dn=" . urlencode($ldap_base_dn);
    $redirect_url .= "&test_filter=" . urlencode($ldap_connection_filter);
    $redirect_url .= "&test_asset_type=" . urlencode($asset_type);
    $redirect_url .= "&test_asset_field=" . urlencode($asset_field);

    Html::redirect($redirect_url);
}

throw new BadRequestHttpException("Lost");
