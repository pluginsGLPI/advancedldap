<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of advancedldap.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * AdvancedLDAP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdvancedLDAP. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2023 by Teclib'.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * -------------------------------------------------------------------------
 */

use GlpiPlugin\Advancedldap\SyncFilter;

include('../../../inc/includes.php');

// Check if plugin is activated
$plugin = new Plugin();
if (!$plugin->isActivated('advancedldap')) {
    Html::displayNotFoundError();
}

// Check access rights
Session::checkCentralAccess();

$syncfilter = new SyncFilter();

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Handle form submission
if (isset($_POST["update"])) {
    // Check update rights
    $syncfilter->check($_POST['id'], UPDATE);

    // Update the sync filter
    if ($syncfilter->update($_POST)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => __('Field mappings have been saved successfully', 'advancedldap')
            ]);
            exit;
        }

        Session::addMessageAfterRedirect(
            __('Field mappings have been saved successfully', 'advancedldap'),
            true,
            INFO
        );
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => __('Failed to save field mappings', 'advancedldap')
            ]);
            exit;
        }

        Session::addMessageAfterRedirect(
            __('Failed to save field mappings', 'advancedldap'),
            false,
            ERROR
        );
    }

    // Redirect back to the form
    Html::redirect($syncfilter->getLinkURL());
} else if (isset($_POST["add"])) {
    // Check create rights
    $syncfilter->check(-1, CREATE, $_POST);

    // Add new sync filter
    if ($newid = $syncfilter->add($_POST)) {
        Session::addMessageAfterRedirect(
            __('Sync filter has been created successfully', 'advancedldap'),
            true,
            INFO
        );
        Html::redirect($syncfilter->getLinkURL($newid));
    } else {
        Session::addMessageAfterRedirect(
            __('Failed to create sync filter', 'advancedldap'),
            false,
            ERROR
        );
        Html::back();
    }
} else if (isset($_POST["purge"])) {
    // Check delete rights
    $syncfilter->check($_POST['id'], PURGE);

    // Delete the sync filter
    if ($syncfilter->delete($_POST, true)) {
        Session::addMessageAfterRedirect(
            __('Sync filter has been deleted successfully', 'advancedldap'),
            true,
            INFO
        );
        $syncfilter->redirectToList();
    } else {
        Session::addMessageAfterRedirect(
            __('Failed to delete sync filter', 'advancedldap'),
            false,
            ERROR
        );
        Html::back();
    }
} else {
    // Display form
    Html::header(
        SyncFilter::getTypeName(Session::getPluralNumber()),
        $_SERVER['PHP_SELF'],
        'config',
        'plugin:advancedldap:syncfilter'
    );

    // Check read rights
    $syncfilter->checkGlobal(READ);

    // Show the sync filter form
    $syncfilter->display(['id' => $_GET['id'] ?? -1]);

    Html::footer();
}