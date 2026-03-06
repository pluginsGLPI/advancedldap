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

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Advancedldap\Inventory\LdapSyncExecutor;
use GlpiPlugin\Advancedldap\SyncFilter;

use function Safe\json_encode;

header('Content-Type: application/json');

Session::checkLoginUser();

$action         = $_POST['action'] ?? null;
$syncfilters_id = (int)($_POST['syncfilters_id'] ?? 0);

$syncfilter = new SyncFilter();
if ($syncfilters_id <= 0 || !$syncfilter->getFromDB($syncfilters_id)) {
    throw new BadRequestHttpException('Invalid SyncFilter');
}

$required_right = ($action === 'execute') ? UPDATE : READ;
$syncfilter->check($syncfilters_id, $required_right);

$executor = new LdapSyncExecutor();

switch ($action) {
    case 'execute':
        session_write_close();
        $results = $executor->executeSingleFilter($syncfilter);
        echo json_encode(['success' => true, 'results' => $results]);
        break;
    case 'dry_run':
        $preview = $executor->previewSyncFilter($syncfilter);
        echo json_encode(['success' => true, 'preview' => $preview]);
        break;
    default:
        throw new BadRequestHttpException('Unknown action');
}
