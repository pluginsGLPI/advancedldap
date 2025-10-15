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

namespace GlpiPlugin\Advancedldap\Models;

use CommonDBRelation;

/**
 * AuthLdapSyncFilter class for managing relations between AuthLDAP and SyncFilter
 */
class AuthLdapSyncFilter extends CommonDBRelation
{
    /** @var string */
    public static $rightname = 'config';
    /** @var string */
    public static $table = 'glpi_plugin_advancedldap_authldap_syncfilters';

    /** @var string */
    public static $itemtype_1 = 'AuthLDAP';
    /** @var string */
    public static $items_id_1 = 'authldap_id';

    /** @var string */
    public static $itemtype_2 = SyncFilter::class;
    /** @var string */
    public static $items_id_2 = 'syncfilter_id';

    /**
     * Get the table name for this class
     *
     * @param string|null $classname Class name
     * @return string
     */
    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_advancedldap_authldap_syncfilters';
    }

    /**
     * Get the type name for this class
     *
     * @param int $nb Number of items (for plural)
     * @return string
     */
    public static function getTypeName($nb = 0): string
    {
        return _n('AuthLDAP - Sync Filter', 'AuthLDAP - Sync Filters', $nb, 'advancedldap');
    }

    /**
     * Get search options for this class
     *
     * @return array<int, array<string, mixed>>
     */
    public function rawSearchOptions(): array
    {
        $tab = [];

        $tab[] = [
            'id'                => 'common',
            'name'              => __('Characteristics'),
        ];

        $tab[] = [
            'id'                => '1',
            'table'             => 'glpi_authldaps',
            'field'             => 'name',
            'name'              => __('AuthLDAP'),
            'datatype'          => 'itemlink',
            'itemlink_type'     => 'AuthLDAP',
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '2',
            'table'             => SyncFilter::getTable(),
            'field'             => 'name',
            'name'              => __('Sync Filter', 'advancedldap'),
            'datatype'          => 'itemlink',
            'itemlink_type'     => SyncFilter::class,
            'massiveaction'     => false,
        ];

        $tab[] = [
            'id'                => '3',
            'table'             => static::getTable(),
            'field'             => 'is_active',
            'name'              => __('Active'),
            'datatype'          => 'bool',
        ];

        $tab[] = [
            'id'                => '121',
            'table'             => static::getTable(),
            'field'             => 'date_creation',
            'name'              => __('Creation date'),
            'datatype'          => 'datetime',
            'massiveaction'     => false,
        ];

        return $tab;
    }

    /**
     * Define tabs to display on form
     *
     * @param array<string, mixed> $options Parameters
     * @return array<string, string>
     */
    public function defineTabs($options = []): array
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        return $ong;
    }

}
