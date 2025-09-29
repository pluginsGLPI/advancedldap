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

include('../../../inc/includes.php');

use GlpiPlugin\Advancedldap\Bootstrap;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;

// Check user rights - same as LDAP configuration
Session::checkRight('config', UPDATE);

// Validate required parameters
if (!isset($_POST['authldap_id']) || empty($_POST['authldap_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => __('Missing LDAP configuration ID', 'advancedldap')
    ]);
    exit;
}

$authldap_id = (int) $_POST['authldap_id'];
$replicate_id = isset($_POST['replicate_id']) ? (int) $_POST['replicate_id'] : -1;

try {
    // Initialize services using Bootstrap
    $container = Bootstrap::getContainer();
    $ldap_service = $container->get(LdapConnectionInterface::class);

    // Test the connection with detailed error reporting
    $result = $ldap_service->testConnectionWithDetails($authldap_id, $replicate_id);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($result);

} catch (Exception $e) {
    Toolbox::logDebug("Advanced LDAP - AJAX Connection Test Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => sprintf(__('Internal error: %s', 'advancedldap'), $e->getMessage())
    ]);
}