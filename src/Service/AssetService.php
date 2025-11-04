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

use CommonDBTM;
use Computer;
use DBmysql;
use Exception;
use Glpi\Asset\AssetDefinition;
use Glpi\Asset\Capacity\IsInventoriableCapacity;
use GlpiPlugin\Advancedldap\Config\AssetFieldConfig;
use Location;
use NetworkEquipment;
use Phone;
use Printer;
use Session;
use SingletonTrait;
use Toolbox;

/**
 * Service consolidé pour la gestion des assets GLPI
 *
 * Fusionne les fonctionnalités de :
 * - AssetCreationService : création/mise à jour d'assets
 * - AssetFieldService : récupération des champs disponibles
 * - AssetTypeClassifier : classification des assets (inventoriable ou traditionnel)
 */
class AssetService
{
    use SingletonTrait;

    /**
     * Types d'assets natifs supportés
     */
    private const NATIVE_ASSET_TYPES = [
        'Computer' => Computer::class,
        'NetworkEquipment' => NetworkEquipment::class,
        'Phone' => Phone::class,
        'Printer' => Printer::class,
    ];

    /**
     * Types d'assets inventoriables par défaut (GLPI core)
     */
    private const INVENTORIABLE_TYPES = [
        Computer::class,
        Phone::class,
        Printer::class,
        NetworkEquipment::class,
    ];

    // =========================================================================
    // SECTION: Création et mise à jour d'assets
    // =========================================================================

    /**
     * Crée ou met à jour un asset GLPI
     *
     * @param string $asset_type Nom de classe d'asset (Computer, Printer, etc.) ou GenericAsset_ID
     * @param array<string, mixed> $asset_data Données de l'asset depuis LDAP
     * @return array{success: bool, action: string|null, asset_id: int|null, error: string|null}
     */
    public function createOrUpdateAsset(string $asset_type, array $asset_data): array
    {
        $result = [
            'success' => false,
            'action' => null,
            'asset_id' => null,
            'error' => null,
        ];

        try {
            // Valider les données requises
            if (empty($asset_data['name'])) {
                $result['error'] = __('Asset name is required', 'advancedldap');
                return $result;
            }

            // Gérer les assets génériques (format: GenericAsset_ID)
            if (str_starts_with($asset_type, 'GenericAsset_')) {
                return $this->handleGenericAsset($asset_type, $asset_data);
            }

            // Valider le type d'asset natif
            if (!class_exists($asset_type)) {
                $result['error'] = sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type);
                return $result;
            }

            // Créer l'instance d'asset
            /** @phpstan-ignore glpi.forbidDynamicInstantiation */
            $asset = new $asset_type();
            if (!$asset instanceof CommonDBTM) {
                $result['error'] = sprintf(__('Invalid asset type %s', 'advancedldap'), $asset_type);
                return $result;
            }

            // Préparer les données pour GLPI
            $prepared_data = $this->prepareAssetData($asset_data, $asset_type);

            // Vérifier si l'asset existe déjà
            $existing_asset = $this->findExistingAsset($asset, $prepared_data);

            if ($existing_asset) {
                // Mettre à jour l'asset existant
                $update_data = $prepared_data;
                $update_data['id'] = $existing_asset['id'];

                if ($asset->update($update_data)) {
                    $result['success'] = true;
                    $result['action'] = 'updated';
                    $result['asset_id'] = $existing_asset['id'];
                } else {
                    $result['error'] = __('Failed to update asset', 'advancedldap');
                }
            } else {
                // Créer un nouvel asset
                $asset_id = $asset->add($prepared_data);
                if ($asset_id) {
                    $result['success'] = true;
                    $result['action'] = 'created';
                    $result['asset_id'] = $asset_id;
                } else {
                    $result['error'] = __('Failed to create asset', 'advancedldap');
                }
            }

        } catch (Exception $e) {
            $result['error'] = sprintf(__('Asset creation error: %s', 'advancedldap'), $e->getMessage());
        }

        return $result;
    }

    /**
     * Prépare les données d'asset pour GLPI
     *
     * @param array<string, mixed> $asset_data Données brutes
     * @param string $asset_type Type d'asset
     * @return array<string, mixed> Données préparées
     */
    private function prepareAssetData(array $asset_data, string $asset_type): array
    {
        // Copier les données de base
        $prepared = [];
        foreach ($asset_data as $field => $value) {
            $prepared[$field] = is_string($value) ? trim($value) : $value;
        }

        // Gérer le champ location (convertir nom en ID)
        if (isset($prepared['locations_id']) && !is_numeric($prepared['locations_id'])) {
            $prepared['locations_id'] = $this->getLocationId($prepared['locations_id']);
        }

        // Gérer l'entité (utiliser l'entité active de la session)
        if (!isset($prepared['entities_id'])) {
            $prepared['entities_id'] = Session::getActiveEntity();
        }

        // Appliquer les valeurs par défaut depuis la configuration
        $asset_type_name = basename(str_replace('\\', '/', $asset_type));
        $prepared = AssetFieldConfig::applyDefaults($asset_type_name, $prepared);

        // S'assurer que le nom est défini
        if (empty($prepared['name'])) {
            $prepared['name'] = __('Unnamed Asset', 'advancedldap');
        }

        // Date de création
        if (!isset($prepared['date_creation'])) {
            $prepared['date_creation'] = date('Y-m-d H:i:s');
        }

        return $prepared;
    }

    /**
     * Trouve un asset existant par son nom
     *
     * @param CommonDBTM $asset Instance d'asset
     * @param array<string, mixed> $asset_data Données de l'asset
     * @return array<string, mixed>|null Données de l'asset existant ou null
     */
    private function findExistingAsset(CommonDBTM $asset, array $asset_data): ?array
    {
        global $DB;

        $table = $asset->getTable();
        $name = $asset_data['name'];

        $iterator = $DB->request([
            'FROM' => $table,
            'WHERE' => ['name' => $name],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $data) {
            return $data;
        }

        return null;
    }

    /**
     * Récupère l'ID d'un emplacement par son nom, le crée si nécessaire
     *
     * @param string $location_name Nom de l'emplacement
     * @return int ID de l'emplacement
     */
    private function getLocationId(string $location_name): int
    {
        global $DB;

        if (empty($location_name)) {
            return 0;
        }

        // Chercher l'emplacement existant
        $iterator = $DB->request([
            'FROM' => 'glpi_locations',
            'WHERE' => ['name' => $location_name],
            'LIMIT' => 1,
        ]);

        foreach ($iterator as $data) {
            return (int) $data['id'];
        }

        // Créer un nouvel emplacement
        $location = new Location();
        $location_id = $location->add([
            'name' => $location_name,
            'entities_id' => Session::getActiveEntity(),
        ]);

        return $location_id ?: 0;
    }

    /**
     * Gère la création/mise à jour d'un asset générique
     *
     * @param string $asset_type GenericAsset_ID format
     * @param array<string, mixed> $asset_data Données de l'asset
     * @return array{success: bool, action: string|null, asset_id: int|null, error: string|null}
     */
    private function handleGenericAsset(string $asset_type, array $asset_data): array
    {
        $result = [
            'success' => false,
            'action' => null,
            'asset_id' => null,
            'error' => null,
        ];

        try {
            // Extraire l'ID de définition d'asset (GenericAsset_1 => 1)
            $asset_definition_id = (int) str_replace('GenericAsset_', '', $asset_type);

            // Charger la définition d'asset
            $definition = new AssetDefinition();
            if (!$definition->getFromDB($asset_definition_id)) {
                $result['error'] = sprintf(__('Asset definition %d not found', 'advancedldap'), $asset_definition_id);
                return $result;
            }

            // Récupérer la classe concrète de l'asset
            $concrete_class = $definition->getAssetClassName();

            // Créer l'instance d'asset avec la classe concrète
            /** @phpstan-ignore glpi.forbidDynamicInstantiation */
            $asset = new $concrete_class();
            if (!$asset instanceof CommonDBTM) {
                $result['error'] = sprintf(__('Invalid asset class %s', 'advancedldap'), $concrete_class);
                return $result;
            }

            // Préparer les données pour l'asset générique
            $prepared_data = $this->prepareGenericAssetData($asset_data, $asset_definition_id);

            // Vérifier si l'asset existe déjà
            $existing_asset = $this->findExistingAsset($asset, $prepared_data);

            if ($existing_asset) {
                // Mettre à jour
                $update_data = $prepared_data;
                $update_data['id'] = $existing_asset['id'];

                if ($asset->update($update_data)) {
                    $result['success'] = true;
                    $result['action'] = 'updated';
                    $result['asset_id'] = $existing_asset['id'];
                } else {
                    $result['error'] = __('Failed to update asset', 'advancedldap');
                }
            } else {
                // Créer
                $asset_id = $asset->add($prepared_data);
                if ($asset_id) {
                    $result['success'] = true;
                    $result['action'] = 'created';
                    $result['asset_id'] = $asset_id;
                } else {
                    $result['error'] = __('Failed to create asset', 'advancedldap');
                }
            }

        } catch (Exception $e) {
            $result['error'] = sprintf(__('Generic asset error: %s', 'advancedldap'), $e->getMessage());
        }

        return $result;
    }

    /**
     * Prépare les données pour un asset générique
     *
     * @param array<string, mixed> $asset_data Données brutes
     * @param int $asset_definition_id ID de la définition d'asset
     * @return array<string, mixed> Données préparées
     */
    private function prepareGenericAssetData(array $asset_data, int $asset_definition_id): array
    {
        $prepared = [];

        // Copier les champs de base
        foreach ($asset_data as $field => $value) {
            $prepared[$field] = is_string($value) ? trim($value) : $value;
        }

        // Gérer le champ location
        if (isset($prepared['locations_id']) && !is_numeric($prepared['locations_id'])) {
            $prepared['locations_id'] = $this->getLocationId($prepared['locations_id']);
        }

        // Gérer l'entité
        if (!isset($prepared['entities_id'])) {
            $prepared['entities_id'] = Session::getActiveEntity();
        }

        // Associer à la définition d'asset
        $prepared['assets_assetdefinitions_id'] = $asset_definition_id;

        // S'assurer que le nom est défini
        if (empty($prepared['name'])) {
            $prepared['name'] = __('Unnamed Asset', 'advancedldap');
        }

        return $prepared;
    }

    // =========================================================================
    // SECTION: Classification des types d'assets
    // =========================================================================

    /**
     * Vérifie si un type d'asset est inventoriable
     *
     * @param string $asset_type Nom de classe ou GenericAsset_ID
     * @return bool True si inventoriable
     */
    public function isInventoriableAsset(string $asset_type): bool
    {
        if (empty($asset_type)) {
            return false;
        }

        // Gérer les assets génériques (GenericAsset_ID)
        if (str_starts_with($asset_type, 'GenericAsset_')) {
            return $this->isGenericAssetInventoriable($asset_type);
        }

        // Vérifier si c'est un type natif inventoriable
        if (!class_exists($asset_type)) {
            return false;
        }

        return in_array($asset_type, self::INVENTORIABLE_TYPES, true);
    }

    /**
     * Récupère la méthode de synchronisation pour un type d'asset
     *
     * @param string $asset_type Nom de classe
     * @return string 'inventory' ou 'traditional'
     */
    public function getSyncMethod(string $asset_type): string
    {
        return $this->isInventoriableAsset($asset_type) ? 'inventory' : 'traditional';
    }

    /**
     * Vérifie si un asset générique est inventoriable
     *
     * @param string $asset_type GenericAsset_ID format
     * @return bool True si inventoriable
     */
    private function isGenericAssetInventoriable(string $asset_type): bool
    {
        try {
            // Extraire l'ID de définition
            $asset_definition_id = (int) str_replace('GenericAsset_', '', $asset_type);

            if ($asset_definition_id <= 0) {
                return false;
            }

            // Charger la définition
            $definition = new AssetDefinition();
            if (!$definition->getFromDB($asset_definition_id)) {
                return false;
            }

            // Vérifier si la capacité IsInventoriable est activée
            return $definition->hasCapacityEnabled(new IsInventoriableCapacity());

        } catch (Exception) {
            return false;
        }
    }

    // =========================================================================
    // SECTION: Récupération des champs d'assets
    // =========================================================================

    /**
     * Récupère tous les types d'assets disponibles (natifs + génériques)
     *
     * @return array<string, array{name: string, type: string}> Types d'assets avec métadonnées
     */
    public function getAllAssetTypes(): array
    {
        global $DB;

        $asset_types = [];

        // Ajouter les assets natifs
        foreach (self::NATIVE_ASSET_TYPES as $short_name => $class_name) {
            if (class_exists($class_name) && method_exists($class_name, 'getTypeName')) {
                $asset_types[$class_name] = [
                    'name' => $class_name::getTypeName(1),
                    'type' => 'native',
                ];
            }
        }

        // Ajouter les assets génériques
        try {
            $iterator = $DB->request([
                'FROM' => 'glpi_assets_assetdefinitions',
                'WHERE' => ['is_active' => 1],
                'ORDER' => 'system_name',
            ]);

            foreach ($iterator as $data) {
                if (!empty($data['system_name'])) {
                    $asset_types['GenericAsset_' . $data['id']] = [
                        'name' => $data['label'] ?? $data['name'] ?? $data['system_name'],
                        'type' => 'generic',
                    ];
                }
            }
        } catch (Exception $e) {
            Toolbox::logDebug("AssetService: Error loading generic assets: " . $e->getMessage());
        }

        return $asset_types;
    }

    /**
     * Récupère les itemtypes disponibles (format simple nom => label)
     *
     * @return array<string, string> Tableau itemtype => label
     */
    public function getAvailableItemtypes(): array
    {
        $asset_types = $this->getAllAssetTypes();
        $itemtypes = [];

        foreach ($asset_types as $itemtype => $info) {
            $itemtypes[$itemtype] = $info['name'];
        }

        asort($itemtypes);
        return $itemtypes;
    }

    /**
     * Récupère les champs disponibles pour un itemtype
     *
     * @param string $itemtype Nom de classe ou GenericAsset_ID
     * @return array<string, string> Tableau field_key => field_label
     */
    public function getItemtypeFields(string $itemtype): array
    {
        // Pour l'instant, retourner des champs de base
        // Cette méthode peut être étendue pour retourner les champs réels de chaque type
        return [
            'name' => __('Name'),
            'serial' => __('Serial number'),
            'otherserial' => __('Inventory number'),
            'comment' => __('Comments'),
            'locations_id' => __('Location'),
            'manufacturers_id' => __('Manufacturer'),
            'models_id' => __('Model'),
            'contact' => __('Alternate username'),
            'contact_num' => __('Alternate username number'),
        ];
    }

    /**
     * Valide les données d'un asset avant création
     *
     * @param array<string, mixed> $asset_data Données de l'asset
     * @param string $asset_type Type d'asset
     * @return array{valid: bool, errors: array<string>} Résultat de validation
     */
    public function validateAssetData(array $asset_data, string $asset_type): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
        ];

        // Vérifier les champs requis
        if (empty($asset_data['name'])) {
            $result['valid'] = false;
            $result['errors'][] = __('Asset name is required', 'advancedldap');
        }

        // Vérifier la validité du type
        if (!class_exists($asset_type) && !str_starts_with($asset_type, 'GenericAsset_')) {
            $result['valid'] = false;
            $result['errors'][] = sprintf(__('Invalid asset type: %s', 'advancedldap'), $asset_type);
        }

        return $result;
    }
}
