/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of advancedldap.
 *
 * advancedldap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * advancedldap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with advancedldap. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2024 by advancedldap plugin team.
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

(function($) {
    'use strict';

    /**
     * Field Mapping Management
     * Handles the dynamic mapping interface between GLPI fields and LDAP attributes
     */
    var FieldMapping = {
        rowIndex: 0,
        itemtype: '',
        syncfilterId: 0,
        csrfToken: '',
        currentMappings: [],

        /**
         * Initialize the field mapping interface
         */
        init: function(config) {
            this.currentMappings = config.currentMappings || [];
            this.rowIndex = this.currentMappings.length;
            this.itemtype = config.itemtype || '';
            this.syncfilterId = config.syncfilterId || 0;
            this.csrfToken = config.csrfToken || '';

            // Load all existing field dropdowns
            this.loadExistingDropdowns();

            // Ensure there's always at least one empty row
            if ($('.field-mapping-row').length === 0) {
                this.addNewRow();
            }

            // Bind event handlers
            this.bindEvents();

            // Initial update of hidden JSON
            this.updateMappingsJson();
        },

        /**
         * Load dropdowns for existing mapping rows
         */
        loadExistingDropdowns: function() {
            var self = this;
            $('.field-mapping-row').each(function() {
                var index = $(this).data('index');
                var selectedField = $(this).find('[id^="glpi-field-dropdown-"]').data('selected');
                self.loadFieldDropdown(index, selectedField);
            });
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;

            // Handle remove button click
            $(document).on('click', '.remove-mapping-row', function() {
                self.handleRemoveRow($(this));
            });

            // Handle save button click
            $('#save-mappings-btn').on('click', function() {
                self.handleSave($(this));
            });
        },

        /**
         * Update the hidden JSON input with current mappings
         */
        updateMappingsJson: function() {
            var mappings = {};
            $('.field-mapping-row').each(function() {
                var glpiField = $(this).find('select[name^="glpi_field_"]').val();
                var ldapAttr = $(this).find('.ldap-attribute-input').val();

                if (glpiField && ldapAttr) {
                    mappings[glpiField] = ldapAttr;
                }
            });

            $('#field_mappings_json').val(JSON.stringify(mappings));

            // Also update the field_mappings field in the main form if it exists (for tab integration)
            this.syncWithParentForm(mappings);
        },

        /**
         * Sync mappings with parent form if in iframe/tab context
         */
        syncWithParentForm: function(mappings) {
            if (window.parent && window.parent.document !== document) {
                var mainForm = $(window.parent.document).find('form[name="asset_form"]');
                if (mainForm.length) {
                    var hiddenField = mainForm.find('input[name="field_mappings"]');
                    if (hiddenField.length === 0) {
                        hiddenField = $('<input>').attr({
                            type: 'hidden',
                            name: 'field_mappings',
                            value: JSON.stringify(mappings)
                        });
                        mainForm.append(hiddenField);
                    } else {
                        hiddenField.val(JSON.stringify(mappings));
                    }
                }
            }
        },

        /**
         * Get all currently selected fields across all rows
         */
        getSelectedFields: function() {
            var fields = [];
            $('.field-mapping-row select[name^="glpi_field_"]').each(function() {
                var value = $(this).val();
                if (value) {
                    fields.push(value);
                }
            });
            return fields;
        },

        /**
         * Load field dropdown for a specific row via AJAX
         */
        loadFieldDropdown: function(index, selectedField) {
            var self = this;
            var container = $('#glpi-field-dropdown-' + index);
            var allSelectedFields = this.getSelectedFields();

            $.ajax({
                url: CFG_GLPI.root_doc + '/plugins/advancedldap/ajax/getAssetFields.php',
                type: 'GET',
                data: {
                    itemtype: this.itemtype,
                    name: 'glpi_field_' + index,
                    selected: selectedField || ''
                },
                success: function(data) {
                    container.html(data);

                    // Disable already selected options in other dropdowns
                    var select = container.find('select');
                    allSelectedFields.forEach(function(field) {
                        if (field !== selectedField) {
                            select.find('option[value="' + field + '"]').prop('disabled', true);
                        }
                    });

                    // Store initial value
                    if (selectedField) {
                        select.data('previous-value', selectedField);
                    }

                    // Add change handler
                    self.bindDropdownChangeHandler(select, container, index);

                    // Add input handler for LDAP attribute
                    container.closest('.field-mapping-row').find('.ldap-attribute-input').on('input', function() {
                        self.updateMappingsJson();
                    });
                },
                error: function(xhr, status, error) {
                    container.html('<div class="alert alert-danger">Error loading fields</div>');
                    console.error('Error loading field dropdown:', error);
                }
            });
        },

        /**
         * Bind change handler to field dropdown
         */
        bindDropdownChangeHandler: function(select, container, index) {
            var self = this;

            select.on('change', function() {
                var row = container.closest('.field-mapping-row');
                var ldapInput = row.find('.ldap-attribute-input');
                var removeBtn = row.find('.remove-mapping-row');
                var previousValue = $(this).data('previous-value');
                var newValue = $(this).val();

                if (newValue) {
                    // Show LDAP input and remove button
                    ldapInput.show();
                    removeBtn.show();

                    // Update all other dropdowns to disable this newly selected option
                    self.updateOtherDropdowns(index, newValue, previousValue);

                    // Store the current value as previous for next change
                    $(this).data('previous-value', newValue);

                    // Add a new empty row if this was the last row
                    if (row.is('#field-mappings-container .field-mapping-row:last-child')) {
                        self.addNewRow();
                    }
                } else {
                    // Hide LDAP input and remove button, clear value
                    ldapInput.hide().val('');
                    removeBtn.hide();

                    // Re-enable the previously selected option in other dropdowns
                    if (previousValue) {
                        self.enableOptionInOtherDropdowns(index, previousValue);
                    }

                    $(this).data('previous-value', '');
                }

                // Update the JSON whenever a field changes
                self.updateMappingsJson();
            });
        },

        /**
         * Update option states in other dropdowns
         */
        updateOtherDropdowns: function(currentIndex, newValue, previousValue) {
            $('.field-mapping-row').each(function() {
                if ($(this).data('index') !== currentIndex) {
                    var otherSelect = $(this).find('select[name^="glpi_field_"]');

                    // Disable newly selected option
                    otherSelect.find('option[value="' + newValue + '"]').prop('disabled', true);

                    // Enable previously selected option
                    if (previousValue) {
                        otherSelect.find('option[value="' + previousValue + '"]').prop('disabled', false);
                    }
                }
            });
        },

        /**
         * Enable an option in all other dropdowns
         */
        enableOptionInOtherDropdowns: function(currentIndex, optionValue) {
            $('.field-mapping-row').each(function() {
                if ($(this).data('index') !== currentIndex) {
                    var otherSelect = $(this).find('select[name^="glpi_field_"]');
                    otherSelect.find('option[value="' + optionValue + '"]').prop('disabled', false);
                }
            });
        },

        /**
         * Generate HTML for a new mapping row
         */
        getNewRowHtml: function(index) {
            return `
                <div class="field-mapping-row mb-3" data-index="${index}">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <div id="glpi-field-dropdown-${index}" data-selected="">
                            </div>
                        </div>
                        <div class="col-md-1 text-center">
                            <i class="ti ti-arrow-right"></i>
                        </div>
                        <div class="col-md-5">
                            <input type="text"
                                   class="form-control ldap-attribute-input"
                                   placeholder="LDAP attribute (e.g., cn, serialNumber)"
                                   value=""
                                   data-index="${index}"
                                   style="display: none;">
                        </div>
                        <div class="col-md-1">
                            <button type="button"
                                    class="btn btn-sm btn-ghost-danger remove-mapping-row"
                                    data-index="${index}"
                                    title="Remove mapping"
                                    style="display: none;">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Add a new empty mapping row
         */
        addNewRow: function() {
            this.rowIndex++;
            var newRow = $(this.getNewRowHtml(this.rowIndex));
            $('#field-mappings-container').append(newRow);
            this.loadFieldDropdown(this.rowIndex, '');
        },

        /**
         * Handle remove row button click
         */
        handleRemoveRow: function(button) {
            var row = button.closest('.field-mapping-row');
            var select = row.find('select[name^="glpi_field_"]');
            var fieldValue = select.val();

            // Enable this field in other dropdowns before removing
            if (fieldValue) {
                this.enableOptionInOtherDropdowns(row.data('index'), fieldValue);
            }

            row.remove();
            this.updateMappingsJson();

            // Ensure at least one empty row remains
            if ($('.field-mapping-row').length === 0) {
                this.addNewRow();
            }
        },

        /**
         * Handle save button click
         */
        handleSave: function(button) {
            var self = this;
            button.prop('disabled', true);

            // Update mappings JSON
            this.updateMappingsJson();

            // Prepare data
            var mappingsData = JSON.parse($('#field_mappings_json').val());

            // Submit via AJAX
            $.ajax({
                url: CFG_GLPI.root_doc + '/plugins/advancedldap/front/syncfilter.form.php',
                type: 'POST',
                data: {
                    id: this.syncfilterId,
                    field_mappings: JSON.stringify(mappingsData),
                    update: 'update',
                    _glpi_csrf_token: this.csrfToken
                },
                success: function(response) {
                    glpi_toast_info('Mappings saved successfully');
                    button.prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    glpi_toast_error('Error saving mappings');
                    button.prop('disabled', false);
                    console.error('Error saving mappings:', error);
                }
            });
        }
    };

    // Expose FieldMapping globally for explicit initialization from templates
    window.AdvancedLdapFieldMapping = FieldMapping;

})(jQuery);
