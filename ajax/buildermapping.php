<?php

/**
 * -------------------------------------------------------------------------
 * AdvancedLDAP plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of AdvancedLDAP.
 *
 * AdvancedLDAP is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 */

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Advancedldap\AbstractBuilderMapping;

use function Safe\json_encode;

header('Content-Type: application/json');

Session::checkLoginUser();

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$builder_itemtype = $_POST['builder_itemtype'] ?? $_GET['builder_itemtype'] ?? null;

if (!is_string($action) || empty($action) || !is_string($builder_itemtype) || empty($builder_itemtype)) {
    throw new BadRequestHttpException('Missing action or builder_itemtype');
}

if (!class_exists($builder_itemtype) || !is_subclass_of($builder_itemtype, AbstractBuilderMapping::class)) {
    throw new BadRequestHttpException('Invalid builder_itemtype');
}

switch ($action) {
    case 'get_default_section':
        $section = $_POST['section'] ?? $_GET['section'] ?? null;
        if (!is_string($section) || empty($section)) {
            throw new BadRequestHttpException('Missing section parameter');
        }

        $default_content = $builder_itemtype::loadDefaultTemplate($section);
        echo json_encode([
            'success' => true,
            'content' => json_encode($default_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
        break;

    case 'get_all_default_sections':
        $defaults = $builder_itemtype::loadAllDefaultTemplates();
        $sections = [];
        foreach ($defaults as $section_name => $content) {
            $sections[$section_name] = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        echo json_encode([
            'success'  => true,
            'sections' => $sections,
        ]);
        break;

    default:
        throw new BadRequestHttpException('Unknown action');
}
