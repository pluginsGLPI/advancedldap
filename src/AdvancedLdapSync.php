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

namespace GlpiPlugin\Advancedldap;

use CommonGLPI;
use AuthLDAP;

/**
 * Main class for Advanced LDAP Sync functionality
 */
class AdvancedLdapSync extends CommonGLPI
{
    static $rightname = 'config';

    /**
     * Get tab name for AuthLDAP item
     *
     * @param CommonGLPI $item         Item for which tab is displayed
     * @param int        $withtemplate Template mode
     * @return array
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP && $item->can($item->getID(), READ)) {
            return [
                1 => self::createTabEntry(__('Items to synchronize', 'advancedldap'), 0, $item::class, "ti ti-adjustments-alt"),
            ];
        }
        return '';
    }

    /**
     * Display tab content for AuthLDAP item
     *
     * @param CommonGLPI $item      Item for which tab is displayed
     * @param int        $tabnum    Tab number
     * @param int        $withtemplate Template mode
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof AuthLDAP) {
            switch ($tabnum) {
                case 1:
                    self::showAdvancedSyncForm($item);
                    break;
            }
        }
        return true;
    }

    /**
     * Show advanced sync configuration form
     *
     * @param AuthLDAP $authldap AuthLDAP instance
     * @return void
     */
    public static function showAdvancedSyncForm(AuthLDAP $authldap)
    {
        $ID = $authldap->getField('id');
        
        if (!$authldap->can($ID, READ)) {
            return false;
        }

        echo "<div class='spaced'>";
        echo "<div class='center'>";
        echo "<h3>" . __('Advanced LDAP Synchronization', 'advancedldap') . "</h3>";
        echo "<p>" . __('Configuration will be implemented in a future version.', 'advancedldap') . "</p>";
        echo "</div>";
        echo "</div>";
    }
}