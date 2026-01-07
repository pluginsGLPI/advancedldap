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
 * @copyright Copyright (C) 2024-2026 by advancedldap plugin team.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

// TODO: remove - test purpose only (entire file)

use GlpiPlugin\Advancedldap\Inventory\LdapSyncExecutor;
use GlpiPlugin\Advancedldap\SyncFilter;

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

Session::checkRight("config", UPDATE);

$syncfilters_id = $_POST['syncfilters_id'] ?? 0;

if (!is_numeric($syncfilters_id) || (int) $syncfilters_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => __('Invalid SyncFilter ID', 'advancedldap'),
    ]);
    exit;
}

$syncfilter = new SyncFilter();
if (!$syncfilter->getFromDB((int) $syncfilters_id)) {
    echo json_encode([
        'success' => false,
        'message' => __('SyncFilter not found', 'advancedldap'),
    ]);
    exit;
}

Toolbox::debug('AdvancedLDAP: === Starting Test Sync ===');
Toolbox::debug(sprintf('AdvancedLDAP: SyncFilter ID: %d, Name: %s', $syncfilter->getID(), $syncfilter->fields['name']));

$executor = new LdapSyncExecutor();
$results = $executor->executeForSyncFilter($syncfilter);

Toolbox::debug('AdvancedLDAP: === Test Sync Results ===');
Toolbox::debug($results);
Toolbox::debug('AdvancedLDAP: === End Test Sync ===');

echo json_encode([
    'success' => true,
    'results' => $results,
]);
