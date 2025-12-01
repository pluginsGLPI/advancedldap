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

Session::checkCentralAccess();

$syncfilter = new SyncFilter();

if (isset($_POST["add"])) {
    $syncfilter->check(-1, CREATE, $_POST);
    if ($newID = $syncfilter->add($_POST)) {
        if ($_SESSION['glpibackcreated']) {
            Html::redirect($syncfilter->getLinkURL());
        }
    }
    Html::back();
} elseif (isset($_POST["update"])) {
    $syncfilter->check($_POST['id'], UPDATE);
    $syncfilter->update($_POST);
    Html::back();
} else {
    $menus = ["config", "commondropdown", SyncFilter::class];
    SyncFilter::displayFullPageForItem($_GET["id"] ?? "", $menus, [
        'formoptions' => "data-track-changes=true",
    ]);
}
