# Advanced LDAP Plugin - Notes Développeur

Ce document présente l'architecture technique du plugin Advanced LDAP et les bonnes pratiques pour le développer.

**Dernière mise à jour : 17 octobre 2025**

**Note** : Ce document reflète l'état du plugin après la refactorisation majeure d'octobre 2025. Pour le détail des changements appliqués, consulter [PRE-REVIEW/REFACTORING_DONE.md](PRE-REVIEW/REFACTORING_DONE.md).

**Nouveauté octobre 2025** : Support complet des assets génériques inventoriables avec détection automatique de la capacité `IsInventoriableCapacity`.

## Vue d'Ensemble

Le plugin **advancedldap** étend GLPI en ajoutant des capacités avancées de synchronisation LDAP pour les assets (équipements). Il permet de définir des filtres de synchronisation personnalisés qui s'intègrent directement dans les serveurs LDAP existants de GLPI via un onglet dédié dans AuthLDAP.

### Points Clés de l'Architecture

- **Architecture SOLID** : Respect des principes SRP, OCP, DIP, ISP avec injection complète de dépendances
- **Pattern Strategy** : AssetFieldHandlers extensibles pour gérer différents types d'assets
- **Services spécialisés** : Extraction du God Object SyncFilter en services dédiés (Validation, Cron, FormPresenter)
- **Injection de dépendances** : Élimination totale des instanciations `new` dans les constructeurs
- **Zero dépendance externe** : Utilise uniquement le core GLPI
- **Double workflow** : Support traditionnel (CommonDBTM) + inventaire natif GLPI
- **Namespace moderne** : `GlpiPlugin\Advancedldap` avec compatibilité legacy
- **PHP 8.2+** : Types stricts, promotion de constructeur, attributs
- **Sécurité LDAP** : Protection complète contre les injections (RFC 4515/4514)

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
  │       ├── SyncFilter.php                 # Modèle principal filtres LDAP (façade déléguant aux services)
  │       │   • extends CommonDBTM           # → CRUD complet, massive actions
  │       │   • Alias legacy automatique     # → class_alias() pour Search GLPI 11
  │       │   • Délégation services          # → Validation, Cron, FormPresenter
  │       │
  │       └── AuthLdapSyncFilter.php         # Modèle relation many-to-many
  │           • extends CommonDBRelation     # → AuthLDAP ↔ SyncFilter
  │           • Gestion unicity constraint   # → (authldap_id, syncfilter_id)
  │
  ├── 🔧 SERVICES MÉTIER (20 services)
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
  │       │   ├── AssetCreationService.php           # Création/MAJ assets GLPI (Pattern Strategy)
  │       │   └── AssetTypeClassifier.php            # Classification inventoriables vs traditionnels
  │       │
  │       ├── 🎨 ASSET FIELD HANDLERS (4) - PATTERN STRATEGY ✨
  │       │   └── AssetFieldHandlers/
  │       │       ├── ComputerFieldHandler.php       # Handler pour type Computer
  │       │       ├── PrinterFieldHandler.php        # Handler pour type Printer
  │       │       ├── NetworkEquipmentFieldHandler.php # Handler pour type NetworkEquipment
  │       │       └── UserFieldHandler.php           # Handler pour type User
  │       │
  │       ├── 🔍 SERVICES SYNCFILTER (5)
  │       │   ├── SyncFilterService.php              # Logique métier filtres
  │       │   ├── SyncFilterFormHelper.php           # Helpers formulaire (dropdown, config, connexion)
  │       │   ├── SyncFilterValidationService.php    # ✨ Validation LDAP inputs & field mappings
  │       │   └── SyncFilterCronService.php          # ✨ Exécution tâches cron automatiques
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
  ├── 🎨 PRESENTERS (Préparation données pour vues)
  │   └── src/Presenters/
  │       └── SyncFilterFormPresenter.php           # ✨ Préparation données pour Twig
  │
  ├── 🔌 CONTRATS (11 interfaces)
  │   └── src/Contracts/
  │       ├── AssetFieldProviderInterface.php       # Contrat providers de champs
  │       ├── AssetFieldHandlerInterface.php        # ✨ Contrat handlers Strategy pattern
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
      ├── tests/                                    # Tests unitaires
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

#### **SyncFilter** (`src/Models/SyncFilter.php`)
Modèle principal des filtres de synchronisation LDAP - Architecture **Façade** après refactorisation :

**Principe architectural** : SyncFilter agit comme une **façade** qui délègue les responsabilités à des services spécialisés, tout en conservant son API publique inchangée (rétrocompatibilité 100%).

**Héritage** : `extends CommonDBTM` → CRUD complet, historique, massive actions

**Table** : `glpi_plugin_advancedldap_syncfilters`

**Champs principaux** :
```php
- name              : Nom du filtre
- ldap_filter       : Filtre LDAP (ex: (&(objectClass=device)(cn=*)))
- base_dn           : DN de base (ex: ou=devices,dc=example,dc=com)
- asset_type        : Classe GLPI (Computer, Monitor, Asset_Generic...)
- field_mappings    : JSON mappings LDAP→GLPI (ex: {"name":"cn","serial":"serialNumber"})
- is_active         : Statut (booléen)
```

**Architecture de délégation** :

```php
// API publique conservée (façade)
public function prepareInputForAdd($input) {
    // Délégation au service spécialisé
    $container = Bootstrap::getContainer();
    $validator = $container->get(SyncFilterValidationService::class);
    return $validator->validateAndPrepare($input);
}

public function prepareInputForUpdate($input) {
    // Même délégation pour update
    $container = Bootstrap::getContainer();
    $validator = $container->get(SyncFilterValidationService::class);
    return $validator->validateAndPrepare($input);
}

// Méthode cron déléguée
public static function cronSyncLdapFilters(?CronTask $task = null): int {
    $container = Bootstrap::getContainer();
    $cronService = $container->get(SyncFilterCronService::class);
    return $cronService->executeSyncTask($task);
}

// Affichage formulaire délégué
public function showForm($ID, array $options = []) {
    // Préparation des données déléguée au Presenter
    $container = Bootstrap::getContainer();
    $presenter = $container->get(SyncFilterFormPresenter::class);
    $viewModel = $presenter->prepareViewModel($this, $ID, $options);

    // Rendu du template
    return TemplateRenderer::getInstance()->display('syncfilter_form.html.twig', $viewModel);
}
```

**Services associés** :
- **SyncFilterValidationService** : Validation LDAP inputs et field mappings
- **SyncFilterCronService** : Exécution tâches cron automatiques
- **SyncFilterFormPresenter** : Préparation données pour la vue Twig

**Bénéfices de la refactorisation** :
- ✅ Respect du principe **Single Responsibility**
- ✅ API publique **inchangée** (zéro breaking change)
- ✅ Services **testables indépendamment**
- ✅ Code plus **maintenable** et **lisible**

**Méthodes conservées** :
- CRUD : `prepareInputForAdd()`, `prepareInputForUpdate()`, `pre_deleteItem()`
- Affichage : `showForm()`, `getTabNameForItem()`, `displayTabContentForItem()`
- Cron : `cronInfo()`, `cronSyncLdapFilters()`
- Utilities : `rawSearchOptionsToAdd()`, `getForbiddenStandardMassiveAction()`
- Massive actions : `showMassiveActionsSubForm()`, `processMassiveActionsForOneItemtype()`

#### **AuthLdapSyncFilter** (`src/Models/AuthLdapSyncFilter.php`)
Modèle de relation many-to-many AuthLDAP ↔ SyncFilter :
- **Héritage** : `extends CommonDBRelation`
- **Table** : `glpi_plugin_advancedldap_authldap_syncfilters`
- **Relations** :
  - `$itemtype_1` = `AuthLDAP` / `$items_id_1` = `authldap_id`
  - `$itemtype_2` = `SyncFilter` / `$items_id_2` = `syncfilter_id`
- **Contrainte** : UNIQUE KEY `unicity` (`authldap_id`, `syncfilter_id`)

---

### 3. Services Métier (20 services organisés)

#### 📋 **Services LDAP (8 services)**

##### **LdapSyncService** (`src/Services/LdapSyncService.php`)
Orchestration complète de la synchronisation LDAP → GLPI avec **injection complète de dépendances** :

**Injection de dépendances** (pattern DIP) :
```php
public function __construct(
    LdapConnectionInterface $ldap_connection,
    AssetCreationService $asset_creation_service,
    AssetTypeClassifier $asset_type_classifier,
    LdapDataExtractor $data_extractor,          // ✨ Injecté (plus de new)
    LdapParameterValidator $parameter_validator  // ✨ Injecté (plus de new)
) {
    $this->ldap_connection = $ldap_connection;
    $this->asset_creation_service = $asset_creation_service;
    $this->asset_type_classifier = $asset_type_classifier;
    $this->data_extractor = $data_extractor;
    $this->parameter_validator = $parameter_validator;
}
```

**Workflow** :
1. Validation paramètres via `LdapParameterValidator`
2. Récupération SyncFilter depuis DB
3. Connexion LDAP et recherche via `AuthLDAP::connectToServer()`
4. Classification assets (inventoriable vs traditionnel)
5. Routage vers workflow approprié (inventaire ou CommonDBTM)
6. Retour statistiques (`created`, `updated`, `errors`)

**Méthodes clés** :
- `synchronizeFromFilter()` : Point d'entrée principal
- `syncTraditionalWorkflow()` : Assets non-inventoriables
- `syncInventoryWorkflow()` : Assets inventoriables via JSON

##### **LdapTestService** (`src/Services/LdapTestService.php`)
Tests et validation des filtres LDAP en temps réel :
- **Connexion safe** : Gestion des erreurs de connexion LDAP
- **Résultats** : Count, sample entries, champs disponibles
- **Validation** : Test before save pour éviter erreurs de synchronisation
- **Support Assets Génériques** : Analyse d'impact GLPI pour format `GenericAsset_ID`

**Méthode publique** :
- `testLdapFilter(int $authldap_id, string $base_dn, string $filter, string $asset_type, string $asset_field = '', array $field_mappings = []): array`

##### **LdapInventoryService** (`src/Services/LdapInventoryService.php`)
Intégration avec le système d'inventaire natif GLPI :
- **Conversion** : Utilise `LdapToInventoryConverter` pour transformer LDAP → JSON
- **Envoi** : Appelle `Inventory::sendInventory()` avec JSON formaté
- **Workflow** : Respecte le cycle complet inventaire GLPI (règles, fusion, etc.)
- **Détection d'échec silencieux** : Vérifie `$assetId = $item->getID()` après inventaire

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
- **Types supportés** : Computer, NetworkEquipment, Printer, Phone, **Assets génériques**
- **Respect strict des Field Mappings** : Seuls les champs LDAP configurés dans les field mappings sont utilisés

**Gestion des assets génériques** (ajout octobre 2025) :
- Détecte le format `GenericAsset_ID` (ex: `GenericAsset_5`)
- Convertit l'ID en nom de classe réel via `AssetDefinition::getAssetClassName()`
  - Exemple : `GenericAsset_5` → `Glpi\CustomAsset\TestinventoriableAsset`
- Utilise le nom de classe réel dans le JSON d'inventaire (requis par le schema validator)
- Construit des sections d'inventaire basiques (hardware, networks) pour les assets génériques

**Méthodes clés** :
- `convertToInventoryFormat()` - Conversion principale LDAP → JSON
- `getGenericAssetClassName()` - Résolution classe réelle pour assets génériques
- `buildGenericAssetSections()` - Construction sections inventaire pour assets génériques

**Méthode de filtrage** : `isFieldAllowed(string $ldapField, array $fieldMappings): bool`

**Logique de filtrage** :
1. Si `$fieldMappings` est vide → Tous les champs LDAP autorisés (backward compatibility)
2. Champs critiques TOUJOURS autorisés : `cn`, `name`, `displayname`, `samaccountname` (requis pour création assets)
3. Pour les autres champs → Vérification dans `$fieldMappings`

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
  - `front/syncfilter.form.php` - Validation avant test
  - `src/Models/SyncFilter.php` - Validation avant sauvegarde (add/update)
  - `src/Services/GlpiLdapConnectionService.php` - Validation avant recherche LDAP

---

#### 🎯 **Services Assets (3 services)**

##### **AssetCreationService** (`src/Services/AssetCreationService.php`)
Création et mise à jour des assets GLPI (workflow traditionnel) avec **Pattern Strategy** :

**Architecture Strategy** après refactorisation :

```php
// AVANT : Switch/case (violation principe Open/Closed)
switch ($asset_type) {
    case 'Computer':
        $data = $this->handleComputerFields($data);
        break;
    case 'Printer':
        $data = $this->handlePrinterFields($data);
        break;
    // ...
}

// APRÈS : Pattern Strategy (extensible sans modification)
public function __construct(
    DatabaseInterface $database,
    array $field_handlers = []  // ✨ Injection des handlers
) {
    $this->database = $database;
    $this->field_handlers = $field_handlers;
}

private function handleSpecialFields(array $data, string $asset_type): array
{
    // Boucle sur les handlers jusqu'à trouver le bon
    foreach ($this->field_handlers as $handler) {
        if ($handler->supports($asset_type)) {
            return $handler->handle($data);
        }
    }
    return $data;
}
```

**Bénéfices du Pattern Strategy** :
- ✅ **Open/Closed** : Ajouter un type = créer un handler, pas modifier le service
- ✅ **Extensibilité** : Nouveaux types d'assets sans toucher au code existant
- ✅ **Testabilité** : Chaque handler testable indépendamment
- ✅ **Maintenabilité** : Logique isolée par type d'asset

**Support** :
- Assets natifs : Computer, Printer, Monitor, NetworkEquipment, Phone, Peripheral
- Assets génériques : Format `GenericAsset_ID` (ex: `GenericAsset_1`)

**Workflow** :
- Assets natifs → Instanciation directe de la classe (ex: `new Computer()`)
- Assets génériques → Résolution via `AssetDefinition::getAssetClassName()` puis instanciation dynamique

**Méthodes publiques** :
- `createOrUpdateAsset(string $asset_type, array $asset_data): array` - Création/MAJ assets
- `validateAssetData(array $asset_data, string $asset_type): array` - Validation données

##### **AssetFieldService** (`src/Services/AssetFieldService.php`)
Gestion unifiée des champs disponibles pour tous types d'assets :
- **Factory pattern** : Utilise `AssetFieldProviderFactory` pour instancier providers
- **Cache** : Optimisation performance via cache des métadonnées
- **Support** : Assets natifs + génériques + custom via providers

##### **AssetTypeClassifier** (`src/Services/AssetTypeClassifier.php`)
Classification automatique des assets (inventoriables vs traditionnels) :
- **Assets natifs** : Computer, NetworkEquipment, Printer, Phone → inventoriables (si dans `inventory_types`)
- **Assets génériques** : Détection via format `GenericAsset_ID` (ex: `GenericAsset_5`)
  - Charge l'`AssetDefinition` correspondante depuis la base de données
  - Vérifie si la capacité `IsInventoriableCapacity` est activée
  - Si activée → workflow inventaire, sinon → workflow traditionnel
- **Exception** : Si inventaire désactivé → tous traditionnels
- **Usage** : Décision workflow dans `LdapSyncService`

**Méthodes clés** :
- `isInventoriableAsset(string $asset_type): bool` - Détermine si inventoriable (natif ou générique)
- `isGenericAssetInventoriable(string $asset_type): bool` - Vérifie capacité pour assets génériques
- `getSyncMethod(string $asset_type): string` - Retourne 'inventory' ou 'traditional'

---

#### 🎨 **Asset Field Handlers (4 services) - PATTERN STRATEGY** ✨

Les handlers implémentent tous l'interface `AssetFieldHandlerInterface` et suivent le **pattern Strategy** :

**Interface commune** (`src/Contracts/AssetFieldHandlerInterface.php`) :
```php
interface AssetFieldHandlerInterface
{
    // Indique si le handler supporte ce type d'asset
    public function supports(string $assetType): bool;

    // Traite les données pour ce type d'asset
    public function handle(array $data): array;

    // Retourne les champs obligatoires
    public function getRequiredFields(): array;

    // Retourne les valeurs par défaut
    public function getDefaultValues(): array;
}
```

##### **ComputerFieldHandler** (`src/Services/AssetFieldHandlers/ComputerFieldHandler.php`)
- **Supporte** : `Computer` (type natif GLPI)
- **Champs requis** : `name`
- **Valeurs par défaut** : `computertypes_id`, `states_id`, `manufacturers_id`

##### **PrinterFieldHandler** (`src/Services/AssetFieldHandlers/PrinterFieldHandler.php`)
- **Supporte** : `Printer` (type natif GLPI)
- **Champs requis** : `name`
- **Valeurs par défaut** : `printertypes_id`, `states_id`, `manufacturers_id`

##### **NetworkEquipmentFieldHandler** (`src/Services/AssetFieldHandlers/NetworkEquipmentFieldHandler.php`)
- **Supporte** : `NetworkEquipment` (type natif GLPI)
- **Champs requis** : `name`, `serial` (ou `mac`)
- **Valeurs par défaut** : `networkequipmenttypes_id`, `states_id`, `manufacturers_id`

##### **UserFieldHandler** (`src/Services/AssetFieldHandlers/UserFieldHandler.php`)
- **Supporte** : `User` (type natif GLPI)
- **Champs requis** : `name` (ou `firstname` + `realname`)
- **Valeurs par défaut** : `entities_id`, `profiles_id`

**Principe de fonctionnement** :
1. `AssetCreationService` reçoit un tableau de handlers via constructeur
2. Lors du traitement d'un asset, il boucle sur les handlers
3. Appelle `supports($assetType)` sur chaque handler
4. Dès qu'un handler retourne `true`, il appelle `handle($data)` et s'arrête
5. Si aucun handler ne supporte le type, les données sont retournées telles quelles

---

#### 🔍 **Services SyncFilter (5 services)**

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

##### **SyncFilterValidationService** ✨ (`src/Services/SyncFilterValidationService.php`)
**Service extrait du God Object SyncFilter** - Responsable de la validation des inputs :

**Responsabilités** :
- Validation des inputs LDAP (Base DN, filter)
- Préparation des field mappings
- Sanitization via `LdapFilterSanitizer`

**Méthodes publiques** :
- `validateLdapInputs(array $input)` - Valide Base DN et filter
- `prepareMappingsInput(array $input)` - Prépare les mappings
- `validateAndPrepare(array $input)` - Workflow complet

**Code migré depuis SyncFilter** :
```php
// AVANT : Méthodes privées dans SyncFilter
private function validateLdapInputs(array $input) { ... }
private function prepareMappingsInput(array $input) { ... }

// APRÈS : Service dédié avec méthodes publiques
$validator = $container->get(SyncFilterValidationService::class);
$validatedInput = $validator->validateAndPrepare($input);
```

**Bénéfice** : Logique de validation testable indépendamment et réutilisable

##### **SyncFilterCronService** ✨ (`src/Services/SyncFilterCronService.php`)
**Service extrait du God Object SyncFilter** - Responsable des tâches cron automatiques :

**Responsabilités** :
- Exécution des tâches cron automatiques
- Récupération des filtres actifs avec AuthLDAP
- Logging et reporting détaillé

**Méthodes publiques** :
- `executeSyncTask(?CronTask $task)` - Exécute la synchronisation
- `getCronInfo(string $name)` - Retourne les infos de la tâche (statique)

**Code migré depuis SyncFilter** :
```php
// AVANT : Méthode statique dans SyncFilter
public static function cronSyncLdapFilters(?CronTask $task): int { ... }

// APRÈS : Délégation au service
public static function cronSyncLdapFilters(?CronTask $task = null): int {
    $container = Bootstrap::getContainer();
    $cronService = $container->get(SyncFilterCronService::class);
    return $cronService->executeSyncTask($task);
}
```

**Bénéfice** : Logique cron isolée, testable et maintenable

##### **SyncFilterFormPresenter** ✨ (`src/Presenters/SyncFilterFormPresenter.php`)
**Service extrait du God Object SyncFilter** - Responsable de la préparation des données pour la vue :

**Responsabilités** :
- Préparation des données pour la vue Twig
- Résolution du contexte AuthLDAP parent
- Transformation modèle → vue

**Méthodes publiques** :
- `prepareViewModel(SyncFilter $filter, int $ID, array $options)` - Prépare les données pour Twig

**Code migré depuis SyncFilter** :
```php
// AVANT : Logique dans showForm()
public function showForm($ID, array $options = []) {
    // ... préparation données (résolution AuthLDAP, dropdowns, config)
    return TemplateRenderer::getInstance()->display('...', $data);
}

// APRÈS : Délégation au Presenter
public function showForm($ID, array $options = []) {
    $container = Bootstrap::getContainer();
    $presenter = $container->get(SyncFilterFormPresenter::class);
    $viewModel = $presenter->prepareViewModel($this, $ID, $options);

    return TemplateRenderer::getInstance()->display('syncfilter_form.html.twig', $viewModel);
}
```

**Bénéfice** : Séparation claire entre logique métier et présentation (pattern MVC)

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
- `validateAssetTypeExists(string $asset_type): ?string` - Supporte assets génériques
- `validateSyncFilter(SyncFilter $sync_filter): ?string`
- `validateConnectionParameters(string $host, int $port, string $base_dn): ?string`
- `validateFieldMappings(array $field_mappings): ?string`

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

**Principe clé** : **Injection complète des dépendances** - tous les services reçoivent leurs dépendances via constructeur

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

// Classes concrètes avec injection de dépendances
GlpiConfigurationService::class
AssetFieldProviderFactory::class
LdapDataExtractor::class              // ✨ Maintenant enregistré pour injection
LdapParameterValidator::class         // ✨ Maintenant enregistré pour injection
LdapTestService::class
SyncFilterService::class
AssetTypeClassifier::class

// Services avec injection de handlers (Pattern Strategy)
AssetCreationService::class → new AssetCreationService(
    $c->get(DatabaseInterface::class),
    [
        new ComputerFieldHandler(),           // ✨ Handlers injectés
        new PrinterFieldHandler(),
        new NetworkEquipmentFieldHandler(),
        new UserFieldHandler(),
    ]
)

// Services avec injection complète (plus de new dans constructeur)
LdapSyncService::class → new LdapSyncService(
    $c->get(LdapConnectionInterface::class),
    $c->get(AssetCreationService::class),
    $c->get(AssetTypeClassifier::class),
    $c->get(LdapDataExtractor::class),       // ✨ Injecté (au lieu de new)
    $c->get(LdapParameterValidator::class)   // ✨ Injecté (au lieu de new)
)

// Services extraits du God Object
SyncFilterValidationService::class    // ✨ NOUVEAU
SyncFilterCronService::class          // ✨ NOUVEAU
SyncFilterFormPresenter::class        // ✨ NOUVEAU

LdapToInventoryConverter::class
LdapInventoryService::class (injecté si inventaire activé)
```

**Caractéristiques** :
- **Singleton** : `getInstance()` pour accès global
- **Registration** : `register($id, callable $factory)` pour enregistrer services
- **Lazy loading** : Services créés uniquement à la demande

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

## Principes de Refactorisation Appliqués

Cette section explique les **patterns et principes SOLID** appliqués lors de la refactorisation majeure d'octobre 2025.

### A. Pattern Strategy (AssetCreationService)

**Problème résolu** : Violation du principe **Open/Closed** avec `switch/case`

**Code AVANT** (non extensible) :
```php
class AssetCreationService
{
    public function createOrUpdateAsset(string $asset_type, array $asset_data): array
    {
        // Switch/case rigide - pour ajouter un type, il faut modifier cette classe
        switch ($asset_type) {
            case 'Computer':
                $data = $this->handleComputerFields($data);
                break;
            case 'Printer':
                $data = $this->handlePrinterFields($data);
                break;
            case 'NetworkEquipment':
                $data = $this->handleNetworkEquipmentFields($data);
                break;
            case 'User':
                $data = $this->handleUserFields($data);
                break;
        }
        // ...
    }

    // 4 méthodes privées avec logique dupliquée
    private function handleComputerFields(array $data): array { ... }
    private function handlePrinterFields(array $data): array { ... }
    // etc.
}
```

**Code APRÈS** (extensible sans modification) :
```php
// 1. Interface commune pour tous les handlers
interface AssetFieldHandlerInterface
{
    public function supports(string $assetType): bool;
    public function handle(array $data): array;
    public function getRequiredFields(): array;
    public function getDefaultValues(): array;
}

// 2. Un handler par type d'asset
class ComputerFieldHandler implements AssetFieldHandlerInterface
{
    public function supports(string $assetType): bool
    {
        return $assetType === 'Computer';
    }

    public function handle(array $data): array
    {
        // Logique spécifique Computer
        $defaults = $this->getDefaultValues();
        foreach ($defaults as $field => $value) {
            if (!isset($data[$field])) {
                $data[$field] = $value;
            }
        }
        return $data;
    }

    public function getRequiredFields(): array
    {
        return ['name'];
    }

    public function getDefaultValues(): array
    {
        return [
            'computertypes_id' => 0,
            'states_id' => 0,
            'manufacturers_id' => 0,
        ];
    }
}

// 3. Service modifié pour utiliser les handlers
class AssetCreationService
{
    private array $field_handlers;

    // Injection des handlers via constructeur
    public function __construct(
        DatabaseInterface $database,
        array $field_handlers = []
    ) {
        $this->database = $database;
        $this->field_handlers = $field_handlers;
    }

    // Plus de switch/case ! Boucle sur les handlers
    private function handleSpecialFields(array $data, string $asset_type): array
    {
        foreach ($this->field_handlers as $handler) {
            if ($handler->supports($asset_type)) {
                return $handler->handle($data);
            }
        }
        return $data;
    }
}

// 4. Enregistrement dans ServiceContainer
$this->register(AssetCreationService::class, fn($c) =>
    new AssetCreationService(
        $c->get(DatabaseInterface::class),
        [
            new ComputerFieldHandler(),
            new PrinterFieldHandler(),
            new NetworkEquipmentFieldHandler(),
            new UserFieldHandler(),
        ]
    )
);
```

**Bénéfices** :
- ✅ **Open/Closed** : Ajouter un nouveau type = créer un handler, pas toucher au service
- ✅ **Extensibilité** : Nouveaux types d'assets sans modification du code existant
- ✅ **Testabilité** : Chaque handler testable indépendamment avec ses propres tests
- ✅ **Maintenabilité** : Logique isolée par type, pas de méthode géante
- ✅ **Lisibilité** : Code plus clair et explicite

---

### B. Extraction du God Object (SyncFilter)

**Problème résolu** : Classe `SyncFilter` avec **trop de responsabilités** (God Object anti-pattern)

**AVANT la refactorisation** :
- SyncFilter gérait 7+ responsabilités différentes
- Mélange de logique métier, validation, présentation, cron
- Difficile à tester, maintenir et faire évoluer

**Responsabilités identifiées** :
1. ✅ CRUD de base (CommonDBTM) → **Conservé** dans SyncFilter
2. ✅ Affichage formulaire → **Conservé** dans SyncFilter (façade)
3. ❌ Validation LDAP inputs → **Extrait** vers SyncFilterValidationService
4. ❌ Préparation field mappings → **Extrait** vers SyncFilterValidationService
5. ❌ Exécution tâches cron → **Extrait** vers SyncFilterCronService
6. ❌ Préparation données vue Twig → **Extrait** vers SyncFilterFormPresenter
7. ✅ Gestion relations AuthLDAP → **Conservé** dans SyncFilter

**Solution appliquée** : **Extraction de 3 services spécialisés**

#### 1. SyncFilterValidationService (Validation)

**Responsabilités extraites** :
- Validation des inputs LDAP (Base DN, filter)
- Préparation des field mappings
- Sanitization via `LdapFilterSanitizer`

**Code AVANT** (dans SyncFilter) :
```php
class SyncFilter extends CommonDBTM
{
    public function prepareInputForAdd($input) {
        // Logique de validation mélangée avec CRUD
        $input = $this->validateLdapInputs($input);
        if ($input === false) {
            return false;
        }
        return $this->prepareMappingsInput($input);
    }

    private function validateLdapInputs(array $input) { /* ... */ }
    private function prepareMappingsInput(array $input) { /* ... */ }
}
```

**Code APRÈS** (délégation au service) :
```php
class SyncFilter extends CommonDBTM
{
    public function prepareInputForAdd($input) {
        // Délégation au service spécialisé
        $container = Bootstrap::getContainer();
        $validator = $container->get(SyncFilterValidationService::class);
        return $validator->validateAndPrepare($input);
    }
}

// Service dédié testable indépendamment
class SyncFilterValidationService
{
    public function validateAndPrepare(array $input): array|false
    {
        $input = $this->validateLdapInputs($input);
        if ($input === false) {
            return false;
        }
        return $this->prepareMappingsInput($input);
    }

    public function validateLdapInputs(array $input): array|false { /* ... */ }
    public function prepareMappingsInput(array $input): array { /* ... */ }
}
```

#### 2. SyncFilterCronService (Tâches automatiques)

**Responsabilités extraites** :
- Exécution des tâches cron automatiques
- Récupération des filtres actifs avec AuthLDAP
- Logging et reporting détaillé

**Code AVANT** (dans SyncFilter) :
```php
class SyncFilter extends CommonDBTM
{
    public static function cronSyncLdapFilters(?CronTask $task): int
    {
        // Grosse logique cron mélangée avec modèle
        // Récupération filtres actifs
        // Boucle synchronisation
        // Logging
        // Gestion erreurs
        // ...
    }
}
```

**Code APRÈS** (délégation au service) :
```php
class SyncFilter extends CommonDBTM
{
    public static function cronSyncLdapFilters(?CronTask $task = null): int
    {
        // Délégation au service spécialisé
        $container = Bootstrap::getContainer();
        $cronService = $container->get(SyncFilterCronService::class);
        return $cronService->executeSyncTask($task);
    }
}

// Service dédié pour les cron tasks
class SyncFilterCronService
{
    public function executeSyncTask(?CronTask $task): int
    {
        // Logique cron isolée et testable
        // ...
    }
}
```

#### 3. SyncFilterFormPresenter (Présentation)

**Responsabilités extraites** :
- Préparation des données pour la vue Twig
- Résolution du contexte AuthLDAP parent
- Transformation modèle → vue

**Code AVANT** (dans SyncFilter) :
```php
class SyncFilter extends CommonDBTM
{
    public function showForm($ID, array $options = [])
    {
        // Grosse méthode mélangant logique et présentation
        // Résolution AuthLDAP parent
        // Préparation dropdowns
        // Récupération config
        // Test connexion LDAP
        // Préparation données pour Twig
        // ...
        return TemplateRenderer::getInstance()->display('syncfilter_form.html.twig', $data);
    }
}
```

**Code APRÈS** (délégation au Presenter) :
```php
class SyncFilter extends CommonDBTM
{
    public function showForm($ID, array $options = [])
    {
        // Délégation au Presenter
        $container = Bootstrap::getContainer();
        $presenter = $container->get(SyncFilterFormPresenter::class);
        $viewModel = $presenter->prepareViewModel($this, $ID, $options);

        return TemplateRenderer::getInstance()->display('syncfilter_form.html.twig', $viewModel);
    }
}

// Presenter dédié pour préparation vue (pattern MVC)
class SyncFilterFormPresenter
{
    public function prepareViewModel(SyncFilter $filter, int $ID, array $options): array
    {
        // Logique de présentation isolée
        // Résolution contexte
        // Préparation ViewModel
        // ...
        return $viewModel;
    }
}
```

**Bénéfices de l'extraction** :
- ✅ **Single Responsibility** : Chaque classe a une seule responsabilité claire
- ✅ **Testabilité** : Services testables indépendamment du modèle
- ✅ **Maintenabilité** : Code plus court, plus clair, plus facile à modifier
- ✅ **Réutilisabilité** : Services peuvent être utilisés ailleurs
- ✅ **Évolutivité** : Ajouter des fonctionnalités sans toucher au modèle
- ✅ **Rétrocompatibilité** : API publique de SyncFilter inchangée (zéro breaking change)

---

### C. Injection Complète des Dépendances

**Problème résolu** : **Couplage fort** avec `new` dans les constructeurs

**Code AVANT** (couplage fort) :
```php
class LdapSyncService
{
    public function __construct(
        LdapConnectionInterface $ldap_connection,
        AssetCreationService $asset_creation_service,
        AssetTypeClassifier $asset_type_classifier
    ) {
        $this->ldap_connection = $ldap_connection;
        $this->asset_creation_service = $asset_creation_service;
        $this->asset_type_classifier = $asset_type_classifier;

        // ❌ Instanciations directes = couplage fort
        $this->data_extractor = new LdapDataExtractor();
        $this->parameter_validator = new LdapParameterValidator();
    }
}
```

**Problèmes** :
- ❌ Impossible de mocker `LdapDataExtractor` et `LdapParameterValidator` dans les tests
- ❌ Dépendances cachées (pas visibles dans la signature du constructeur)
- ❌ Violation du principe **Dependency Inversion** (dépend de classes concrètes)
- ❌ Code rigide, difficile à tester et faire évoluer

**Code APRÈS** (injection complète) :
```php
class LdapSyncService
{
    public function __construct(
        LdapConnectionInterface $ldap_connection,
        AssetCreationService $asset_creation_service,
        AssetTypeClassifier $asset_type_classifier,
        LdapDataExtractor $data_extractor,          // ✅ Injecté
        LdapParameterValidator $parameter_validator  // ✅ Injecté
    ) {
        $this->ldap_connection = $ldap_connection;
        $this->asset_creation_service = $asset_creation_service;
        $this->asset_type_classifier = $asset_type_classifier;
        $this->data_extractor = $data_extractor;
        $this->parameter_validator = $parameter_validator;
    }
}

// Enregistrement dans ServiceContainer
$this->register(LdapDataExtractor::class, fn() => new LdapDataExtractor());
$this->register(LdapParameterValidator::class, fn() => new LdapParameterValidator());

$this->register(LdapSyncService::class, function ($c) {
    return new LdapSyncService(
        $c->get(LdapConnectionInterface::class),
        $c->get(AssetCreationService::class),
        $c->get(AssetTypeClassifier::class),
        $c->get(LdapDataExtractor::class),       // ✅ Injection depuis conteneur
        $c->get(LdapParameterValidator::class)   // ✅ Injection depuis conteneur
    );
});
```

**Bénéfices** :
- ✅ **Testabilité** : Toutes les dépendances mockables facilement
- ✅ **Transparence** : Dépendances visibles dans la signature du constructeur
- ✅ **Dependency Inversion** : Dépend d'interfaces ou de contrats clairs
- ✅ **Flexibilité** : Facile de changer l'implémentation d'une dépendance
- ✅ **Maintenabilité** : Code découplé, facile à refactorer

---

### Résumé des Principes SOLID Appliqués

| Principe | Application | Bénéfice |
|----------|-------------|----------|
| **Single Responsibility** | Extraction de 3 services depuis SyncFilter | Chaque classe a une seule responsabilité claire |
| **Open/Closed** | Pattern Strategy pour AssetCreationService | Extensible sans modification du code existant |
| **Liskov Substitution** | Interfaces communes (AssetFieldHandlerInterface) | Handlers interchangeables sans casser le code |
| **Interface Segregation** | Interfaces spécialisées par domaine | Pas de dépendances inutiles |
| **Dependency Inversion** | Injection complète via ServiceContainer | Code découplé, testable, maintenable |

## Points d'Extension

Cette section guide les développeurs pour **étendre le plugin** en ajoutant de nouveaux types d'assets grâce au **pattern Strategy**.

### Comment Ajouter un Nouveau Type d'Asset

Le pattern Strategy permet d'ajouter un nouveau type d'asset **sans modifier le code existant** (principe Open/Closed).

#### Workflow d'Extension

**Étape 1 : Créer le Handler**

Créer une classe qui implémente `AssetFieldHandlerInterface` dans `src/Services/AssetFieldHandlers/` :

```php
// src/Services/AssetFieldHandlers/PeripheralFieldHandler.php
<?php

namespace GlpiPlugin\Advancedldap\Services\AssetFieldHandlers;

use GlpiPlugin\Advancedldap\Contracts\AssetFieldHandlerInterface;

/**
 * Field handler for Peripheral asset type
 */
class PeripheralFieldHandler implements AssetFieldHandlerInterface
{
    /**
     * Check if this handler supports the given asset type
     */
    public function supports(string $assetType): bool
    {
        return $assetType === 'Peripheral';
    }

    /**
     * Handle asset data by applying default values
     */
    public function handle(array $data): array
    {
        $defaults = $this->getDefaultValues();

        // Merge defaults with existing data (existing data takes precedence)
        foreach ($defaults as $field => $value) {
            if (!isset($data[$field])) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * Get required fields for this asset type
     */
    public function getRequiredFields(): array
    {
        return ['name', 'serial'];
    }

    /**
     * Get default values for optional fields
     */
    public function getDefaultValues(): array
    {
        return [
            'peripheraltypes_id' => 0,
            'states_id' => 0,
            'manufacturers_id' => 0,
            'locations_id' => 0,
        ];
    }
}
```

**Étape 2 : Enregistrer le Handler dans ServiceContainer**

Ajouter le handler dans l'enregistrement de `AssetCreationService` :

```php
// src/Container/ServiceContainer.php - Dans registerDefaultServices()

$this->register(AssetCreationService::class, fn($c) =>
    new AssetCreationService(
        $c->get(DatabaseInterface::class),
        [
            new ComputerFieldHandler(),
            new PrinterFieldHandler(),
            new NetworkEquipmentFieldHandler(),
            new UserFieldHandler(),
            new PeripheralFieldHandler(),        // ✅ Ajouter le nouveau handler ici
        ]
    )
);
```

**C'est tout !** Le nouveau type `Peripheral` est maintenant supporté automatiquement. ✅

#### Test du Nouveau Handler

Le nouveau type d'asset sera automatiquement :
- ✅ Disponible dans le dropdown "Asset Type" du formulaire SyncFilter
- ✅ Pris en charge par `AssetCreationService` lors de la synchronisation
- ✅ Géré avec les valeurs par défaut définies dans le handler

#### Exemple d'Utilisation

```php
// Dans LdapSyncService, le handler sera automatiquement utilisé
$assetData = [
    'name' => 'Souris Logitech MX Master',
    'serial' => 'MX123456789',
    // peripheraltypes_id, states_id, etc. seront ajoutés automatiquement par le handler
];

$result = $this->asset_creation_service->createOrUpdateAsset('Peripheral', $assetData);
// Le PeripheralFieldHandler détecte 'Peripheral' via supports() et applique getDefaultValues()
```

#### Points Importants

1. **Nom du handler** : Doit se terminer par `FieldHandler` par convention
2. **Méthode `supports()`** : Doit retourner `true` uniquement pour le type géré
3. **Méthode `handle()`** : Ne doit pas écraser les données existantes, seulement ajouter les defaults
4. **Champs requis** : Définir dans `getRequiredFields()` pour validation et UI
5. **Tests** : Créer un fichier de test unitaire dans `tests/` pour valider le handler

#### Extension Avancée : Custom Logic

Si vous avez besoin d'une logique plus complexe que juste des valeurs par défaut :

```php
public function handle(array $data): array
{
    $defaults = $this->getDefaultValues();

    foreach ($defaults as $field => $value) {
        if (!isset($data[$field])) {
            $data[$field] = $value;
        }
    }

    // Logique custom : définir le type de périphérique selon le nom
    if (!isset($data['peripheraltypes_id']) && isset($data['name'])) {
        if (stripos($data['name'], 'souris') !== false) {
            $data['peripheraltypes_id'] = 1; // ID pour "Souris"
        } elseif (stripos($data['name'], 'clavier') !== false) {
            $data['peripheraltypes_id'] = 2; // ID pour "Clavier"
        }
    }

    // Validation : garantir qu'on a au moins le nom et le serial
    if (empty($data['name']) || empty($data['serial'])) {
        throw new \RuntimeException('Peripheral requires name and serial');
    }

    return $data;
}
```

### Avantages du Pattern Strategy pour l'Extension

- ✅ **Zero modification** du code existant (AssetCreationService)
- ✅ **Isolation** : Logique du nouveau type complètement isolée
- ✅ **Testabilité** : Handler testable indépendamment avec ses propres tests
- ✅ **Découplage** : Pas de dépendance entre les handlers
- ✅ **Simplicité** : Seulement 2 étapes pour ajouter un type (créer + enregistrer)
- ✅ **Maintenabilité** : Code clair, explicite, facile à comprendre

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
1. **Validation avant test** (`front/syncfilter.form.php`)
   - Base DN : `isValidDN()` - Rejet si métacaractères ou structure invalide
   - Filtre : `sanitizeFilter()` - Rejet si syntaxe invalide

2. **Validation avant sauvegarde** (`src/Models/SyncFilter.php`)
   - Méthode `validateLdapInputs()` via `SyncFilterValidationService`
   - Empêche la sauvegarde de données malveillantes en base

3. **Validation avant recherche LDAP** (`src/Services/GlpiLdapConnectionService.php`)
   - Double validation DN + filtre avant `ldap_search()`
   - Retourne erreur si validation échoue (pas d'exécution)

**Tests de sécurité** :
- Tests unitaires dans `tests/LdapFilterSanitizerTest.php`
- Cas d'injection testés : `*))(|(objectClass=*`, `admin)(uid=*)`, backslash bypass, etc.
- Conformité RFC vérifiée : RFC 4515 (filtres) et RFC 4514 (DN)

#### **Protection XSS dans les Templates Twig** 🔒
Le plugin protège contre les injections XSS via un échappement systématique dans tous les templates :

**Templates sécurisés** :
1. **syncfilter_form.html.twig**
   - Échappement HTML explicite `|e('html')` pour données LDAP (DN, attributs, messages erreur)
   - Échappement JavaScript `|e('js')` pour chaînes insérées dans code JS inline
   - JSON sécurisé : `field_mappings|json_encode|raw` au lieu de `|raw` seul

2. **syncfilters_list.html.twig**
   - Échappement HTML pour `base_dn` et `ldap_filter`

**Principes appliqués** :
- Toutes les données provenant de sources externes (LDAP, DB user input) sont échappées explicitement
- Contexte HTML : `|e('html')` pour empêcher injection de tags HTML/scripts
- Contexte JavaScript : `|e('js')` pour empêcher injection dans code JS inline
- JSON dans JavaScript : `|json_encode|raw` pour sérialisation sécurisée

#### **Autres mesures de sécurité**
- Validation stricte des paramètres d'entrée
- Pas d'exposition des variables globales
- Gestion centralisée des erreurs avec logs
- Droits GLPI respectés (READ, UPDATE requis)

### **Performance**
- Services instanciés une seule fois (singleton)
- Chargement paresseux des dépendances
- Requêtes base de données optimisées
- Cache des métadonnées d'assets
- Logs de debug optimisés (réduction volume important)

### **Standards de Code**
- PHP 8.2+ avec types stricts
- PSR-12 pour le style de code
- Psalm et PHPStan pour l'analyse statique
- Tests unitaires pour chaque service
- Makefile avec commandes de développement

## Compatibilité Legacy GLPI 11

### **Pourquoi les alias `class_alias()` sont nécessaires**

Le plugin utilise une architecture moderne avec namespaces PHP (`GlpiPlugin\Advancedldap\Models\SyncFilter`), mais GLPI 11 a des limitations dans son moteur de recherche et ses actions massives qui nécessitent l'ancien format de nommage (`PluginAdvancedldapSyncFilter`).

**Erreur sans compatibilité legacy :**
```
Class name must be a valid object or a string
In ./src/Glpi/Search/Provider/SQLProvider.php(6431)
```

### **Implémentation de la compatibilité**

1. **`class_alias()` automatique** : Créé en fin de fichier `SyncFilter.php` pour mapper l'ancien nom vers la nouvelle classe
2. **Double enregistrement** : Classes enregistrées avec les deux conventions dans `setup.php`
3. **`getType()` conditionnel** : Retourne le nom legacy sauf dans le contexte MassiveAction où le namespace est requis

**Fichiers concernés :**
- [src/Models/SyncFilter.php:927-932](src/Models/SyncFilter.php#L927-L932) - Création de l'alias
- [src/Models/SyncFilter.php:105-121](src/Models/SyncFilter.php#L105-L121) - Méthode `getType()` avec logique conditionnelle
- [setup.php:75-86](setup.php#L75-L86) - Enregistrement double + forçage du chargement

### **Quand supprimer cette compatibilité ?**

- ✅ Lorsque le plugin ciblera **GLPI 12+** uniquement
- ✅ Lorsque GLPI corrigera complètement le support des namespaces dans `Search::show()` et `MassiveAction`

**Jusqu'à GLPI 11.0.99, cette compatibilité est INDISPENSABLE.**

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

## État Actuel du Plugin

### ✅ **Architecture Complète et Opérationnelle**

#### **Backend - Architecture SOLID**
- **Pattern Strategy** : AssetFieldHandlers extensibles pour différents types d'assets
- **Injection de dépendances complète** : Tous les services reçoivent leurs dépendances via constructeur
- **Services spécialisés** : 20 services organisés par domaine (LDAP, Assets, SyncFilter, Validation, Wrappers)
- **Extraction God Object** : SyncFilter refactorisé en façade déléguant à 3 services spécialisés
- **11 contrats (Interfaces)** : Respect principes SOLID (DIP, ISP)
- **Conteneur DI** : ServiceContainer avec lazy loading et factory pattern
- **Workflows doubles** : Traditionnel (CommonDBTM) + Inventaire natif GLPI
- **Legacy compatibility** : Alias automatiques pour Search GLPI 11
- **🔒 Sécurité LDAP** : Protection injections RFC 4515/4514, validation triple couche

#### **Frontend - Interface Complète**
- **Onglet AuthLDAP** : "Advanced sync" avec badge comptage intégré (icône `ti ti-filter`)
- **2 Templates Twig** :
  - `syncfilters_list.html.twig` : Liste filtres dans onglet AuthLDAP
  - `syncfilter_form.html.twig` : Formulaire édition avec validation
- **2 Pages front** :
  - `front/syncfilter.php` : Liste via Search::show
  - `front/syncfilter.form.php` : CRUD complet avec actions multiples
- **1 Endpoint AJAX** : Chargement dynamique champs assets (`ajax/getAssetFields.php`)
- **Actions supportées** : add, update, delete, test_ldap, sync_from_ldap, duplicate (massive action)
- **Filtrage contextuel** : Hook `addDefaultWhere` pour filtrer par AuthLDAP

#### **Base de Données**
- **Table principale** : `glpi_plugin_advancedldap_syncfilters`
  - Champs : id, name, ldap_filter, base_dn, asset_type, field_mappings, is_active, date_creation, date_mod
- **Table relation** : `glpi_plugin_advancedldap_authldap_syncfilters`
  - Champs : id, authldap_id, syncfilter_id, is_active, date_creation
- **Contrainte unicité** : UNIQUE KEY `unicity` (authldap_id, syncfilter_id)
- **Indexes** : Optimisation requêtes (name, is_active, asset_type, dates, foreign keys)
- **Installation/Désinstallation** : Gestion automatique via hooks
- **Engine** : InnoDB avec charset utf8mb4_unicode_ci

#### **Qualité de Code**
- **PHP 8.2+** : Types stricts, promotion constructeur, readonly properties, expressions match
- **PSR-12** : Code style via `.php-cs-fixer.php`
- **Analyse statique** : Psalm + PHPStan configurés (`psalm.xml`, `phpstan.neon`)
- **Tests unitaires** : Fichiers de tests couvrant services, modèles, repositories, providers
  - Tests dédiés à la sécurité LDAP (`LdapFilterSanitizerTest.php`)
  - Bootstrap configuré avec autoload GLPI
  - **Stratégie assets génériques** : Tests unitaires pour validation, tests d'intégration pour création
- **Documentation** : PHPDoc complet avec types, @param, @return, @throws
- **Logs** : `Toolbox::logDebug()` dans tous les services critiques pour traçabilité
- **Namespaces** : Organisation moderne `GlpiPlugin\Advancedldap\*` avec alias legacy

#### **Fonctionnalités Avancées**
- **Synchronisation intelligente** : Classification automatique inventoriables vs traditionnels
- **Test LDAP temps réel** : Validation avant sauvegarde, aperçu résultats
- **Mapping automatique** : Suggestion attributs LDAP via `LdapAttributeMapper` (RFC 4519)
- **Parsing filtres** : Extraction et validation attributs via `LdapFilterParser` (RFC 4515)
- **Massive actions** : Duplication filtres avec relations associées
- **Gestion erreurs** : Messages explicites, logs détaillés, fallbacks gracieux
- **Injection de dépendances** : ServiceContainer avec lazy loading et factory pattern
- **Conversion inventaire** : `LdapToInventoryConverter` pour format JSON natif GLPI
- **Workflows hybrides** : Support simultané CommonDBTM (legacy) et Inventory API (moderne)

### 🔍 **Code Mort & Fichiers Deprecated**

#### **État actuel**
- ✅ **Aucun code mort détecté**
- ✅ Tous les services sont enregistrés et utilisés dans ServiceContainer
- ✅ Tous les contrats ont une implémentation active
- ✅ Toutes les méthodes publiques sont utilisées
- ✅ Architecture cohérente sans redondance

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

**Méthodes publiques** :

1. **`cronInfo(string $name): array`**
   - Fournit la description de la tâche cron pour l'interface GLPI
   - Retourne le nom et la description du paramètre
   - Utilisé par GLPI pour afficher les informations dans Configuration > Actions automatiques

2. **`cronSyncLdapFilters(?CronTask $task = null): int`**
   - **Point d'entrée principal** pour l'exécution automatique
   - Signature conforme au standard GLPI (type `?CronTask`)
   - Délègue l'exécution à `SyncFilterCronService`
   - **Codes de retour** :
     - `0` : Rien à faire (aucun filtre actif)
     - `1` : Succès (au moins un filtre synchronisé)
     - `-1` : Besoin de relancer (limite `max_filters` atteinte)

##### **SyncFilterCronService - Service Dédié**

Le service `SyncFilterCronService` (extrait du God Object) gère toute la logique cron :

**Workflow** :
1. Récupération du paramètre `max_filters` depuis `$task->fields['param']`
2. Récupération des filtres actifs via repository
3. Boucle de synchronisation avec gestion d'erreurs isolées
4. Logging détaillé via `$task->log()` et `Toolbox::logDebug()`
5. Calcul du volume (nombre d'assets synchronisés)

**Critères de sélection des filtres** :
- Filtre actif (`is_active = 1`)
- Relation active (`is_active = 1`)
- Serveur AuthLDAP actif (`is_active = 1`)

**Gestion des erreurs** :
- Chaque filtre traité dans un `try/catch` indépendant
- Une erreur sur un filtre ne bloque pas les autres
- Logging détaillé pour chaque erreur

##### **Enregistrement de la CronTask - hook.php**

**Installation** (dans `plugin_advancedldap_install()`) :
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

---

## Évolutions Récentes

### 📈 **Refactorisations Majeures**

#### **15/10/2025 - Refactorisation Majeure : Architecture SOLID**
- ✅ **Pattern Strategy** : AssetCreationService extensible sans modification (Open/Closed)
- ✅ **4 handlers d'assets** : Computer, Printer, NetworkEquipment, User (extensible facilement)
- ✅ **Extraction God Object** : SyncFilter devient façade déléguant à 3 services spécialisés
  - SyncFilterValidationService : Validation LDAP inputs & field mappings
  - SyncFilterCronService : Exécution tâches cron automatiques
  - SyncFilterFormPresenter : Préparation données pour vues Twig
- ✅ **Injection complète** : Élimination totale des `new` dans constructeurs
  - LdapSyncService : Injection de LdapDataExtractor et LdapParameterValidator
  - AssetCreationService : Injection d'un tableau de handlers
- ✅ **Respect SOLID** : Single Responsibility, Open/Closed, Dependency Inversion
- ✅ **Zero breaking change** : API publique inchangée, rétrocompatibilité 100%
- ✅ **Documentation complète** : Guide d'extension avec exemples concrets (Pattern Strategy)
- ✅ **Tests unitaires** : Tous les tests mis à jour et passent

#### **10/10/2025 - Synchronisation Automatique via Cron Tasks**
- ✅ **Nouvelle fonctionnalité** : Synchronisation automatique des filtres LDAP via tâches cron GLPI
- ✅ **Service dédié** : SyncFilterCronService pour logique cron isolée
- ✅ **Configuration par défaut** :
  - Fréquence : 1 heure (ajustable)
  - Mode : CLI + Web (flexible)
  - Paramètre : illimité (ajustable pour limiter le nombre de filtres)
- ✅ **Gestion d'erreurs robuste** :
  - Isolation des erreurs par filtre (un échec ne bloque pas les autres)
  - Logging détaillé via `$task->log()` et `Toolbox::logDebug()`
  - Codes de retour appropriés (0, 1, -1)
- ✅ **Enregistrement CronTask** : Ajout de `CronTask::register()` dans `hook.php`
- ✅ **Documentation complète** : Section dédiée avec guide d'utilisation, monitoring

#### **06/10/2025 - Révision Tests Unitaires & Support Assets Génériques**
- ✅ **Révision complète des tests unitaires** suite à l'adaptation de la synchronisation des assets génériques
- ✅ **Nouveaux tests ajoutés** :
  - `GlpiConfigurationService` : Tests pour `isInventoryEnabled()`
  - `LdapParameterValidator` : Tests pour validation assets génériques (`GenericAsset_ID`)
- ✅ **Documentation explicative** :
  - Ajout commentaires dans chaque fichier de test expliquant pourquoi assets génériques non testés unitairement
  - Référence aux tests d'intégration pour couverture complète
- ✅ **Stratégie de test GLPI** :
  - Méthodes générant des logs (`Toolbox::logDebug()`) non testées unitairement
  - Framework GLPI rejette les "unexpected log entries"
  - Tests d'intégration pour méthodes avec logs (création assets génériques)

#### **03/10/2025 - Correctifs & Améliorations UX**
- ✅ **Workflow de Test LDAP Sécurisé** : Le filtre doit être sauvegardé avant test
  - Refactorisation complète de `SyncFilter::handleTestRequest()`
  - Données lues uniquement depuis la base de données (plus de paramètres `$_GET`)
  - Bouton POST → Lien GET avec message d'aide explicite
- ✅ **Gestion intelligente des Field Mappings** :
  - Respect strict de la configuration utilisateur dans `LdapToInventoryConverter`
  - Nouvelle méthode `isFieldAllowed()` pour filtrage des champs LDAP
  - Champs critiques toujours autorisés : `cn`, `name`, `displayname`, `samaccountname`
- ✅ **Détection d'échec silencieux de l'inventaire** :
  - Vérification `$assetId = $item->getID()` après `doInventory()`
  - Nouvelle méthode `getMinimumFieldRequirements()` avec exigences par type d'asset
  - Messages d'erreur explicites guidant l'utilisateur
- ✅ **Correctif Repository AuthLdapSyncFilterRepository** :
  - Gestion correcte des itérateurs GLPI : `is_array()` → `is_iterable()`
  - Respect des conventions GLPI et cohérence avec `SyncFilterRepository`
- ✅ **Améliorations Interface Utilisateur** :
  - Pré-sélection automatique des champs obligatoires (objet JavaScript `minimumRequiredFields`)
  - Simplification workflow de test (bouton → lien, texte d'aide)

#### **02/10/2025 - Sécurité : Protection XSS dans Templates Twig**
- ✅ **Templates sécurisés** : Échappement systématique de toutes les données externes
- ✅ **Vulnérabilité critique corrigée** : `field_mappings|raw` → `field_mappings|json_encode|raw`
- ✅ **Contexte HTML** : Ajout `|e('html')` pour données LDAP (DN, attributs, messages erreur)
- ✅ **Contexte JavaScript** : Ajout `|e('js')` pour chaînes insérées dans code JS inline
- ✅ **Protection complète** : Prévention XSS sur toutes les données user-provided et LDAP

#### **02/10/2025 - Sécurité : Protection Injections LDAP (RFC 4515/4514)**
- ✅ **Nouveau service** : `LdapFilterSanitizer` - Protection complète anti-injection LDAP
- ✅ **Nouveau contrat** : `LdapFilterSanitizerInterface` - 4 méthodes (escape, validate DN/filter)
- ✅ **Tests unitaires complets** : Couverture complète des cas d'injection
- ✅ **3 points de protection** :
  - Validation avant test LDAP (`front/syncfilter.form.php`)
  - Validation avant sauvegarde BDD (`src/Models/SyncFilter.php` via `SyncFilterValidationService`)
  - Validation avant recherche LDAP (`src/Services/GlpiLdapConnectionService.php`)
- ✅ **Conformité RFC** : RFC 4515 (filtres) + RFC 4514 (Distinguished Names)
- ✅ **Cas bloqués** : Injection parenthèses, wildcards, DN malformés, métacaractères

#### **30/09/2025 - Documentation complète + Nettoyage**
- ✅ Analyse exhaustive de l'architecture
- ✅ Mise à jour documentation développeur complète
- ✅ Cartographie complète : modèles, services, repositories, providers, infra
- ✅ Identification et suppression fichiers deprecated
- ✅ Code base nettoyée

#### **Septembre 2025 - Refactorisation SRP - SyncFilter**
- ✅ Extraction 3 services spécialisés (LdapFilterParser, LdapAttributeMapper, SyncFilterFormHelper)
- ✅ Réduction du God Object SyncFilter
- ✅ Respect principe SRP (Single Responsibility Principle)

#### **Septembre 2025 - Audit de Code - Architecture Optimisée**
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
- **Users** : Utilisateurs avec attributs variés
- **Computers** : Ordinateurs avec serialNumber, model, etc.
- **Printers** : Imprimantes avec attributs spécifiques
- **Network Equipment** : Équipements réseau
- **Groups** : Structures organisationnelles complexes

Cette infrastructure de test permet de valider tous les cas d'usage du plugin sans avoir besoin d'un serveur LDAP de production.

## Outils de Développement

### **Workflow humain / IA**

🤖 Le développement de ce plugin a été fortement assisté par un LLM (Claude Code, Anthropic).

#### L'IA a contribué à :

- la génération de l'architecture initiale (organisation fichiers, services, interfaces),
- la production de portions de code récurrentes ou verbeuses (CRUD, formulaires, repositories),
- la rédaction et la mise à jour de la documentation technique,
- la création de tests unitaires et de scripts de tests (relus et ajustés par le développeur).

#### Rôle du développeur humain

- Supervision de l'ensemble du code métier et des choix d'architecture,
- Relecture, ajustement et validation des tests unitaires existants,
- Refactorisation et adaptation du code généré pour respecter SOLID et PSR-12,
- Vérification de la cohérence avec GLPI et correction des anomalies,
- Validation des principes de sécurité et de performance.

---

**Documentation maintenue par l'équipe du plugin Advanced LDAP**
