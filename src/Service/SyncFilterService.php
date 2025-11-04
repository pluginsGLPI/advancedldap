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

namespace GlpiPlugin\Advancedldap\Service;

use CronTask;
use Exception;
use Session;
use SingletonTrait;
use Toolbox;

/**
 * Service consolidé pour la gestion des filtres de synchronisation LDAP
 *
 * Fusionne les fonctionnalités de :
 * - SyncFilterService : logique métier des filtres de sync
 * - SyncFilterValidationService : validation et préparation des inputs
 * - SyncFilterCronService : exécution automatique par cron
 * - LdapFilterParser : parsing et analyse des filtres LDAP
 * - LdapFilterSanitizer : sanitisation et validation des filtres/DN
 */
class SyncFilterService
{
    use SingletonTrait;

    // =========================================================================
    // SECTION: Gestion des filtres de synchronisation
    // =========================================================================

    /**
     * Récupère tous les filtres de synchronisation actifs
     *
     * @return array<int, array<string, mixed>> Liste des filtres actifs
     */
    public function getAvailableSyncFilters(): array
    {
        global $DB;

        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_advancedldap_syncfilters',
            'WHERE' => ['is_active' => 1],
            'ORDER' => 'name',
        ]);

        $filters = [];
        foreach ($iterator as $data) {
            $filters[] = $data;
        }

        return $filters;
    }

    /**
     * Récupère les filtres de sync configurés pour un AuthLDAP
     *
     * @param int $authldap_id ID de l'AuthLDAP
     * @return array<int, array<string, mixed>> Liste des filtres
     */
    public function getSyncFiltersForAuthLdap(int $authldap_id): array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['sf.*'],
            'FROM' => 'glpi_plugin_advancedldap_syncfilters AS sf',
            'INNER JOIN' => [
                'glpi_plugin_advancedldap_authldap_syncfilters AS asf' => [
                    'ON' => [
                        'asf' => 'plugin_advancedldap_syncfilters_id',
                        'sf' => 'id'
                    ]
                ]
            ],
            'WHERE' => [
                'asf.authldaps_id' => $authldap_id,
                'asf.is_active' => 1,
                'sf.is_active' => 1,
            ],
            'ORDER' => 'sf.name',
        ]);

        $filters = [];
        foreach ($iterator as $data) {
            $filters[] = $data;
        }

        return $filters;
    }

    /**
     * Crée un nouveau filtre de synchronisation
     *
     * @param string $name Nom du filtre
     * @param string $ldap_filter Filtre LDAP
     * @param string $base_dn Base DN
     * @param string $asset_type Type d'asset
     * @param array<string, mixed> $field_mappings Mappings de champs
     * @param bool $is_active Statut actif
     * @return int|false ID du filtre créé ou false en cas d'échec
     */
    public function createSyncFilter(
        string $name,
        string $ldap_filter,
        string $base_dn,
        string $asset_type,
        array $field_mappings = [],
        bool $is_active = true
    ) {
        global $DB;

        $data = [
            'name' => $name,
            'ldap_filter' => $ldap_filter,
            'base_dn' => $base_dn,
            'asset_type' => $asset_type,
            'field_mappings' => json_encode($field_mappings),
            'is_active' => $is_active ? 1 : 0,
            'date_creation' => date('Y-m-d H:i:s'),
        ];

        return $DB->insert('glpi_plugin_advancedldap_syncfilters', $data);
    }

    /**
     * Met à jour un filtre de synchronisation
     *
     * @param int $id ID du filtre
     * @param array<string, mixed> $data Données à mettre à jour
     * @return bool Succès de la mise à jour
     */
    public function updateSyncFilter(int $id, array $data): bool
    {
        global $DB;

        if (isset($data['field_mappings']) && is_array($data['field_mappings'])) {
            $data['field_mappings'] = json_encode($data['field_mappings']);
        }

        $data['date_mod'] = date('Y-m-d H:i:s');

        return $DB->update(
            'glpi_plugin_advancedldap_syncfilters',
            $data,
            ['id' => $id]
        );
    }

    /**
     * Supprime un filtre de synchronisation et ses relations
     *
     * @param int $id ID du filtre
     * @return bool Succès de la suppression
     */
    public function deleteSyncFilter(int $id): bool
    {
        global $DB;

        // Supprimer d'abord les relations AuthLDAP
        $DB->delete(
            'glpi_plugin_advancedldap_authldap_syncfilters',
            ['plugin_advancedldap_syncfilters_id' => $id]
        );

        // Supprimer le filtre
        return $DB->delete(
            'glpi_plugin_advancedldap_syncfilters',
            ['id' => $id]
        );
    }

    /**
     * Assigne un filtre de sync à un AuthLDAP
     *
     * @param int $authldap_id ID de l'AuthLDAP
     * @param int $syncfilter_id ID du filtre
     * @param bool $is_active Statut actif
     * @return int|false ID de la relation ou false en cas d'échec
     */
    public function assignSyncFilterToAuthLdap(int $authldap_id, int $syncfilter_id, bool $is_active = true)
    {
        global $DB;

        // Vérifier si la relation existe déjà
        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_advancedldap_authldap_syncfilters',
            'WHERE' => [
                'authldaps_id' => $authldap_id,
                'plugin_advancedldap_syncfilters_id' => $syncfilter_id,
            ],
            'LIMIT' => 1,
        ]);

        if (count($iterator) > 0) {
            // Mettre à jour la relation existante
            foreach ($iterator as $data) {
                $DB->update(
                    'glpi_plugin_advancedldap_authldap_syncfilters',
                    ['is_active' => $is_active ? 1 : 0],
                    ['id' => $data['id']]
                );
                return $data['id'];
            }
        }

        // Créer une nouvelle relation
        return $DB->insert('glpi_plugin_advancedldap_authldap_syncfilters', [
            'authldaps_id' => $authldap_id,
            'plugin_advancedldap_syncfilters_id' => $syncfilter_id,
            'is_active' => $is_active ? 1 : 0,
        ]);
    }

    /**
     * Désassigne un filtre de sync d'un AuthLDAP
     *
     * @param int $authldap_id ID de l'AuthLDAP
     * @param int $syncfilter_id ID du filtre
     * @return bool Succès de la suppression
     */
    public function unassignSyncFilterFromAuthLdap(int $authldap_id, int $syncfilter_id): bool
    {
        global $DB;

        return $DB->delete('glpi_plugin_advancedldap_authldap_syncfilters', [
            'authldaps_id' => $authldap_id,
            'plugin_advancedldap_syncfilters_id' => $syncfilter_id,
        ]);
    }

    /**
     * Récupère les mappings de champs d'un filtre de sync
     *
     * @param int $syncfilter_id ID du filtre
     * @return array<string, mixed> Mappings de champs
     */
    public function getFieldMappings(int $syncfilter_id): array
    {
        global $DB;

        $iterator = $DB->request([
            'FROM' => 'glpi_plugin_advancedldap_syncfilters',
            'WHERE' => ['id' => $syncfilter_id],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $data) {
            if (!empty($data['field_mappings']) && is_string($data['field_mappings'])) {
                $mappings = json_decode($data['field_mappings'], true);
                return is_array($mappings) ? $mappings : [];
            }
        }

        return [];
    }

    // =========================================================================
    // SECTION: Validation et sanitisation des filtres LDAP
    // =========================================================================

    /**
     * Valide et sanitise les inputs LDAP (Base DN et filtre)
     *
     * @param array<string, mixed> $input Données d'entrée
     * @return array<string, mixed>|false Données validées ou false en cas d'erreur
     */
    public function validateLdapInputs(array $input)
    {
        // Valider le Base DN
        if (isset($input['base_dn']) && !empty($input['base_dn'])) {
            if (!$this->isValidDN($input['base_dn'])) {
                Session::addMessageAfterRedirect(
                    __('Invalid LDAP Base DN syntax. Please check the format (example: ou=users,dc=example,dc=com)', 'advancedldap'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        // Valider le filtre LDAP
        if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
            $validated_filter = $this->sanitizeFilter($input['ldap_filter']);
            if ($validated_filter === null) {
                Session::addMessageAfterRedirect(
                    __('Invalid LDAP filter syntax. Please check your filter format (example: (objectClass=person))', 'advancedldap'),
                    false,
                    ERROR
                );
                return false;
            }
            $input['ldap_filter'] = $validated_filter;
        }

        return $input;
    }

    /**
     * Prépare les mappings de champs depuis l'input
     *
     * @param array<string, mixed> $input Données d'entrée
     * @return array<string, mixed> Input avec field_mappings préparés
     */
    public function prepareMappingsInput(array $input): array
    {
        // Convertir field_mappings en JSON si c'est un tableau
        if (isset($input['field_mappings']) && is_array($input['field_mappings'])) {
            $input['field_mappings'] = json_encode($input['field_mappings']);
        }

        // Gérer asset_fields (multiple fields selection)
        if (isset($input['asset_fields']) && is_array($input['asset_fields']) && !empty($input['asset_fields'])) {
            $field_mappings = [];

            // Parser le filtre LDAP pour obtenir les attributs disponibles
            $available_attributes = [];
            if (isset($input['ldap_filter']) && !empty($input['ldap_filter'])) {
                $available_attributes = $this->parseFilterAttributes($input['ldap_filter']);
            }

            // Mapper chaque champ sélectionné à un attribut LDAP
            $ldap_data_service = LdapDataService::getInstance();
            foreach ($input['asset_fields'] as $glpi_field) {
                if (!empty($glpi_field)) {
                    $ldap_attribute = $ldap_data_service->findMatchingAttribute($glpi_field, $available_attributes);
                    $field_mappings[$glpi_field] = $ldap_attribute;
                }
            }

            if ($field_mappings !== []) {
                $input['field_mappings'] = json_encode($field_mappings);
            }
        }

        return $input;
    }

    /**
     * Valide et prépare tous les inputs d'un SyncFilter
     *
     * @param array<string, mixed> $input Données d'entrée
     * @return array<string, mixed>|false Données préparées ou false en cas d'erreur
     */
    public function validateAndPrepare(array $input)
    {
        // Valider les inputs LDAP
        $input = $this->validateLdapInputs($input);
        if ($input === false) {
            return false;
        }

        // Préparer les mappings
        return $this->prepareMappingsInput($input);
    }

    // =========================================================================
    // SECTION: Parsing et sanitisation des filtres LDAP (RFC 4515)
    // =========================================================================

    /**
     * Parse un filtre LDAP pour extraire les attributs disponibles
     *
     * @param string $ldap_filter Filtre LDAP
     * @return array<string> Liste des attributs LDAP uniques
     */
    public function parseFilterAttributes(string $ldap_filter): array
    {
        $attributes = [];

        // Pattern pour matcher les attributs LDAP
        if (preg_match_all('/\(([a-zA-Z][a-zA-Z0-9]*)\s*[=<>~]/', $ldap_filter, $matches)) {
            $attributes = array_unique($matches[1]);
            sort($attributes);
        }

        return $attributes;
    }

    /**
     * Valide la syntaxe d'un filtre LDAP
     *
     * @param string $ldap_filter Filtre LDAP
     * @return bool True si le filtre est valide
     */
    public function isValidFilter(string $ldap_filter): bool
    {
        // Vérifier si le filtre est vide
        if (empty(trim($ldap_filter))) {
            return false;
        }

        // Vérifier les parenthèses équilibrées
        $open_count = substr_count($ldap_filter, '(');
        $close_count = substr_count($ldap_filter, ')');
        if ($open_count !== $close_count) {
            return false;
        }

        // Vérifier qu'il y a au moins une paire attribut-valeur
        if (!preg_match('/\([a-zA-Z][a-zA-Z0-9]*\s*[=<>~]/', $ldap_filter)) {
            return false;
        }

        // Vérifier l'absence de caractères invalides
        return !preg_match('/[^\w\s()\[\]&|!=<>~*\-:.,;@\\\]/', $ldap_filter);
    }

    /**
     * Sanitise et valide un filtre LDAP
     *
     * @param string $filter Filtre fourni par l'utilisateur
     * @return string|null Filtre sanitisé ou null si invalide
     */
    public function sanitizeFilter(string $filter): ?string
    {
        $filter = trim($filter);

        if (!$this->isValidFilter($filter)) {
            return null;
        }

        return $filter;
    }

    /**
     * Valide la structure d'un Distinguished Name (DN)
     *
     * @param string $dn Distinguished Name à valider
     * @return bool True si la structure du DN est valide
     */
    public function isValidDN(string $dn): bool
    {
        if (empty(trim($dn))) {
            return false;
        }

        // Vérifier la présence d'au moins une paire attribut=valeur
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9\-]*\s*=/', $dn)) {
            return false;
        }

        // Vérifier l'absence de virgule ou égal en fin de chaîne
        if (preg_match('/[,=]\s*$/', $dn)) {
            return false;
        }

        // Vérifier chaque composant
        $components = explode(',', $dn);
        foreach ($components as $component) {
            $component = trim($component);

            // Chaque composant doit être attribut=valeur
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9\-]*\s*=\s*\S/', $component)) {
                return false;
            }

            // Pas de métacaractères de filtre LDAP dans les DN
            if (preg_match('/[()&|!~*]/', $component)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Échappe une valeur pour l'utiliser dans un filtre LDAP (RFC 4515)
     *
     * @param string $str Chaîne à échapper
     * @param bool $for_dn Échapper pour un DN (règles plus strictes)
     * @return string Chaîne échappée
     */
    public function escapeFilterValue(string $str, bool $for_dn = false): string
    {
        // Caractères spéciaux RFC 4515
        $meta_chars = ['\\', '*', '(', ')', "\x00"];
        $quoted_chars = [];

        foreach ($meta_chars as $char) {
            $quoted_chars[] = '\\' . str_pad(dechex(ord($char)), 2, '0', STR_PAD_LEFT);
        }

        $escaped = str_replace($meta_chars, $quoted_chars, $str);

        // Pour les DN, échapper aussi les caractères supplémentaires (RFC 4514)
        if ($for_dn) {
            $dn_meta_chars = [',', '+', '"', '<', '>', ';', '=', '#'];
            foreach ($dn_meta_chars as $char) {
                $escaped = str_replace($char, '\\' . $char, $escaped);
            }

            // Échapper les espaces de début et de fin
            if (strlen($escaped) > 0) {
                if ($escaped[0] === ' ') {
                    $escaped = '\\' . $escaped;
                }
                $len = strlen($escaped);
                if ($escaped[$len - 1] === ' ' && !($escaped[0] === '\\' && $len === 2)) {
                    $escaped = substr($escaped, 0, -1) . '\\ ';
                }
            }
        }

        return $escaped;
    }

    /**
     * Extrait les objectClass d'un filtre LDAP
     *
     * @param string $ldap_filter Filtre LDAP
     * @return array<string> Liste des objectClass
     */
    public function extractObjectClasses(string $ldap_filter): array
    {
        $object_classes = [];

        if (preg_match_all('/\(objectClass\s*=\s*([^)]+)\)/', $ldap_filter, $matches)) {
            $object_classes = array_filter(
                array_unique($matches[1]),
                fn($value) => $value !== '*' && !empty(trim($value))
            );
            $object_classes = array_values($object_classes);
        }

        return $object_classes;
    }

    // =========================================================================
    // SECTION: Tâches CRON de synchronisation automatique
    // =========================================================================

    /**
     * Exécute la tâche CRON de synchronisation automatique
     *
     * @param CronTask|null $task Instance de CronTask (null dans les tests)
     * @return int 0 = rien à faire, 1 = succès, -1 = besoin de re-exécuter
     */
    public function executeSyncTask(?CronTask $task = null): int
    {
        // Récupérer le nombre maximum de filtres à traiter
        $max_filters = 0;
        if ($task !== null && isset($task->fields['param'])) {
            $max_filters = (int) $task->fields['param'];
        }

        // Récupérer tous les filtres actifs avec leurs AuthLDAP
        $filters_to_sync = $this->getAllActiveSyncFiltersWithAuthLdap();

        if ($filters_to_sync === []) {
            if ($task !== null) {
                $task->log(__('No active LDAP sync filters found', 'advancedldap'));
            }
            return 0;
        }

        $total_filters = count($filters_to_sync);
        $processed_count = 0;
        $success_count = 0;
        $error_count = 0;
        $total_assets_synced = 0;

        if ($task !== null) {
            $msg = sprintf(__('Found %d active filter(s) to synchronize', 'advancedldap'), $total_filters);
            $task->log($msg);
        }

        // Récupérer le service de synchronisation LDAP
        $ldap_sync_service = LdapSyncService::getInstance();

        // Traiter chaque filtre
        foreach ($filters_to_sync as $filter_data) {
            // Vérifier la limite
            if ($max_filters > 0 && $processed_count >= $max_filters) {
                if ($task !== null) {
                    $msg = sprintf(__('Reached maximum limit of %d filters per execution', 'advancedldap'), $max_filters);
                    $task->log($msg);
                }
                break;
            }

            $syncfilter_id = (int) $filter_data['syncfilter_id'];
            $authldap_id = (int) $filter_data['authldap_id'];
            $filter_name = $filter_data['name'] ?? "ID {$syncfilter_id}";

            $processed_count++;

            try {
                // Synchroniser avec le service LDAP
                $sync_results = $ldap_sync_service->synchronizeFromFilter($syncfilter_id, $authldap_id);

                if ($sync_results['success']) {
                    $success_count++;
                    $assets_created = $sync_results['stats']['created'] ?? 0;
                    $assets_updated = $sync_results['stats']['updated'] ?? 0;
                    $assets_errors = $sync_results['stats']['errors'] ?? 0;
                    $total_assets_synced += ($assets_created + $assets_updated);

                    $msg = sprintf(
                        __('Filter "%s": %d created, %d updated, %d errors', 'advancedldap'),
                        mb_substr($filter_name, 0, 50),
                        $assets_created,
                        $assets_updated,
                        $assets_errors
                    );

                    if ($task !== null) {
                        $task->log($msg);
                    }
                } else {
                    $error_count++;
                    $error_msg = $sync_results['error'] ?? __('Unknown error', 'advancedldap');
                    $msg = sprintf(
                        __('Filter "%s": Error - %s', 'advancedldap'),
                        mb_substr($filter_name, 0, 50),
                        mb_substr($error_msg, 0, 100)
                    );

                    if ($task !== null) {
                        $task->log($msg);
                    }
                }
            } catch (Exception $e) {
                $error_count++;
                $msg = sprintf(
                    __('Filter "%s": Exception - %s', 'advancedldap'),
                    mb_substr($filter_name, 0, 50),
                    mb_substr($e->getMessage(), 0, 100)
                );

                if ($task !== null) {
                    $task->log($msg);
                }
            }
        }

        // Résumé final
        $summary = sprintf(
            __('Processed %d/%d filters: %d success, %d errors. Total assets: %d', 'advancedldap'),
            $processed_count,
            $total_filters,
            $success_count,
            $error_count,
            $total_assets_synced
        );

        if ($task !== null) {
            $task->log($summary);
            $task->setVolume($total_assets_synced);
        }

        // Codes de retour : -1 = plus de filtres à traiter, 0 = rien à faire, 1 = succès
        if ($max_filters > 0 && $processed_count < $total_filters) {
            return -1;
        }

        return $success_count > 0 ? 1 : 0;
    }

    /**
     * Récupère tous les filtres actifs avec leurs serveurs AuthLDAP associés
     *
     * @return array<int, array<string, mixed>> Tableau de données de filtres
     */
    private function getAllActiveSyncFiltersWithAuthLdap(): array
    {
        global $DB;

        // Récupérer les filtres actifs
        $active_filters = $this->getAvailableSyncFilters();

        if ($active_filters === []) {
            return [];
        }

        // Récupérer les serveurs AuthLDAP actifs
        $iterator = $DB->request([
            'FROM' => 'glpi_authldaps',
            'WHERE' => ['is_active' => 1],
        ]);

        $active_authldap_ids = [];
        foreach ($iterator as $data) {
            $active_authldap_ids[] = $data['id'];
        }

        $results = [];

        // Pour chaque filtre actif, récupérer ses AuthLDAP associés
        foreach ($active_filters as $filter) {
            $syncfilter_id = (int) $filter['id'];

            // Récupérer les AuthLDAP associés à ce filtre
            $iterator = $DB->request([
                'FROM' => 'glpi_plugin_advancedldap_authldap_syncfilters',
                'WHERE' => [
                    'plugin_advancedldap_syncfilters_id' => $syncfilter_id,
                    'is_active' => 1,
                ],
            ]);

            foreach ($iterator as $relation) {
                $authldap_id = (int) $relation['authldaps_id'];

                // Ne garder que les AuthLDAP actifs
                if (in_array($authldap_id, $active_authldap_ids, true)) {
                    $results[] = [
                        'syncfilter_id' => $syncfilter_id,
                        'name' => $filter['name'] ?? '',
                        'base_dn' => $filter['base_dn'] ?? '',
                        'ldap_filter' => $filter['ldap_filter'] ?? '',
                        'asset_type' => $filter['asset_type'] ?? '',
                        'authldap_id' => $authldap_id,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Récupère les informations de la tâche CRON
     *
     * @param string $name Nom de la tâche
     * @return array<string, string> Informations de la tâche
     */
    public static function getCronInfo(string $name): array
    {
        return [
            'description' => __('Automatically synchronize active LDAP filters with GLPI assets', 'advancedldap'),
            'parameter' => __('Maximum number of filters to process per execution (0 = unlimited)', 'advancedldap'),
        ];
    }
}
