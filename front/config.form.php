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

global $CFG_GLPI;

Session::checkRight('config', UPDATE);

if (isset($_POST['update_config'])) {
    $show_inactive_generic_assets = isset($_POST['show_inactive_generic_assets']) ? intval($_POST['show_inactive_generic_assets']) : 0;
    $ldap_connection_filter = isset($_POST['ldap_connection_filter']) ? trim($_POST['ldap_connection_filter']) : '';
    $ldap_base_dn = isset($_POST['ldap_base_dn']) ? trim($_POST['ldap_base_dn']) : '';

    Config::setConfigurationValues('plugin:Advancedldap', [
        'show_inactive_generic_assets' => $show_inactive_generic_assets,
        'ldap_connection_filter' => $ldap_connection_filter,
        'ldap_base_dn' => $ldap_base_dn,
    ]);

    Session::addMessageAfterRedirect(__('Configuration updated successfully', 'advancedldap'), false, INFO);

    // Redirect back to the AuthLDAP form
    $authldap_id = $_POST['authldap_id'] ?? null;
    if ($authldap_id && is_numeric($authldap_id)) {
        Html::redirect($CFG_GLPI['root_doc'] . "/front/authldap.form.php?id=" . intval($authldap_id));
    } else {
        Html::redirect($CFG_GLPI['root_doc'] . "/front/authldap.php");
    }
} else if (isset($_POST['test_ldap_filter'])) {
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
