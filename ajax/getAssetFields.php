<?php

/**
 * ---------------------------------------------------------------------
 *
 * Advanced LDAP Plugin for GLPI
 *
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.fr.html
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 *
 * ---------------------------------------------------------------------
 */

include('../../../inc/includes.php');

use GlpiPlugin\Advancedldap\Bootstrap;
use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;

// Check user rights - use entity right like other GLPI plugins
Session::checkRight('entity', \UPDATE);

// Validate required parameters
if (!isset($_POST['itemtype']) || empty($_POST['itemtype'])) {
    http_response_code(400);
    exit;
}

$itemtype = $_POST['itemtype'];
$selected = $_POST['selected'] ?? '';

try {
    // Initialize services using Bootstrap
    $container = Bootstrap::getContainer();
    $assetFieldProvider = $container->get(AssetFieldProviderInterface::class);

    // Get fields for the itemtype
    $fields = $assetFieldProvider->getItemTypeFields($itemtype);
} catch (Exception $e) {
    error_log("Advanced LDAP AJAX Error: " . $e->getMessage());
    http_response_code(500);
    exit;
}

// Generate dropdown HTML
echo "<select name='asset_field' class='form-select'>";
echo "<option value=''>" . __('Select a field', 'advancedldap') . "</option>";

foreach ($fields as $field_key => $field_label) {
    $field_key   = htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8');
    $field_label = htmlspecialchars($field_label, ENT_QUOTES, 'UTF-8');
    $selectedAttr = ($field_key === $selected) ? ' selected' : '';
    echo "<option value='$field_key'$selectedAttr>$field_label</option>";
}

echo "</select>";
