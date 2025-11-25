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

(function($) {
    'use strict';

    var RelationForm = {
        init: function() {
            this.bindEvents();
            this.updateButtonState();
        },

        bindEvents: function() {
            var self = this;

            $(document).on('select2:select', '[data-relation-dropdown="true"]', function(e) {
                var selectedValue = null;
                if (e.params && e.params.data && e.params.data.id) {
                    selectedValue = e.params.data.id;
                }
                self.updateButtonState(selectedValue);
            });
        },

        updateButtonState: function(value) {
            var $button = $('#relation-add-button');

            if ($button.length === 0) {
                return;
            }

            var selectedValue = value;

            if (!selectedValue) {
                var $dropdown = $('[data-relation-dropdown="true"]');
                if ($dropdown.length > 0) {
                    selectedValue = $dropdown.val();
                }
            }

            if (selectedValue && selectedValue !== '0' && selectedValue !== '') {
                $button.prop('disabled', false);
            } else {
                $button.prop('disabled', true);
            }
        }
    };

    window.AdvancedLdapRelationForm = RelationForm;

})(jQuery);
