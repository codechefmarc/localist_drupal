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
  - [Source Plugin Changes](#source-plugin-changes)
  - [Event Dates](#event-dates)
  - [Group Migration Requirement](#group-migration-requirement)
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
10. At this point, all Preflight Checks should be green and the module is set up for accepting custom migrations. No events will be synchronized until an Event Migration is specified. See below for more information about creating an event migration.

## Optional Example Migration

1. An optional example migration can be added by opening the "Example Migration" details and clicking on "Create Example".
2. This will create a content type called `localist_event` and a taxonomy vocabulary called `localist_places`.
3. It will also override the configuration and add two migrations to the settings: `localist_example_events` and `localist_example_places`.

# Running Migrations
* As long as the "Enable Localist sync" is checked and the all Preflight Checks are green. migrations will run on cron and will sync events roughly every hour.
* Manual sync is also possible via the settings form by clicking on the "Sync Now" button.
* If a migration is not found, a warning message will inform only when running migrations from the settings page. Therefore it is a good idea to test migrations via the "Sync Now" button on the settings page.

# Overriding Migrations

Migrations used for this Localist module follow the standard Drupal migration YML structure with a few small changes noted below. If you are new to the migration API, a great resource is [31 days of Drupal migrations](https://understanddrupal.com/courses/31-days-of-migrations/).

To create your own migrations, create a custom module with a `migrations` directory under the root of the module, and create migration `yml` files in that directory that follow a similar structure to the examples provided.

Enable your custom module, and then enter the migration `id` into the Localist settings form in the appropriate field. For example, if it is an events migration, enter the machine name into the "Event Migration" field and save the settings. The group migration can also be overridden (see below) and there are also dependency migrations (that will get imported before events - i.e. for things like taxonomy terms).

The following notes will refer to the `migrations/localist_example_events` migration provided in this module as an example.

## Source Plugin Changes

Take a look at the source structure of the example migration:

```yml
(/migrations/localist_example_events.yml)
id: localist_example_events
label: 'Localist example events'
source:
  plugin: url
  data_fetcher_plugin: http
  data_parser_plugin: localist_json
  track_changes: true
  urls:
    # @see localist_drupal.module
    callback: localist_drupal_migrate_url
  localist_endpoint: 'events'
  item_selector: events
  fields:
    -
      name: event_id
      label: 'Event ID'
      selector: localist_data/id
    -
      name: localist_title
      label: 'Localist title'
      selector: localist_data/title
  ids:
    event_id:
      # This would be an int, but it is too long for the DB
      type: string
```

1. The `id` of the migration must be unique and is what is used in the settings form to inform the Localist Drupal module.
2. The data parser plugin as part of the source uses a custom `localist_json` parser that handles the unique structure of the Localist API.
3. The `urls` does not provide a direct URL, but instead a callback function to allow the URL to come from the settings form, supports paging in the Localist API, and allows the same callback to be used across multiple migrations.
4. The `localist_endpoint` is required to tell the callback which endpoint to use. The following endpoints are currently supported:
- `events`
- `places`
- `filters`
- `groups`
- `photos`
- `tickets`
5. In the `fields` section for the title field (for example), notice the `selector` is pointing to `localist_data/title` - the `localist_data/` is important to preface before the field name from Localist. Field names from Localist can be found in the events section of the [Localist API documentation](https://developer.localist.com/doc/api#events).

## Event Dates

Obviously one of the most important parts of the Localist event migration are the dates of the event. We have found the best way to support dates coming from Localist is to use the [Smart Date](https://www.drupal.org/project/smart_date) contrib module which is a requirement of this module. This is because Smart Date handles better reoccurring events, more formatting options, and all-day events.

Note the (truncated) code from the example:

```yml
(/migrations/localist_example_events.yml)
source:
  fields:
    -
      name: event_dates
      label: 'Event dates'
      selector: instances
```

For dates coming from Localist, use simply `instances` for the selector and it will grab all future dates from the API. Currently, only future dates are supported. In Drupal, the field the dates will go into must be a "Smart date range" field set to allow an unlimited amount of dates. This is to support all date instances of an event on one node.

## Group Migration Requirement

For the group migration, the migration destination must to be set to the `localist_groups` taxonomy vocabulary. Additionally, the `group_id` must go into a field called `field_localist_group_id` as this is what is expected from the source parser plugin callback as noted above.

```yml
(/migrations/localist_groups.yml)
process:
  name: group_name
  field_localist_group_id: group_id
destination:
  plugin: 'entity:taxonomy_term'
  default_bundle: localist_groups
```

Aside from those requirements, additional group information from Localist can be migrated over via an overridden group migration. See [The Localist API Groups](https://developer.localist.com/doc/api#groups) for more fields that can come over into additiomnal fields in this taxonomy vocabulary.

## Optional Process Plugins

Filters

# Helper Methods

Get ticket Info here...
