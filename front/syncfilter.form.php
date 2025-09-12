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

use GlpiPlugin\Advancedldap\Models\SyncFilter;
use GlpiPlugin\Advancedldap\Bootstrap;
use Glpi\Event;

Session::checkRight(SyncFilter::$rightname, READ);

if (!isset($_GET['id'])) {
    $_GET['id'] = "";
}

$syncfilter = new SyncFilter();

if (isset($_POST["add"])) {
    $syncfilter->check(-1, CREATE, $_POST);
    
    if ($newID = $syncfilter->add($_POST)) {
        Event::log(
            $newID,
            "syncfilter",
            4,
            "setup",
            sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"])
        );
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($syncfilter->getLinkURL());
        }
    }
    Html::back();
} elseif (isset($_POST["purge"])) {
    $syncfilter->check($_POST['id'], PURGE);
    
    if ($syncfilter->delete($_POST, true)) {
        Event::log(
            $_POST['id'],
            "syncfilter", 
            4,
            "setup",
            sprintf(__('%s purges an item'), $_SESSION["glpiname"])
        );
        $syncfilter->redirectToList();
    } else {
        Html::back();
    }
} elseif (isset($_POST["update"])) {
    $syncfilter->check($_POST['id'], UPDATE);
    
    $syncfilter->update($_POST);
    Event::log(
        $_POST['id'],
        "syncfilter",
        4,
        "setup", 
        sprintf(__('%s updates an item'), $_SESSION["glpiname"])
    );
    Html::back();
} elseif (isset($_POST['test_ldap_filter'])) {
    // Handle LDAP filter test
    $syncfilter_id = $_POST['id'] ?? 0;
    $authldap_id = $_POST['authldap_id'] ?? 0;
    $ldap_base_dn = trim($_POST['base_dn'] ?? '');
    $ldap_connection_filter = trim($_POST['ldap_filter'] ?? '');
    $asset_type = $_POST['asset_type'] ?? '';
    $asset_field_mappings = $_POST['field_mappings'] ?? [];
    
    // Get field mappings and first field for testing
    $field_mappings_json = $_POST['field_mappings'] ?? '{}';
    if (is_array($field_mappings_json)) {
        // Convert array to JSON if needed
        $field_mappings = $field_mappings_json;
    } else {
        // Parse JSON string
        $field_mappings = json_decode($field_mappings_json, true) ?: [];
    }
    
    $asset_field = !empty($field_mappings) ? array_key_first($field_mappings) : '';
    
    if ($authldap_id && $ldap_base_dn && $ldap_connection_filter) {
        // Redirect back to the form with test parameters
        global $CFG_GLPI;
        $redirect_url = $CFG_GLPI['root_doc'] . "/plugins/advancedldap/front/syncfilter.form.php?id=" . $syncfilter_id;
        $redirect_url .= "&test_ldap=1";
        $redirect_url .= "&test_authldap_id=" . urlencode($authldap_id);
        $redirect_url .= "&test_base_dn=" . urlencode($ldap_base_dn);
        $redirect_url .= "&test_filter=" . urlencode($ldap_connection_filter);
        $redirect_url .= "&test_asset_type=" . urlencode($asset_type);
        $redirect_url .= "&test_asset_field=" . urlencode($asset_field);
        
        Html::redirect($redirect_url);
    } else {
        Session::addMessageAfterRedirect(__('Please select an AuthLDAP server and provide Base DN and Filter', 'advancedldap'), false, ERROR);
        Html::back();
    }
}

$menus = ["config", "auth", "SyncFilter"];
SyncFilter::displayFullPageForItem($_GET["id"], $menus, $_GET);