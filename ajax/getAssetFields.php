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
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
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
    $asset_field_provider = $container->get(AssetFieldProviderInterface::class);

    // Get fields for the itemtype
    $fields = $asset_field_provider->getItemTypeFields($itemtype);
} catch (Exception $e) {
    Toolbox::logDebug("Advanced LDAP - AJAX Error: " . $e->getMessage());
    http_response_code(500);
    exit;
}

// Generate dropdown HTML
echo "<select name='asset_field' class='form-select'>";
echo "<option value=''>" . __('Select a field', 'advancedldap') . "</option>";

foreach ($fields as $field_key => $field_label) {
    $field_key   = htmlspecialchars($field_key, ENT_QUOTES, 'UTF-8');
    $field_label = htmlspecialchars($field_label, ENT_QUOTES, 'UTF-8');
    $selected_attr = ($field_key === $selected) ? ' selected' : '';
    echo "<option value='$field_key'$selected_attr>$field_label</option>";
}

echo "</select>";
