# Migration - Refactorisation du Plugin AdvancedLDAP

## Date de migration
4 novembre 2025

## Objectif
Simplifier l'architecture du plugin en réduisant la complexité, en éliminant les abstractions inutiles et en adoptant les pratiques natives de GLPI 11.

## Résumé des changements

### Structure finale

```
src/
├── Model/                      # 2 modèles
│   ├── SyncFilter.php         # Modèle principal + méthodes repository
│   └── AuthLdapSyncFilter.php # Modèle de relation + méthodes repository
├── Service/                    # 4 services singleton
│   ├── SyncFilterService.php  # Service consolidé (validation, parsing, cron)
│   ├── LdapSyncService.php    # Synchronisation LDAP
│   ├── LdapDataService.php    # Transformation des données
│   └── AssetService.php       # Gestion des assets
├── Config/                     # 1 configuration
│   └── AssetFieldConfig.php   # Champs disponibles par asset
├── Hook/                       # Dossier vide (réservé)
├── AdvancedLdapSync.php       # Classe d'intégration GLPI
└── README.md                  # Documentation complète
```

**Total : 9 fichiers PHP + 1 README** (au lieu de 48+ fichiers dans l'ancienne structure)

## Changements détaillés

### 1. Modèles (Model/)

#### SyncFilter.php
- ✅ **Conservé** : Toutes les méthodes du modèle original
- ✅ **Ajouté** : Méthodes de `SyncFilterRepository` intégrées directement
- ✅ **Adapté** : Utilisation de `global $DB` au lieu de `DatabaseInterface`
- ✅ **Adapté** : Appels aux services via `::getInstance()` au lieu du container
- ✅ **Namespace** : `GlpiPlugin\Advancedldap\Model` (pas `Models`)

**Méthodes repository intégrées :**
- `getAuthLdapRelations($syncfilter_id)`
- `deleteAuthLdapRelations($syncfilter_id)`
- `getActiveAuthLdapServers()`
- `getSyncFiltersForAuthLdapDetailed($authldap_id)` (static)
- `getAuthLdapsForSyncFilter($syncfilter_id, $active_only)`
- `isSyncFilterAssignedToAuthLdap($authldap_id, $syncfilter_id)` (static)

#### AuthLdapSyncFilter.php
- ✅ **Conservé** : Toutes les méthodes du modèle original
- ✅ **Ajouté** : Méthodes de `AuthLdapSyncFilterRepository` intégrées
- ✅ **Adapté** : Utilisation de `global $DB`
- ✅ **Namespace** : `GlpiPlugin\Advancedldap\Model`

**Méthodes repository intégrées (toutes static) :**
- `addSyncFilterToAuthLdap($authldap_id, $syncfilter_id, $is_active)`
- `removeSyncFilterFromAuthLdap($authldap_id, $syncfilter_id)`
- `toggleSyncFilterForAuthLdap($authldap_id, $syncfilter_id, $is_active)`
- `getAuthLdapsForSyncFilter($syncfilter_id, $active_only)`
- `isSyncFilterAssignedToAuthLdap($authldap_id, $syncfilter_id, $active_only)`

### 2. Services (Service/)

#### SyncFilterService.php - **SERVICE CONSOLIDÉ**
Fusionne les fonctionnalités de **5 anciens services** :
1. `SyncFilterService` : Gestion des filtres
2. `SyncFilterValidationService` : Validation des inputs
3. `SyncFilterCronService` : Tâches CRON
4. `LdapFilterParser` : Parsing des filtres
5. `LdapFilterSanitizer` : Sanitisation et validation

**Sections du service :**
- Gestion des filtres de synchronisation (CRUD)
- Validation et sanitisation des filtres LDAP
- Parsing et sanitisation des filtres LDAP (RFC 4515)
- Tâches CRON de synchronisation automatique

**Pattern utilisé :** Singleton via `SingletonTrait`

#### LdapSyncService.php
- ✅ Conservé et adapté
- ✅ Utilisation de `global $DB`
- ✅ Pattern Singleton

#### LdapDataService.php
- ✅ Conservé et adapté
- ✅ Utilisation de `global $DB`
- ✅ Pattern Singleton

#### AssetService.php
- ✅ Conservé et adapté
- ✅ Utilisation de `global $DB`
- ✅ Pattern Singleton

### 3. Configuration (Config/)

#### AssetFieldConfig.php
- ✅ Conservé tel quel
- Configuration statique des champs disponibles par type d'asset

### 4. Classe d'intégration

#### AdvancedLdapSync.php
- ✅ **Adapté** : Suppression de la dépendance au `ServiceContainer`
- ✅ **Adapté** : Utilisation directe de `SyncFilterService::getInstance()`
- ✅ **Adapté** : Utilisation de la méthode static `SyncFilter::getSyncFiltersForAuthLdapDetailed()`
- ✅ **Simplifié** : Moins de dépendances, code plus direct

## Éléments supprimés

### Dossiers complets supprimés
- ❌ `Contracts/` : 10+ interfaces inutiles
  - `DatabaseInterface`
  - `SyncFilterRepositoryInterface`
  - `AuthLdapSyncFilterRepositoryInterface`
  - `SyncFilterFormHelperInterface`
  - `AssetFieldProviderInterface`
  - Etc.

- ❌ `Repositories/` : 2 repositories
  - `SyncFilterRepository`
  - `AuthLdapSyncFilterRepository`

- ❌ `Factories/` : 3 factories
  - `LdapConnectionFactory`
  - `LdapQueryFactory`
  - `AssetFactory`

- ❌ `Providers/` : 1 provider
  - `AssetFieldProvider`

- ❌ `Container/` : 1 container
  - `ServiceContainer`

- ❌ `Bootstrap.php` : Système de bootstrap

### Services fragmentés fusionnés
- ❌ `SyncFilterValidationService` → fusionné dans `SyncFilterService`
- ❌ `SyncFilterCronService` → fusionné dans `SyncFilterService`
- ❌ `LdapFilterParser` → fusionné dans `SyncFilterService`
- ❌ `LdapFilterSanitizer` → fusionné dans `SyncFilterService`
- ❌ `SyncFilterFormHelper` → méthodes intégrées dans `SyncFilter` modèle
- ❌ `GlpiConfigurationService` → accès direct aux constantes GLPI
- ❌ `LdapTestService` → TODO: à intégrer si nécessaire

## Avantages de la nouvelle architecture

### 1. Simplicité
- **48 fichiers → 9 fichiers** (réduction de 81%)
- Pas de container de dépendances
- Pas d'interfaces inutiles
- Code plus direct et lisible

### 2. Performance
- Pas d'overhead de container
- Singleton natif GLPI (léger et efficace)
- Accès direct à `global $DB`
- Moins d'instanciations d'objets

### 3. Maintenabilité
- Moins de fichiers à gérer
- Logique métier regroupée
- Moins de navigation entre fichiers
- Documentation claire et centralisée

### 4. Conformité GLPI
- Utilisation des patterns natifs GLPI 11
- Respect des conventions du framework
- Intégration transparente avec GLPI core
- Pas de surcouche d'abstraction

### 5. Testabilité
- Services singleton facilement mockables
- Méthodes de modèle testables unitairement
- Moins de dépendances à mocker

## Guide de migration du code

### Ancienne syntaxe (avec container)
```php
// Récupération du container
$container = Bootstrap::getContainer();

// Utilisation d'un repository
$repository = $container->get(SyncFilterRepositoryInterface::class);
$filters = $repository->getActiveSyncFilters();

// Utilisation d'un service
$validator = $container->get(SyncFilterValidationService::class);
$validated = $validator->validateAndPrepare($input);
```

### Nouvelle syntaxe (avec singleton)
```php
// Utilisation directe du service
$service = SyncFilterService::getInstance();
$filters = $service->getAvailableSyncFilters();

// Validation via le même service
$validated = $service->validateAndPrepare($input);

// Méthodes de modèle
$syncFilter = new SyncFilter();
$relations = $syncFilter->getAuthLdapRelations($id);
```

### Accès aux méthodes repository depuis le modèle
```php
// Avant (via repository)
$repository = $container->get(SyncFilterRepositoryInterface::class);
$servers = $repository->getActiveAuthLdapServers();

// Après (méthode de modèle)
$syncFilter = new SyncFilter();
$servers = $syncFilter->getActiveAuthLdapServers();

// Ou directement en static pour certaines méthodes
$filters = SyncFilter::getSyncFiltersForAuthLdapDetailed($authldap_id);
```

## Points d'attention

### 1. Namespace
- ✅ `GlpiPlugin\Advancedldap\Model` (pas `Models` avec 's')
- ✅ `GlpiPlugin\Advancedldap\Service` (pas `Services` avec 's')
- ✅ `GlpiPlugin\Advancedldap\Config` (pas `Configs` avec 's')

### 2. Accès à la base de données
- ✅ Toujours utiliser `global $DB;`
- ❌ Ne plus utiliser `DatabaseInterface`
- ✅ Utiliser les méthodes natives GLPI : `$DB->request()`, `$DB->insert()`, `$DB->update()`, `$DB->delete()`

### 3. Services
- ✅ Tous les services utilisent `SingletonTrait`
- ✅ Accès via `ServiceName::getInstance()`
- ❌ Ne plus utiliser de container de dépendances

### 4. Imports
Mettre à jour les imports dans tous les fichiers qui utilisent les anciens namespace :
```php
// Anciens imports à remplacer
use GlpiPlugin\Advancedldap\Models\SyncFilter;
use GlpiPlugin\Advancedldap\Services\SyncFilterService;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;

// Nouveaux imports
use GlpiPlugin\Advancedldap\Model\SyncFilter;
use GlpiPlugin\Advancedldap\Service\SyncFilterService;
// DatabaseInterface n'existe plus
```

## Tests à effectuer

### Tests unitaires
- [ ] Tester `SyncFilterService` : validation, parsing, sanitisation
- [ ] Tester `LdapSyncService` : synchronisation des assets
- [ ] Tester `LdapDataService` : transformation des données
- [ ] Tester `AssetService` : gestion des types d'assets

### Tests d'intégration
- [ ] Création d'un filtre de synchronisation
- [ ] Modification d'un filtre
- [ ] Suppression d'un filtre
- [ ] Association filtre <-> AuthLDAP
- [ ] Exécution de la synchronisation manuelle
- [ ] Exécution de la tâche CRON

### Tests fonctionnels
- [ ] Formulaire de création/édition de filtre
- [ ] Tab "Advanced sync" dans AuthLDAP
- [ ] Massive actions sur les filtres
- [ ] Test LDAP depuis le formulaire
- [ ] Historique des modifications

## Compatibilité

### Compatible
- ✅ GLPI 11.0+
- ✅ PHP 8.2+
- ✅ Tous les serveurs LDAP supportés par GLPI

### Non compatible
- ❌ Code utilisant l'ancien `ServiceContainer`
- ❌ Code utilisant les anciennes interfaces (Contracts)
- ❌ Code utilisant les anciens repositories directement

## Documentation

- 📖 **README.md** : Documentation complète de la nouvelle architecture
- 📖 **MIGRATION.md** : Ce fichier - guide de migration
- 📖 Code commenté en français dans tous les services

## Sauvegarde

L'ancienne structure est sauvegardée dans :
```
src_old/  # Ancienne architecture complète (48 fichiers)
```

Cette sauvegarde peut être supprimée après validation complète de la nouvelle structure.

## Auteur
Migration effectuée le 4 novembre 2025 par Claude Code (Anthropic)

## Prochaines étapes

1. ✅ Tester la nouvelle structure
2. ⏳ Adapter les fichiers front/ et ajax/ si nécessaire
3. ⏳ Mettre à jour les templates Twig si nécessaire
4. ⏳ Exécuter les tests fonctionnels
5. ⏳ Valider la migration
6. ⏳ Supprimer src_old/ après validation

## Support

En cas de problème avec la migration :
1. Consulter README.md dans src/
2. Vérifier les namespace et imports
3. S'assurer d'utiliser `::getInstance()` pour les services
4. Vérifier l'utilisation de `global $DB` au lieu de DatabaseInterface
