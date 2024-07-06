# Table of Contents

- [Table of Contents](#table-of-contents)
- [What This Module Does](#what-this-module-does)
- [What is Localist?](#what-is-localist)
- [Module Requirements](#module-requirements)
  - [Add Composer Patches](#add-composer-patches)
- [Initial Setup](#initial-setup)
  - [Optional Example Migration](#optional-example-migration)
- [Running Migrations](#running-migrations)
- [Overriding Migrations](#overriding-migrations)
  - [Group Migration Requirement](#group-migration-requirement)
  - [Source Plugin Requirement](#source-plugin-requirement)
  - [Optional Process Plugins](#optional-process-plugins)
- [Helper Methods](#helper-methods)

# What This Module Does
* Utilizes Drupal migrations to import events, groups, and taxonomy terms into Drupal. The module provides custom migration plugins and functions to aid with importing data from the Localist API. It also provides a basic group migration with an associated taxonomy vocabulary called Localist Groups.

* Optionally, an example migration can be created to show how custom migrations can be written.

Any of the migrations can be overridden in a custom module to import most data from the Localist API to any content type and field in Drupal. This includes custom Localist filters. Creating these custom migrations is documented below.

# What is Localist?
[Localist](https://www.localist.com) is a hosted event management system which offers a branded solution for entering, viewing, filtering, and finding events, they also have an [API](https://developer.localist.com/doc/api) to be able to integrate these events on other platforms.

# Module Requirements

* Drupal Core Migrate
* [Migrate Plus](https://www.drupal.org/project/migrate_plus)
* [Migrate Tools](https://www.drupal.org/project/migrate_tools)
* [Smart Date](https://www.drupal.org/project/smart_date) - Smart date handles start and end times that work better with Localist dates.
* **NOTE: Composer patches needs to be enabled in the root `composer.json`**
  * Currently, there is a patch for the `migrate_plus` module that enables the use of a callback function for migration source URLs.
  * This patch is included in this module's `composer.json` and this requirement will be removed once [the issue on Drupal.org](https://www.drupal.org/project/migrate_plus/issues/3040427) has been solved.

## Add Composer Patches
If not already added, add the following to the relevant sections of the root `composer.json` and then run `composer update`.

```(json)
"require": {
  "cweagans/composer-patches": "^1.7",
},
"config": {
  "allow-plugins": {
    "cweagans/composer-patches": true
  }
},
"extra": {
  "enable-patching": true,
  "composer-exit-on-patch-failure": true,
  "patchLevel": {
    "drupal/core": "-p2"
  }
}
```

# Initial Setup
1. Verify the module requirements above. Note the requirement of having composer patches enabled in the root `composer.json`.
2. Enable the module
3. Visit the Localist settings page at `/admin/config/services/localist`
4. Check the box next to Enable Localist sync.
5. Enter in the Localist endpoint base URL for your organization. You can get this from visiting your Localist home page or by asking your Localist representative.
6. Click Save Configuration at the bottom.
7. Once the form refreshes, check the Preflight Check section at the top. If the endpoint works, a green checkmark will appear next to Localist Endpoint.
8. Click "Create Groups" to create the group taxonomy terms. Groups will synchronize from Localist and be added to the <code>localist_groups</code> taxonomy vocabulary.
9. Once the groups have been created, select a group in the autocomplete for "Group to Sync Events". This will synchronize events from the selected group.
10. At this point, all Preflight Checks should be green and the module is set up for accepting custom migrations. No events will be synchronized until an Event Migration is specified.

## Optional Example Migration

1. An optional example migration can be added by opening the "Example Migration" details and clicking on "Create Example".
2. This will create a content type called `localist_event` and a taxonomy vocabulary called `localist_places`.
3. It will also override the configuration and add two migrations to the settings: `localist_example_events` and `localist_example_places`.

# Running Migrations
* Migrations will run on cron and will sync events roughly every hour if Enable Localist sync is checked.
* Manual sync is also possible via the settings form by clicking on the "Sync Now" button.
* If a migration is not found, a warning message will inform only when running migrations from the settings page, so this is a good place to test migration status.

# Overriding Migrations

General info here

## Group Migration Requirement

Group migration needs to go to the group taxonomy vocab

## Source Plugin Requirement

Parser plugin requirement

## Optional Process Plugins

Filters

# Helper Methods

Get ticket Info here...
