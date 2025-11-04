# Changelog - Refactorisation AdvancedLDAP

## [2025-11-04] Refactorisation majeure

### Structure finale (9 fichiers PHP)

#### Nouveaux fichiers créés
```
src/
├── Model/
│   ├── SyncFilter.php              [NOUVEAU] Modèle + méthodes repository
│   └── AuthLdapSyncFilter.php      [NOUVEAU] Modèle relation + méthodes repository
├── Service/
│   ├── SyncFilterService.php       [CRÉÉ] Service consolidé (5 services fusionnés)
│   ├── LdapSyncService.php         [ADAPTÉ] Pattern Singleton
│   ├── LdapDataService.php         [ADAPTÉ] Pattern Singleton
│   └── AssetService.php            [ADAPTÉ] Pattern Singleton
├── Config/
│   └── AssetFieldConfig.php        [CONSERVÉ] Configuration des champs
├── Hook/                            [VIDE] Réservé pour futurs hooks
├── AdvancedLdapSync.php            [ADAPTÉ] Classe d'intégration
└── README.md                        [CRÉÉ] Documentation complète
```

### Fichiers de documentation
```
├── MIGRATION.md                     [CRÉÉ] Guide de migration
└── CHANGELOG_REFACTORING.md         [CRÉÉ] Ce fichier
```

### Sauvegarde ancienne structure
```
src_old/                             [RENOMMÉ] Ancienne structure (48 fichiers)
```

## Détails des modifications

### Model/SyncFilter.php
- **Base** : `src_old/Models/SyncFilter.php`
- **Ajouts** : Méthodes de `SyncFilterRepository` intégrées
- **Changements** :
  - Namespace: `GlpiPlugin\Advancedldap\Model` (sans 's')
  - Utilisation de `global $DB` au lieu de `DatabaseInterface`
  - Appels services via `::getInstance()` au lieu de `Bootstrap::getContainer()`
  - Méthodes helper intégrées depuis `SyncFilterFormHelper`
- **Méthodes ajoutées** :
  - `getAuthLdapRelations($syncfilter_id)`
  - `deleteAuthLdapRelations($syncfilter_id)`
  - `getActiveAuthLdapServers()`
  - `getSyncFiltersForAuthLdapDetailed($authldap_id)` (static)
  - `getAuthLdapsForSyncFilter($syncfilter_id, $active_only)`
  - `isSyncFilterAssignedToAuthLdap(...)` (static)
  - `getAuthLdapIdFromRequest()` (private helper)
  - `getCurrentConfiguration(...)` (private)
  - `checkLdapConnectionStatus(...)` (private)
  - `checkAuthLdapActiveStatus(...)` (private)

### Model/AuthLdapSyncFilter.php
- **Base** : `src_old/Models/AuthLdapSyncFilter.php`
- **Ajouts** : Méthodes de `AuthLdapSyncFilterRepository` intégrées
- **Changements** :
  - Namespace: `GlpiPlugin\Advancedldap\Model`
  - Utilisation de `global $DB`
- **Méthodes ajoutées (static)** :
  - `addSyncFilterToAuthLdap($authldap_id, $syncfilter_id, $is_active)`
  - `removeSyncFilterFromAuthLdap($authldap_id, $syncfilter_id)`
  - `toggleSyncFilterForAuthLdap($authldap_id, $syncfilter_id, $is_active)`
  - `getAuthLdapsForSyncFilter($syncfilter_id, $active_only)`
  - `isSyncFilterAssignedToAuthLdap($authldap_id, $syncfilter_id, $active_only)`

### Service/SyncFilterService.php - **NOUVEAU SERVICE CONSOLIDÉ**
Fusionne **5 anciens services** :

#### 1. SyncFilterService (ancien)
- Méthodes de gestion CRUD des filtres
- Association filtres <-> AuthLDAP

#### 2. SyncFilterValidationService
- `validateLdapInputs($input)`
- `prepareMappingsInput($input)`
- `validateAndPrepare($input)`

#### 3. SyncFilterCronService
- `executeSyncTask($task)`
- `getAllActiveSyncFiltersWithAuthLdap()` (private)
- `getCronInfo($name)` (static)

#### 4. LdapFilterParser
- `parseFilterAttributes($ldap_filter)`
- `extractObjectClasses($ldap_filter)`

#### 5. LdapFilterSanitizer
- `isValidFilter($ldap_filter)`
- `isValidDN($dn)`
- `sanitizeFilter($filter)`
- `escapeFilterValue($str, $for_dn)`

**Total** : 4 sections fonctionnelles, ~760 lignes

### Service/LdapSyncService.php
- **Base** : Service existant
- **Changements** :
  - Ajout de `SingletonTrait`
  - Utilisation de `global $DB`
  - Namespace: `GlpiPlugin\Advancedldap\Service`

### Service/LdapDataService.php
- **Base** : Service existant
- **Changements** :
  - Ajout de `SingletonTrait`
  - Utilisation de `global $DB`
  - Namespace: `GlpiPlugin\Advancedldap\Service`

### Service/AssetService.php
- **Base** : Service existant fusionné avec AssetFieldProvider
- **Changements** :
  - Ajout de `SingletonTrait`
  - Utilisation de `global $DB`
  - Namespace: `GlpiPlugin\Advancedldap\Service`
  - Intégration des méthodes de `AssetFieldProvider`

### AdvancedLdapSync.php
- **Base** : `src_old/AdvancedLdapSync.php`
- **Changements** :
  - Suppression de la dépendance `ServiceContainer`
  - Suppression du constructeur avec injection
  - Utilisation directe de `SyncFilterService::getInstance()`
  - Utilisation de `SyncFilter::getSyncFiltersForAuthLdapDetailed()` (static)
  - Simplification générale du code

### Config/AssetFieldConfig.php
- **Status** : Conservé tel quel
- Aucune modification nécessaire

## Suppressions (dans src_old/)

### Contracts/ (10+ interfaces)
- ❌ `DatabaseInterface`
- ❌ `SyncFilterRepositoryInterface`
- ❌ `AuthLdapSyncFilterRepositoryInterface`
- ❌ `SyncFilterFormHelperInterface`
- ❌ `AssetFieldProviderInterface`
- ❌ Etc.

### Repositories/ (2 repositories)
- ❌ `SyncFilterRepository` → Intégré dans `Model/SyncFilter`
- ❌ `AuthLdapSyncFilterRepository` → Intégré dans `Model/AuthLdapSyncFilter`

### Services/ (anciens services fragmentés)
- ❌ `SyncFilterValidationService` → Fusionné dans `SyncFilterService`
- ❌ `SyncFilterCronService` → Fusionné dans `SyncFilterService`
- ❌ `LdapFilterParser` → Fusionné dans `SyncFilterService`
- ❌ `LdapFilterSanitizer` → Fusionné dans `SyncFilterService`
- ❌ `SyncFilterFormHelper` → Méthodes intégrées dans `SyncFilter` modèle
- ❌ `GlpiConfigurationService` → Accès direct aux constantes/fonctions GLPI
- ❌ `LdapTestService` → TODO: À intégrer si nécessaire

### Factories/ (3 factories)
- ❌ `LdapConnectionFactory`
- ❌ `LdapQueryFactory`
- ❌ `AssetFactory`

### Providers/ (1 provider)
- ❌ `AssetFieldProvider` → Intégré dans `AssetService`

### Container/
- ❌ `ServiceContainer` → Pattern Singleton natif GLPI

### Autres
- ❌ `Bootstrap.php` → Plus nécessaire

## Statistiques

### Réduction de fichiers
- **Avant** : 48 fichiers PHP
- **Après** : 9 fichiers PHP
- **Réduction** : 81%

### Lignes de code (estimation)
- **Avant** : ~6000 lignes (avec interfaces, factories, repositories)
- **Après** : ~3500 lignes (code consolidé, sans abstractions inutiles)
- **Réduction** : ~42%

### Complexité
- **Avant** : 
  - 10+ interfaces
  - 2 repositories
  - 3 factories
  - 1 provider
  - 1 container
  - 8+ services
- **Après** :
  - 0 interface
  - 0 repository séparé
  - 0 factory
  - 0 provider
  - 0 container
  - 4 services consolidés

## Migration du code existant

### Changements à effectuer dans les autres fichiers

#### 1. Imports (dans tous les fichiers PHP)
```php
// AVANT
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use GlpiPlugin\Advancedldap\Services\SyncFilterService;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Bootstrap;

// APRÈS
use GlpiPlugin\Advancedldap\Model\SyncFilter;
use GlpiPlugin\Advancedldap\Service\SyncFilterService;
// DatabaseInterface et Bootstrap supprimés
```

#### 2. Utilisation des services
```php
// AVANT
$container = Bootstrap::getContainer();
$service = $container->get(SyncFilterService::class);

// APRÈS
$service = SyncFilterService::getInstance();
```

#### 3. Utilisation des repositories
```php
// AVANT
$repository = $container->get(SyncFilterRepositoryInterface::class);
$servers = $repository->getActiveAuthLdapServers();

// APRÈS
$syncFilter = new SyncFilter();
$servers = $syncFilter->getActiveAuthLdapServers();
```

#### 4. Accès à la base de données
```php
// AVANT (dans un service avec DatabaseInterface)
$this->database->request([...]);

// APRÈS
global $DB;
$DB->request([...]);
```

## Fichiers à vérifier/adapter

### Priorité haute
- [ ] `front/syncfilter.php`
- [ ] `front/syncfilter.form.php`
- [ ] `ajax/syncfilter_test.php`
- [ ] `hook.php`
- [ ] `setup.php`

### Priorité moyenne
- [ ] Templates Twig dans `templates/`
- [ ] Tests dans `tests/`
- [ ] Fichiers JavaScript si ils utilisent les anciens endpoints

### Priorité basse
- [ ] Documentation utilisateur
- [ ] Fichiers de traduction (si nouveaux textes)

## Validation

### Tests à effectuer
1. ✅ Structure des fichiers créée
2. ⏳ Compilation PHP sans erreur
3. ⏳ Chargement du plugin dans GLPI
4. ⏳ Création d'un filtre de synchronisation
5. ⏳ Modification d'un filtre
6. ⏳ Test LDAP depuis le formulaire
7. ⏳ Exécution de la synchronisation
8. ⏳ Exécution de la tâche CRON
9. ⏳ Massive actions sur les filtres
10. ⏳ Vérification de l'historique

## Notes importantes

1. **Namespace** : Attention au 's' dans les anciens namespace
   - `Models` → `Model`
   - `Services` → `Service`
   - `Configs` → `Config`

2. **Singleton** : Tous les services utilisent `SingletonTrait`
   - Accès via `ServiceName::getInstance()`
   - Pas de `new Service()` direct

3. **Base de données** : Toujours utiliser `global $DB;`
   - Ne plus utiliser `DatabaseInterface`
   - Méthodes natives GLPI

4. **Bootstrap** : Ne plus utiliser `Bootstrap::getContainer()`
   - Accès direct aux services via Singleton
   - Pas de container de dépendances

## Date de validation finale
⏳ En attente de tests complets

## Auteur
Claude Code (Anthropic) - 4 novembre 2025
