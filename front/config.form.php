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

Session::checkRight('config', UPDATE);

if (isset($_POST['update_config'])) {
    $show_inactive_generic_assets = isset($_POST['show_inactive_generic_assets']) ? intval($_POST['show_inactive_generic_assets']) : 0;
    
    Config::setConfigurationValues('plugin:Advancedldap', [
        'show_inactive_generic_assets' => $show_inactive_generic_assets
    ]);
    
    Session::addMessageAfterRedirect(__('Configuration updated successfully', 'advancedldap'), false, INFO);
    
    // Redirect back to the AuthLDAP form
    if (isset($_POST['authldap_id']) && is_numeric($_POST['authldap_id'])) {
        Html::redirect($CFG_GLPI['root_doc'] . "/front/authldap.form.php?id=" . intval($_POST['authldap_id']));
    } else {
        Html::redirect($CFG_GLPI['root_doc'] . "/front/authldap.php");
    }
}

Html::displayErrorAndDie("Lost");