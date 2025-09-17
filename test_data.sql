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
-- University Main Directory
(
    'University LDAP - Main Directory',
    '172.17.0.1',
    'dc=univ,dc=example,dc=edu',
    'cn=admin,dc=univ,dc=example,dc=edu',
    389,
    '(objectClass=inetOrgPerson)',
    'uid',
    'entryuuid',
    0,
    'memberOf',
    '(objectClass=groupOfNames)',
    0,
    'member',
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
    'Main university directory for user authentication and device inventory',
    0,
    1,
    'admin',
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
    'univ.example.edu',
    NULL,
    NULL,
    1,
    15,
    NULL
),
-- IT Department Directory  
(
    'IT Department LDAP - Assets & Equipment',
    '172.17.0.1',
    'dc=it,dc=univ,dc=example,dc=edu',
    'cn=admin,dc=it,dc=univ,dc=example,dc=edu',
    389,
    '(|(objectClass=device)(objectClass=inetOrgPerson))',
    'uid',
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
    'IT department specialized directory for asset management and technical equipment',
    0,
    1,
    'admin',
    'serialNumber',
    '',
    '',
    '',
    'l',
    'owner',
    50,
    0,
    1,
    '',
    '',
    '',
    NOW(),
    'it.univ.example.edu',
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
(1, 'Computers - All Desktop & Laptops', 
 '(&(objectClass=device)(serialNumber=*)(cn=COMP*))', 
 'ou=Computers,dc=univ,dc=example,dc=edu', 
 'Computer', 
 '{"name": "cn", "serial": "serialNumber", "otherserial": "description", "locations_id": "l", "comment": "description"}',
 1, NOW(), NOW()),

(2, 'Computers - Desktop Workstations Only', 
 '(&(objectClass=device)(serialNumber=*)(cn=COMP*)(description=*Desktop*))', 
 'ou=Computers,dc=univ,dc=example,dc=edu', 
 'Computer', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

(3, 'Computers - Laptops Only', 
 '(&(objectClass=device)(serialNumber=*)(cn=COMP*)(description=*Laptop*))', 
 'ou=Computers,dc=univ,dc=example,dc=edu', 
 'Computer', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Printer filters
(4, 'Printers - Network Printers', 
 '(&(objectClass=device)(cn=PRINTER*)(serialNumber=*))', 
 'ou=Printers,dc=univ,dc=example,dc=edu', 
 'Printer', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l", "contact": "owner"}',
 1, NOW(), NOW()),

(5, 'Printers - Color Laser Printers', 
 '(&(objectClass=device)(cn=PRINTER*)(serialNumber=*)(description=*Color*Laser*))', 
 'ou=Printers,dc=univ,dc=example,dc=edu', 
 'Printer', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Monitor filters
(6, 'Monitors - All Display Devices', 
 '(&(objectClass=device)(cn=MONITOR*)(serialNumber=*))', 
 'ou=Monitors,dc=univ,dc=example,dc=edu', 
 'Monitor', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l", "size": "displaySize"}',
 1, NOW(), NOW()),

(7, 'Monitors - Large Displays (27+ inches)', 
 '(&(objectClass=device)(cn=MONITOR*)(serialNumber=*)(displaySize>=27))', 
 'ou=Monitors,dc=univ,dc=example,dc=edu', 
 'Monitor', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l", "size": "displaySize"}',
 1, NOW(), NOW()),

-- Phone filters
(8, 'Phones - IP & VoIP Systems', 
 '(&(objectClass=device)(cn=PHONE*)(serialNumber=*))', 
 'ou=Phones,dc=univ,dc=example,dc=edu', 
 'Phone', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l", "contact": "assignedTo"}',
 1, NOW(), NOW()),

-- Network equipment filters
(9, 'Network Equipment - Switches', 
 '(&(objectClass=device)(cn=SWITCH*)(serialNumber=*))', 
 'ou=Network,dc=univ,dc=example,dc=edu', 
 'NetworkEquipment', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l", "contact": "responsible"}',
 1, NOW(), NOW()),

(10, 'Network Equipment - Routers', 
 '(&(objectClass=device)(cn=ROUTER*)(serialNumber=*))', 
 'ou=Network,dc=univ,dc=example,dc=edu', 
 'NetworkEquipment', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

-- Generic asset filters for testing
(11, 'Test Filter - Database Instances', 
 '(&(objectClass=device)(cn=DB*)(serialNumber=*))', 
 'ou=Databases,dc=univ,dc=example,dc=edu', 
 'DatabaseInstance', 
 '{"name": "cn", "comment": "description", "locations_id": "l"}',
 1, NOW(), NOW()),

(12, 'Test Filter - Cables & Connections', 
 '(&(objectClass=device)(cn=CABLE*)(serialNumber=*))', 
 'ou=Infrastructure,dc=univ,dc=example,dc=edu', 
 'Cable', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description"}',
 1, NOW(), NOW()),

-- Inactive filter for testing
(13, 'INACTIVE - Old Equipment Filter', 
 '(&(objectClass=device)(cn=OLD*)(serialNumber=*))', 
 'ou=Retired,dc=univ,dc=example,dc=edu', 
 'Computer', 
 '{"name": "cn", "serial": "serialNumber", "comment": "description"}',
 0, NOW(), NOW());

-- =========================================================================
-- 3. LDAP-SYNCFILTER RELATIONS (glpi_plugin_advancedldap_authldap_syncfilters)
-- =========================================================================

-- Clear existing relations and create test associations
DELETE FROM `glpi_plugin_advancedldap_authldap_syncfilters` WHERE id >= 1;

-- Get the IDs of the newly created LDAP directories
-- Assuming the University directory will get the next available ID
-- and IT Department will get the following ID
SET @univ_ldap_id = (SELECT MAX(id)-1 FROM `glpi_authldaps`);
SET @it_ldap_id = (SELECT MAX(id) FROM `glpi_authldaps`);

INSERT INTO `glpi_plugin_advancedldap_authldap_syncfilters` (
    `id`,
    `authldap_id`, 
    `syncfilter_id`, 
    `is_active`, 
    `date_creation`
) VALUES 
-- University LDAP associations
(1, @univ_ldap_id, 1, 1, NOW()),  -- Computers - All
(2, @univ_ldap_id, 2, 1, NOW()),  -- Computers - Desktop
(3, @univ_ldap_id, 3, 1, NOW()),  -- Computers - Laptops
(4, @univ_ldap_id, 4, 1, NOW()),  -- Printers - Network
(5, @univ_ldap_id, 6, 1, NOW()),  -- Monitors - All
(6, @univ_ldap_id, 8, 1, NOW()),  -- Phones - IP/VoIP

-- IT Department LDAP associations
(7, @it_ldap_id, 5, 1, NOW()),   -- Printers - Color Laser
(8, @it_ldap_id, 7, 1, NOW()),   -- Monitors - Large
(9, @it_ldap_id, 9, 1, NOW()),   -- Network - Switches
(10, @it_ldap_id, 10, 1, NOW()),  -- Network - Routers
(11, @it_ldap_id, 11, 1, NOW()),  -- Test - Database Instances
(12, @it_ldap_id, 12, 1, NOW()),  -- Test - Cables

-- Inactive relation for testing
(13, @it_ldap_id, 13, 0, NOW());  -- INACTIVE filter

-- =========================================================================
-- SUMMARY
-- =========================================================================
-- This script creates:
-- • 2 LDAP directories (University Main + IT Department)
-- • 13 sync filters covering various asset types
-- • 13 relations between LDAP directories and sync filters
-- • Mix of active/inactive items for comprehensive testing
-- =========================================================================

SET foreign_key_checks = 1;