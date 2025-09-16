# Advanced LDAP Plugin - Notes Développeur

Ce document présente l'architecture technique du plugin Advanced LDAP et les bonnes pratiques pour le développer.

## Structure Complète du Plugin

```
plugins/advancedldap/
├── 📁 ajax/                           # Requêtes AJAX
│   └── getAssetFields.php             # Récupération dynamique des champs d'assets
├── 📁 front/                          # Pages d'interface utilisateur (CRUD)
│   ├── config.form.php                # Configuration du plugin
│   ├── plugin_advancedldap.form.php   # Page principale du plugin
│   ├── syncfilter.php                 # Liste des filtres de synchronisation
│   └── syncfilter.form.php            # Formulaire CRUD pour les filtres
├── 📁 src/                            # Code source principal (architecture SOLID)
│   ├── 📁 Container/                  # Conteneur d'injection de dépendances
│   │   └── ServiceContainer.php       # Gestionnaire des services (7.3KB)
│   ├── 📁 Contracts/                  # Interfaces (abstractions)
│   │   ├── AssetFieldProviderInterface.php         # Contrat pour les fournisseurs de champs
│   │   ├── AuthLdapSyncFilterRepositoryInterface.php # Contrat pour les relations
│   │   ├── ConfigurationInterface.php              # Contrat pour la configuration
│   │   ├── DatabaseInterface.php                   # Contrat pour l'accès base de données
│   │   ├── LdapConnectionInterface.php             # Contrat pour les connexions LDAP
│   │   └── SyncFilterRepositoryInterface.php       # Contrat pour les filtres
│   ├── 📁 Factories/                  # Pattern Factory
│   │   └── AssetFieldProviderFactory.php     # Création des providers d'assets
│   ├── 📁 Models/                     # Modèles métier (CommonDBTM)
│   │   ├── SyncFilter.php             # Modèle principal des filtres (18KB) + legacy alias
│   │   └── AuthLdapSyncFilter.php     # Relations many-to-many AuthLDAP ↔ SyncFilter
│   ├── 📁 Providers/                  # Fournisseurs spécialisés
│   │   ├── GenericAssetFieldProvider.php     # Champs des assets génériques
│   │   └── NativeAssetFieldProvider.php      # Champs des assets natifs GLPI
│   ├── 📁 Repositories/               # Pattern Repository (accès aux données)
│   │   ├── AuthLdapSyncFilterRepository.php  # Relations AuthLDAP/SyncFilter
│   │   └── SyncFilterRepository.php          # Données des filtres de synchronisation
│   ├── 📁 Services/                   # Services métier
│   │   ├── AssetFieldService.php             # Service principal des champs d'assets (6.8KB)
│   │   ├── GlpiConfigurationService.php      # Wrapper configuration GLPI
│   │   ├── GlpiDatabaseService.php           # Wrapper base de données GLPI
│   │   ├── GlpiLdapConnectionService.php     # Wrapper connexions LDAP GLPI
│   │   ├── LdapTestService.php               # Service de test des filtres LDAP (12.3KB)
│   │   └── SyncFilterService.php             # Service métier des filtres de synchronisation (7.2KB)
│   ├── AdvancedLdapSync.php           # Classe principale du plugin (10.7KB)
│   └── Bootstrap.php                  # Point d'entrée et initialisation (2.9KB)
├── 📁 templates/                      # Templates Twig (interface)
│   ├── ldap_sync.html.twig            # Template principal de synchronisation (legacy)
│   ├── syncfilter_form.html.twig      # Formulaire de création/édition de filtres
│   └── syncfilters_list.html.twig     # Liste des filtres dans l'onglet AuthLDAP
├── 📁 tests/                          # Tests (structure de base)
│   └── bootstrap.php                  # Configuration des tests
├── 📁 tools/                          # Outils de développement
├── 📁 var/                            # Cache et fichiers temporaires
│   └── php-cs-fixer/                  # Cache du formateur de code
├── 📄 setup.php                       # Configuration et hooks du plugin
├── 📄 hook.php                        # Fonctions d'installation/désinstallation
├── 📄 composer.json                   # Dépendances et autoloading (PHP 8.2+)
├── 📄 advancedldap.xml               # Métadonnées du plugin
├── 📄 CLAUDE.md                       # Instructions de collaboration IA
├── 📄 advancedldap_developer_notes.md # Documentation technique (ce fichier)
├── 📄 ldap-notes.md                   # Documentation technique LDAP
├── 📄 .php-cs-fixer.php              # Configuration style de code (PSR-12)
├── 📄 psalm.xml                       # Configuration analyse statique
├── 📄 phpunit.xml                     # Configuration tests unitaires
├── 📄 .gitignore                      # Exclusions Git
└── 📄 README.md                       # Documentation utilisateur
```


## Composants Principaux

### **1. Classe Principale**
- `AdvancedLdapSync` : Contrôleur principal, gestion d'un onglet AuthLDAP pour la synchronisation

### **2. Services Métier**
- `AssetFieldService` : Gestion unifiée des champs d'assets
- `LdapTestService` : Tests et validation des filtres LDAP
- `SyncFilterService` : Gestion métier des filtres de synchronisation LDAP

### **3. Modèles de Données (SOLID)**
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
- `front/config.form.php` : Page de configuration
- `templates/` : Templates Twig pour l'affichage
- `ajax/getAssetFields.php` : Requêtes dynamiques

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
- PHP 8.0+ avec types stricts
- PSR-12 pour le style de code
- PHPStan niveau 5+ pour l'analyse statique
- Tests unitaires pour chaque service

## Dépendances

### **GLPI Core Uniquement**
- Classes natives : `AuthLDAP`, `Config`, `CommonGLPI`, etc.
- Base de données : via `$DB` global encapsulé
- Configuration : via `$CFG_GLPI` global encapsulé
- Templates : Système Twig de GLPI

### **PHP 8.0+**
- Types de propriétés
- Promotion de constructeur
- Expressions match
- Attributs de méthode

Le plugin est **autonome** et ne nécessite aucune dépendance externe hormis GLPI.

## Architecture SOLID (Version 2.0)

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

### **Respect des Principes SOLID**

#### **S - Single Responsibility**
- `SyncFilter` : Modèle de données uniquement
- `SyncFilterRepository` : Accès aux données uniquement  
- `SyncFilterService` : Logique métier uniquement

#### **O - Open/Closed**
- Extension via interfaces sans modification du code existant
- Nouveaux providers d'assets facilement ajoutables

#### **L - Liskov Substitution**
- Toutes les implémentations respectent leurs contrats d'interface

#### **I - Interface Segregation** 
- `SyncFilterRepositoryInterface` : Uniquement les opérations de données
- `AuthLdapSyncFilterRepositoryInterface` : Uniquement les relations

#### **D - Dependency Inversion**
- Services dépendent d'interfaces, pas d'implémentations concrètes
- Injection via ServiceContainer

### **Avantages de l'Architecture**

1. **Testabilité** : Injection de dépendances facilite les mocks
2. **Maintenabilité** : Séparation claire des responsabilités
3. **Évolutivité** : Nouveaux filtres/relations sans casse
4. **Performance** : Pattern Repository optimise les requêtes DB
5. **Sécurité** : Validation centralisée dans les services métier

## État Actuel du Plugin (Septembre 2025)

### **Interface Utilisateur Fonctionnelle**
- ✅ **Onglet AuthLDAP** : "Items to synchronize" intégré dans `setup.php:60-62`
- ✅ **Liste des filtres** : Template `syncfilters_list.html.twig` (7.3KB)
- ✅ **Formulaire CRUD** : `front/syncfilter.form.php` + template associé (12.5KB)
- ✅ **Pages de liste** : `front/syncfilter.php` avec Search::show()
- ✅ **AJAX dynamique** : `ajax/getAssetFields.php` pour les champs d'assets
- ✅ **Test LDAP** : Fonctionnel via `LdapTestService` (12.3KB)

### **Architecture Backend Complète & Opérationnelle**
- ✅ **Modèles SOLID** : `SyncFilter.php` (18KB) et `AuthLdapSyncFilter.php` (4.6KB)
- ✅ **CRUD complet** : extends CommonDBTM, actions de masse, droits utilisateurs
- ✅ **Repositories** : Accès aux données avec gestion d'erreurs et validation
- ✅ **Services métier** : 6 services spécialisés, logique applicative séparée
- ✅ **Injection de dépendances** : ServiceContainer complet (7.3KB)
- ✅ **Base de données** : Tables créées, relations many-to-many
- ✅ **Legacy compatibility** : Alias de classe intégré dans `src/Models/SyncFilter.php` pour GLPI 11 Search

### **Code Mort & Refactoring Récent**
- ✅ **Nettoyage effectué** : Suppression des classes obsolètes (AssetFieldManager, LdapTester)
- ✅ **Migration Models/** : SyncFilter et AuthLdapSyncFilter déplacés dans Models/
- ✅ **Architecture cohérente** : Aucune référence orpheline détectée
- ✅ **Standards respectés** : PHP 8.2+, PSR-12, typage strict, commentaires anglais

### **Prêt pour Production**
L'architecture actuelle permet :
- ✅ **Synchronisation LDAP** : Filtres configurables par AuthLDAP
- ✅ **Gestion des assets** : Native + Generic via providers
- ✅ **Tests et validation** : LdapTestService opérationnel
- ✅ **Interface complète** : CRUD, liste, test, relations
- ✅ **Maintenabilité** : SOLID, services découplés, injection de dépendances

### **Extensions Possibles**
- **Automatisation** : Intégration avec le système de cron GLPI
- **Monitoring** : Logs détaillés des synchronisations
- **Administration** : Import/export des configurations
- **Performance** : Cache des résultats LDAP
- **Sécurité** : Audit trail des modifications