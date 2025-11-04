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

use Glpi\Asset\AssetDefinition;
use GlpiPlugin\Advancedldap\Model\SyncFilter;
use SingletonTrait;

/**
 * Service consolidé pour la manipulation des données LDAP
 *
 * Fusionne les fonctionnalités de :
 * - LdapDataExtractor : extraction et normalisation des données LDAP
 * - LdapParameterValidator : validation des paramètres LDAP
 * - LdapAttributeMapper : mapping entre champs GLPI et attributs LDAP
 */
class LdapDataService
{
    use SingletonTrait;

    /**
     * Attributs LDAP communs pour le nom d'un appareil/asset (par priorité)
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

    /**
     * Mappings standards RFC 4519 entre champs GLPI et attributs LDAP
     *
     * @var array<string, string>
     */
    private array $standard_mappings = [
        'serial' => 'serialNumber',
        'name' => 'cn',
        'comment' => 'description',
        'location' => 'l',
        'phone' => 'telephoneNumber',
        'mail' => 'mail',
        'email' => 'mail',
        'emails' => 'mail',
        'firstname' => 'givenName',
        'realname' => 'sn',
        'mobile' => 'mobile',
        'title' => 'title',
        'uuid' => 'entryUUID',
        'dn' => 'distinguishedName',
        'address' => 'street',
        'street' => 'street',
        'city' => 'l',
        'postalcode' => 'postalCode',
        'country' => 'c',
        'fax' => 'facsimileTelephoneNumber',
    ];

    // =========================================================================
    // SECTION: Extraction de données LDAP
    // =========================================================================

    /**
     * Extrait le nom d'un appareil/asset depuis une entrée LDAP
     *
     * @param array<string, mixed> $ldap_entry Entrée LDAP
     * @param string|null $fallback Valeur par défaut si aucun nom trouvé
     * @return string Nom extrait ou valeur par défaut
     */
    public function extractDeviceName(array $ldap_entry, ?string $fallback = 'Unknown Device'): string
    {
        foreach (self::NAME_ATTRIBUTES as $attribute) {
            $normalized_attr = strtolower($attribute);

            if (isset($ldap_entry[$normalized_attr])) {
                $value = $this->normalizeValue($ldap_entry[$normalized_attr]);
                if (!empty($value)) {
                    return (string) $value;
                }
            }
        }

        return $fallback ?? 'Unknown Device';
    }

    /**
     * Extrait les données d'un asset depuis une entrée LDAP avec les mappings de champs
     *
     * @param array<string, mixed> $ldap_entry Entrée LDAP
     * @param array<string, string> $field_mappings Mapping champ GLPI => attribut LDAP
     * @return array<string, mixed> Données extraites avec noms de champs GLPI
     */
    public function extractAssetData(array $ldap_entry, array $field_mappings): array
    {
        $asset_data = [];

        foreach ($field_mappings as $glpi_field => $ldap_attribute) {
            $normalized_ldap_attribute = strtolower($ldap_attribute);

            if (isset($ldap_entry[$normalized_ldap_attribute])) {
                $ldap_value = $ldap_entry[$normalized_ldap_attribute];
                $asset_data[$glpi_field] = $this->normalizeValue($ldap_value, true);
            }
        }

        // S'assurer qu'on a au moins un nom
        if (empty($asset_data['name'])) {
            $asset_data['name'] = $this->extractDeviceName($ldap_entry);
        }

        return $asset_data;
    }

    /**
     * Normalise une valeur LDAP (gestion des tableaux)
     *
     * @param mixed $value Valeur LDAP (scalaire ou tableau)
     * @param bool $multi_value_join Si true, joint les valeurs multiples avec une virgule
     * @return mixed Valeur normalisée
     */
    public function normalizeValue($value, bool $multi_value_join = false)
    {
        if (!is_array($value)) {
            return $value;
        }

        // Supprimer les métadonnées LDAP 'count'
        if (isset($value['count'])) {
            unset($value['count']);
        }

        // Tableau vide après suppression du count
        if ($value === []) {
            return '';
        }

        // Valeur unique
        if (count($value) === 1) {
            return $value[0];
        }

        // Valeurs multiples
        if ($multi_value_join) {
            return implode(', ', $value);
        }

        return $value[0];
    }

    /**
     * Vérifie si une entrée LDAP a un attribut avec une valeur non vide
     *
     * @param array<string, mixed> $ldap_entry Entrée LDAP
     * @param string $attribute Nom de l'attribut (case-insensitive)
     * @return bool True si l'attribut existe et a une valeur non vide
     */
    public function hasAttribute(array $ldap_entry, string $attribute): bool
    {
        $normalized = strtolower($attribute);

        if (!isset($ldap_entry[$normalized])) {
            return false;
        }

        $value = $this->normalizeValue($ldap_entry[$normalized]);
        return !empty($value);
    }

    /**
     * Récupère la valeur d'un attribut depuis une entrée LDAP
     *
     * @param array<string, mixed> $ldap_entry Entrée LDAP
     * @param string $attribute Nom de l'attribut (case-insensitive)
     * @param mixed $default Valeur par défaut si attribut non trouvé
     * @param bool $multi_value_join Joint les valeurs multiples avec virgule
     * @return mixed Valeur de l'attribut ou défaut
     */
    public function getAttribute(array $ldap_entry, string $attribute, $default = null, bool $multi_value_join = false)
    {
        $normalized = strtolower($attribute);

        if (!isset($ldap_entry[$normalized])) {
            return $default;
        }

        return $this->normalizeValue($ldap_entry[$normalized], $multi_value_join);
    }

    // =========================================================================
    // SECTION: Validation de paramètres LDAP
    // =========================================================================

    /**
     * Valide les paramètres LDAP de base
     *
     * @param string $base_dn Base DN
     * @param string $filter Filtre LDAP
     * @param string $asset_type Type d'asset
     * @return string|null Message d'erreur ou null si valide
     */
    public function validateBasicParameters(string $base_dn, string $filter, string $asset_type): ?string
    {
        if (empty($base_dn)) {
            return __('Base DN is required', 'advancedldap');
        }

        if (empty($filter)) {
            return __('LDAP filter is required', 'advancedldap');
        }

        if (empty($asset_type)) {
            return __('Asset type is required', 'advancedldap');
        }

        return null;
    }

    /**
     * Valide qu'un type d'asset existe
     *
     * @param string $asset_type Nom de classe d'asset ou GenericAsset_ID
     * @return string|null Message d'erreur ou null si valide
     */
    public function validateAssetTypeExists(string $asset_type): ?string
    {
        // Gérer les assets génériques (format: GenericAsset_ID)
        if (str_starts_with($asset_type, 'GenericAsset_')) {
            $asset_definition_id = (int) str_replace('GenericAsset_', '', $asset_type);
            $definition = new AssetDefinition();
            if (!$definition->getFromDB($asset_definition_id)) {
                return sprintf(__('Asset definition %d not found', 'advancedldap'), $asset_definition_id);
            }
            return null;
        }

        // Valider les types d'assets natifs
        if (!class_exists($asset_type)) {
            return sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type);
        }

        return null;
    }

    /**
     * Valide un objet SyncFilter
     *
     * @param SyncFilter $sync_filter Filtre de synchronisation à valider
     * @return string|null Message d'erreur ou null si valide
     */
    public function validateSyncFilter(SyncFilter $sync_filter): ?string
    {
        // Valider les paramètres de base
        $error = $this->validateBasicParameters(
            $sync_filter->getField('base_dn') ?? '',
            $sync_filter->getField('ldap_filter') ?? '',
            $sync_filter->getField('asset_type') ?? ''
        );

        if ($error !== null) {
            return $error;
        }

        // Valider que les mappings de champs existent
        $field_mappings = $sync_filter->getFieldMappings();
        if ($field_mappings === []) {
            return __('Field mappings are required', 'advancedldap');
        }

        // Valider que le type d'asset existe
        $asset_type = $sync_filter->getField('asset_type');
        return $this->validateAssetTypeExists($asset_type);
    }

    /**
     * Valide les paramètres de connexion LDAP
     *
     * @param string $host Hôte LDAP
     * @param int $port Port LDAP
     * @param string $base_dn Base DN
     * @return string|null Message d'erreur ou null si valide
     */
    public function validateConnectionParameters(string $host, int $port, string $base_dn): ?string
    {
        if (empty($host)) {
            return __('LDAP host is required', 'advancedldap');
        }

        if ($port <= 0 || $port > 65535) {
            return __('Invalid LDAP port', 'advancedldap');
        }

        if (empty($base_dn)) {
            return __('Base DN is required', 'advancedldap');
        }

        return null;
    }

    /**
     * Valide un tableau de mappings de champs
     *
     * @param array<int, array<string, mixed>> $field_mappings Tableau de mappings
     * @return string|null Message d'erreur ou null si valide
     */
    public function validateFieldMappings(array $field_mappings): ?string
    {
        if ($field_mappings === []) {
            return __('Field mappings are required', 'advancedldap');
        }

        foreach ($field_mappings as $mapping) {
            if (empty($mapping['ldap_field']) || empty($mapping['glpi_field'])) {
                return __('Invalid field mapping: both LDAP and GLPI fields are required', 'advancedldap');
            }
        }

        return null;
    }

    // =========================================================================
    // SECTION: Mapping d'attributs LDAP
    // =========================================================================

    /**
     * Trouve l'attribut LDAP correspondant à un champ GLPI
     *
     * @param string $glpi_field Nom du champ GLPI
     * @param array<string> $available_ldap_attributes Attributs LDAP disponibles
     * @return string Meilleur attribut LDAP correspondant
     */
    public function findMatchingAttribute(string $glpi_field, array $available_ldap_attributes): string
    {
        $normalized_field = strtolower($glpi_field);

        // Vérifier si on a un mapping standard et que l'attribut LDAP existe
        if (isset($this->standard_mappings[$normalized_field])) {
            $mapped_attribute = $this->standard_mappings[$normalized_field];
            if (in_array($mapped_attribute, $available_ldap_attributes)) {
                return $mapped_attribute;
            }
        }

        // Fallback : si le nom exact du champ GLPI existe comme attribut LDAP, l'utiliser
        if (in_array($glpi_field, $available_ldap_attributes)) {
            return $glpi_field;
        }

        // Fallback final : utiliser le nom du champ GLPI (peut ne pas exister dans LDAP)
        return $glpi_field;
    }

    /**
     * Récupère tous les mappings standards RFC 4519
     *
     * @return array<string, string> Tableau de mapping [champ_glpi => attribut_ldap]
     */
    public function getStandardMappings(): array
    {
        return $this->standard_mappings;
    }

    /**
     * Récupère le mapping inverse (attribut LDAP vers champ GLPI)
     *
     * @param string $ldap_attribute Nom de l'attribut LDAP
     * @return string|null Champ GLPI correspondant ou null
     */
    public function getGlpiFieldForAttribute(string $ldap_attribute): ?string
    {
        $reverse_mapping = array_flip($this->standard_mappings);

        return $reverse_mapping[$ldap_attribute] ?? null;
    }

    /**
     * Valide si un attribut LDAP existe dans les attributs disponibles
     *
     * @param string $ldap_attribute Attribut à vérifier
     * @param array<string> $available_attributes Liste des attributs disponibles
     * @return bool True si l'attribut est disponible
     */
    public function isAttributeAvailable(string $ldap_attribute, array $available_attributes): bool
    {
        return in_array($ldap_attribute, $available_attributes);
    }
}
