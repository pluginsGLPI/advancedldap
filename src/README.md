# Architecture du Plugin AdvancedLDAP

## Vue d'ensemble

Cette architecture simplifiée utilise le pattern **Singleton** pour tous les services et intègre directement les méthodes de repository dans les modèles pour réduire la complexité.

## Structure des répertoires

```
src/
├── Model/                      # Modèles de données (étendent CommonDBTM/CommonDBRelation)
│   ├── SyncFilter.php         # Modèle principal pour les filtres de synchronisation
│   └── AuthLdapSyncFilter.php # Modèle de relation AuthLDAP <-> SyncFilter
├── Service/                    # Services métier (tous Singleton)
│   ├── SyncFilterService.php  # Service consolidé pour les filtres
│   ├── LdapSyncService.php    # Service de synchronisation LDAP
│   ├── LdapDataService.php    # Service de gestion des données LDAP
│   └── AssetService.php       # Service de gestion des assets GLPI
├── Config/                     # Configuration
│   └── AssetFieldConfig.php   # Configuration des champs disponibles par type d'asset
└── AdvancedLdapSync.php       # Classe principale d'intégration avec GLPI
```

## Services consolidés

### 1. SyncFilterService (Service principal)

**Responsabilités fusionnées :**
- Gestion des filtres de synchronisation (création, mise à jour, suppression)
- Validation et sanitisation des filtres LDAP (RFC 4515)
- Parsing des filtres LDAP et extraction d'attributs
- Exécution des tâches CRON de synchronisation automatique

**Utilisation :**
```php
$service = SyncFilterService::getInstance();

// Récupérer les filtres actifs
$filters = $service->getAvailableSyncFilters();

// Valider un filtre LDAP
$validated = $service->validateAndPrepare($input);

// Exécuter la synchronisation CRON
$result = $service->executeSyncTask($cronTask);
```

**Méthodes principales :**
- `getAvailableSyncFilters()` : Récupère tous les filtres actifs
- `getSyncFiltersForAuthLdap($authldap_id)` : Filtres pour un serveur LDAP spécifique
- `createSyncFilter(...)` : Crée un nouveau filtre
- `validateAndPrepare($input)` : Valide et prépare les inputs
- `isValidFilter($ldap_filter)` : Valide la syntaxe d'un filtre LDAP
- `isValidDN($dn)` : Valide la structure d'un Distinguished Name
- `executeSyncTask($task)` : Exécute la tâche CRON de synchronisation

### 2. LdapSyncService

**Responsabilités :**
- Synchronisation des assets depuis LDAP vers GLPI
- Connexion et requêtes LDAP
- Traitement des résultats LDAP et création/mise à jour des assets

**Utilisation :**
```php
$service = LdapSyncService::getInstance();

// Synchroniser depuis un filtre
$result = $service->synchronizeFromFilter($syncfilter_id, $authldap_id);

// Synchroniser manuellement
$result = $service->synchronizeAssets($authldap_id, $base_dn, $ldap_filter, $asset_type, $field_mappings);
```

**Méthodes principales :**
- `synchronizeFromFilter($syncfilter_id, $authldap_id)` : Synchronise depuis un filtre configuré
- `synchronizeAssets(...)` : Synchronisation manuelle avec paramètres
- `testConnection($authldap_id)` : Teste la connexion LDAP

### 3. LdapDataService

**Responsabilités :**
- Transformation des données LDAP en format GLPI
- Mapping des attributs LDAP vers les champs GLPI
- Extraction et normalisation des valeurs LDAP

**Utilisation :**
```php
$service = LdapDataService::getInstance();

// Transformer les données LDAP
$glpi_data = $service->transformLdapEntry($ldap_entry, $field_mappings, $asset_type);

// Trouver un attribut LDAP correspondant
$attribute = $service->findMatchingAttribute($glpi_field, $available_attributes);
```

**Méthodes principales :**
- `transformLdapEntry($entry, $mappings, $asset_type)` : Transforme une entrée LDAP
- `extractValue($entry, $attribute)` : Extrait une valeur d'attribut LDAP
- `findMatchingAttribute($field, $attributes)` : Trouve l'attribut LDAP correspondant

### 4. AssetService

**Responsabilités :**
- Gestion des types d'assets disponibles
- Vérification du support des assets
- Récupération des champs disponibles par type

**Utilisation :**
```php
$service = AssetService::getInstance();

// Récupérer les types d'assets disponibles
$types = $service->getAvailableAssetTypes();

// Vérifier si un type est supporté
$supported = $service->isSupportedAssetType('Computer');

// Récupérer les champs disponibles
$fields = $service->getAvailableFieldsForAsset('Computer');
```

**Méthodes principales :**
- `getAvailableAssetTypes()` : Liste des types d'assets disponibles
- `isSupportedAssetType($type)` : Vérifie le support d'un type
- `getAvailableFieldsForAsset($type)` : Champs disponibles pour un type
- `isInventoryEnabled()` : Vérifie si l'inventaire GLPI est activé

## Modèles

### SyncFilter

**Modèle principal** pour les filtres de synchronisation LDAP. Étend `CommonDBTM` et intègre les méthodes de repository.

**Méthodes de modèle :**
- `getFieldMappings()` : Récupère les mappings de champs JSON
- `setFieldMappings($mappings)` : Définit les mappings de champs
- `getAssociatedAuthLDAPs()` : Récupère les serveurs AuthLDAP associés
- `getParentAuthLdapId()` : ID du serveur AuthLDAP parent
- `prepareInputForAdd($input)` : Valide et prépare les données avant ajout
- `prepareInputForUpdate($input)` : Valide et prépare les données avant mise à jour

**Méthodes de repository intégrées :**
- `getAuthLdapRelations($syncfilter_id)` : Récupère toutes les relations
- `deleteAuthLdapRelations($syncfilter_id)` : Supprime les relations
- `getActiveAuthLdapServers()` : Liste des serveurs LDAP actifs
- `getSyncFiltersForAuthLdapDetailed($authldap_id)` : Filtres détaillés pour un AuthLDAP
- `getAuthLdapsForSyncFilter($syncfilter_id)` : AuthLDAP utilisant ce filtre
- `isSyncFilterAssignedToAuthLdap($authldap_id, $syncfilter_id)` : Vérifie l'assignation

**Méthodes CRON :**
- `cronInfo($name)` : Information sur la tâche CRON
- `cronSyncLdapFilters($task)` : Exécution de la tâche CRON

### AuthLdapSyncFilter

**Modèle de relation** entre AuthLDAP et SyncFilter. Étend `CommonDBRelation` et intègre les méthodes de repository.

**Méthodes statiques intégrées :**
- `addSyncFilterToAuthLdap($authldap_id, $syncfilter_id, $is_active)` : Ajoute une relation
- `removeSyncFilterFromAuthLdap($authldap_id, $syncfilter_id)` : Supprime une relation
- `toggleSyncFilterForAuthLdap($authldap_id, $syncfilter_id, $is_active)` : Change le statut
- `getAuthLdapsForSyncFilter($syncfilter_id, $active_only)` : AuthLDAP pour un filtre
- `isSyncFilterAssignedToAuthLdap($authldap_id, $syncfilter_id)` : Vérifie l'assignation

## Configuration

### AssetFieldConfig

Configuration centralisée des champs disponibles pour chaque type d'asset. Définit les champs qui peuvent être synchronisés depuis LDAP.

**Structure :**
```php
[
    'Computer' => [
        'name' => 'Nom',
        'serial' => 'Numéro de série',
        'otherserial' => 'Numéro d\'inventaire',
        // ...
    ],
    'Monitor' => [...],
    'NetworkEquipment' => [...],
    // ...
]
```

## Changements par rapport à l'ancienne structure

### Supprimé
- ❌ `Contracts/` : Interfaces DatabaseInterface, SyncFilterRepositoryInterface, etc.
- ❌ `Repositories/` : SyncFilterRepository, AuthLdapSyncFilterRepository
- ❌ `Factories/` : LdapConnectionFactory, LdapQueryFactory, AssetFactory
- ❌ `Providers/` : AssetFieldProvider
- ❌ `Container/ServiceContainer` : Container de dépendances
- ❌ `Bootstrap` : Système de bootstrap complexe
- ❌ Services fragmentés : SyncFilterValidationService, SyncFilterCronService, LdapFilterParser, LdapFilterSanitizer

### Consolidé
- ✅ 5 services singleton au lieu de 15+ classes
- ✅ Méthodes de repository intégrées dans les modèles
- ✅ Validation, parsing et sanitisation fusionnés dans SyncFilterService
- ✅ Configuration centralisée dans Config/

### Bénéfices
- 🎯 **Simplicité** : Moins de fichiers, moins de classes, moins de complexité
- 🚀 **Performance** : Pas d'instanciation de container, accès direct via Singleton
- 🔧 **Maintenabilité** : Code plus facile à comprendre et à maintenir
- 📦 **Cohésion** : Logique métier regroupée par domaine fonctionnel
- 💾 **GLPI natif** : Utilisation de `global $DB` au lieu de wrapper DatabaseInterface

## Utilisation du pattern Singleton

Tous les services utilisent `SingletonTrait` de GLPI. Cela garantit une seule instance par service pendant toute la durée de vie de la requête.

**Exemple :**
```php
use GlpiPlugin\Advancedldap\Service\SyncFilterService;

// Récupérer l'instance unique
$service = SyncFilterService::getInstance();

// Utiliser le service
$filters = $service->getAvailableSyncFilters();
```

## Accès à la base de données

Tous les services utilisent directement `global $DB` (l'instance GLPI native) au lieu d'un wrapper DatabaseInterface.

**Exemple :**
```php
public function getAvailableSyncFilters(): array
{
    global $DB;

    $iterator = $DB->request([
        'FROM' => 'glpi_plugin_advancedldap_syncfilters',
        'WHERE' => ['is_active' => 1],
    ]);

    $filters = [];
    foreach ($iterator as $data) {
        $filters[] = $data;
    }

    return $filters;
}
```

## Migration depuis l'ancienne structure

Si vous avez du code utilisant l'ancienne structure :

### Avant (avec container)
```php
$container = Bootstrap::getContainer();
$repository = $container->get(SyncFilterRepositoryInterface::class);
$filters = $repository->getActiveSyncFilters();
```

### Après (avec singleton)
```php
$service = SyncFilterService::getInstance();
$filters = $service->getAvailableSyncFilters();
```

### Avant (avec repository)
```php
$repository = $container->get(SyncFilterRepositoryInterface::class);
$relations = $repository->getAuthLdapRelations($syncfilter_id);
```

### Après (méthode de modèle)
```php
$syncFilter = new SyncFilter();
$relations = $syncFilter->getAuthLdapRelations($syncfilter_id);
```

## Tests

Les services singleton peuvent être testés en mockant les méthodes ou en utilisant des données de test dans la base de données de test GLPI.

## Évolutions futures

Cette architecture simplifiée est conçue pour :
- ✅ Faciliter l'ajout de nouveaux types d'assets
- ✅ Permettre l'extension des services via héritage
- ✅ Rester compatible avec les pratiques GLPI 11
- ✅ Minimiser la dette technique

## Support

Pour toute question sur cette architecture, consultez :
- La documentation GLPI 11 : https://glpi-developer-documentation.rtfd.io/
- Le code source dans `src/`
- Les tests fonctionnels dans `tests/`
