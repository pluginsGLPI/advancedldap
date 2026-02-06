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
 * @author    GLPI-Project
 * @copyright Copyright (C) GLPI-Project
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap;

use CommonDBTM;

use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\json_encode;

/**
 * Abstract class for inventory JSON builder mappings.
 *
 * Each concrete class (ComputerBuilderMapping, etc.) defines the JSON sections
 * used to build inventory data from LDAP entries.
 */
abstract class AbstractBuilderMapping extends CommonDBTM
{
    public static $rightname = 'config';

    /**
     * Get the GLPI itemtype this builder produces.
     *
     * @return string Itemtype class name (e.g., 'Computer')
     */
    abstract public static function getTargetItemtype(): string;

    /**
     * Get the base path for template files.
     *
     * @return string Path relative to plugin root (e.g., 'builder/computer/data')
     */
    abstract protected static function getTemplateBasePath(): string;

    /**
     * Get the list of JSON section names managed by this builder.
     *
     * @return array<string> Section names (e.g., ['main', 'hardware'])
     */
    abstract public static function getSectionNames(): array;

    /**
     * Get the database column name for a section.
     *
     * @param string $section Section name (e.g., 'hardware')
     * @return string Column name in the database table
     */
    public static function getColumnForSection(string $section): string
    {
        // By default, section name = column name
        // Override in child class if different
        return $section;
    }

    /**
     * Load default JSON content for a section from template file.
     *
     * @param string $section Section name (e.g., 'main', 'hardware')
     * @return array<string, mixed> Decoded JSON content
     */
    public static function loadDefaultTemplate(string $section): array
    {
        $base_path = static::getTemplateBasePath();
        $path = PLUGIN_ADVANCEDLDAP_DIR . '/' . $base_path . '/' . $section . '.json';

        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $decoded = json_decode($content, true);

        /** @var array<string, mixed> $result */
        $result = is_array($decoded) ? $decoded : [];
        return $result;
    }

    /**
     * Load all default templates for this builder.
     *
     * @return array<string, array<string, mixed>> Section name => decoded JSON
     */
    public static function loadAllDefaultTemplates(): array
    {
        $templates = [];
        foreach (static::getSectionNames() as $section) {
            $templates[$section] = static::loadDefaultTemplate($section);
        }

        return $templates;
    }

    /**
     * Get a section's JSON content as decoded array.
     *
     * @param string $section Section name
     * @return array<string, mixed> Decoded JSON or empty array
     */
    public function getSection(string $section): array
    {
        $column = static::getColumnForSection($section);
        $raw = $this->fields[$column] ?? null;

        if (empty($raw) || !is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        /** @var array<string, mixed> $result */
        $result = is_array($decoded) ? $decoded : [];
        return $result;
    }

    /**
     * Get all sections as decoded arrays.
     *
     * @return array<string, array<string, mixed>> Section name => decoded JSON
     */
    public function getAllSections(): array
    {
        $sections = [];
        foreach (static::getSectionNames() as $section) {
            $sections[$section] = $this->getSection($section);
        }

        return $sections;
    }

    /**
     * Prepare input with JSON-encoded sections for storage.
     *
     * @param array<string, mixed> $input Input data with section arrays
     * @return array<string, mixed> Input with JSON-encoded sections
     */
    protected function prepareJsonSections(array $input): array
    {
        foreach (static::getSectionNames() as $section) {
            $column = static::getColumnForSection($section);
            if (isset($input[$column]) && is_array($input[$column])) {
                $input[$column] = json_encode($input[$column], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }

        return $input;
    }

    /**
     * Create a new builder mapping with default template values.
     *
     * @return int|false ID of created item or false on failure
     */
    public function createWithDefaults(): int|false
    {
        $input = [
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s'),
        ];

        $templates = static::loadAllDefaultTemplates();
        foreach ($templates as $section => $content) {
            $column = static::getColumnForSection($section);
            $input[$column] = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $this->add($input);
    }

    /**
     * Reset a section to its default template value.
     *
     * @param string $section Section name to reset
     * @return bool Success
     */
    public function resetSection(string $section): bool
    {
        $default = static::loadDefaultTemplate($section);
        $column = static::getColumnForSection($section);

        return $this->update([
            'id'     => $this->getID(),
            $column  => json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Reset all sections to default template values.
     *
     * @return bool Success
     */
    public function resetAllSections(): bool
    {
        $input = ['id' => $this->getID()];
        $templates = static::loadAllDefaultTemplates();

        foreach ($templates as $section => $content) {
            $column = static::getColumnForSection($section);
            $input[$column] = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $this->update($input);
    }
}
