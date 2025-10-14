# Advanced LDAP Plugin - Notes Développeur

Ce document présente l'architecture technique du plugin Advanced LDAP et les bonnes pratiques pour le développer.

**Dernière mise à jour : 10 octobre 2025**

## Vue d'Ensemble

Le plugin **advancedldap** étend GLPI en ajoutant des capacités avancées de synchronisation LDAP pour les assets (équipements). Il permet de définir des filtres de synchronisation personnalisés qui s'intègrent directement dans les serveurs LDAP existants de GLPI via un onglet dédié dans AuthLDAP.

### Points Clés
- **Architecture SOLID** : Respect des principes SRP, DIP, ISP avec injection de dépendances
- **Zero dépendance externe** : Utilise uniquement le core GLPI
- **Double workflow** : Support traditionnel (CommonDBTM) + inventaire natif GLPI
- **Namespace moderne** : `GlpiPlugin\Advancedldap` avec compatibilité legacy
- **PHP 8.2+** : Types stricts, promotion de constructeur, attributs

## Structure Complète du Plugin

```
📁 advancedldap/
  │
  ├── 🎯 INTERFACE UTILISATEUR
  │   ├── front/
  │   │   ├── syncfilter.php           # Page liste des SyncFilters (Search::show)
  │   │   └── syncfilter.form.php      # Page CRUD SyncFilter (add/update/delete/test/sync)
  │   │
  │   ├── ajax/
  │   │   └── getAssetFields.php       # Endpoint AJAX chargement dynamique champs assets
  │   │
  │   └── templates/
  │       ├── syncfilter_form.html.twig      # Formulaire édition SyncFilter
  │       └── syncfilters_list.html.twig     # Liste filtres onglet AuthLDAP
  │
  ├── 🧩 MODÈLES DE DONNÉES
  │   └── src/Models/
  │       ├── SyncFilter.php                 # Modèle principal filtres LDAP
  │       │   • extends CommonDBTM           # → CRUD complet, massive actions
  │       │   • Alias legacy automatique     # → class_alias() pour Search GLPI 11
  │       │
  │       └── AuthLdapSyncFilter.php         # Modèle relation many-to-many
  │           • extends CommonDBRelation     # → AuthLDAP ↔ SyncFilter
  │           • Gestion unicity constraint   # → (authldap_id, syncfilter_id)
  │
  ├── 🔧 SERVICES MÉTIER (17 services)
  │   └── src/Services/
  │       │
  │       ├── 📋 SERVICES LDAP (8)
  │       │   ├── LdapSyncService.php                # Orchestration synchronisation complète
  │       │   ├── LdapTestService.php                # Tests et validation filtres LDAP
  │       │   ├── LdapInventoryService.php           # Intégration inventaire natif GLPI
  │       │   ├── LdapToInventoryConverter.php       # Conversion LDAP → format JSON Inventory
  │       │   ├── LdapFilterParser.php               # Parsing filtres LDAP (RFC 4515)
  │       │   ├── LdapAttributeMapper.php            # Mapping GLPI ↔ LDAP (RFC 4519)
  │       │   ├── LdapDataExtractor.php              # Extraction/normalisation données LDAP
  │       │   └── LdapFilterSanitizer.php            # 🔒 Sanitization anti-injection LDAP (RFC 4515/4514)
  │       │
  │       ├── 🎯 SERVICES ASSETS (3)
  │       │   ├── AssetFieldService.php              # Gestion unifiée champs d'assets
  │       │   ├── AssetCreationService.php           # Création/MAJ assets GLPI
  │       │   └── AssetTypeClassifier.php            # Classification inventoriables vs traditionnels
  │       │
  │       ├── 🔍 SERVICES SYNCFILTER (2)
  │       │   ├── SyncFilterService.php              # Logique métier filtres
  │       │   └── SyncFilterFormHelper.php           # Helpers formulaire (dropdown, config, connexion)
  │       │
  │       ├── 🛡️ VALIDATION (1)
  │       │   └── LdapParameterValidator.php         # Validation centralisée paramètres LDAP
  │       │
  │       └── 🔌 WRAPPERS GLPI (3)
  │           ├── GlpiDatabaseService.php            # Abstraction base de données
  │           ├── GlpiLdapConnectionService.php      # Abstraction connexions LDAP
  │           └── GlpiConfigurationService.php       # Abstraction configuration GLPI
  │
  ├── 📦 REPOSITORIES (Pattern Data Access)
  │   └── src/Repositories/
  │       ├── SyncFilterRepository.php               # Accès données filtres
  │       └── AuthLdapSyncFilterRepository.php       # Accès relations AuthLDAP/SyncFilter
  │
  ├── 🏭 PROVIDERS & FACTORIES
  │   ├── src/Providers/
  │   │   ├── NativeAssetFieldProvider.php          # Assets GLPI natifs (Computer, Monitor...)
  │   │   └── GenericAssetFieldProvider.php         # Assets génériques personnalisés
  │   │
  │   └── src/Factories/
  │       └── AssetFieldProviderFactory.php         # Factory création providers dynamiques
  │
  ├── 🔌 CONTRATS (10 interfaces)
  │   └── src/Contracts/
  │       ├── AssetFieldProviderInterface.php       # Contrat providers de champs
  │       ├── SyncFilterRepositoryInterface.php     # Contrat repository filtres
  │       ├── AuthLdapSyncFilterRepositoryInterface.php # Contrat repository relations
  │       ├── SyncFilterFormHelperInterface.php     # Contrat helpers formulaire
  │       ├── LdapFilterParserInterface.php         # Contrat parser filtres
  │       ├── LdapAttributeMapperInterface.php      # Contrat mapper attributs
  │       ├── LdapFilterSanitizerInterface.php      # 🔒 Contrat sanitization LDAP
  │       ├── DatabaseInterface.php                 # Contrat accès BDD
  │       ├── ConfigurationInterface.php            # Contrat configuration
  │       └── LdapConnectionInterface.php           # Contrat connexions LDAP
  │
  ├── 🏗️ INFRASTRUCTURE
  │   ├── src/Bootstrap.php                         # Initialisation services + factory
  │   ├── src/AdvancedLdapSync.php                  # Contrôleur principal (onglet AuthLDAP)
  │   └── src/Container/
  │       └── ServiceContainer.php                  # Conteneur DI (Dependency Injection)
  │
  ├── ⚙️ CONFIGURATION & HOOKS
  │   ├── setup.php                                 # Déclaration plugin, hooks, registrations
  │   ├── hook.php                                  # Installation/désinstallation/hooks
  │   ├── advancedldap.xml                          # Métadonnées plugin
  │   └── composer.json                             # Dépendances PHP 8.2+
  │
  └── 🧪 TESTS & OUTILS
      ├── tests/                                    # Tests unitaires (18 fichiers)
      ├── tools/                                    # Scripts maintenance
      ├── .php-cs-fixer.php                         # Configuration PSR-12
      ├── psalm.xml                                 # Configuration analyse statique
      └── phpunit.xml                               # Configuration tests

```


## Composants Principaux

### 1. Contrôleur Principal

#### **AdvancedLdapSync** (`src/AdvancedLdapSync.php`)
Classe principale étendant `CommonGLPI`, responsable de l'intégration dans GLPI :
- **Onglet AuthLDAP** : Ajoute "Advanced sync" avec badge de comptage
- **Affichage liste** : Utilise `syncfilters_list.html.twig` pour lister les filtres associés
- **Injection de dépendances** : Reçoit le `ServiceContainer` via constructeur
- **Méthodes clés** :
  - `getTabNameForItem()` : Déclaration onglet avec icône `ti-filter`
  - `displayTabContentForItem()` : Rendu template via `TemplateRenderer`
  - `getSyncFiltersForAuthLdap()` : Récupération filtres via repository

**Point d'entrée** : `http://localhost:8080/front/authldap.form.php?id=1`

---

### 2. Modèles de Données (Pattern Active Record)

#### **SyncFilter** (`src/Models/SyncFilter.php`) - 804 lignes
Modèle principal des filtres de synchronisation LDAP :
- **Héritage** : `extends CommonDBTM` → CRUD complet, historique, massive actions
- **Table** : `glpi_plugin_advancedldap_syncfilters`
- **Caractéristiques** :
  - Legacy compatibility via `class_alias()` pour Search GLPI 11
  - URLs personnalisées (`getSearchURL()`, `getFormURL()`)
  - Redirection intelligente vers AuthLDAP parent après suppression
  - Massive action "duplicate" avec duplication des relations
  - Formulaire principal (voir méthode `showForm()`)

**Champs principaux** :
```php
- name              : Nom du filtre
- ldap_filter       : Filtre LDAP (ex: (&(objectClass=device)(cn=*)))
- base_dn           : DN de base (ex: ou=devices,dc=example,dc=com)
- asset_type        : Classe GLPI (Computer, Monitor, Asset_Generic...)
- field_mappings    : JSON mappings LDAP→GLPI (ex: {"name":"cn","serial":"serialNumber"})
- is_active         : Statut (booléen)
```

**Méthodes de formulaire** :
- `showForm()` : Orchestration affichage formulaire (délégation vers services)
- `resolveParentAuthLdap()` : Résolution contexte parent depuis options/relations
- `prepareFormData()` : Préparation données (services, dropdowns, configuration)
- `handleTestRequest()` : Gestion requête de test LDAP ($_GET['test_ldap'])
- `collectFormMetadata()` : Collecte métadonnées (inventaire, connexion LDAP)

#### **AuthLdapSyncFilter** (`src/Models/AuthLdapSyncFilter.php`) - 142 lignes
Modèle de relation many-to-many AuthLDAP ↔ SyncFilter :
- **Héritage** : `extends CommonDBRelation`
- **Table** : `glpi_plugin_advancedldap_authldap_syncfilters`
- **Relations** :
  - `$itemtype_1` = `AuthLDAP` / `$items_id_1` = `authldap_id`
  - `$itemtype_2` = `SyncFilter` / `$items_id_2` = `syncfilter_id`
- **Contrainte** : UNIQUE KEY `unicity` (`authldap_id`, `syncfilter_id`)

---

### 3. Services Métier (17 services organisés)

#### 📋 **Services LDAP (8 services)**

##### **LdapSyncService** (`src/Services/LdapSyncService.php`)
Orchestration complète de la synchronisation LDAP → GLPI :
- **Dépendances** : `LdapConnectionInterface`, `AssetCreationService`, `AssetTypeClassifier`
- **Injection optionnelle** : `LdapInventoryService` (si inventaire activé)
- **Workflow** :
  1. Validation paramètres via `LdapParameterValidator`
  2. Récupération SyncFilter depuis DB
  3. Connexion LDAP et recherche via `AuthLDAP::connectToServer()`
  4. Classification assets (inventoriable vs traditionnel)
  5. Routage vers workflow approprié (inventaire ou CommonDBTM)
  6. Retour statistiques (`created`, `updated`, `errors`)

**Méthodes clés** :
- `synchronizeFromFilter()` : Point d'entrée principal
- `syncTraditionalWorkflow()` : Assets non-inventoriables (Computer natif si disabled)
- `syncInventoryWorkflow()` : Assets inventoriables via JSON

##### **LdapTestService** (`src/Services/LdapTestService.php`)
Tests et validation des filtres LDAP en temps réel :
- **Connexion safe** : Gestion des erreurs de connexion LDAP
- **Résultats** : Count, sample entries, champs disponibles
- **Validation** : Test before save pour éviter erreurs de synchronisation
- **Support Assets Génériques** : Analyse d'impact GLPI pour format `GenericAsset_ID`

**Méthode publique** :
- `testLdapFilter(int $authldap_id, string $base_dn, string $filter, string $asset_type, string $asset_field = '', array $field_mappings = []): array`

**Analyse d'Impact pour Assets Génériques** :
La méthode privée `analyzeGlpiImpact()` gère les assets génériques (lignes 297-319) :

```php
// Détection du format GenericAsset_ID
if (str_starts_with($asset_type, 'GenericAsset_')) {
    // Extraction ID et validation de la définition
    $asset_definition_id = (int) str_replace('GenericAsset_', '', $asset_type);
    $definition = new \Glpi\Asset\AssetDefinition();

    if (!$definition->getFromDB($asset_definition_id)) {
        return [
            'exists' => false,
            'message' => sprintf(__('Asset definition %d not found', 'advancedldap'), $asset_definition_id)
        ];
    }

    // Recherche dans la table glpi_assets_assets
    $asset_table = 'glpi_assets_assets';
} else {
    // Gestion assets natifs (Computer, Printer, etc.)
    if (!class_exists($asset_type)) {
        return ['exists' => false, 'message' => sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type)];
    }
    $asset_table = $this->database->getTableForItemType($asset_type);
}

// Recherche si l'asset existe déjà
$iterator = $this->database->request([
    'FROM'  => $asset_table,
    'WHERE' => ['name' => $asset_name],
    'LIMIT' => 1,
]);

// Message personnalisé selon existence
if (count($iterator) > 0) {
    $impact['message'] = sprintf(__('Asset "%s" exists, fields will be updated: %s', 'advancedldap'), ...);
} else {
    $impact['message'] = sprintf(__('Asset "%s" will be created with fields: %s', 'advancedldap'), ...);
}
```

**Tests unitaires** :
- ✅ 12 tests couvrant la méthode `testLdapFilter()` avec différents scénarios
- ⚠️ Tests pour assets génériques **non inclus** dans les tests unitaires
- Raison : Complexité de création d'asset definitions dans le contexte de test
- **Solution** : Tests d'intégration pour couvrir cette fonctionnalité

##### **LdapInventoryService** (`src/Services/LdapInventoryService.php`)
Intégration avec le système d'inventaire natif GLPI :
- **Conversion** : Utilise `LdapToInventoryConverter` pour transformer LDAP → JSON
- **Envoi** : Appelle `Inventory::sendInventory()` avec JSON formaté
- **Workflow** : Respecte le cycle complet inventaire GLPI (règles, fusion, etc.)
- **Détection d'échec silencieux** : Vérifie `$assetId = $item->getID()` après inventaire
  - Si `$assetId <= 0` → Échec de création
  - Appel à `getMinimumFieldRequirements($itemtype)` pour obtenir les exigences
  - Message explicite : "Inventory system could not create/update asset. Insufficient field mappings. For {itemtype}, you need at least: {requirements}"

**Exigences minimales par type d'asset** :
- **Computer** : Name (requis pour identification)
- **NetworkEquipment** : Name + Serial Number OU MAC Address (identification unique)
- **Printer** : Name (requis)
- **Phone** : Name + Serial Number (recommandé pour identification unique)
- **Défaut** : Name + unique identifier (Serial Number, MAC Address, etc.)

##### **LdapToInventoryConverter** (`src/Services/LdapToInventoryConverter.php`)
Conversion données LDAP vers format JSON attendu par `Inventory::sendInventory()` :
- **Format** : Respect spec JSON inventaire GLPI
- **Mapping** : Attributs LDAP → Sections inventaire
- **Types supportés** : Computer, NetworkEquipment, Printer, cf : https://github.com/glpi-project/glpi/blob/11.0/bugfixes/src/autoload/CFG_GLPI.php#L443-L448
- **Respect strict des Field Mappings** : Seuls les champs LDAP configurés dans les field mappings sont utilisés

**Nouvelle méthode** : `isFieldAllowed(string $ldapField, array $fieldMappings): bool`

**Logique de filtrage** :
1. Si `$fieldMappings` est vide → Tous les champs LDAP autorisés (backward compatibility)
2. Champs critiques TOUJOURS autorisés : `cn`, `name`, `displayname`, `samaccountname` (requis pour création assets)
3. Pour les autres champs → Vérification dans `$fieldMappings`

**Application dans toutes les méthodes de conversion** :
- `buildHardwareSection()` : UUID, chassis_type, memory
- `buildNetworkDeviceSection()` : Serial, manufacturer, model, firmware, MAC, IP, location, contact
- `buildComputerSpecificSections()` : Operating system, memory
- `buildNetworkEquipmentSections()` : Firmware
- `buildPrinterSections()` : Driver, serial, description
- `buildBiosSection()` : Manufacturer, version, date, model, serial, motherboard
- `buildNetworkSection()` : IP, MAC, description

**Impact** :
- Les assets créés via inventaire contiennent UNIQUEMENT les champs configurés
- Évite la création d'assets avec des données non souhaitées
- Respect de la configuration utilisateur

##### **LdapFilterParser** (`src/Services/LdapFilterParser.php`)
Parsing et validation des filtres LDAP selon RFC 4515 :
- **Extraction attributs** : Parse le filtre pour récupérer les attributs utilisés
- **Validation syntaxe** : Vérifie parenthèses, opérateurs, structure
- **Exemples** : `(&(objectClass=device)(cn=*))` → `['objectClass', 'cn']`

##### **LdapAttributeMapper** (`src/Services/LdapAttributeMapper.php`)
Mapping intelligent entre attributs GLPI et LDAP selon RFC 4519 :
- **Dictionnaire** : Mappings prédéfinis (ex: `name` → `cn`, `serial` → `serialNumber`)
- **Fallback** : Si pas de correspondance, retourne l'attribut tel quel
- **Usage** : Automatisation champs dans formulaire SyncFilter

##### **LdapDataExtractor** (`src/Services/LdapDataExtractor.php`)
Extraction et normalisation des données depuis entrées LDAP :
- **Constantes** : Listes d'attributs prioritaires (NAME_ATTRIBUTES, etc.)
- **Méthodes utilitaires** : Extraction robuste avec fallbacks
- **Centralisation** : Évite duplication logique d'extraction

##### **LdapFilterSanitizer** 🔒 (`src/Services/LdapFilterSanitizer.php`)
**Service de sécurité critique** - Protection contre les injections LDAP (RFC 4515/4514) :
- **Échappement filtres** : `escapeFilterValue()` - Échappe `\`, `*`, `(`, `)`, `\x00` selon RFC 4515
- **Échappement DN** : `sanitizeDN()` - Échappe caractères spéciaux DN (`,`, `+`, `"`, etc.) selon RFC 4514
- **Validation filtres** : `isValidFilter()` - Vérifie structure (parenthèses équilibrées, syntaxe valide)
- **Validation DN** : `isValidDN()` - Vérifie structure DN (composants valides, pas de métacaractères)
- **Points protégés** :
  - `front/syncfilter.form.php:108-122` - Validation avant test
  - `src/Models/SyncFilter.php:302-335` - Validation avant sauvegarde (add/update)
  - `src/Services/GlpiLdapConnectionService.php:187-196` - Validation avant recherche LDAP

**Tests** : 66 tests unitaires couvrant tous les cas d'injection et RFC compliance

##### **Workflow de Test LDAP Sécurisé**

**Règle fondamentale** : Le filtre doit être sauvegardé avant test

Le workflow de test a été modifié pour des raisons de sécurité :

**AVANT (vulnérable)** :
- Les paramètres de test étaient passés via `$_GET` (authldap_id, base_dn, filter)
- Possibilité de tester des filtres non validés

**APRÈS (sécurisé)** :
- Le filtre DOIT être sauvegardé en base (`$this->getID() > 0`)
- TOUTES les données proviennent de la base de données
- Validation stricte avant test :
  - Base DN : récupéré depuis `$this->fields['base_dn']`
  - Filtre LDAP : récupéré depuis `$this->fields['ldap_filter']`
  - AuthLDAP : récupéré via `getParentAuthLdapId()`
- Triple validation de sécurité appliquée (voir section Sécurité)

**Impact utilisateur** :
- Message explicite si tentative de test sur un filtre non sauvegardé
- Bouton "Test LDAP Filter" désactivé pour les nouveaux filtres (ID = 0)
- Texte d'aide : "Save your changes before testing to ensure accurate results"

**Fichiers modifiés** :
- `src/Models/SyncFilter.php` : Méthode `handleTestRequest()` complètement refactorisée (lignes 804-838)
- `templates/syncfilter_form.html.twig` : Bouton POST → Lien GET, ajout texte d'aide (lignes 204-216)
- `front/syncfilter.form.php` : Suppression de la gestion POST `test_ldap_filter` (lignes 272-304 supprimées)

---

#### 🎯 **Services Assets (3 services)**

##### **AssetFieldService** (`src/Services/AssetFieldService.php`)
Gestion unifiée des champs disponibles pour tous types d'assets :
- **Factory pattern** : Utilise `AssetFieldProviderFactory` pour instancier providers
- **Cache** : Optimisation performance via cache des métadonnées
- **Support** : Assets natifs + génériques + custom via providers

##### **AssetCreationService** (`src/Services/AssetCreationService.php`)
Création et mise à jour des assets GLPI (workflow traditionnel) :
- **Support Assets Natifs** : Computer, Printer, Monitor, NetworkEquipment, Phone, Peripheral
- **Support Assets Génériques** : Format `GenericAsset_ID` (ex: `GenericAsset_1`)
- **Workflow** :
  - Assets natifs → Instanciation directe de la classe (ex: `new Computer()`)
  - Assets génériques → Résolution via `AssetDefinition::getAssetClassName()` puis instanciation dynamique
- **Validation** : Vérification de l'existence de la définition d'asset générique via `getFromDB()`
- **Logs** : `Toolbox::logDebug()` pour traçabilité (méthode privée `handleGenericAsset()`)

**Méthodes publiques** :
- `createOrUpdateAsset(string $asset_type, array $asset_data): array` - Création/MAJ assets
- `validateAssetData(array $asset_data, string $asset_type): array` - Validation données

**Gestion des Assets Génériques** :
```php
// Format attendu pour asset générique
$asset_type = 'GenericAsset_1'; // ID = 1 de la table glpi_assets_assetdefinitions

// Workflow interne
1. Extraction ID depuis le format : str_replace('GenericAsset_', '', $asset_type) → 1
2. Chargement définition : new \Glpi\Asset\AssetDefinition()->getFromDB(1)
3. Récupération classe concrète : $definition->getAssetClassName() → 'Glpi\CustomAsset\FooAsset'
4. Instanciation dynamique : new $concrete_class()
5. Préparation données avec assets_assetdefinitions_id
6. Création/MAJ via méthodes CommonDBTM standards
```

**Tests unitaires** :
- ⚠️ Les tests pour assets génériques ne sont **pas inclus** dans les tests unitaires
- Raison : La méthode privée `handleGenericAsset()` génère des logs via `Toolbox::logDebug()`
- Framework GLPI rejette les "unexpected log entries" dans les tests unitaires
- **Solution** : Tests d'intégration pour couvrir cette fonctionnalité

##### **AssetTypeClassifier** (`src/Services/AssetTypeClassifier.php`)
Classification automatique des assets (inventoriables vs traditionnels) :
- **Règles** : Computer, NetworkEquipment, Printer... = inventoriables
- **Exception** : Si inventaire désactivé → tous traditionnels
- **Usage** : Décision workflow dans `LdapSyncService`

---

#### 🔍 **Services SyncFilter (2 services)**

##### **SyncFilterService** (`src/Services/SyncFilterService.php`)
Logique métier pour les filtres de synchronisation :
- **CRUD** : Opérations business sur SyncFilter (via repository)
- **Validation** : Règles métier avant sauvegarde
- **Relations** : Gestion associations AuthLDAP ↔ SyncFilter

##### **SyncFilterFormHelper** (`src/Services/SyncFilterFormHelper.php`)
Helpers spécialisés pour le formulaire SyncFilter :
- **Dropdowns** : Génération listes AuthLDAP, assets disponibles
- **Configuration** : Récupération config courante depuis DB
- **Connexion** : Test statut connexion LDAP pour UI

---

#### 🛡️ **Validation (1 service)**

##### **LdapParameterValidator** (`src/Services/LdapParameterValidator.php`)
Validation centralisée des paramètres LDAP :
- **Réduction duplication** : Code commun entre services
- **Validation** : Base DN, filter, asset type, field mappings
- **Retours** : Messages d'erreur explicites ou `null` si OK
- **Support Assets Génériques** : Validation du format `GenericAsset_ID`

**Méthodes publiques** :
- `validateBasicParameters(string $base_dn, string $filter, string $asset_type): ?string`
- `validateAssetTypeExists(string $asset_type): ?string` - **Supporte assets génériques**
- `validateSyncFilter(SyncFilter $sync_filter): ?string`
- `validateConnectionParameters(string $host, int $port, string $base_dn): ?string`
- `validateFieldMappings(array $field_mappings): ?string`

**Validation Assets Génériques** :
```php
// Méthode validateAssetTypeExists() - lignes 71-89
if (str_starts_with($asset_type, 'GenericAsset_')) {
    $asset_definition_id = (int) str_replace('GenericAsset_', '', $asset_type);
    $definition = new \Glpi\Asset\AssetDefinition();

    if (!$definition->getFromDB($asset_definition_id)) {
        return sprintf(__('Asset definition %d not found', 'advancedldap'), $asset_definition_id);
    }
    return null; // Validation réussie
}

// Validation classes natives GLPI
if (!class_exists($asset_type)) {
    return sprintf(__('Asset type %s not found', 'advancedldap'), $asset_type);
}
```

**Tests unitaires** :
- ✅ 30 tests couvrant toutes les méthodes publiques
- ✅ 2 tests spécifiques pour assets génériques :
  - `testValidateAssetTypeExistsWithValidGenericAsset()` - Création asset definition + validation
  - `testValidateAssetTypeExistsWithInvalidGenericAsset()` - ID inexistant (999999)

---

#### 🔌 **Wrappers GLPI (3 services)**

##### **GlpiDatabaseService** (`src/Services/GlpiDatabaseService.php`)
Abstraction complète de l'accès base de données :
- **Interface** : `DatabaseInterface` (testabilité, DIP)
- **Encapsulation** : `global $DB` caché derrière méthodes
- **Méthodes** : `request()`, `insert()`, `update()`, `delete()`, `tableExists()`

##### **GlpiLdapConnectionService** (`src/Services/GlpiLdapConnectionService.php`)
Abstraction des connexions LDAP via `AuthLDAP` :
- **Interface** : `LdapConnectionInterface`
- **Méthodes** : `connectToServer()`, `testConnection()`, `searchLdap()`
- **Gestion erreurs** : Wrapping exceptions LDAP

##### **GlpiConfigurationService** (`src/Services/GlpiConfigurationService.php`)
Abstraction de la configuration GLPI :
- **Interface** : `ConfigurationInterface`
- **Encapsulation** : `global $CFG_GLPI` caché
- **Méthodes** : `getGlpiConfig()`, `isInventoryEnabled()`, `getInventoryConfigUrl()`

---

### 4. Repositories (Pattern Data Access)

#### **SyncFilterRepository** (`src/Repositories/SyncFilterRepository.php`)
Accès aux données des filtres de synchronisation :
- **Méthodes** :
  - `getSyncFiltersForAuthLdap()` : Filtres pour un AuthLDAP donné
  - `getSyncFiltersForAuthLdapDetailed()` : Avec métadonnées complètes
  - `getAssociatedAuthLdaps()` : Liste AuthLDAP pour un filtre
  - `deleteAuthLdapRelations()` : Nettoyage relations

#### **AuthLdapSyncFilterRepository** (`src/Repositories/AuthLdapSyncFilterRepository.php`)
Accès aux relations AuthLDAP ↔ SyncFilter :
- **Méthodes** :
  - `getAuthLdapsForSyncFilter()` : AuthLDAP liés à un filtre
  - `getSyncFiltersForAuthLdap()` : Filtres liés à un AuthLDAP
  - `createRelation()` : Création relation avec gestion unicity
  - `deleteRelation()` : Suppression relation

**Correctif important** : Gestion correcte des itérateurs GLPI

**Problème** : Utilisation incorrecte de `is_array()` sur des itérateurs GLPI retournés par `$this->database->request()`

**Méthodes corrigées** :

1. **getAuthLdapsForSyncFilter()** (lignes 649-669)
   - **AVANT** : `if (!is_array($results))` + `array_column($results, 'authldap_id')`
   - **APRÈS** : `if (!is_iterable($iterator))` + boucle `foreach` pour construire le tableau
   - Respect des best practices GLPI (identique à `SyncFilterRepository`)

2. **hasSyncFiltersForAuthLdap()** (lignes 676-696)
   - **AVANT** : `if (!is_array($results))` + `!empty($results)`
   - **APRÈS** : `if (!is_iterable($iterator))` + `foreach` avec `return true` au premier résultat
   - Optimisation : arrêt dès qu'un résultat existe

**Impact** :
- Correction de bugs potentiels liés à la manipulation incorrecte des itérateurs
- Cohérence avec `SyncFilterRepository`
- Respect des conventions GLPI

---

### 5. Providers & Factories

#### **AssetFieldProviderFactory** (`src/Factories/AssetFieldProviderFactory.php`)
Factory pour création dynamique de providers de champs :
- **Stratégie** : Détection automatique type asset (natif, générique, custom)
- **Providers** :
  - `NativeAssetFieldProvider` : Computer, Monitor, Printer... (natifs GLPI)
  - `GenericAssetFieldProvider` : Assets génériques personnalisés
- **Extensibilité** : Ajout facile de nouveaux providers

#### **NativeAssetFieldProvider** (`src/Providers/NativeAssetFieldProvider.php`)
Gestion des champs pour assets GLPI natifs :
- **Types supportés** : Computer, Monitor, NetworkEquipment, Peripheral, Phone, Printer
- **Méthodes** : Extraction champs depuis SearchOption GLPI

#### **GenericAssetFieldProvider** (`src/Providers/GenericAssetFieldProvider.php`)
Gestion des champs pour assets génériques (plugin Assets) :
- **Détection dynamique** : Scan des définitions d'assets génériques
- **Flexibilité** : Support champs customs définis par utilisateurs

---

### 6. Infrastructure & Conteneur DI

#### **ServiceContainer** (`src/Container/ServiceContainer.php`)
Conteneur d'injection de dépendances (pattern Service Locator + Factory) :
- **Singleton** : `getInstance()` pour accès global
- **Registration** : `register($id, callable $factory)` pour enregistrer services
- **Lazy loading** : Services créés uniquement à la demande
- **16 services enregistrés** : Voir `registerDefaultServices()`

**Services enregistrés** :
```php
// Interfaces → Implémentations
DatabaseInterface::class                      → GlpiDatabaseService
ConfigurationInterface::class                 → GlpiConfigurationService
LdapConnectionInterface::class                → GlpiLdapConnectionService
LdapFilterParserInterface::class              → LdapFilterParser
LdapAttributeMapperInterface::class           → LdapAttributeMapper
LdapFilterSanitizerInterface::class           → LdapFilterSanitizer 🔒
AssetFieldProviderInterface::class            → AssetFieldService
SyncFilterRepositoryInterface::class          → SyncFilterRepository
AuthLdapSyncFilterRepositoryInterface::class  → AuthLdapSyncFilterRepository
SyncFilterFormHelperInterface::class          → SyncFilterFormHelper

// Classes concrètes
GlpiConfigurationService::class
AssetFieldProviderFactory::class
LdapTestService::class
SyncFilterService::class
AssetTypeClassifier::class
AssetCreationService::class
LdapToInventoryConverter::class
LdapInventoryService::class (injecté si inventaire activé)
LdapSyncService::class
```

#### **Bootstrap** (`src/Bootstrap.php`)
Classe statique d'initialisation simplifiée :
- **Méthodes** :
  - `initialize()` : Initialise et retourne le conteneur
  - `getContainer()` : Accès rapide au conteneur
  - `createAdvancedLdapSync()` : Factory pour contrôleur principal
  - `reset()` : Reset pour tests unitaires

**Usage** :
```php
$container = Bootstrap::getContainer();
$service = $container->get(LdapSyncService::class);
```

## Points d'Extension

### Ajouter un Nouveau Type d'Asset

1. **Créer le provider** :
```php
class CustomAssetFieldProvider implements AssetFieldProviderInterface {
    public function getItemtypeFields(string $itemtype): array {
        // Logique spécifique
    }
}
```

2. **Modifier la factory** :
```php
// AssetFieldProviderFactory::createProvider()
if (str_starts_with($itemtype, 'CustomAsset_')) {
    return new CustomAssetFieldProvider();
}
```

### Ajouter un Nouveau Service

1. **Créer l'interface** dans `Contracts/`
2. **Implémenter** dans `Services/`
3. **Enregistrer** dans `ServiceContainer::registerDefaultServices()`

### Étendre les Fonctionnalités LDAP

1. Étendre `LdapConnectionInterface` si nécessaire
2. Créer un service spécialisé héritant de `LdapTestService`
3. Enregistrer dans le conteneur

## Fichiers Clés

### **Configuration & Hooks**
- `setup.php` : Déclaration du plugin, enregistrement hooks et classes
  - `plugin_init_advancedldap()` : Initialisation hooks, tabs, massive actions
  - `plugin_version_advancedldap()` : Métadonnées (nom, version, compatibilité GLPI)
  - `plugin_advancedldap_check_prerequisites()` : Vérification prérequis installation
  - Enregistrement des alias legacy pour compatibilité Search GLPI 11
- `hook.php` : Installation, désinstallation, hooks
  - `plugin_advancedldap_install()` : Création tables BDD
  - `plugin_advancedldap_uninstall()` : Suppression tables BDD
  - `plugin_advancedldap_addDefaultWhere()` : Filtrage contextuel (AuthLDAP → SyncFilters)
  - `plugin_advancedldap_MassiveActions()` : Déclaration massive action "duplicate"

### **Interface Utilisateur**
- `front/syncfilter.php` : Page liste des filtres (utilise Search::show)
- `front/syncfilter.form.php` : Page CRUD complète (add, update, delete, test, sync)
- `templates/syncfilter_form.html.twig` : Formulaire édition SyncFilter
- `templates/syncfilters_list.html.twig` : Liste filtres dans onglet AuthLDAP
- `ajax/getAssetFields.php` : Endpoint AJAX pour chargement dynamique champs d'assets

#### **Améliorations de l'interface utilisateur**

##### **1. Pré-sélection des champs obligatoires**

**Template** : `syncfilter_form.html.twig`

**Nouvelle fonctionnalité** : Pré-sélection automatique des champs obligatoires lors du changement de type d'asset

**Objet JavaScript** : `minimumRequiredFields` (lignes 308-322)
```javascript
var minimumRequiredFields = {
    'Computer': ['name'],
    'NetworkEquipment': ['name', 'serial'],
    'Printer': ['name'],
    'Phone': ['name', 'serial']
};
```

**Fonction JavaScript** : `getMinimumRequiredFields(itemtype)` (lignes 334-344)
- Extrait le nom de classe depuis le namespace complet
- Retourne les champs obligatoires pour le type donné

**Workflow** :
1. L'utilisateur sélectionne un type d'asset (Computer, NetworkEquipment, etc.)
2. La fonction `loadAssetFields()` est appelée (ligne 346)
3. Les champs obligatoires sont fusionnés avec les champs déjà sélectionnés (lignes 354-360)
4. Appel AJAX pour charger les champs avec pré-sélection (ligne 362)
5. Message d'information affiché : "Minimum required fields for {itemtype}: {fields}" (lignes 371-378)

**Impact utilisateur** :
- Guidage automatique pour éviter les erreurs de configuration
- Message clair sur les champs requis
- Gain de temps lors de la configuration

##### **2. Simplification du workflow de test**

**Template** : `syncfilter_form.html.twig`

**AVANT** (lignes 204-208 supprimées) :
```html
<button type="submit" name="test_ldap_filter" class="btn btn-info me-2">
    <i class="ti ti-test-pipe"></i>
    <span>{{ __('Test and Sync LDAP Filter', 'advancedldap') }}</span>
</button>
```

**APRÈS** (lignes 204-216) :
```twig
{% set test_url = config('root_doc') ~ '/plugins/advancedldap/front/syncfilter.form.php?id=' ~ item.fields['id'] ~ '&test_ldap=1' %}
{% if current_authldap_id %}
    {% set test_url = test_url ~ '&authldap_id=' ~ current_authldap_id %}
{% endif %}
<a href="{{ test_url }}" class="btn btn-info me-2">
    <i class="ti ti-test-pipe"></i>
    <span>{{ __('Test LDAP Filter', 'advancedldap') }}</span>
</a>
<div class="form-text text-info mb-2">
    <i class="ti ti-info-circle me-1"></i>{{ __('Save your changes before testing to ensure accurate results', 'advancedldap') }}
</div>
```

**Changements** :
- Bouton POST → Lien GET (pas de soumission de formulaire)
- Texte simplifié : "Test and Sync" → "Test LDAP Filter"
- Ajout texte d'aide : "Save your changes before testing"
- Construction URL avec paramètres `id` et `authldap_id`

**Impact** :
- Workflow plus clair et intuitif
- Évite les soumissions de formulaire accidentelles
- Message explicite sur la nécessité de sauvegarder avant test

##### **3. Nettoyage des templates**

**Template** : `syncfilters_list.html.twig`

**Suppression** : Colonne "Actions" inutilisée (lignes 934-942 supprimées)
- Suppression de la colonne `<th>{{ __('Actions') }}</th>`
- Suppression des boutons "Test Filter" dans chaque ligne
- Raison : Redondance avec le formulaire d'édition

**Impact** :
- Interface plus épurée
- Moins de confusion pour l'utilisateur
- Tests disponibles uniquement dans le formulaire d'édition (cohérence)

### **Points d'Entrée**
- `Bootstrap::getContainer()` : Accès au conteneur de services (singleton)
- `Bootstrap::createAdvancedLdapSync()` : Factory pour contrôleur principal avec DI
- `ServiceContainer::getInstance()` : Accès direct au conteneur de services
- `AdvancedLdapSync::displayTabContentForItem()` : Affichage onglet "Advanced sync" dans AuthLDAP
- `AdvancedLdapSync::getTabNameForItem()` : Déclaration onglet avec icône et badge compteur

## Sécurité et Performance

### **Sécurité** 🔒

#### **Protection Injections LDAP (RFC 4515/4514)**
Le plugin implémente une protection complète contre les injections LDAP via le service `LdapFilterSanitizer` :

**Métacaractères échappés dans les filtres LDAP** (RFC 4515) :
- `\` (backslash) → `\5c`
- `*` (asterisk) → `\2a`
- `(` (parenthèse ouvrante) → `\28`
- `)` (parenthèse fermante) → `\29`
- `\x00` (null byte) → `\00`

**Métacaractères échappés dans les DN** (RFC 4514) :
- Tous les métacaractères de filtre ci-dessus
- `,` (virgule) → `\,`
- `+` (plus) → `\+`
- `"` (guillemets) → `\"`
- `<` `>` → `\<` `\>`
- `;` (point-virgule) → `\;`
- `=` (égal) → `\=`
- `#` (dièse) → `\#`
- Espaces de début/fin → `\ `

**Points de protection** :
1. **Validation avant test** (`front/syncfilter.form.php:108-122`)
   - Base DN : `isValidDN()` - Rejet si métacaractères ou structure invalide
   - Filtre : `sanitizeFilter()` - Rejet si syntaxe invalide

2. **Validation avant sauvegarde** (`src/Models/SyncFilter.php:302-335`)
   - Méthode `validateLdapInputs()` appelée dans `prepareInputForAdd()` et `prepareInputForUpdate()`
   - Empêche la sauvegarde de données malveillantes en base

3. **Validation avant recherche LDAP** (`src/Services/GlpiLdapConnectionService.php:187-196`)
   - Double validation DN + filtre avant `ldap_search()`
   - Retourne erreur si validation échoue (pas d'exécution)

**Tests de sécurité** :
- 66 tests unitaires dans `tests/LdapFilterSanitizerTest.php`
- Cas d'injection testés : `*))(|(objectClass=*`, `admin)(uid=*)`, backslash bypass, etc.
- Conformité RFC vérifiée : RFC 4515 (filtres) et RFC 4514 (DN)

#### **Protection XSS dans les Templates Twig** 🔒
Le plugin protège contre les injections XSS via un échappement systématique dans tous les templates :

**Templates sécurisés** :
1. **syncfilter_form.html.twig** (9 corrections)
   - Ligne 56 : `server_name` → échappement HTML explicite `|e('html')`
   - Ligne 75 : `server_name` → échappement HTML explicite `|e('html')`
   - Ligne 77 : `error` (message LDAP) → échappement HTML `|e('html')`
   - Ligne 231 : `test_results.error` → échappement HTML `|e('html')`
   - Ligne 266 : `entry.dn` (DN LDAP) → échappement HTML `|e('html')`
   - Ligne 269-273 : Attributs LDAP (`attr`, `values`) → échappement HTML `|e('html')`
   - Ligne 285 : `entry.glpi_impact.message` → échappement HTML `|e('html')`
   - Ligne 306 : **CRITIQUE** - `field_mappings` → `|json_encode|raw` au lieu de `|raw` seul
   - Lignes 327, 331, 344 : Chaînes JavaScript → échappement JS `|e('js')`
   - Suppression commentaires DEBUG (lignes 194, 197, 199) - Exposition logique interne

2. **syncfilters_list.html.twig** (2 corrections)
   - Ligne 46 : `filter.base_dn` → échappement HTML `|e('html')`
   - Ligne 49 : `filter.ldap_filter` → échappement HTML `|e('html')`

**Principes appliqués** :
- Toutes les données provenant de sources externes (LDAP, DB user input) sont échappées explicitement
- Contexte HTML : `|e('html')` pour empêcher injection de tags HTML/scripts
- Contexte JavaScript : `|e('js')` pour empêcher injection dans code JS inline
- JSON dans JavaScript : `|json_encode|raw` pour sérialisation sécurisée
- Commentaires de debug retirés pour éviter exposition de la logique applicative

**Vulnérabilité critique corrigée** :
```twig
# AVANT (Vulnérable XSS)
var fieldMappingsRaw = {{ item.fields['field_mappings']|default('{}')|raw }};

# APRÈS (Sécurisé)
var fieldMappingsRaw = {{ item.fields['field_mappings']|default('{}')|json_encode|raw }};
```

#### **Autres mesures de sécurité**
- Validation stricte des paramètres d'entrée
- Échappement HTML dans tous les templates Twig (explicite pour données externes)
- Pas d'exposition des variables globales
- Gestion centralisée des erreurs avec logs
- Droits GLPI respectés (READ, UPDATE requis)
- Suppression de tous les commentaires DEBUG en production

### **Performance**
- Services instanciés une seule fois (singleton)
- Chargement paresseux des dépendances
- Requêtes base de données optimisées
- Cache des métadonnées d'assets
- Logs de debug optimisés (réduction 99% du volume)

#### **Gestion du Timeout PHP lors des Synchronisations**

**Problème identifié** :
- Les synchronisations de gros volumes LDAP (300+ entrées) peuvent dépasser le timeout PHP par défaut (30 secondes)
- Le timeout se produit dans les requêtes DB (création/mise à jour assets), pas dans les requêtes LDAP
- Performance observée : ~100-150ms par entrée (recherche asset existant + création/mise à jour)

**Solutions standard GLPI** :

1. **Configuration PHP (✅ Solution recommandée)** :
   ```ini
   # Dans php.ini ou .htaccess
   max_execution_time = 300  # 5 minutes
   ```
   - Approche standard utilisée dans les déploiements GLPI
   - Mentionnée dans la documentation officielle GLPI pour les synchronisations LDAP
   - Ne nécessite pas de modification du code

2. **Commandes CLI (✅ Recommandé pour gros volumes)** :
   ```bash
   # Les commandes CLI n'ont pas de timeout par défaut
   php bin/console glpi:plugin:advancedldap:sync
   ```
   - CronTasks GLPI utilisent cette approche
   - Aucune limite d'exécution
   - Idéal pour automatisation

3. **Utilisation de `set_time_limit()` (⚠️ Option alternative)** :
   ```php
   // Au début de synchronizeFromFilter()
   @set_time_limit(300); // 5 minutes
   ```
   - Bien que GLPI core ne l'utilise pas, c'est acceptable pour un plugin
   - Utilisé par certains plugins communautaires
   - À documenter clairement si implémenté

**Solution implémentée dans ce plugin** :
- ✅ Optimisation des logs de debug (~99% de réduction)
  - AVANT : ~900-1500 logs pour 300 entrées
  - APRÈS : ~10 logs pour 300 entrées (début, progression tous les 50, fin)
- ✅ Logs de pagination LDAP conservés (utiles pour diagnostic)
- ✅ Mesure du temps d'exécution et affichage dans les logs
- ⚠️ `set_time_limit()` **non implémenté** : configuration PHP préférée

**Recommandations pour les administrateurs** :
1. **Petits volumes (<200 entrées)** : Synchronisation manuelle via interface web (OK avec timeout 30s)
2. **Volumes moyens (200-500 entrées)** : Augmenter `max_execution_time` à 300s dans php.ini
3. **Gros volumes (>500 entrées)** : Utiliser les CronTasks GLPI (automatisation CLI)
4. **Optimisation** : Utiliser `ldap_maxlimit` pour limiter les entrées par synchronisation

**Fichiers impactés** :
- `src/Services/LdapSyncService.php` : Logs de progression optimisés
- `src/Services/GlpiLdapConnectionService.php` : Logs de pagination LDAP conservés

### **Standards de Code**
- PHP 8.2+ avec types stricts
- PSR-12 pour le style de code
- Psalm et PHPStan pour l'analyse statique
- Tests unitaires pour chaque service
- Makefile avec commandes de développement

## Dépendances

### **GLPI Core Uniquement**
- Classes natives : `AuthLDAP`, `Config`, `CommonGLPI`, `CommonDBTM`, `CommonDBRelation`, `MassiveAction`, `Search`, etc.
- Base de données : via `$DB` global encapsulé dans `GlpiDatabaseService`
- Configuration : via `$CFG_GLPI` global encapsulé dans `GlpiConfigurationService`
- Templates : Système Twig de GLPI (`TemplateRenderer`)
- Inventaire : API `Inventory::sendInventory()` pour workflow natif

### **PHP 8.2+**
- Types de propriétés stricts (`private ServiceContainer $container`)
- Promotion de constructeur (`public function __construct(private readonly string $field)`)
- Expressions match (pour classification des assets)
- Attributs de méthode et classes
- Readonly properties pour immutabilité

### **Composer**
- Fichier `composer.json` minimaliste
- Dépendances dev : `glpi-project/tools` (pour analyse statique et tests)
- Pas de dépendances runtime (seulement GLPI core)

Le plugin est **autonome** et ne nécessite aucune dépendance externe hormis GLPI.

## Architecture du Plugin
### **Nouvelles Tables de Données**

#### **glpi_plugin_advancedldap_syncfilters**
Stockage des filtres de synchronisation LDAP :
- `id` (int unsigned) : Identifiant unique, PRIMARY KEY
- `name` (varchar 255) : Nom du filtre
- `ldap_filter` (text) : Filtre LDAP (ex: (&(objectClass=device)(serialNumber=*)))
- `base_dn` (varchar 255) : DN de base pour la recherche
- `asset_type` (varchar 255) : Type d'asset GLPI ciblé (Computer, Monitor, etc.)
- `field_mappings` (longtext) : Mappages champ LDAP → champ GLPI (format JSON)
- `is_active` (tinyint) : Statut actif/inactif (défaut 1)
- `date_creation` (timestamp) : Date de création
- `date_mod` (timestamp) : Date de dernière modification

**Index** : name, is_active, asset_type, date_creation, date_mod
**Engine** : InnoDB, CHARSET utf8mb4_unicode_ci

#### **glpi_plugin_advancedldap_authldap_syncfilters**
Table de liaison many-to-many AuthLDAP ↔ SyncFilter :
- `id` (int unsigned) : Identifiant unique, PRIMARY KEY
- `authldap_id` (int unsigned) : Référence vers glpi_authldaps (défaut 0)
- `syncfilter_id` (int unsigned) : Référence vers les filtres (défaut 0)
- `is_active` (tinyint) : Statut de la relation (défaut 1)
- `date_creation` (timestamp) : Date de création de la liaison

**Contraintes** :
- UNIQUE KEY `unicity` (authldap_id, syncfilter_id) : Empêche les doublons
- KEY authldap_id, syncfilter_id, is_active, date_creation

**Engine** : InnoDB, CHARSET utf8mb4_unicode_ci


## État Actuel du Plugin (2 Octobre 2025)

### ✅ **Architecture Complète et Opérationnelle**

#### **Backend (100% fonctionnel)**
- ✅ **2 Modèles** : `SyncFilter` (804 lignes) + `AuthLdapSyncFilter` (142 lignes)
- ✅ **17 Services métier** : Organisation par domaine (LDAP, Assets, SyncFilter, Validation, Wrappers)
- ✅ **2 Repositories** : Pattern Data Access avec gestion erreurs et validation
- ✅ **3 Providers** : Native, Generic + Factory dynamique
- ✅ **10 Contrats (Interfaces)** : Respect principes SOLID (DIP, ISP)
- ✅ **Conteneur DI** : ServiceContainer avec 17 services enregistrés, lazy loading
- ✅ **Workflows doubles** : Traditionnel (CommonDBTM) + Inventaire natif GLPI
- ✅ **Legacy compatibility** : Alias automatiques pour Search GLPI 11
- ✅ **🔒 Sécurité LDAP** : Protection injections RFC 4515/4514, validation triple couche

#### **Frontend (100% fonctionnel)**
- ✅ **Onglet AuthLDAP** : "Advanced sync" avec badge comptage intégré (icône `ti ti-filter`)
- ✅ **2 Templates Twig** :
  - `syncfilters_list.html.twig` : Liste filtres dans onglet AuthLDAP
  - `syncfilter_form.html.twig` : Formulaire édition avec 5 étapes
- ✅ **2 Pages front** :
  - `front/syncfilter.php` : Liste via Search::show
  - `front/syncfilter.form.php` : CRUD complet avec actions multiples
- ✅ **1 Endpoint AJAX** : Chargement dynamique champs assets (`ajax/getAssetFields.php`)
- ✅ **Actions supportées** : add, update, delete, test_ldap, sync_from_ldap, duplicate (massive action)
- ✅ **Filtrage contextuel** : Hook `addDefaultWhere` pour filtrer par AuthLDAP

#### **Base de Données**
- ✅ **Table principale** : `glpi_plugin_advancedldap_syncfilters` (9 colonnes + id)
  - Champs : id, name, ldap_filter, base_dn, asset_type, field_mappings, is_active, date_creation, date_mod
- ✅ **Table relation** : `glpi_plugin_advancedldap_authldap_syncfilters` (4 colonnes + id)
  - Champs : id, authldap_id, syncfilter_id, is_active, date_creation
- ✅ **Contrainte unicité** : UNIQUE KEY `unicity` (authldap_id, syncfilter_id)
- ✅ **Indexes** : Optimisation requêtes (name, is_active, asset_type, dates, foreign keys)
- ✅ **Installation/Désinstallation** : Gestion automatique via `plugin_advancedldap_install()` et `plugin_advancedldap_uninstall()`
- ✅ **Engine** : InnoDB avec charset utf8mb4_unicode_ci pour support Unicode complet

#### **Qualité de Code**
- ✅ **PHP 8.2+** : Types stricts, promotion constructeur, readonly properties, expressions match
- ✅ **PSR-12** : Code style via `.php-cs-fixer.php`
- ✅ **Analyse statique** : Psalm + PHPStan configurés (`psalm.xml`, `phpstan.neon`)
- ✅ **Tests unitaires** : 25 fichiers de tests (24 tests + 1 bootstrap)
  - Tests pour tous les services, modèles, repositories, providers
  - 66 tests dédiés à la sécurité LDAP (`LdapFilterSanitizerTest.php`)
  - **79 tests au total** après révision (octobre 2025)
  - Bootstrap configuré avec autoload GLPI
  - **Stratégie assets génériques** : Tests unitaires pour validation, tests d'intégration pour création
- ✅ **Documentation** : PHPDoc complet avec types, @param, @return, @throws
- ✅ **Logs** : `Toolbox::logDebug()` dans tous les services critiques pour traçabilité
- ✅ **Namespaces** : Organisation moderne `GlpiPlugin\Advancedldap\*` avec alias legacy
- ✅ **🔒 Audit sécurité** : `SECURITY_AUDIT.md` + cas de test documentés

#### **Tests Unitaires et Assets Génériques**

**Stratégie de test adoptée** (conforme conventions GLPI) :

1. **Méthodes testables** :
   - ✅ Seules les méthodes **publiques** sont testées
   - ✅ Les méthodes **sans logs** sont testées unitairement
   - ⚠️ Les méthodes générant des **logs de debug** ne sont **pas testées** unitairement

2. **Assets Génériques - Couverture par service** :

   | Service | Tests Unitaires | Raison |
   |---------|----------------|--------|
   | `AssetCreationService` | ❌ Non testés | Méthode `handleGenericAsset()` génère des logs |
   | `LdapParameterValidator` | ✅ 2 tests ajoutés | Méthode `validateAssetTypeExists()` sans logs |
   | `LdapTestService` | ❌ Non testés | Complexité création asset definitions en test |
   | `GlpiConfigurationService` | ✅ 3 tests ajoutés | Méthode `isInventoryEnabled()` sans logs |
   | `LdapInventoryService` | ⚠️ Documentés uniquement | Méthode `syncInventoriableAsset()` génère des logs |

3. **Documentation explicative** :
   - Chaque fichier de test contient un commentaire expliquant pourquoi certains tests sont absents
   - Référence aux tests d'intégration pour couverture complète
   - Exemples :
     - `AssetCreationServiceTest.php` lignes 14-19
     - `LdapTestServiceTest.php` lignes 17-19
     - `LdapInventoryServiceTest.php` lignes 13-26

4. **Statistiques tests (révision 06/10/2025)** :
   - **Tests initiaux** : 74 tests
   - **Tests ajoutés** : 5 tests
   - **Total final** : 79 tests
   - **Répartition** :
     - AssetCreationService : 10 tests (assets natifs uniquement)
     - GlpiConfigurationService : 14 tests (+3 pour `isInventoryEnabled()`)
     - LdapInventoryService : 13 tests (hors `syncInventoriableAsset()`)
     - LdapParameterValidator : 30 tests (+2 pour assets génériques)
     - LdapTestService : 12 tests (hors assets génériques)

5. **Principes respectés** :
   - ✅ Framework GLPI rejette les "unexpected log entries"
   - ✅ Pas de mock de `Toolbox::logDebug()` (anti-pattern)
   - ✅ Séparation claire : tests unitaires vs tests d'intégration
   - ✅ Documentation des limitations et justifications

#### **Fonctionnalités Avancées**
- ✅ **Synchronisation intelligente** : Classification automatique inventoriables vs traditionnels via `AssetTypeClassifier`
- ✅ **Test LDAP temps réel** : Validation avant sauvegarde, aperçu résultats (action `test_ldap`)
- ✅ **Mapping automatique** : Suggestion attributs LDAP via `LdapAttributeMapper` (RFC 4519)
- ✅ **Parsing filtres** : Extraction et validation attributs via `LdapFilterParser` (RFC 4515)
- ✅ **Massive actions** : Duplication filtres avec relations associées (action `duplicate`)
- ✅ **Gestion erreurs** : Messages explicites, logs détaillés, fallbacks gracieux
- ✅ **Injection de dépendances** : ServiceContainer avec lazy loading et factory pattern
- ✅ **Conversion inventaire** : `LdapToInventoryConverter` pour format JSON natif GLPI
- ✅ **Workflows hybrides** : Support simultané CommonDBTM (legacy) et Inventory API (moderne)



### 🔍 **Code Mort & Fichiers Deprecated**

#### **État actuel (02/10/2025)**
- ✅ **Aucun code mort détecté**
- ✅ Tous les services sont enregistrés et utilisés dans ServiceContainer
- ✅ Tous les contrats ont une implémentation active
- ✅ Toutes les méthodes publiques sont utilisées
- ✅ Architecture cohérente sans redondance

#### **Fichiers deprecated supprimés (30/09/2025)**
- ✅ ~~`front/config.form.php`~~ : Remplacé par `syncfilter.form.php` → **SUPPRIMÉ**
- ✅ ~~`templates/ldap_sync.html.twig`~~ : Remplacé par `syncfilter_form.html.twig` → **SUPPRIMÉ**

#### **Services utilitaires centralisés (à conserver)**
- ✅ `LdapDataExtractor` : Centralise l'extraction de données LDAP (utilisé par LdapTestService et LdapToInventoryConverter)
- ✅ `LdapParameterValidator` : Centralise la validation paramètres (utilisé par LdapSyncService et LdapTestService)
- **Justification** : Ces services évitent la duplication de code et suivent le principe DRY (Don't Repeat Yourself)

---

### ⏰ **Synchronisation Automatique via Cron Tasks**

#### **Vue d'ensemble**
Le plugin supporte la synchronisation automatique des filtres LDAP via le système de tâches cron de GLPI. Cette fonctionnalité permet d'exécuter périodiquement la synchronisation des assets sans intervention manuelle.

#### **Architecture**

##### **Modèle SyncFilter - Méthodes Cron**

**Constante** :
```php
public const CRON_TASK_NAME = 'SyncLdapFilters';
```

**Méthodes publiques** (ajoutées lignes 991-1200) :

1. **`cronInfo(string $name): array`** - Ligne 997
   - Fournit la description de la tâche cron pour l'interface GLPI
   - Retourne le nom et la description du paramètre
   - Utilisé par GLPI pour afficher les informations dans Configuration > Actions automatiques

2. **`cronSyncLdapFilters(?CronTask $task = null): int`** - Ligne 1015
   - **Point d'entrée principal** pour l'exécution automatique
   - Signature conforme au standard GLPI (type `?CronTask` comme le core)
   - Paramètre `$task` : Instance CronTask pour le logging (null dans les tests)
   - **Workflow** :
     1. Récupération du paramètre `max_filters` depuis `$task->fields['param']`
     2. Instanciation des services via `Bootstrap::getContainer()`
     3. Récupération des filtres actifs via `getAllActiveSyncFiltersWithAuthLdap()`
     4. Boucle de synchronisation avec gestion d'erreurs isolées
     5. Logging détaillé via `$task->log()` et `Toolbox::logDebug()`
     6. Calcul du volume (nombre d'assets synchronisés)
   - **Codes de retour** :
     - `0` : Rien à faire (aucun filtre actif)
     - `1` : Succès (au moins un filtre synchronisé)
     - `-1` : Besoin de relancer (limite `max_filters` atteinte)

3. **`getAllActiveSyncFiltersWithAuthLdap(SyncFilterRepositoryInterface $repository): array`** - Ligne 1158
   - Méthode helper privée pour récupérer les filtres éligibles
   - **Critères de sélection** :
     - Filtre actif (`is_active = 1`)
     - Relation active (`is_active = 1`)
     - Serveur AuthLDAP actif (`is_active = 1`)
   - Utilise les repositories existants (pas de SQL direct)
   - **Retour** : Tableau de filtres avec leurs métadonnées

**Gestion des erreurs** :
- Chaque filtre est traité dans un `try/catch` indépendant
- Une erreur sur un filtre ne bloque pas les autres
- Logging détaillé pour chaque erreur (nom du filtre, message d'erreur)
- Compteurs séparés : `success_count`, `error_count`

**Logging** :
- Logs CronTask via `$task->log()` (max 200 caractères, affiché dans l'interface)
- Logs détaillés via `Toolbox::logDebug()` (fichier `php-errors.log`)
- Messages traduits via `__()` pour internationalisation

##### **Enregistrement de la CronTask - hook.php**

**Installation** (lignes 91-106 de `hook.php`) :
```php
CronTask::register(
    \GlpiPlugin\Advancedldap\Models\SyncFilter::class,
    \GlpiPlugin\Advancedldap\Models\SyncFilter::CRON_TASK_NAME,
    HOUR_TIMESTAMP,
    [
        'comment' => __('Automatically synchronize active LDAP filters with GLPI assets', 'advancedldap'),
        'mode' => CronTask::MODE_EXTERNAL,
        'allowmode' => CronTask::MODE_INTERNAL | CronTask::MODE_EXTERNAL,
        'hourmin' => 0,
        'hourmax' => 24,
        'logs_lifetime' => 30,
        'param' => 0, // 0 = unlimited filters per execution
        'state' => CronTask::STATE_WAITING,
    ],
);
```

**Configuration par défaut** :
- **Fréquence** : `HOUR_TIMESTAMP` (1 heure)
- **Mode** : `MODE_EXTERNAL` (CLI) mais autorise aussi `MODE_INTERNAL` (web)
- **Plage horaire** : 0-24h (toute la journée)
- **Rétention logs** : 30 jours
- **Paramètre** : `0` (illimité, traite tous les filtres)
- **État initial** : `STATE_WAITING` (en attente)

**Paramètre `max_filters`** :
- Permet de limiter le nombre de filtres traités par exécution
- Valeur `0` = illimité (tous les filtres)
- Valeur `N` > 0 = traite maximum N filtres puis retourne `-1`
- Utile pour éviter les timeouts sur gros volumes

#### **Bugs Corrigés dans SyncFilterRepository**

**Problème identifié** : Les méthodes retournaient des itérateurs au lieu de tableaux

**Méthodes corrigées** (lignes 64-152 de `SyncFilterRepository.php`) :

1. **`getActiveSyncFilters()`** - Ligne 64
   ```php
   // AVANT (incorrect)
   return is_array($results) ? $results : [];

   // APRÈS (correct)
   if (!is_iterable($iterator)) { return []; }
   $filters = [];
   foreach ($iterator as $data) {
       $filters[] = $data;
   }
   return $filters;
   ```

2. **`getSyncFiltersForAuthLdap(int $authldap_id)`** - Ligne 90
   - Même correction que ci-dessus
   - Conversion itérateur → tableau

3. **`findById(int $id)`** - Ligne 134
   - Conversion itérateur → tableau
   - Retourne la première ligne ou `null`

**Impact** :
- Correction du bug "Active filters count: 0" alors que des filtres existent
- Respect des conventions GLPI pour la gestion des itérateurs
- Cohérence avec les autres méthodes du repository

#### **Utilisation**

##### **Via l'interface web GLPI**

1. Aller dans **Configuration > Actions automatiques**
2. Rechercher `SyncLdapFilters`
3. Cliquer sur le nom de la tâche
4. Cliquer sur **Exécuter** pour lancer manuellement
5. Consulter les logs dans l'onglet **Historique**

##### **Via CLI (ligne de commande)**

**Exécution forcée de la tâche** :
```bash
php /var/www/html/front/cron.php --force syncldapfilters
```

**Exécution de toutes les tâches en attente** :
```bash
php /var/www/html/front/cron.php
```

**Dans Docker** :
```bash
docker exec -it <conteneur_glpi> php /var/www/html/front/cron.php --force syncldapfilters
```

##### **Planification automatique**

**Ajout au crontab système** (Linux) :
```bash
# Exécution toutes les heures
0 * * * * php /var/www/html/front/cron.php

# Ou via docker-compose
0 * * * * docker exec glpi-app php /var/www/html/front/cron.php
```

**Configuration dans GLPI** :
1. Modifier la fréquence dans l'interface (ex: 30 minutes = 1800 secondes)
2. Activer/désactiver la tâche selon les besoins
3. Ajuster le paramètre `param` pour limiter les filtres par exécution

#### **Monitoring et Logs**

##### **Logs CronTask (base de données)**

**Table** : `glpi_crontasklogs`
```sql
SELECT * FROM glpi_crontasklogs
WHERE crontasks_id = (
    SELECT id FROM glpi_crontasks
    WHERE itemtype = 'GlpiPlugin\\Advancedldap\\Models\\SyncFilter'
)
ORDER BY date DESC LIMIT 10;
```

##### **Logs applicatifs (fichier)**

**Emplacement** : `files/_log/php-errors.log`

**Exemples de logs** :
```
SyncFilter::cronSyncLdapFilters - Found 3 active filter(s) to synchronize
SyncFilter::cronSyncLdapFilters - Processing filter 'Printers - MFP' (ID: 2)
SyncFilter::cronSyncLdapFilters - SUCCESS: Filter "Printers - MFP": 30 created, 0 updated, 0 errors
SyncFilter::cronSyncLdapFilters - SUMMARY: Processed 3/3 filters: 3 success, 0 errors. Total assets: 85
```

**Filtrage des logs** :
```bash
# Voir tous les logs cron
grep "SyncFilter::cron" /path/to/files/_log/php-errors.log

# Voir uniquement les succès
grep "SUCCESS" /path/to/files/_log/php-errors.log | grep SyncFilter

# Voir uniquement les erreurs
grep "ERROR" /path/to/files/_log/php-errors.log | grep SyncFilter

# Voir les résumés
grep "SUMMARY" /path/to/files/_log/php-errors.log | grep SyncFilter
```

#### **Tests et Validation**

**Scénarios de test recommandés** :

1. **Test sans filtres actifs** :
   - Désactiver tous les filtres
   - Exécuter la tâche
   - Vérifier : code retour `0`, message "No active LDAP sync filters found"

2. **Test avec limite de filtres** :
   - Créer 3 filtres actifs
   - Paramètre `max_filters = 1`
   - Exécuter 3 fois
   - Vérifier : 3 exécutions, chacune traite 1 filtre, code retour `-1` puis `1`

3. **Test avec erreur LDAP** :
   - Créer un filtre avec Base DN invalide
   - Exécuter la tâche
   - Vérifier : erreur loggée, autres filtres traités normalement

4. **Test de performance** :
   - Créer plusieurs filtres avec beaucoup d'entrées LDAP
   - Mesurer le temps d'exécution
   - Ajuster `max_filters` si nécessaire

**Checklist de validation** :
- [ ] Tâche visible dans Configuration > Actions automatiques
- [ ] Exécution manuelle fonctionne
- [ ] Exécution CLI fonctionne
- [ ] Seuls les filtres actifs sont traités
- [ ] Logs détaillés générés
- [ ] Volume correctement calculé
- [ ] Erreurs isolées par filtre
- [ ] Codes de retour appropriés

#### **Fichiers Modifiés**

**Création/Modification** (10 octobre 2025) :

1. **src/Models/SyncFilter.php** :
   - Ligne 72 : Constante `CRON_TASK_NAME`
   - Ligne 49 : Import `use CronTask;`
   - Lignes 991-1006 : Méthode `cronInfo()`
   - Lignes 1008-1148 : Méthode `cronSyncLdapFilters()`
   - Lignes 1150-1200 : Méthode `getAllActiveSyncFiltersWithAuthLdap()`

2. **hook.php** :
   - Lignes 91-106 : Enregistrement `CronTask::register()` dans `plugin_advancedldap_install()`

3. **src/Repositories/SyncFilterRepository.php** :
   - Lignes 64-82 : Correction `getActiveSyncFilters()`
   - Lignes 90-126 : Correction `getSyncFiltersForAuthLdap()`
   - Lignes 134-152 : Correction `findById()`

**Statistiques** :
- **Lignes ajoutées** : ~250 lignes (méthodes cron + enregistrement)
- **Bugs corrigés** : 3 méthodes repository
- **Services utilisés** : Bootstrap, ServiceContainer, SyncFilterRepository, LdapSyncService
- **Conformité** : Standard GLPI CronTask (comme core GLPI 11)

---

### 📈 **Évolutions Récentes**

#### **10/10/2025 - Synchronisation Automatique via Cron Tasks**
- ✅ **Nouvelle fonctionnalité** : Synchronisation automatique des filtres LDAP via tâches cron GLPI
- ✅ **Méthodes ajoutées au modèle SyncFilter** :
  - `cronInfo()` : Description de la tâche pour l'interface GLPI
  - `cronSyncLdapFilters()` : Point d'entrée principal (164 lignes)
  - `getAllActiveSyncFiltersWithAuthLdap()` : Helper pour récupérer les filtres éligibles
- ✅ **Enregistrement CronTask** : Ajout de `CronTask::register()` dans `hook.php`
- ✅ **Configuration par défaut** :
  - Fréquence : 1 heure (ajustable)
  - Mode : CLI + Web (flexible)
  - Paramètre : illimité (ajustable pour limiter le nombre de filtres)
- ✅ **Gestion d'erreurs robuste** :
  - Isolation des erreurs par filtre (un échec ne bloque pas les autres)
  - Logging détaillé via `$task->log()` et `Toolbox::logDebug()`
  - Codes de retour appropriés (0, 1, -1)
- ✅ **Bugs critiques corrigés dans SyncFilterRepository** :
  - `getActiveSyncFilters()` : Conversion itérateur → tableau
  - `getSyncFiltersForAuthLdap()` : Conversion itérateur → tableau
  - `findById()` : Conversion itérateur → tableau
  - Impact : Correction du bug "Active filters count: 0"
- ✅ **Import CronTask** : Ajout de `use CronTask;` pour respecter les conventions GLPI
- ✅ **Conformité standard GLPI** : Signature `?CronTask` comme le core GLPI 11
- ✅ **Tests réussis** : 30 imprimantes synchronisées automatiquement via workflow inventory
- ✅ **Documentation complète** : Section dédiée avec guide d'utilisation, monitoring, tests
- ✅ **Fichiers modifiés** : 3 fichiers (SyncFilter.php, hook.php, SyncFilterRepository.php)
- ✅ **Statistiques** : ~250 lignes ajoutées, 3 bugs corrigés, conformité GLPI

#### **06/10/2025 - Révision Tests Unitaires & Support Assets Génériques**
- ✅ **Révision complète des tests unitaires** suite à l'adaptation de la synchronisation des assets génériques
- ✅ **5 services testés** : AssetCreationService, GlpiConfigurationService, LdapInventoryService, LdapParameterValidator, LdapTestService
- ✅ **5 nouveaux tests ajoutés** :
  - `GlpiConfigurationService` : +3 tests pour `isInventoryEnabled()` (correction commentaire erroné)
  - `LdapParameterValidator` : +2 tests pour validation assets génériques (`GenericAsset_ID`)
- ✅ **Documentation explicative** :
  - Ajout commentaires dans chaque fichier de test expliquant pourquoi assets génériques non testés unitairement
  - Référence aux tests d'intégration pour couverture complète
- ✅ **Correction PHPStan** : Import `use function Safe\json_encode;` dans `AssetCreationService.php`
- ✅ **Stratégie de test GLPI** :
  - Méthodes générant des logs (`Toolbox::logDebug()`) non testées unitairement
  - Framework GLPI rejette les "unexpected log entries"
  - Tests d'intégration pour méthodes avec logs (création assets génériques)
- ✅ **Statistiques** : 74 → 79 tests (+5 tests)
- ✅ **Fichiers modifiés** :
  - 5 fichiers de tests (ajouts commentaires + nouveaux tests)
  - 1 fichier source (import Safe\json_encode)
  - 1 fichier documentation (cette section)

**Services avec support assets génériques** :
- `AssetCreationService` : Format `GenericAsset_ID` → résolution via `AssetDefinition::getAssetClassName()`
- `LdapParameterValidator` : Validation définitions d'assets génériques (✅ testée)
- `LdapTestService` : Analyse d'impact GLPI avec table `glpi_assets_assets` (⚠️ non testée)

#### **03/10/2025 - Correctifs & Améliorations UX**
- ✅ **Workflow de Test LDAP Sécurisé** : Le filtre doit être sauvegardé avant test
  - Refactorisation complète de `SyncFilter::handleTestRequest()` (lignes 804-838)
  - Données lues uniquement depuis la base de données (plus de paramètres `$_GET`)
  - Bouton POST → Lien GET avec message d'aide explicite
  - Suppression de la gestion POST `test_ldap_filter` dans `front/syncfilter.form.php`
- ✅ **Gestion intelligente des Field Mappings** :
  - Respect strict de la configuration utilisateur dans `LdapToInventoryConverter`
  - Nouvelle méthode `isFieldAllowed()` pour filtrage des champs LDAP
  - Application dans 11 méthodes de conversion (hardware, network, bios, etc.)
  - Champs critiques toujours autorisés : `cn`, `name`, `displayname`, `samaccountname`
- ✅ **Détection d'échec silencieux de l'inventaire** :
  - Vérification `$assetId = $item->getID()` après `doInventory()`
  - Nouvelle méthode `getMinimumFieldRequirements()` avec exigences par type d'asset
  - Messages d'erreur explicites guidant l'utilisateur
- ✅ **Correctif Repository AuthLdapSyncFilterRepository** :
  - Gestion correcte des itérateurs GLPI : `is_array()` → `is_iterable()`
  - Méthodes corrigées : `getAuthLdapsForSyncFilter()` et `hasSyncFiltersForAuthLdap()`
  - Respect des conventions GLPI et cohérence avec `SyncFilterRepository`
- ✅ **Améliorations Interface Utilisateur** :
  - Pré-sélection automatique des champs obligatoires (objet JavaScript `minimumRequiredFields`)
  - Simplification workflow de test (bouton → lien, texte d'aide)
  - Nettoyage colonne "Actions" redondante dans `syncfilters_list.html.twig`
- ✅ **Fichiers modifiés** : 11 fichiers (services, models, templates, repositories)

#### **02/10/2025 - Sécurité : Protection XSS dans Templates Twig**
- ✅ **Templates sécurisés** : Échappement systématique de toutes les données externes (11 corrections)
- ✅ **Vulnérabilité critique corrigée** : `field_mappings|raw` → `field_mappings|json_encode|raw`
- ✅ **Contexte HTML** : Ajout `|e('html')` pour données LDAP (DN, attributs, messages erreur)
- ✅ **Contexte JavaScript** : Ajout `|e('js')` pour chaînes insérées dans code JS inline
- ✅ **Nettoyage** : Suppression de tous les commentaires DEBUG exposant la logique interne
- ✅ **Fichiers modifiés** :
  - `templates/syncfilter_form.html.twig` (9 corrections)
  - `templates/syncfilters_list.html.twig` (2 corrections)
- ✅ **Protection complète** : Prévention XSS sur toutes les données user-provided et LDAP
- ✅ **Documentation** : Section "Protection XSS dans les Templates Twig" ajoutée
- ✅ **Respect SECURITY_AUDIT.md** : Partie 2 (XSS Templates) complétée

#### **02/10/2025 - Sécurité : Protection Injections LDAP (RFC 4515/4514)**
- ✅ **Nouveau service** : `LdapFilterSanitizer` - Protection complète anti-injection LDAP
- ✅ **Nouveau contrat** : `LdapFilterSanitizerInterface` - 4 méthodes (escape, validate DN/filter)
- ✅ **66 tests unitaires** : `tests/LdapFilterSanitizerTest.php` - Couverture complète injections
- ✅ **3 points de protection** :
  - Validation avant test LDAP (`front/syncfilter.form.php`)
  - Validation avant sauvegarde BDD (`src/Models/SyncFilter.php`)
  - Validation avant recherche LDAP (`src/Services/GlpiLdapConnectionService.php`)
- ✅ **Conformité RFC** : RFC 4515 (filtres) + RFC 4514 (Distinguished Names)
- ✅ **Cas bloqués** : Injection parenthèses, wildcards, DN malformés, métacaractères
- ✅ **Documentation sécurité** : `tests/SECURITY_TEST_CASES.md` avec 19 cas de test
- ✅ Actualisation statistiques : 17 services (au lieu de 16), 10 contrats (au lieu de 9)
- ✅ Décompte précis des fichiers : 35 fichiers src/ + 2 front/ + 1 ajax/
- ✅ Ajout informations manquantes (repositories, providers, factory)
- ✅ Vérification cohérence de l'architecture

#### **30/09/2025 - Documentation complète + Nettoyage**
- ✅ Analyse exhaustive de l'architecture (35 fichiers)
- ✅ Mise à jour `advancedldap_developer_notes.md` (650+ lignes)
- ✅ Cartographie complète : modèles, services, repositories, providers, infra
- ✅ Identification et suppression fichiers deprecated (2 fichiers)
- ✅ Statistiques détaillées du plugin
- ✅ Code base nettoyée : 35 fichiers PHP actifs (hors tests)

#### **Refactorisation SRP - SyncFilter (septembre 2025)**
- ✅ Extraction 3 services spécialisés (LdapFilterParser, LdapAttributeMapper, SyncFilterFormHelper)
- ✅ Réduction SyncFilter.php : 887 → 804 lignes (-9.3%)
- ✅ Respect principe SRP (Single Responsibility Principle)
- ✅ Total services : 13 → 16 services

#### **Audit de Code - Architecture Optimisée (septembre 2025)**
- ✅ Élimination doublons via centralisation (LdapDataExtractor, LdapParameterValidator)
- ✅ Architecture hybride : Support double workflow justifié
- ✅ Standards PHP 8.2+, PSR-12, typage strict respectés
- ✅ Logs `Toolbox::logDebug()` dans tous les services critiques

## Tests et Environnement de Développement

### **Environnement de Test LDAP avec Docker**

Pour faciliter le développement et les tests du plugin, un environnement Docker complet est disponible :

**Repository** : https://github.com/f2cmb/ldaps-docker

#### **Caractéristiques**
- **Serveurs LDAP multiples** : Différentes configurations LDAP pré-configurées
- **Données de test variées** :
  - Utilisateurs avec différents attributs
  - Groupes avec relations complexes
  - Équipements (computers, printers, network devices)
  - Structures organisationnelles variées
- **Scénarios de test** :
  - Authentication simple et complexe
  - Synchronisation de masse
  - Cas d'erreur et edge cases
- **Support SSL/TLS** : Tests de connexions sécurisées

#### **Utilisation Rapide**
```bash
# Cloner le repository
git clone https://github.com/f2cmb/ldaps-docker
cd ldaps-docker

# Lancer l'environnement
docker-compose up -d

# Les serveurs LDAP sont disponibles sur :
# - ldap://localhost:389 (LDAP simple)
# - ldaps://localhost:636 (LDAP avec SSL)
```

#### **Configuration dans GLPI**
1. Ajouter un serveur LDAP dans GLPI
2. Utiliser les paramètres fournis dans le README du repo
3. Tester avec le plugin Advanced LDAP

#### **Données de Test Disponibles**
- **Users** : 100+ utilisateurs avec attributs variés
- **Computers** : 50+ ordinateurs avec serialNumber, model, etc.
- **Printers** : 30+ imprimantes avec attributs spécifiques
- **Network Equipment** : 20+ équipements réseau
- **Groups** : Structures organisationnelles complexes

Cette infrastructure de test permet de valider tous les cas d'usage du plugin sans avoir besoin d'un serveur LDAP de production.

## Outils de Développement

### **Workflow humain / IA**

  🤖 Le développement de ce plugin a été fortement assisté par un LLM (Claude Code, Anthropic).
  #### L’IA a contribué à :

  - la génération de l’architecture initiale (organisation fichiers, services, interfaces),
  - la production de portions de code récurrentes ou verbeuses (CRUD, formulaires, repositories),
  - la rédaction et la mise à jour de la documentation technique,
  - la création de tests unitaires et de scripts de tests (relus et ajustés par le développeur).

  #### Rôle du développeur humain

  - Supervision de l’ensemble du code métier et des choix d’architecture,
  - Relecture, ajustement et validation des tests unitaires existants,
  - Refactorisation et adaptation du code généré pour respecter SOLID et PSR-12,
  - Vérification de la cohérence avec GLPI et correction des anomalies,