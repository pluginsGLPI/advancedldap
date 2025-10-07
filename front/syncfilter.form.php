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
use GlpiPlugin\Advancedldap\Services\GlpiConfigurationService;
use GlpiPlugin\Advancedldap\Services\SyncFilterFormHelper;

$configService = new GlpiConfigurationService();

// Get security service for LDAP filter sanitization
$container = \GlpiPlugin\Advancedldap\Bootstrap::getContainer();
$ldapSanitizer = $container->get(\GlpiPlugin\Advancedldap\Contracts\LdapFilterSanitizerInterface::class);

Session::checkRight(SyncFilter::$rightname, READ);

// Use the helper method from SyncFilterFormHelper instead of local function
// This ensures consistency across the application

if (!isset($_GET['id'])) {
    $_GET['id'] = "";
}

$syncfilter = new SyncFilter();

if (isset($_POST["add"])) {
    $syncfilter->check(-1, CREATE, $_POST);

    // Remove empty id field to prevent MySQL error
    if (isset($_POST['id']) && $_POST['id'] === '') {
        unset($_POST['id']);
    }

    if ($newID = $syncfilter->add($_POST)) {
        $user_pref = $_SESSION['glpibackcreated'] ?? false;
        if ($user_pref) {
            // Build correct redirect URL (not using getLinkURL which points to wrong path)
            $redirect_url = $configService->getGlpiConfig('root_doc') . "/plugins/advancedldap/front/syncfilter.form.php?id=" . $newID;

            // Preserve authldap_id if it was provided
            $authldap_id = SyncFilterFormHelper::getAuthLdapIdFromRequest();
            if ($authldap_id > 0) {
                $redirect_url .= "&authldap_id=" . $authldap_id;
            }

            Html::redirect($redirect_url);
        }
    }
    Html::back();
} elseif (isset($_POST["purge"])) {
    $syncfilter->check($_POST['id'], PURGE);

    // Get authldap_id from POST data or URL before deletion
    $authldap_id = SyncFilterFormHelper::getAuthLdapIdFromRequest();

    if ($syncfilter->delete($_POST, true)) {

        // Redirect to parent AuthLDAP if we have the ID
        if ($authldap_id > 0) {
            Html::redirect($configService->getGlpiConfig('root_doc') . "/front/authldap.form.php?id=" . $authldap_id);
        } else {
            $syncfilter->redirectToList();
        }
    } else {
        Html::back();
    }
} elseif (isset($_POST["update"])) {
    $syncfilter->check($_POST['id'], UPDATE);

    // Check if this is a field mappings update from the dedicated tab
    if (isset($_POST['update_mappings']) && $_POST['update_mappings'] === '1') {
        // Handle field mappings update using dedicated method
        if ($syncfilter->getFromDB($_POST['id'])) {
            if ($syncfilter->updateFieldMappings($_POST)) {
                Session::addMessageAfterRedirect(__('Field mappings updated successfully', 'advancedldap'), false, INFO);
            } else {
                Session::addMessageAfterRedirect(__('Failed to update field mappings', 'advancedldap'), false, ERROR);
            }
        }
    } else {
        // Regular update of all fields
        $syncfilter->update($_POST);
    }

    Html::back();
} elseif (isset($_POST['sync_from_ldap'])) {
    // Handle LDAP synchronization
    $syncfilter_id = Toolbox::cleanInteger($_POST['id'] ?? 0);
    $authldap_id = SyncFilterFormHelper::getAuthLdapIdFromRequest();

    // Validate required parameters
    if ($syncfilter_id <= 0 || $authldap_id <= 0) {
        Session::addMessageAfterRedirect(__('Sync filter ID and AuthLDAP ID are required for synchronization', 'advancedldap'), false, ERROR);
        Html::back();
        exit;
    }

    // Load sync filter to validate it exists
    $syncfilter = new SyncFilter();
    if (!$syncfilter->getFromDB($syncfilter_id)) {
        Session::addMessageAfterRedirect(__('Sync filter not found', 'advancedldap'), false, ERROR);
        Html::back();
        exit;
    }

    // Check permissions
    if (!$syncfilter->can($syncfilter_id, UPDATE)) {
        Session::addMessageAfterRedirect(__('Permission denied', 'advancedldap'), false, ERROR);
        Html::back();
        exit;
    }

    try {
        // Get service container and sync service
        $container = \GlpiPlugin\Advancedldap\Bootstrap::getContainer();
        $sync_service = $container->get(\GlpiPlugin\Advancedldap\Services\LdapSyncService::class);

        // Perform synchronization
        $sync_results = $sync_service->synchronizeFromFilter($syncfilter_id, $authldap_id);

        if ($sync_results['success']) {
            // Success message with statistics
            $stats = $sync_results['stats'];
            $message = sprintf(
                __('Synchronization completed successfully! Created: %d, Updated: %d, Errors: %d', 'advancedldap'),
                $stats['created'],
                $stats['updated'],
                $stats['errors'],
            );
            Session::addMessageAfterRedirect($message, false, INFO);
        } else {
            // Error message
            $error_message = $sync_results['error'] ?? __('Unknown synchronization error', 'advancedldap');
            Session::addMessageAfterRedirect($error_message, false, ERROR);
        }

    } catch (Exception $e) {
        // Catch any unexpected errors
        $error_message = sprintf(__('Synchronization failed: %s', 'advancedldap'), $e->getMessage());
        Session::addMessageAfterRedirect($error_message, false, ERROR);
    }

    // Redirect back to form
    Html::back();
}

$menus = ["config", "auth", "SyncFilter"];
SyncFilter::displayFullPageForItem($_GET["id"], $menus, $_GET);
