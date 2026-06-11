# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [unreleased]

### Added

- Manual LDAP to GLPI inventory synchronization from a sync filter, with a dry-run preview and an execute mode (Computer itemtype)

### Fixed

- Read the correct `deref_option` field from the LDAP directory configuration
- Prevent JSON injection from LDAP attribute values when building the inventory payload
- Prevent mass assignment when creating an AuthLDAP / SyncFilter relation