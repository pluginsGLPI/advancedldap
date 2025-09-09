<?php

/**
 * ---------------------------------------------------------------------
 * 
 * Advanced LDAP Plugin for GLPI
 * 
 * @copyright 2024
 * @license   https://www.gnu.org/licenses/gpl-3.0.html
 * 
 * ---------------------------------------------------------------------
 */

include ('../../../inc/includes.php');

// Include plugin classes
require_once(GLPI_ROOT . '/plugins/advancedldap/src/AssetFieldManager.php');

// Check user rights - use entity right like other GLPI plugins
Session::checkRight('entity', UPDATE);

// Validate required parameters
if (!isset($_POST['itemtype']) || empty($_POST['itemtype'])) {
    http_response_code(400);
    exit;
}

$itemtype = $_POST['itemtype'];

// Get fields for the itemtype
$fields = AssetFieldManager::getItemTypeFields($itemtype);

// Generate dropdown HTML
echo "<select name='asset_field' class='form-select'>";
echo "<option value=''>" . __('Select a field', 'advancedldap') . "</option>";

foreach ($fields as $field_key => $field_label) {
    $field_key = htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8');
    $field_label = htmlspecialchars($field_label, ENT_QUOTES, 'UTF-8');
    echo "<option value='$field_key'>$field_label</option>";
}

echo "</select>";