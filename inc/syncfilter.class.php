<?php

/**
 * Legacy compatibility class for GLPI 11 Search engine
 * Maps legacy plugin naming to modern namespace
 *
 * @see https://github.com/glpi-project/glpi/issues/8449
 */

// Avoid loading this file if modern namespace already loaded
if (!class_exists('PluginAdvancedldapSyncFilter', false)) {
    class PluginAdvancedldapSyncFilter extends \GlpiPlugin\Advancedldap\Models\SyncFilter
    {
        // This class exists purely for legacy Search compatibility
        // All functionality is inherited from the modern namespace class
    }
}
