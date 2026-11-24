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

/* global glpi_toast_info, glpi_toast_error */

(function () {
    'use strict';

    const READY_SELECTOR = '#builder-mapping:not([data-advancedldap-ready])';

    const initBuilderMapping = (container) => {
        container.dataset.advancedldapReady = '1';

        const sections = JSON.parse(container.dataset.advancedldapSections);
        const completions = JSON.parse(container.dataset.advancedldapCompletions);
        const builderItemtype = container.dataset.advancedldapBuilderItemtype;
        const csrfToken = container.dataset.advancedldapCsrfToken;
        const i18n = JSON.parse(container.dataset.advancedldapI18n);
        const ajaxUrl = `${CFG_GLPI.root_doc}/plugins/advancedldap/ajax/buildermapping.php`;
        const editors = {};

        // Ensure Monaco module is loaded (needed for AJAX-loaded tabs)
        import(`${CFG_GLPI.root_doc}/js/modules/Monaco/MonacoEditor.js`).then(() => {
            sections.forEach((section) => {
                const containerId = `editor-${section.name}`;
                const inputId = `input-${section.name}`;

                window.GLPI.Monaco.createEditor(containerId, 'twig', section.content, completions).then((monaco) => {
                    editors[section.name] = monaco;

                    const editorContainer = document.getElementById(containerId);

                    monaco.editor.updateOptions({
                        scrollBeyondLastLine: false,
                        scrollbar: { vertical: 'hidden', alwaysConsumeMouseWheel: false },
                        automaticLayout: false,
                    });

                    const updateHeight = () => {
                        const height = monaco.editor.getContentHeight();
                        editorContainer.style.height = `${height}px`;
                        monaco.editor.layout({ width: editorContainer.offsetWidth, height });
                    };

                    monaco.editor.onDidContentSizeChange(updateHeight);
                    updateHeight();

                    // Update hidden input on change
                    monaco.editor.onDidChangeModelContent(() => {
                        document.getElementById(inputId).value = monaco.editor.getValue();
                    });
                });
            });
        });

        // Handle form submission - sync editor values to hidden inputs
        $(container).find('#builder-mapping-form').on('submit', () => {
            sections.forEach((section) => {
                const inputId = `input-${section.name}`;
                if (editors[section.name]) {
                    document.getElementById(inputId).value = editors[section.name].editor.getValue();
                }
            });
        });

        // Reset single section
        $(container).on('click', '.reset-section', function () {
            const sectionName = $(this).data('section');
            if (!confirm(i18n.confirm_section)) {
                return;
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_default_section',
                    section: sectionName,
                    builder_itemtype: builderItemtype,
                    _glpi_csrf_token: csrfToken
                },
                success: function (response) {
                    if (response.success && editors[sectionName]) {
                        editors[sectionName].editor.setValue(response.content);
                        glpi_toast_info(i18n.section_reset);
                    }
                },
                error: function () {
                    glpi_toast_error(i18n.section_reset_error);
                }
            });
        });

        // Reset all sections
        $(container).on('click', '#reset-all-sections', () => {
            if (!confirm(i18n.confirm_all)) {
                return;
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_all_default_sections',
                    builder_itemtype: builderItemtype,
                    _glpi_csrf_token: csrfToken
                },
                success: function (response) {
                    if (response.success) {
                        for (const [sectionName, content] of Object.entries(response.sections)) {
                            if (editors[sectionName]) {
                                editors[sectionName].editor.setValue(content);
                            }
                        }
                        glpi_toast_info(i18n.all_reset);
                    }
                },
                error: function () {
                    glpi_toast_error(i18n.all_reset_error);
                }
            });
        });
    };

    document.querySelectorAll(READY_SELECTOR).forEach(initBuilderMapping);
})();
