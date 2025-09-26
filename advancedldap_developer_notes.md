# Advanced LDAP Plugin - Notes Développeur

Ce document présente l'architecture technique du plugin Advanced LDAP et les bonnes pratiques pour le développer.

## Structure Complète du Plugin

```
📁 advancedldap/
  ├── 🎯 INTERFACE (front/)
  │   ├── config.form.php          # Configuration filtres LDAP
  │   ├── syncfilter.php           # Liste des filtres
  │   ├── syncfilter.form.php      # CRUD filtres
  │   └── ajax/getAssetFields.php  # Champs dynamiques
  │
  ├── 🧩 MODÈLES (src/Models/)
  │   ├── SyncFilter.php           # Filtre synchronisation + alias legacy
  │   └── AuthLdapSyncFilter.php   # Liaison AuthLDAP ↔ Filtres
  │
  ├── 🔧 SERVICES MÉTIER (src/Services/)
  │   ├── AssetFieldService.php          # Gestion champs d'assets
  │   ├── SyncFilterService.php          # Logique métier filtres
  │   ├── LdapTestService.php           # Tests & validation LDAP
  │   ├── AssetCreationService.php      # Création/MAJ assets GLPI
  │   ├── LdapSyncService.php           # Orchestration synchronisation
  │   ├── LdapInventoryService.php      # Workflow inventaire natif
  │   ├── AssetTypeClassifier.php       # Classification assets inventoriables
  │   ├── LdapToInventoryConverter.php  # Conversion LDAP → JSON inventaire
  │   ├── GlpiConfigurationService.php  # Wrapper config GLPI
  │   ├── GlpiDatabaseService.php       # Wrapper base données
  │   └── GlpiLdapConnectionService.php # Wrapper connexions LDAP
  │
  ├── 📦 REPOSITORIES (src/Repositories/)
  │   ├── SyncFilterRepository.php       # Accès données filtres
  │   └── AuthLdapSyncFilterRepository.php # Relations AuthLDAP/SyncFilter
  │
  ├── 🏭 FOURNISSEURS (src/Providers/)
  │   ├── NativeAssetFieldProvider.php   # Assets GLPI natifs
  │   └── GenericAssetFieldProvider.php  # Assets personnalisés
  │
  └── 🎨 TEMPLATES (templates/)
      ├── syncfilter_form.html.twig     # Formulaire CRUD
      └── syncfilters_list.html.twig    # Liste dans onglet AuthLDAP

```


## Composants Principaux

### **1. Classe Principale**
- `AdvancedLdapSync` : Contrôleur principal, gestion d'un onglet AuthLDAP pour la synchronisation

### **2. Services Métier**
- `AssetFieldService` : Gestion unifiée des champs d'assets
- `SyncFilterService` : Gestion métier des filtres de synchronisation
- `LdapTestService` : Tests et validation des filtres LDAP
- `LdapSyncService` : Orchestration complète de la synchronisation
- `AssetCreationService` : Création/mise à jour assets GLPI
- `LdapInventoryService` : Intégration avec système d'inventaire natif GLPI
- `AssetTypeClassifier` : Classification automatique assets inventoriables vs traditionnels
- `LdapToInventoryConverter` : Conversion données LDAP vers format JSON inventaire

### **3. Modèles de Données**
- `SyncFilter` : Modèle principal des filtres de synchronisation LDAP
- `AuthLdapSyncFilter` : Modèle de relation many-to-many AuthLDAP ↔ SyncFilter

### **4. Providers Spécialisés**
- `NativeAssetFieldProvider` : Assets natifs GLPI (Computer, Monitor, etc.)
- `GenericAssetFieldProvider` : Assets génériques personnalisés

### **5. Wrappers GLPI**
- `GlpiDatabaseService` : Abstraction de l'accès base de données
- `GlpiLdapConnectionService` : Abstraction des connexions LDAP
- `GlpiConfigurationService` : Abstraction de la configuration

### **6. Repositories (Pattern Repository)**
- `SyncFilterRepository` : Accès aux données des filtres de synchronisation
- `AuthLdapSyncFilterRepository` : Accès aux relations AuthLDAP/SyncFilter

### **7. Infrastructure**
- `ServiceContainer` : Conteneur d'injection de dépendances (SOLID)
- `Bootstrap` : Initialisation simplifiée des services
- `AssetFieldProviderFactory` : Création automatique des providers

## Points d'Extension

### Ajouter un Nouveau Type d'Asset

1. **Créer le provider** :
```php
class CustomAssetFieldProvider implements AssetFieldProviderInterface {
    public function getItemTypeFields(string $itemtype): array {
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

### **Configuration**
- `setup.php` : Déclaration du plugin, hooks, métadonnées
- `hook.php` : Installation, désinstallation, mise à jour

### **Interface Utilisateur**
- `front/config.form.php` : Configuration et test des filtres LDAP
- `front/syncfilter.php` : Liste des filtres (Search::show)
- `front/syncfilter.form.php` : CRUD complet des filtres
- `templates/` : Templates Twig pour l'affichage
- `ajax/getAssetFields.php` : Récupération dynamique des champs

### **Points d'Entrée**
- `Bootstrap::createAdvancedLdapSync()` : Création d'instance complète
- `ServiceContainer::getInstance()` : Accès direct aux services
- `AdvancedLdapSync::displayTabContentForItem()` : Affichage onglet principal synchronisation
- `AdvancedLdapSync::getTabNameForItem()` : Déclaration d'un seul onglet "Items to synchronize"

## Sécurité et Performance

### **Sécurité**
- Validation stricte des paramètres d'entrée
- Échappement HTML dans tous les templates
- Pas d'exposition des variables globales
- Gestion centralisée des erreurs avec logs

### **Performance**
- Services instanciés une seule fois (singleton)
- Chargement paresseux des dépendances
- Requêtes base de données optimisées
- Cache des métadonnées d'assets

### **Standards de Code**
- PHP 8.2+ avec types stricts
- PSR-12 pour le style de code
- Psalm et PHPStan pour l'analyse statique
- Tests unitaires pour chaque service
- Makefile avec commandes de développement

## Dépendances

### **GLPI Core Uniquement**
- Classes natives : `AuthLDAP`, `Config`, `CommonGLPI`, etc.
- Base de données : via `$DB` global encapsulé
- Configuration : via `$CFG_GLPI` global encapsulé
- Templates : Système Twig de GLPI

### **PHP 8.2+**
- Types de propriétés
- Promotion de constructeur
- Expressions match
- Attributs de méthode

Le plugin est **autonome** et ne nécessite aucune dépendance externe hormis GLPI.

## Architecture du Plugin
### **Nouvelles Tables de Données**

#### **glpi_plugin_advancedldap_syncfilters**
Stockage des filtres de synchronisation LDAP :
- `id` : Identifiant unique
- `name` : Nom du filtre  
- `ldap_filter` : Filtre LDAP (ex: (&(objectClass=device)(serialNumber=*)))
- `base_dn` : DN de base pour la recherche
- `asset_type` : Type d'asset GLPI ciblé
- `field_mappings` : Mappages champ LDAP → champ GLPI (JSON)
- `is_active` : Statut actif/inactif
- `date_creation`, `date_mod` : Horodatage

#### **glpi_plugin_advancedldap_authldap_syncfilters** 
Table de liaison many-to-many AuthLDAP ↔ SyncFilter :
- `id` : Identifiant unique
- `authldap_id` : Référence vers glpi_authldaps
- `syncfilter_id` : Référence vers les filtres
- `is_active` : Statut de la relation
- `date_creation` : Horodatage


## État Actuel du Plugin (Septembre 2025)

### **Audit de Code - Architecture Optimisée (26/09/2025)**
- ✅ **Doublons** : Aucun doublon problématique, méthodes partagées justifiées
- ✅ **Architecture hybride** : Support double workflow (traditionnel + inventaire)
- ✅ **Services spécialisés** : 11 services avec responsabilités distinctes
- ✅ **Standards respectés** : PHP 8.2+, PSR-12, typage strict, documentation PHPDoc
- ✅ **Logs GLPI** : Utilisation de `Toolbox::logDebug()` dans tous les services


### **Interface Utilisateur Fonctionnelle**
- ✅ **Onglet AuthLDAP** : "Items to synchronize" intégré dans `setup.php:60-62`
- ✅ **Liste des filtres** : Template `syncfilters_list.html.twig`
- ✅ **Formulaire CRUD** : `front/syncfilter.form.php` + template associé
- ✅ **Pages de liste** : `front/syncfilter.php` avec Search::show()
- ✅ **AJAX dynamique** : `ajax/getAssetFields.php` pour les champs d'assets
- ✅ **Test LDAP** : Fonctionnel via `LdapTestService`

### **Architecture Backend Complète & Opérationnelle**
- ✅ **Modèles** : `SyncFilter.php` et `AuthLdapSyncFilter.php`
- ✅ **CRUD complet** : extends CommonDBTM, actions de masse, droits utilisateurs
- ✅ **Repositories** : Accès aux données avec gestion d'erreurs et validation
- ✅ **Services métier** : 11 services spécialisés, logique applicative séparée
- ✅ **Injection de dépendances** : ServiceContainer complet
- ✅ **Synchronisation hybride** : Support traditionnel + inventaire natif GLPI
- ✅ **Legacy compatibility** : Alias de classe intégré dans `src/Models/SyncFilter.php` pour GLPI 11 Search

### **Fonctionnalités Avancées (Nouveau)**
- ✅ **Synchronisation intelligente** : Classification automatique assets inventoriables
- ✅ **Double workflow** : Support traditionnel (CommonDBTM) + inventaire natif (Inventory.php)
- ✅ **Conversion LDAP→JSON** : Transformation automatique pour système d'inventaire
- ✅ **Gestion des erreurs** : Validation et logs centralisés dans tous les services
- ✅ **Architecture extensible** : Factory pattern pour nouveaux types d'assets

## Outils de Développement

### **Données de Test**
- `dummy_data.ldif` : Données LDAP pour les tests d'intégration
- `test_data.sql` : Données SQL pour les tests unitaires
- `tests/bootstrap.php` : Configuration de l'environnement de test