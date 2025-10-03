# Tests d'Intégration - Plugin Advanced LDAP

Ce document décrit les tests d'intégration manuels pour les fonctionnalités qui ne peuvent pas être testées unitairement en raison de contraintes techniques (logs GLPI, dépendances système, etc.).

## LdapInventoryService - Tests d'Intégration

### Contexte
La méthode `syncInventoriableAsset()` ne peut pas être testée unitairement car :
- Elle génère des logs via `Toolbox::logDebug()`
- Le framework de test GLPI rejette les "unexpected log entries"
- Elle dépend du système d'inventaire natif GLPI

### Test 1: Détection d'échec silencieux de création (assetId ≤ 0)

**Objectif** : Vérifier que le service détecte quand l'inventaire GLPI échoue silencieusement

**Prérequis** :
- Serveur LDAP de test configuré
- AuthLDAP actif dans GLPI
- SyncFilter configuré avec field mappings incomplets

**Procédure** :
1. Créer un SyncFilter pour Computer avec UNIQUEMENT le mapping `serial → serialNumber` (sans `name`)
2. Exécuter la synchronisation depuis l'interface GLPI
3. Observer les logs : `/files/_log/php-errors.log`

**Résultat attendu** :
- Message d'erreur : `"Inventory system could not create/update asset. Insufficient field mappings. For Computer, you need at least: Name (required for identification)"`
- Aucun asset créé dans GLPI
- Le champ `serial` est présent dans les données LDAP mais l'asset n'est pas créé

**Critères de validation** :
- ✅ Message d'erreur explicite affiché
- ✅ Mention des champs requis (Name)
- ✅ Aucun asset avec ID invalide créé

---

### Test 2: Respect des Field Mappings

**Objectif** : Vérifier que seuls les champs configurés dans field_mappings sont synchronisés

**Prérequis** :
- Serveur LDAP avec entrées complètes (cn, serialNumber, model, manufacturer, ipAddress)
- SyncFilter avec field_mappings = `['name' => 'cn', 'serial' => 'serialnumber']`

**Procédure** :
1. Créer un SyncFilter pour Computer avec le mapping ci-dessus
2. Exécuter la synchronisation
3. Vérifier l'asset créé dans GLPI

**Résultat attendu** :
- Asset créé avec succès
- Champs présents :
  - ✅ Name (de `cn`)
  - ✅ Serial number (de `serialnumber`)
- Champs ABSENTS (non mappés) :
  - ❌ Model (de `model`)
  - ❌ Manufacturer (de `manufacturer`)
  - ❌ IP Address (de `ipaddress`)

**Critères de validation** :
- ✅ Asset créé avec ID > 0
- ✅ Seuls les champs mappés sont remplis
- ✅ Les autres champs LDAP sont ignorés

---

### Test 3: Computer - Exigences minimales

**Objectif** : Vérifier les exigences minimales pour la création d'un Computer

**Prérequis** :
- Entrée LDAP avec attributs : `cn`, `serialNumber`, `model`

**Procédure** :
1. Créer SyncFilter avec mapping `name → cn` (minimum requis)
2. Synchroniser

**Résultat attendu** :
- ✅ Asset Computer créé avec succès
- ✅ Champ Name rempli
- ✅ Message : "Successfully created/updated Computer"

**Test d'échec** :
1. Créer SyncFilter SANS le mapping `name`
2. Synchroniser

**Résultat attendu** :
- ❌ Échec de création
- ❌ Message : "For Computer, you need at least: Name (required for identification)"

---

### Test 4: NetworkEquipment - Exigences minimales

**Objectif** : Vérifier les exigences minimales pour NetworkEquipment

**Prérequis** :
- Entrée LDAP avec : `cn`, `serialNumber`, `macAddress`

**Procédure** :
1. Créer SyncFilter avec mappings :
   - Cas 1: `name → cn` + `serial → serialNumber`
   - Cas 2: `name → cn` + `macaddress → macAddress`
2. Synchroniser chaque cas

**Résultat attendu** :
- ✅ Cas 1 : Asset créé avec Name + Serial
- ✅ Cas 2 : Asset créé avec Name + MAC Address
- ✅ Message : "Successfully created/updated NetworkEquipment"

**Test d'échec** :
1. Créer SyncFilter avec SEULEMENT `name → cn` (sans serial ni MAC)
2. Synchroniser

**Résultat attendu** :
- ❌ Échec de création
- ❌ Message : "For NetworkEquipment, you need at least: Name + Serial Number OR MAC Address"

---

### Test 5: Phone - Exigences minimales

**Objectif** : Vérifier les exigences minimales pour Phone

**Prérequis** :
- Entrée LDAP avec : `cn`, `serialNumber`

**Procédure** :
1. Créer SyncFilter avec mappings `name → cn` + `serial → serialNumber`
2. Synchroniser

**Résultat attendu** :
- ✅ Asset Phone créé avec succès
- ✅ Name et Serial remplis
- ✅ Message : "Successfully created/updated Phone"

**Test d'échec** :
1. Créer SyncFilter avec SEULEMENT `name → cn`
2. Synchroniser

**Résultat attendu** :
- ⚠️ Asset créé MAIS message d'avertissement
- ⚠️ Message : "For Phone, you need at least: Name + Serial Number (recommended for unique identification)"

---

### Test 6: Printer - Exigences minimales

**Objectif** : Vérifier les exigences minimales pour Printer

**Prérequis** :
- Entrée LDAP avec : `cn`, `driver`

**Procédure** :
1. Créer SyncFilter avec mapping `name → cn`
2. Synchroniser

**Résultat attendu** :
- ✅ Asset Printer créé avec succès
- ✅ Champ Name rempli
- ✅ Message : "Successfully created/updated Printer"

---

## Procédure Générale de Test

### Environnement de test recommandé

1. **Serveur LDAP de test** :
   ```bash
   docker run -d -p 389:389 -p 636:636 \
     --name openldap-test \
     --env LDAP_ADMIN_PASSWORD=admin \
     osixia/openldap:latest
   ```

2. **GLPI de test** :
   - Version : 11.0+
   - Plugin advancedldap installé et activé
   - AuthLDAP configuré pointant vers le serveur de test

3. **Données de test** :
   - Créer des entrées LDAP avec différentes combinaisons d'attributs
   - Utiliser `ldapadd` pour injecter les données

### Vérification des logs

**Emplacement** : `/var/www/html/glpi/files/_log/php-errors.log`

**Commande** :
```bash
tail -f /var/www/html/glpi/files/_log/php-errors.log | grep "advancedldap"
```

### Indicateurs de succès globaux

| Test | Critère | Statut |
|------|---------|--------|
| Échec silencieux | Message d'erreur explicite | ⬜ |
| Field mappings | Seuls champs mappés présents | ⬜ |
| Computer min | Name requis | ⬜ |
| NetworkEquipment min | Name + (Serial OU MAC) | ⬜ |
| Phone min | Name + Serial recommandé | ⬜ |
| Printer min | Name requis | ⬜ |

---

## Automatisation Future

Ces tests pourraient être automatisés via :
- **Behat** : Tests d'acceptation BDD
- **Codeception** : Tests fonctionnels avec base de données
- **Scripts shell** : Tests CLI avec ldapsearch/ldapadd

Exemple de script d'automatisation :
```bash
#!/bin/bash
# test_inventory_service.sh

# 1. Setup LDAP test entry
ldapadd -x -D "cn=admin,dc=test,dc=com" -w admin << EOF
dn: cn=test-pc,ou=computers,dc=test,dc=com
objectClass: device
cn: test-pc
serialNumber: SN12345
EOF

# 2. Trigger sync via GLPI CLI
php bin/console glpi:plugin:advancedldap:sync --filter-id=1

# 3. Check result
ASSET_ID=$(mysql -u glpi -pglpi glpi -e "SELECT id FROM glpi_computers WHERE name='test-pc'" -s)

if [ "$ASSET_ID" -gt 0 ]; then
    echo "✅ Test passed: Asset created with ID $ASSET_ID"
else
    echo "❌ Test failed: Asset not created"
fi
```

---

## Notes

- Ces tests doivent être exécutés après chaque modification de `LdapInventoryService`
- Les résultats doivent être documentés dans un rapport de test
- En cas d'échec, vérifier les logs GLPI pour identifier la cause
