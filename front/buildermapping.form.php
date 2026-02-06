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

use GlpiPlugin\Advancedldap\AbstractBuilderMapping;

use function Safe\json_decode;

Session::checkLoginUser();
Session::checkRight('config', UPDATE);

$builder_itemtype = $_POST['builder_itemtype'] ?? null;
$raw_id = $_POST['id'] ?? 0;
$id = is_numeric($raw_id) ? (int) $raw_id : 0;

if (!is_string($builder_itemtype) || empty($builder_itemtype) || $id <= 0) {
    Session::addMessageAfterRedirect(
        __('Invalid parameters.', 'advancedldap'),
        false,
        ERROR,
    );
    Html::back();
}

if (!class_exists($builder_itemtype) || !is_subclass_of($builder_itemtype, AbstractBuilderMapping::class)) {
    Session::addMessageAfterRedirect(
        __('Invalid builder type.', 'advancedldap'),
        false,
        ERROR,
    );
    Html::back();
}

/** @var AbstractBuilderMapping $builder */
$builder = new $builder_itemtype();

if (!$builder->getFromDB($id)) {
    Session::addMessageAfterRedirect(
        __('Builder mapping not found.', 'advancedldap'),
        false,
        ERROR,
    );
    Html::back();
}

if (isset($_POST['update'])) {
    $sections = $_POST['sections'] ?? [];
    $input = ['id' => $id];

    if (is_array($sections)) {
        foreach ($sections as $section_name => $json_content) {
            if (!is_string($section_name) || !is_string($json_content)) {
                continue;
            }
            // Validate JSON
            try {
                json_decode($json_content, true);
            } catch (\Safe\Exceptions\JsonException $e) {
                Session::addMessageAfterRedirect(
                    sprintf(__('Invalid JSON in section "%s": %s', 'advancedldap'), $section_name, $e->getMessage()),
                    false,
                    ERROR,
                );
                Html::back();
            }

            $column = $builder_itemtype::getColumnForSection($section_name);
            $input[$column] = $json_content;
        }
    }

    if ($builder->update($input)) {
        Session::addMessageAfterRedirect(
            __('Builder mapping updated successfully.', 'advancedldap'),
            false,
            INFO,
        );
    } else {
        Session::addMessageAfterRedirect(
            __('Failed to update builder mapping.', 'advancedldap'),
            false,
            ERROR,
        );
    }
}

Html::back();
