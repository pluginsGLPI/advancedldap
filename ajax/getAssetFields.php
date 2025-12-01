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
 * @copyright Copyright (C) 2024 by advancedldap plugin team.
 * @license   GPLv3+ https://www.gnu.org/licenses/gpl-3.0.html
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://services.glpi-network.com
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

use GlpiPlugin\Advancedldap\Service\FieldMappingService;

header('Content-Type: text/html; charset=UTF-8');
Html::header_nocache();

Session::checkLoginUser();
Session::checkRight("config", READ);

$itemtype = $_GET['itemtype'] ?? '';
$name = $_GET['name'] ?? 'glpi_field';
$selected = $_GET['selected'] ?? '';

if (empty($itemtype)) {
    echo '';
    exit;
}

$service = FieldMappingService::getInstance();
$available_fields = $service->getAvailableFields($itemtype);

if (empty($available_fields)) {
    echo '';
    exit;
}

// Add empty option at the beginning
$fields_with_empty = ['' => '---'] + $available_fields;

Dropdown::showFromArray(
    $name,
    $fields_with_empty,
    [
        'value' => $selected,
        'display_emptychoice' => false,
        'width' => '100%',
    ]
);
