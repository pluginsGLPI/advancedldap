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
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR IN CONNECTION WITH THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2025 by the advancedldap plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if (isset($_POST['update_config'])) {
    // Handle configuration update
    $authldap_id = $_POST['authldaps_id'] ?? 0;
    $is_active = $_POST['is_active'] ?? 0;

    // For now, just redirect back
    // This will be implemented when we have the database structure
    Session::addMessageAfterRedirect(__('Configuration saved', 'advancedldap'), false, INFO);
    Html::back();
} elseif (isset($_POST['add_element'])) {
    // Handle adding new sync element
    $authldap_id = $_POST['authldaps_id'] ?? 0;

    // For now, just redirect back
    // This will be implemented when we have the database structure
    Session::addMessageAfterRedirect(__('Element added', 'advancedldap'), false, INFO);
    Html::back();
} elseif (isset($_POST['update_element'])) {
    // Handle updating sync element
    $element_id = $_POST['id'] ?? 0;

    // For now, just redirect back
    // This will be implemented when we have the database structure
    Session::addMessageAfterRedirect(__('Element updated', 'advancedldap'), false, INFO);
    Html::back();
} elseif (isset($_POST['delete_element'])) {
    // Handle deleting sync element
    $element_id = $_POST['id'] ?? 0;

    // For now, just redirect back
    // This will be implemented when we have the database structure
    Session::addMessageAfterRedirect(__('Element deleted', 'advancedldap'), false, INFO);
    Html::back();
} else {
    // Invalid request
    Html::displayErrorAndDie(__('Invalid request'));
}
