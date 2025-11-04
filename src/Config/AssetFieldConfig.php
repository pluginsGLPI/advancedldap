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

namespace GlpiPlugin\Advancedldap\Config;

/**
 * Configuration simple pour les champs d'assets
 *
 * Remplace 5 classes + 1 factory + 2 providers + 2 interfaces par une simple configuration statique
 */
class AssetFieldConfig
{
    /**
     * Configuration des champs pour chaque type d'asset
     *
     * Structure: [asset_type => [required => [...], defaults => [...]]]
     */
    private const ASSET_CONFIGS = [
        'Computer' => [
            'required' => ['name'],
            'defaults' => [
                'computertypes_id' => 0,
                'states_id' => 0,
            ]
        ],
        'NetworkEquipment' => [
            'required' => ['name'],
            'defaults' => [
                'networkequipmenttypes_id' => 0,
                'states_id' => 0,
            ]
        ],
        'Phone' => [
            'required' => ['name', 'serial'],
            'defaults' => [
                'phonetypes_id' => 0,
                'states_id' => 0,
            ]
        ],
        'Printer' => [
            'required' => ['name'],
            'defaults' => [
                'printertypes_id' => 0,
                'states_id' => 0,
            ]
        ],
        'User' => [
            'required' => ['name'],
            'defaults' => [
                'is_active' => 1,
                'is_deleted' => 0,
            ]
        ],
    ];

    /**
     * Récupère les valeurs par défaut pour un type d'asset
     *
     * @param string $asset_type Type d'asset (ex: 'Computer', 'Phone', etc.)
     * @return array<string, mixed> Valeurs par défaut
     */
    public static function getDefaults(string $asset_type): array
    {
        return self::ASSET_CONFIGS[$asset_type]['defaults'] ?? [];
    }

    /**
     * Récupère les champs requis pour un type d'asset
     *
     * @param string $asset_type Type d'asset (ex: 'Computer', 'Phone', etc.)
     * @return array<string> Liste des champs requis
     */
    public static function getRequiredFields(string $asset_type): array
    {
        return self::ASSET_CONFIGS[$asset_type]['required'] ?? ['name'];
    }

    /**
     * Applique les valeurs par défaut à un tableau de données
     *
     * @param string $asset_type Type d'asset
     * @param array<string, mixed> $data Données à compléter
     * @return array<string, mixed> Données avec valeurs par défaut appliquées
     */
    public static function applyDefaults(string $asset_type, array $data): array
    {
        $defaults = self::getDefaults($asset_type);

        foreach ($defaults as $field => $value) {
            if (!isset($data[$field])) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * Vérifie si un type d'asset est supporté
     *
     * @param string $asset_type Type d'asset
     * @return bool True si supporté
     */
    public static function supports(string $asset_type): bool
    {
        return isset(self::ASSET_CONFIGS[$asset_type]);
    }

    /**
     * Récupère tous les types d'assets supportés
     *
     * @return array<string> Liste des types d'assets
     */
    public static function getSupportedAssetTypes(): array
    {
        return array_keys(self::ASSET_CONFIGS);
    }

    /**
     * Valide qu'un tableau de données contient tous les champs requis
     *
     * @param string $asset_type Type d'asset
     * @param array<string, mixed> $data Données à valider
     * @return array<string> Liste des champs manquants (vide si tout est OK)
     */
    public static function getMissingRequiredFields(string $asset_type, array $data): array
    {
        $required = self::getRequiredFields($asset_type);
        $missing = [];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Valide et applique les valeurs par défaut en une seule opération
     *
     * @param string $asset_type Type d'asset
     * @param array<string, mixed> $data Données à traiter
     * @return array{valid: bool, data: array<string, mixed>, missing: array<string>}
     */
    public static function validateAndPrepare(string $asset_type, array $data): array
    {
        // Appliquer les valeurs par défaut
        $data = self::applyDefaults($asset_type, $data);

        // Vérifier les champs requis
        $missing = self::getMissingRequiredFields($asset_type, $data);

        return [
            'valid' => empty($missing),
            'data' => $data,
            'missing' => $missing,
        ];
    }
}
