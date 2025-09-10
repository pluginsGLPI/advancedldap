# Advanced LDAP Plugin - Notes Développeur

Ce document présente l'architecture technique du plugin Advanced LDAP et les bonnes pratiques pour le développer.

## Structure Complète du Plugin

```
plugins/advancedldap/
├── 📁 ajax/                           # Requêtes AJAX
│   └── getAssetFields.php             # Récupération dynamique des champs d'assets
├── 📁 front/                          # Pages d'interface utilisateur
│   └── config.form.php                # Configuration du plugin
├── 📁 src/                            # Code source principal (architecture SOLID)
│   ├── 📁 Container/                  # Conteneur d'injection de dépendances
│   │   └── ServiceContainer.php       # Gestionnaire des services
│   ├── 📁 Contracts/                  # Interfaces (abstractions)
│   │   ├── AssetFieldProviderInterface.php    # Contrat pour les fournisseurs de champs
│   │   ├── ConfigurationInterface.php         # Contrat pour la configuration
│   │   ├── DatabaseInterface.php              # Contrat pour l'accès base de données
│   │   └── LdapConnectionInterface.php        # Contrat pour les connexions LDAP
│   ├── 📁 Factories/                  # Pattern Factory
│   │   └── AssetFieldProviderFactory.php     # Création des providers d'assets
│   ├── 📁 Providers/                  # Fournisseurs spécialisés
│   │   ├── GenericAssetFieldProvider.php     # Champs des assets génériques
│   │   └── NativeAssetFieldProvider.php      # Champs des assets natifs GLPI
│   ├── 📁 Services/                   # Services métier
│   │   ├── AssetFieldService.php             # Service principal des champs d'assets
│   │   ├── GlpiConfigurationService.php      # Wrapper configuration GLPI
│   │   ├── GlpiDatabaseService.php           # Wrapper base de données GLPI
│   │   ├── GlpiLdapConnectionService.php     # Wrapper connexions LDAP GLPI
│   │   └── LdapTestService.php               # Service de test des filtres LDAP
│   ├── AdvancedLdapSync.php           # Classe principale du plugin
│   └── Bootstrap.php                  # Point d'entrée et initialisation
├── 📁 templates/                      # Templates Twig (interface)
├── 📁 tests/                          # Tests (structure de base)
│   └── bootstrap.php                  # Configuration des tests
├── 📁 tools/                          # Outils de développement
├── 📁 var/                            # Cache et fichiers temporaires
├── 📄 setup.php                       # Configuration et hooks du plugin
├── 📄 hook.php                        # Fonctions d'installation/désinstallation
├── 📄 composer.json                   # Dépendances et autoloading
├── 📄 advancedldap.xml               # Métadonnées du plugin
├── 📄 CLAUDE.md                       # Notes de développement
├── 📄 ldap-notes.md                   # Documentation technique LDAP
├── 📄 dummy_data.ldif                # Données de test LDAP
├── 📄 README.md                       # Documentation utilisateur
├── 📄 LICENSE                         # Licence MIT
├── 📄 .php-cs-fixer.php              # Configuration style de code
├── 📄 phpstan.neon                    # Configuration analyse statique
├── 📄 phpunit.xml                     # Configuration tests unitaires
└── 📄 Makefile                        # Commandes de développement
```


## Composants Principaux

### **1. Classe Principale**
- `AdvancedLdapSync` : Contrôleur principal, gestion des onglets AuthLDAP

### **2. Services Métier**
- `AssetFieldService` : Gestion unifiée des champs d'assets
- `LdapTestService` : Tests et validation des filtres LDAP

### **3. Providers Spécialisés**
- `NativeAssetFieldProvider` : Assets natifs GLPI (Computer, Monitor, etc.)
- `GenericAssetFieldProvider` : Assets génériques personnalisés

### **4. Wrappers GLPI**
- `GlpiDatabaseService` : Abstraction de l'accès base de données
- `GlpiLdapConnectionService` : Abstraction des connexions LDAP
- `GlpiConfigurationService` : Abstraction de la configuration

### **5. Infrastructure**
- `ServiceContainer` : Conteneur d'injection de dépendances
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
- `AdvancedLdapSync::displayTabContentForItem()` : Affichage onglet

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