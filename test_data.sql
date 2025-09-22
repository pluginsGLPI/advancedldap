-- -------------------------------------------------------------------------
-- Complete Development Test Data for Advanced LDAP Plugin
-- -------------------------------------------------------------------------
-- This script creates a complete test environment with:
-- - LDAP directories (AuthLDAP)
-- - Sync filters with various asset types
-- - Relations between LDAP directories and sync filters
-- -------------------------------------------------------------------------

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- =========================================================================
-- 1. LDAP DIRECTORIES (glpi_authldaps)
-- =========================================================================

-- Insert test LDAP directories
INSERT INTO `glpi_authldaps` (
    `name`, 
    `host`, 
    `basedn`, 
    `rootdn`, 
    `port`, 
    `condition`, 
    `login_field`, 
    `sync_field`, 
    `use_tls`, 
    `group_field`, 
    `group_condition`, 
    `group_search_type`, 
    `group_member_field`, 
    `email1_field`, 
    `realname_field`, 
    `firstname_field`, 
    `phone_field`, 
    `phone2_field`, 
    `mobile_field`, 
    `comment_field`, 
    `use_dn`, 
    `time_offset`, 
    `deref_option`, 
    `title_field`, 
    `category_field`, 
    `language_field`, 
    `date_mod`, 
    `comment`, 
    `is_default`, 
    `is_active`, 
    `rootdn_passwd`, 
    `registration_number_field`, 
    `email2_field`, 
    `email3_field`, 
    `email4_field`, 
    `location_field`, 
    `responsible_field`, 
    `pagesize`, 
    `ldap_maxlimit`, 
    `can_support_pagesize`, 
    `picture_field`, 
    `begin_date_field`, 
    `end_date_field`, 
    `date_creation`, 
    `inventory_domain`, 
    `tls_certfile`, 
    `tls_keyfile`, 
    `use_bind`, 
    `timeout`, 
    `tls_version`
) VALUES 
-- Teclib LDAP Directory
(
    'Teclib LDAP - Main Directory',
    '172.17.0.1',
    'dc=teclib,dc=labo',
    'cn=admin,dc=teclib,dc=labo',
    389,
    '(objectClass=inetOrgPerson)',
    'uid',
    'entryuuid',
    0,
    'memberOf',
    '(objectClass=posixGroup)',
    0,
    'memberUid',
    'mail',
    'cn',
    'givenName',
    'telephoneNumber',
    'homePhone',
    'mobile',
    'description',
    0,
    0,
    0,
    'title',
    'departmentNumber',
    'preferredLanguage',
    NOW(),
    'Teclib LDAP directory for user authentication and asset management',
    0,
    1,
    'admin123',
    'employeeNumber',
    'mailAlternateAddress',
    '',
    '',
    'l',
    'manager',
    100,
    0,
    1,
    'jpegPhoto',
    'shadowExpire',
    'shadowMax',
    NOW(),
    'teclib.labo',
    NULL,
    NULL,
    1,
    15,
    NULL
),
-- Teclib Assets Directory
(
    'Teclib LDAP - Assets & Equipment',
    '172.17.0.1',
    'dc=teclib,dc=labo',
    'cn=admin,dc=teclib,dc=labo',
    389,
    '(|(objectClass=device)(objectClass=ipHost))',
    'cn',
    'entryuuid',
    0,
    'memberOf',
    '(objectClass=organizationalUnit)',
    0,
    'member',
    'mail',
    'cn',
    'givenName',
    'telephoneNumber',
    '',
    'mobile',
    'description',
    0,
    0,
    0,
    'title',
    'ou',
    'preferredLanguage',
    NOW(),
    'Teclib specialized directory for asset management and technical equipment',
    0,
    1,
    'admin123',
    'serialNumber',
    '',
    '',
    '',
    'l',
    'seeAlso',
    50,
    0,
    1,
    '',
    '',
    '',
    NOW(),
    'teclib.labo',
    NULL,
    NULL,
    1,
    20,
    NULL
);

-- =========================================================================
-- 2. SYNC FILTERS (glpi_plugin_advancedldap_syncfilters)
-- =========================================================================

-- Clear existing test filters and insert comprehensive test data
DELETE FROM `glpi_plugin_advancedldap_syncfilters` WHERE id >= 1;

INSERT INTO `glpi_plugin_advancedldap_syncfilters` (
    `id`,
    `name`, 
    `ldap_filter`, 
    `base_dn`, 
    `asset_type`, 
    `field_mappings`, 
    `is_active`, 
    `date_creation`, 
    `date_mod`
) VALUES 
-- Computer filters
(1, 'Computers - All Workstations & Laptops',
 '(&(objectClass=device)(serialNumber=*)(|(cn=PC-*)(cn=LAPTOP-*)))',
 'ou=Computers,dc=teclib,dc=labo',
 'Computer',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

(2, 'Computers - Desktop Workstations Only',
 '(&(objectClass=device)(serialNumber=*)(cn=PC-*)(description=*workstation))',
 'ou=Computers,dc=teclib,dc=labo',
 'Computer',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

(3, 'Computers - CEO Laptop Only',
 '(&(objectClass=device)(serialNumber=*)(cn=LAPTOP-*)(description=*laptop))',
 'ou=Computers,dc=teclib,dc=labo',
 'Computer',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Printer filters
(4, 'Printers - All Network Printers',
 '(&(objectClass=device)(cn=PRINTER-*)(serialNumber=*))',
 'ou=Printers,dc=teclib,dc=labo',
 'Printer',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

(5, 'Printers - HP LaserJet Printers',
 '(&(objectClass=device)(cn=PRINTER-HP-*)(serialNumber=*)(description=*LaserJet*))',
 'ou=Printers,dc=teclib,dc=labo',
 'Printer',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Equipment filters (Network & Storage)
(6, 'Equipment - Network Switches',
 '(&(objectClass=device)(cn=SWITCH-*)(serialNumber=*)(description=*Switch*))',
 'ou=Equipment,dc=teclib,dc=labo',
 'NetworkEquipment',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

(7, 'Equipment - Storage Devices (NAS)',
 '(&(objectClass=device)(cn=NAS-*)(serialNumber=*)(description=*Storage*))',
 'ou=Equipment,dc=teclib,dc=labo',
 'NetworkEquipment',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Server filters
(8, 'Servers - All Production Servers',
 '(&(objectClass=device)(cn=SERVER-*)(serialNumber=*)(description=*Server))',
 'ou=Servers,dc=teclib,dc=labo',
 'Computer',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Network equipment filters
(9, 'Network Equipment - Routers',
 '(&(objectClass=device)(cn=ROUTER-*)(serialNumber=*)(description=*Router*))',
 'ou=Equipment,dc=teclib,dc=labo',
 'NetworkEquipment',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

(10, 'Network Equipment - UPS Systems',
 '(&(objectClass=device)(cn=UPS-*)(serialNumber=*)(description=*UPS*))',
 'ou=Equipment,dc=teclib,dc=labo',
 'NetworkEquipment',
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- People filters for testing
(11, 'People - All Users',
 '(&(objectClass=inetOrgPerson)(uid=*)(mail=*))',
 'ou=People,dc=teclib,dc=labo',
 'User',
 '{"name": "cn", "firstname": "givenName", "realname": "sn", "emails": "mail", "phone": "telephoneNumber", "mobile": "mobile", "locations_id": "l"}',
 1, NOW(), NOW()),

(12, 'People - Managers Only',
 '(&(objectClass=inetOrgPerson)(uid=*)(title=*Manager*))',
 'ou=People,dc=teclib,dc=labo',
 'User',
 '{"name": "cn", "firstname": "givenName", "realname": "sn", "emails": "mail", "phone": "telephoneNumber", "mobile": "mobile", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Inactive filter for testing
(13, 'INACTIVE - Old Equipment Filter',
 '(&(objectClass=device)(cn=OLD*)(serialNumber=*))',
 'ou=Retired,dc=teclib,dc=labo',
 'Computer',
 '{"name": "cn", "serial": "serialNumber", "comment": "description"}',
 0, NOW(), NOW());

-- =========================================================================
-- 3. LDAP-SYNCFILTER RELATIONS (glpi_plugin_advancedldap_authldap_syncfilters)
-- =========================================================================

-- Clear existing relations and create test associations
DELETE FROM `glpi_plugin_advancedldap_authldap_syncfilters` WHERE id >= 1;

-- Get the IDs of the newly created LDAP directories
-- Assuming the Teclib Main directory will get the next available ID
-- and Teclib Assets directory will get the following ID
SET @teclib_main_ldap_id = (SELECT MAX(id)-1 FROM `glpi_authldaps`);
SET @teclib_assets_ldap_id = (SELECT MAX(id) FROM `glpi_authldaps`);

INSERT INTO `glpi_plugin_advancedldap_authldap_syncfilters` (
    `id`,
    `authldap_id`,
    `syncfilter_id`,
    `is_active`,
    `date_creation`
) VALUES
-- Teclib Main LDAP associations (People & Users)
(1, @teclib_main_ldap_id, 11, 1, NOW()),  -- People - All Users
(2, @teclib_main_ldap_id, 12, 1, NOW()),  -- People - Administrators

-- Teclib Assets LDAP associations (Equipment & Devices)
(3, @teclib_assets_ldap_id, 1, 1, NOW()),   -- Computers - All
(4, @teclib_assets_ldap_id, 2, 1, NOW()),   -- Computers - Desktop
(5, @teclib_assets_ldap_id, 3, 1, NOW()),   -- Computers - Laptops
(6, @teclib_assets_ldap_id, 4, 1, NOW()),   -- Printers - All
(7, @teclib_assets_ldap_id, 5, 1, NOW()),   -- Printers - HP LaserJet
(8, @teclib_assets_ldap_id, 6, 1, NOW()),   -- Equipment - Switches
(9, @teclib_assets_ldap_id, 7, 1, NOW()),   -- Equipment - NAS Storage
(10, @teclib_assets_ldap_id, 8, 1, NOW()),  -- Servers - All Production
(11, @teclib_assets_ldap_id, 9, 1, NOW()),  -- Network - Routers
(12, @teclib_assets_ldap_id, 10, 1, NOW()), -- Network - UPS Systems

-- Inactive relation for testing
(13, @teclib_assets_ldap_id, 13, 0, NOW()); -- INACTIVE filter

-- =========================================================================
-- SUMMARY
-- =========================================================================
-- This script creates:
-- • 2 LDAP directories (Teclib Main + Teclib Assets)
-- • 13 sync filters covering various asset types from Teclib LDAP
-- • 13 relations between LDAP directories and sync filters
-- • Filters aligned with actual data in /home/f2cambourg/Developer/ldaps-docker/init.ldif
-- • Mix of active/inactive items for comprehensive testing
-- =========================================================================

SET foreign_key_checks = 1;