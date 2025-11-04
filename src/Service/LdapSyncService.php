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

use AuthLDAP;
use Computer;
use Exception;
use Glpi\Asset\AssetDefinition;
use Glpi\Inventory\Inventory;
use Glpi\Inventory\Request;
use GlpiPlugin\Advancedldap\Model\SyncFilter;
use InvalidArgumentException;
use NetworkEquipment;
use Phone;
use Printer;
use SingletonTrait;
use Toolbox;

/**
 * Service consolidé pour la synchronisation LDAP vers GLPI
 *
 * Fusionne les fonctionnalités de :
 * - LdapSyncService : orchestration de la synchronisation
 * - LdapInventoryService : gestion des assets inventoriables via Inventory.php
 * - LdapToInventoryConverter : conversion LDAP vers format JSON d'inventaire
 */
class LdapSyncService
{
    use SingletonTrait;

    /**
     * Types d'assets supportés pour l'inventaire
     */
    private const SUPPORTED_INVENTORY_TYPES = [
        Computer::class => 'Computer',
        NetworkEquipment::class => 'NetworkEquipment',
        Printer::class => 'Printer',
        Phone::class => 'Phone',
    ];

    /**
     * Attributs LDAP pour le nom d'un appareil (par priorité)
     */
    private const NAME_ATTRIBUTES = [
        'cn',
        'displayName',
        'name',
        'displayname',
        'sAMAccountName',
        'samaccountname',
        'uid',
        'hostname'
    ];

    // =========================================================================
    // SECTION: Synchronisation depuis un SyncFilter
    // =========================================================================

    /**
     * Synchronise les données LDAP basées sur un filtre de synchronisation
     *
     * @param int $syncfilter_id ID du SyncFilter
     * @param int $authldap_id ID de l'AuthLDAP
     * @return array<string, mixed> Résultats de synchronisation
     */
    public function synchronizeFromFilter(int $syncfilter_id, int $authldap_id): array
    {
        $results = [
            'success' => false,
            'error' => null,
            'stats' => [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => 0,
            ],
            'details' => [],
        ];

        try {
            // Charger le filtre de synchronisation
            $sync_filter = new SyncFilter();
            if (!$sync_filter->getFromDB($syncfilter_id)) {
                $results['error'] = __('Sync filter not found', 'advancedldap');
                return $results;
            }

            // Valider la configuration AuthLDAP
            $authldap = new AuthLDAP();
            if (!$authldap->getFromDB($authldap_id)) {
                $results['error'] = __('AuthLDAP configuration not found', 'advancedldap');
                return $results;
            }

            // Valider le filtre de sync
            $ldap_data_service = LdapDataService::getInstance();
            $validation_error = $ldap_data_service->validateSyncFilter($sync_filter);
            if ($validation_error) {
                $results['error'] = $validation_error;
                return $results;
            }

            // Récupérer les entrées LDAP
            $ldap_entries = $this->fetchLdapEntries(
                $authldap_id,
                $sync_filter->getField('base_dn'),
                $sync_filter->getField('ldap_filter')
            );

            if (isset($ldap_entries['error'])) {
                $results['error'] = $ldap_entries['error'];
                return $results;
            }

            // Traiter chaque entrée LDAP
            $field_mappings = $sync_filter->getFieldMappings();
            $asset_type = $sync_filter->getField('asset_type');

            // Déterminer la méthode de synchronisation
            $asset_service = AssetService::getInstance();
            $sync_method = $asset_service->getSyncMethod($asset_type);

            // Gérer le tableau d'entrées LDAP correctement
            $entries = $ldap_entries['entries'];
            $entry_count = $entries['count'] ?? 0;

            // Logger le début de la synchronisation
            Toolbox::logDebug("LdapSyncService: Starting synchronization of $entry_count entries");
            $start_time = microtime(true);

            for ($i = 0; $i < $entry_count; $i++) {
                if (!isset($entries[$i]) || !is_array($entries[$i])) {
                    continue;
                }

                $ldap_entry = $entries[$i];
                $results['stats']['processed']++;

                $entry_result = $this->processSingleEntry(
                    $ldap_entry,
                    $asset_type,
                    $field_mappings,
                    $sync_method
                );

                if ($entry_result['success']) {
                    if ($entry_result['action'] === 'created') {
                        $results['stats']['created']++;
                    } else {
                        $results['stats']['updated']++;
                    }
                } else {
                    $results['stats']['errors']++;
                }

                $results['details'][] = $entry_result;

                // Logger la progression tous les 50 entrées
                if (($i + 1) % 50 === 0 || ($i + 1) === $entry_count) {
                    $processed = $i + 1;
                    $created = $results['stats']['created'];
                    $updated = $results['stats']['updated'];
                    $errors = $results['stats']['errors'];
                    Toolbox::logDebug("LdapSyncService: Progress: $processed/$entry_count entries (created: $created, updated: $updated, errors: $errors)");
                }
            }

            // Logger la fin de la synchronisation
            $elapsed_time = round(microtime(true) - $start_time, 2);
            $created = $results['stats']['created'];
            $updated = $results['stats']['updated'];
            $errors = $results['stats']['errors'];
            Toolbox::logDebug("LdapSyncService: Synchronization completed in {$elapsed_time}s - $entry_count entries (created: $created, updated: $updated, errors: $errors)");

            $results['success'] = true;

        } catch (Exception $e) {
            $results['error'] = sprintf(__('Synchronization error: %s', 'advancedldap'), $e->getMessage());
        }

        return $results;
    }

    /**
     * Récupère les entrées depuis LDAP
     *
     * @param int $authldap_id ID de l'AuthLDAP
     * @param string $base_dn Base DN
     * @param string $filter Filtre LDAP
     * @return array<string, mixed> Entrées LDAP ou erreur
     */
    private function fetchLdapEntries(int $authldap_id, string $base_dn, string $filter): array
    {
        try {
            // Charger la configuration AuthLDAP
            $authldap = new AuthLDAP();
            if (!$authldap->getFromDB($authldap_id)) {
                return ['error' => __('AuthLDAP configuration not found', 'advancedldap')];
            }

            // Se connecter au serveur LDAP
            $ldap_connection = AuthLDAP::tryToConnectToServer($authldap->fields, $authldap->fields['rootdn'], $authldap->fields['rootdn_passwd']);

            if (!$ldap_connection) {
                return ['error' => __('Failed to connect to LDAP server', 'advancedldap')];
            }

            // Rechercher les entrées
            $search_result = @ldap_search($ldap_connection, $base_dn, $filter);

            if ($search_result === false) {
                $ldap_error = ldap_error($ldap_connection);
                return ['error' => sprintf(__('LDAP search failed: %s', 'advancedldap'), $ldap_error)];
            }

            // Récupérer les entrées
            $entries = ldap_get_entries($ldap_connection, $search_result);

            if ($entries === false) {
                return ['error' => __('Failed to retrieve LDAP entries', 'advancedldap')];
            }

            return ['entries' => $entries];

        } catch (Exception $e) {
            return ['error' => sprintf(__('LDAP fetch error: %s', 'advancedldap'), $e->getMessage())];
        }
    }

    /**
     * Traite une seule entrée LDAP
     *
     * @param array<string, mixed> $ldap_entry Entrée LDAP
     * @param string $asset_type Type d'asset
     * @param array<string, string> $field_mappings Mappings de champs
     * @param string $sync_method Méthode de sync ('inventory' ou 'traditional')
     * @return array<string, mixed> Résultat du traitement
     */
    private function processSingleEntry(array $ldap_entry, string $asset_type, array $field_mappings, string $sync_method): array
    {
        $result = [
            'success' => false,
            'action' => null,
            'asset_id' => null,
            'asset_name' => '',
            'dn' => $ldap_entry['dn'] ?? '',
            'error' => null,
        ];

        try {
            // Extraire les données mappées depuis l'entrée LDAP
            $ldap_data_service = LdapDataService::getInstance();
            $asset_data = $ldap_data_service->extractAssetData($ldap_entry, $field_mappings);

            if ($asset_data === []) {
                $result['error'] = __('No valid data extracted from LDAP entry', 'advancedldap');
                return $result;
            }

            $result['asset_name'] = $asset_data['name'] ?? '';

            // Router vers la méthode de synchronisation appropriée
            if ($sync_method === 'inventory') {
                $creation_result = $this->processInventoryableAsset($asset_type, $asset_data, $ldap_entry, $field_mappings);
            } else {
                $creation_result = $this->processTraditionalAsset($asset_type, $asset_data);
            }

            $result['success'] = $creation_result['success'];
            $result['action'] = $creation_result['action'];
            $result['asset_id'] = $creation_result['asset_id'];
            $result['error'] = $creation_result['error'];

        } catch (Exception $e) {
            $result['error'] = sprintf(__('Error processing entry: %s', 'advancedldap'), $e->getMessage());
        }

        return $result;
    }

    /**
     * Traite un asset inventoriable via le workflow Inventory.php
     *
     * @param string $asset_type Type d'asset
     * @param array<string, mixed> $asset_data Données extraites
     * @param array<string, mixed> $ldap_entry Entrée LDAP originale
     * @param array<string, string> $field_mappings Mappings de champs
     * @return array<string, mixed> Résultat du traitement
     */
    private function processInventoryableAsset(string $asset_type, array $asset_data, array $ldap_entry, array $field_mappings): array
    {
        try {
            // Convertir en format d'inventaire JSON
            $inventory_data = $this->convertToInventoryFormat($ldap_entry, $asset_type, $field_mappings);

            // Créer l'inventaire sans auto-traitement
            $inventory = new Inventory(null, Inventory::FULL_MODE, Request::JSON_MODE);

            // Convertir en objet pour la validation du schéma GLPI
            $inventory_object = json_decode(json_encode($inventory_data));

            if (!$inventory->setData($inventory_object, Request::JSON_MODE)) {
                $errors = $inventory->getErrors();
                return [
                    'success' => false,
                    'action' => 'inventory',
                    'asset_id' => null,
                    'error' => implode(', ', $errors),
                ];
            }

            // Exécuter l'inventaire
            $inventory->doInventory();

            if ($inventory->inError()) {
                $errors = $inventory->getErrors();
                return [
                    'success' => false,
                    'action' => 'inventory',
                    'asset_id' => null,
                    'error' => implode(', ', $errors),
                ];
            }

            // Extraire les informations de résultat
            $item = $inventory->getItem();
            $asset_id = $item->getID();

            // Vérifier l'échec silencieux (ID = -1 ou 0)
            if ($asset_id <= 0) {
                return [
                    'success' => false,
                    'action' => 'inventory',
                    'asset_id' => null,
                    'error' => __('Inventory system could not create/update asset. Insufficient field mappings.', 'advancedldap'),
                ];
            }

            // Déterminer si création ou mise à jour
            $main_asset = $inventory->getMainAsset();
            $is_new = $main_asset->isNew();
            $action = $is_new ? 'created' : 'updated';

            return [
                'success' => true,
                'action' => $action,
                'asset_id' => $asset_id,
                'error' => null,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'action' => 'inventory',
                'asset_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Traite un asset via le workflow traditionnel GLPI
     *
     * @param string $asset_type Type d'asset
     * @param array<string, mixed> $asset_data Données de l'asset
     * @return array<string, mixed> Résultat du traitement
     */
    private function processTraditionalAsset(string $asset_type, array $asset_data): array
    {
        $asset_service = AssetService::getInstance();
        return $asset_service->createOrUpdateAsset($asset_type, $asset_data);
    }

    // =========================================================================
    // SECTION: Conversion LDAP vers format Inventory JSON
    // =========================================================================

    /**
     * Convertit des données LDAP en format JSON d'inventaire
     *
     * @param array<string, mixed> $ldap_data Données LDAP
     * @param string $itemtype Type d'asset GLPI ou GenericAsset_ID
     * @param array<string, string> $field_mappings Mappings de champs
     * @return array<string, mixed> Données au format JSON d'inventaire
     */
    private function convertToInventoryFormat(array $ldap_data, string $itemtype, array $field_mappings = []): array
    {
        // Gérer les assets génériques
        $is_generic_asset = str_starts_with($itemtype, 'GenericAsset_');
        $inventory_itemtype = $itemtype;

        if ($is_generic_asset) {
            $inventory_itemtype = $this->getGenericAssetClassName($itemtype);
            if ($inventory_itemtype === null) {
                throw new InvalidArgumentException("Could not resolve class name for generic asset: $itemtype");
            }
        }

        // Valider le type natif
        if (!$is_generic_asset && !isset(self::SUPPORTED_INVENTORY_TYPES[$itemtype])) {
            throw new InvalidArgumentException("Unsupported itemtype for inventory conversion: $itemtype");
        }

        // Générer l'ID d'appareil unique
        $device_id = $this->generateDeviceId($ldap_data, $itemtype);

        // Construire la structure de base
        $base_inventory = [
            'action' => 'inventory',
            'deviceid' => $device_id,
            'itemtype' => $inventory_itemtype,
            'partial' => false,
            'content' => [
                'versionclient' => '4.1',
            ],
        ];

        // Ajouter la section principale selon le type
        if ($itemtype === NetworkEquipment::class) {
            $base_inventory['content']['network_device'] = $this->buildNetworkDeviceSection($ldap_data, $field_mappings);
        } else {
            $base_inventory['content']['hardware'] = [
                'name' => $this->extractDeviceName($ldap_data),
            ];
        }

        // Ajouter les sections spécifiques
        $sections = $this->buildSelectiveSections($ldap_data, $itemtype, $field_mappings);
        $base_inventory['content'] = array_merge($base_inventory['content'], $sections);

        return $base_inventory;
    }

    /**
     * Construit les sections d'inventaire de manière sélective
     *
     * @param array<string, mixed> $ldap_data Données LDAP
     * @param string $itemtype Type d'asset
     * @param array<string, string> $field_mappings Mappings de champs
     * @return array<string, mixed> Sections d'inventaire
     */
    private function buildSelectiveSections(array $ldap_data, string $itemtype, array $field_mappings): array
    {
        $sections = [];

        // Section hardware
        $hardware = $this->buildHardwareSection($ldap_data, $itemtype, $field_mappings);
        if ($hardware !== []) {
            $sections['hardware'] = $hardware;
        }

        // Section networks
        $sections['networks'] = $this->buildNetworkSection($ldap_data, $field_mappings);

        return $sections;
    }

    /**
     * Construit la section hardware
     *
     * @param array<string, mixed> $ldap_data Données LDAP
     * @param string $itemtype Type d'asset
     * @param array<string, string> $field_mappings Mappings de champs
     * @return array<string, mixed> Section hardware
     */
    private function buildHardwareSection(array $ldap_data, string $itemtype, array $field_mappings): array
    {
        $hardware = [];
        $ldap_data_service = LdapDataService::getInstance();

        // Nom
        $hardware['name'] = $this->extractDeviceName($ldap_data);

        // UUID
        $uuid_fields = ['objectguid', 'entryuuid'];
        foreach ($uuid_fields as $field) {
            $value = $ldap_data_service->getAttribute($ldap_data, $field);
            if ($value !== null) {
                $hardware['uuid'] = $value;
                break;
            }
        }

        // Type de châssis (pour les ordinateurs)
        if ($itemtype === Computer::class) {
            $hardware['chassis_type'] = 'Desktop';
        }

        // Système virtuel
        $hardware['vmsystem'] = 'Physical';

        return $hardware;
    }

    /**
     * Construit la section network
     *
     * @param array<string, mixed> $ldap_data Données LDAP
     * @param array<string, string> $field_mappings Mappings de champs
     * @return array<string, mixed> Section network
     */
    private function buildNetworkSection(array $ldap_data, array $field_mappings): array
    {
        $networks = [];
        $ldap_data_service = LdapDataService::getInstance();

        // Adresse IP
        $ip_fields = ['ipaddress', 'networkaddress', 'ip'];
        $ip_address = null;
        foreach ($ip_fields as $field) {
            if ($this->isFieldAllowed($field, $field_mappings)) {
                $value = $ldap_data_service->getAttribute($ldap_data, $field);
                if ($value !== null) {
                    $ip_address = $value;
                    break;
                }
            }
        }

        // Adresse MAC
        $mac_fields = ['macaddress', 'physicaldeliveryofficename', 'networkaddress'];
        $mac_address = null;
        foreach ($mac_fields as $field) {
            if ($this->isFieldAllowed($field, $field_mappings)) {
                $value = $ldap_data_service->getAttribute($ldap_data, $field);
                if ($value !== null && preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $value)) {
                    $mac_address = $value;
                    break;
                }
            }
        }

        // Créer l'entrée réseau si on a des données
        if ($ip_address || $mac_address) {
            $network = ['description' => 'Network Interface'];

            if ($ip_address) {
                $network['ipaddress'] = $ip_address;
            }

            if ($mac_address) {
                $network['macaddr'] = $mac_address;
            }

            $networks[] = $network;
        }

        return $networks;
    }

    /**
     * Construit la section network_device pour NetworkEquipment
     *
     * @param array<string, mixed> $ldap_data Données LDAP
     * @param array<string, string> $field_mappings Mappings de champs
     * @return array<string, mixed> Section network_device
     */
    private function buildNetworkDeviceSection(array $ldap_data, array $field_mappings): array
    {
        $network_device = [
            'name' => $this->extractDeviceName($ldap_data),
            'type' => 'Networking',
        ];

        $ldap_data_service = LdapDataService::getInstance();

        // Serial
        $serial_fields = ['serialnumber', 'serial', 'hardwareserial'];
        foreach ($serial_fields as $field) {
            if ($this->isFieldAllowed($field, $field_mappings)) {
                $value = $ldap_data_service->getAttribute($ldap_data, $field);
                if ($value !== null) {
                    $network_device['serial'] = $value;
                    break;
                }
            }
        }

        // Manufacturer
        $manufacturer_fields = ['manufacturer', 'vendor', 'company'];
        foreach ($manufacturer_fields as $field) {
            if ($this->isFieldAllowed($field, $field_mappings)) {
                $value = $ldap_data_service->getAttribute($ldap_data, $field);
                if ($value !== null) {
                    $network_device['manufacturer'] = $value;
                    break;
                }
            }
        }

        return $network_device;
    }

    /**
     * Vérifie si un champ LDAP est autorisé dans les mappings
     *
     * @param string $ldap_field Nom du champ LDAP
     * @param array<string, string> $field_mappings Mappings de champs
     * @return bool True si autorisé
     */
    private function isFieldAllowed(string $ldap_field, array $field_mappings): bool
    {
        // Si pas de mappings configurés, tous les champs sont autorisés
        if ($field_mappings === []) {
            return true;
        }

        // Toujours autoriser les champs critiques
        $critical_fields = ['cn', 'name', 'displayname', 'samaccountname'];
        if (in_array(strtolower($ldap_field), $critical_fields)) {
            return true;
        }

        // Vérifier si le champ est dans les mappings configurés
        return in_array(strtolower($ldap_field), array_map('strtolower', $field_mappings));
    }

    /**
     * Extrait le nom d'un appareil depuis les données LDAP
     *
     * @param array<string, mixed> $ldap_data Données LDAP
     * @return string Nom de l'appareil
     */
    private function extractDeviceName(array $ldap_data): string
    {
        $ldap_data_service = LdapDataService::getInstance();
        return $ldap_data_service->extractDeviceName($ldap_data, 'Unknown Device');
    }

    /**
     * Génère un ID d'appareil unique depuis les données LDAP
     *
     * @param array<string, mixed> $ldap_data Données LDAP
     * @param string $itemtype Type d'asset
     * @return string ID unique de l'appareil
     */
    private function generateDeviceId(array $ldap_data, string $itemtype): string
    {
        $ldap_data_service = LdapDataService::getInstance();

        // Essayer les identifiants uniques communs
        $candidates = [
            $ldap_data_service->getAttribute($ldap_data, 'objectguid'),
            $ldap_data_service->getAttribute($ldap_data, 'entryuuid'),
            $ldap_data_service->getAttribute($ldap_data, 'cn'),
            $ldap_data_service->getAttribute($ldap_data, 'name'),
            $ldap_data_service->getAttribute($ldap_data, 'samaccountname'),
        ];

        $identifier = null;
        foreach ($candidates as $candidate) {
            if (!empty($candidate)) {
                $identifier = $candidate;
                break;
            }
        }

        if (!$identifier) {
            $identifier = 'ldap-' . md5(serialize($ldap_data));
        }

        // Préfixe avec le type d'asset pour l'unicité
        $prefix = strtolower(str_replace('\\', '-', $itemtype));
        return $prefix . '-' . $identifier;
    }

    /**
     * Récupère le nom de classe réel pour un asset générique
     *
     * @param string $generic_asset_id GenericAsset_ID format
     * @return string|null Nom de classe réel ou null
     */
    private function getGenericAssetClassName(string $generic_asset_id): ?string
    {
        try {
            $asset_definition_id = (int) str_replace('GenericAsset_', '', $generic_asset_id);

            if ($asset_definition_id <= 0) {
                return null;
            }

            $definition = new AssetDefinition();
            if (!$definition->getFromDB($asset_definition_id)) {
                return null;
            }

            return $definition->getAssetClassName();

        } catch (Exception) {
            return null;
        }
    }

    /**
     * Récupère les types d'assets supportés pour l'inventaire
     *
     * @return array<int, class-string> Liste des types supportés
     */
    public function getSupportedItemtypes(): array
    {
        return array_keys(self::SUPPORTED_INVENTORY_TYPES);
    }

    /**
     * Vérifie si un itemtype peut être géré par le service
     *
     * @param string $itemtype Type d'asset GLPI
     * @return bool True si supporté
     */
    public function canHandleItemtype(string $itemtype): bool
    {
        return isset(self::SUPPORTED_INVENTORY_TYPES[$itemtype]);
    }
}
