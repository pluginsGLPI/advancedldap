# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [unreleased] -

### Added

- Enrich computer inventory builder templates with all inventory schema fields (bios, operatingsystem, ...).

### Changed

- Make the plugin compatible with the Content Security Policy
- Use the shared GLPI plugin baselines for php-cs-fixer and rector.

### Fixed

- Empty sections no longer fall back to the default template when the DB columns are null.
- Avoid multiple scrollbars on the builder mapping edition view.
- CI: fix Psalm cache directory, drop the stray `glpi-project/tools` composer dependency, declare a unique composer autoloader suffix