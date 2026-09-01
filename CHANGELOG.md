# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [unreleased] -

### Added

- Enrich computer inventory builder templates with all inventory schema fields (bios, operatingsystem, ...).

### Fixed

- Empty sections no longer fall back to the default template when the DB columns are null.
- Avoid multiple scrollbars on the builder mapping edition view.
- LDAP connections no longer fall back to port 389 when the directory is configured on another port (636 for LDAPS, for instance).
- Sync filter attribute listing and connection status now read the dereference option actually stored on the directory.
- Testing a directory that has no host configured no longer raises a fatal error.

### Security

- The builder mapping AJAX endpoint now requires the `config` right and only accepts declared section names, closing an arbitrary JSON file disclosure through path traversal that was reachable by any authenticated user.
- Only declared sections may be written from the builder mapping form.
- Listing attributes and checking a directory status now apply its full TLS configuration (client certificate, key, TLS version, bind mode, timeout), as the sync already did.
- JSON embedded in the builder mapping page is escaped against script-context breakout.