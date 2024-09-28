# Table of Contents

- [Table of Contents](#table-of-contents)
- [What This Module Does](#what-this-module-does)
- [What is Localist?](#what-is-localist)
- [Module Requirements](#module-requirements)
- [Initial Setup](#initial-setup)
  - [Optional Example Migration](#optional-example-migration)
- [Running Migrations](#running-migrations)
- [Overriding Migrations](#overriding-migrations)
  - [Create a scaffold migration using Drush generate](#create-a-scaffold-migration-using-drush-generate)
  - [Source Plugin Changes](#source-plugin-changes)
  - [Event Dates](#event-dates)
  - [Group Migration Requirement](#group-migration-requirement)
  - [Retrieving Custom Filters from Localist into a Drupal Taxonomy](#retrieving-custom-filters-from-localist-into-a-drupal-taxonomy)
    - [Localist Setup](#localist-setup)
    - [Initial Taxonomy Setup](#initial-taxonomy-setup)
    - [Using Custom Filters in an Event Migration](#using-custom-filters-in-an-event-migration)
  - [Override Properties](#override-properties)
- [Helper Plugins and Methods](#helper-plugins-and-methods)
  - [Helper migration plugin - Photo description](#helper-migration-plugin---photo-description)
  - [Helper migration plugin - Group taxonomy reference](#helper-migration-plugin---group-taxonomy-reference)
  - [Helper Method - Ticket Info](#helper-method---ticket-info)
- [Advanced Usage](#advanced-usage)
- [Troubleshooting](#troubleshooting)

# What This Module Does
* Utilizes Drupal migrations to import events, groups, and taxonomy terms from Localist into Drupal. The module provides custom migration plugins and functions to aid with importing data from the Localist API. It also provides a basic group migration with an associated taxonomy vocabulary called Localist Groups.

* Optionally, an example migration can be created to show how custom migrations can be written.

* A Drush generator command is available to generate a starting point event migration.

Any of the migrations can be overridden in a custom module to import most data from the Localist API to any content type and field in Drupal. This includes custom Localist filters. Creating these custom migrations is documented below.

# What is Localist?
[Localist](https://www.localist.com) is a hosted event management system which offers a branded solution for entering, viewing, filtering, and finding events. They also have an [API](https://developer.localist.com/doc/api) to be able to integrate these events on other platforms.

# Module Requirements

* Drupal Core Migrate
* [Migrate Plus](https://www.drupal.org/project/migrate_plus)
* [Migrate Tools](https://www.drupal.org/project/migrate_tools)
* [Smart Date](https://www.drupal.org/project/smart_date) - Smart date handles start and end times that work better with Localist dates.

# Initial Setup
1. Verify the module requirements above.
2. Enable the module
3. Visit the Localist settings page under Configuration -> Web services -> Localist settings (`/admin/config/services/localist`).
4. Check the box next to "Enable Localist sync".
5. Enter the Localist endpoint base URL for your organization. Get this by visiting your Localist home page or by asking your Localist representative.
6. Click Save Configuration at the bottom.
7. Once the form refreshes, check the Preflight Check section at the top. If the endpoint works, a green checkmark will appear next to Localist Endpoint.
8. Click "Create Groups" to create the group taxonomy terms. Groups will synchronize from Localist and be added to the <code>localist_groups</code> taxonomy vocabulary.
9. Once the groups have been created, select a group in the autocomplete for "Group to Sync Events". This will synchronize events from the selected group.
10. At this point, all Preflight Checks should be green and the module is set up for accepting custom migrations. No events will be synchronized until an Event Migration is specified. See below for more information about creating an event migration.

## Optional Example Migration

1. An optional example migration can be added by opening the "Example Migration" details and clicking on "Create Example".
2. This will create a content type called `localist_event` and a taxonomy vocabulary called `localist_places`.
3. It will also override this module's configuration and add two migrations to the settings: `localist_example_events` and `localist_example_places`.

# Running Migrations
* As long as the "Enable Localist sync" is checked and the all Preflight Checks are green, migrations will run on cron and will sync events roughly every hour.
* Manual sync is also possible via the settings form by clicking on the "Sync Now" button.
* If a migration is not found, a warning message will inform only when running migrations from the settings page. Therefore, it is a good idea to test migrations via the "Sync Now" button on the settings page.

# Overriding Migrations

Migrations used for this Localist module follow the standard Drupal migration YML structure with a few small changes noted below. If you are new to the migration API, a great resource is [31 days of Drupal migrations](https://understanddrupal.com/courses/31-days-of-migrations/).

To create your own migrations, create a custom module. Create migration `yml` files in a `/migrations` directory under the root of the custom module that follow a similar structure to the examples provided. Examples are located at `localist_drupal/migrations`.

Enable the custom module and then enter the migration `id` into the Localist settings form in the appropriate field. For example, if it is an events migration, enter the machine name into the "Event Migration" field and save the settings. The group migration can also be overridden (see below) and there are also dependency migrations (that will get imported before events - for example Localist filters into a taxonomy vocabulary).

The following notes will refer to the `migrations/localist_example_events` migration provided in this module as an example.

## Create a scaffold migration using Drush generate

You may manually create migrations via the examples or use a Drush generator to create a scaffold for an events migration. To do so, your custom module must first be enabled. Then run:

`drush generate localist_event_migration`

The generator will ask for:
- The machine name of your custom module (this is autocomplete)
- The machine name of the content type to use for events
- The machine name for the description field - Since Localist uses formatted HTML for the description field, this should also be a formatted HTML field.
- The machine name of the smart date range field.

## Source Plugin Changes

Take a look at the source structure of the example migration:

`(migrations/localist_example_events.yml)`
```yml
id: localist_example_events
label: 'Localist example events'
source:
  plugin: localist_url
  data_fetcher_plugin: http
  data_parser_plugin: localist_json
  track_changes: true
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

1. The `id` of the migration must be unique and is what is used in the settings form to inform the Localist Drupal module which migration is for events.
2. The source `plugin` uses a custom `localist_url` which allows the URL to come from the settings form, API caching and paging, among other things unique to Localist.
3. The source `data_parser_plugin` plugin uses a custom `localist_json` parser that handles the unique structure of the Localist API.
4. The `localist_endpoint` is required to tell the migration which endpoint to use. The following endpoints are currently supported:
- `events`
- `places`
- `filters`
- `groups`
- `photos`
- `tickets`
1. In the `fields` section for the title field (for example), notice the `selector` is pointing to `localist_data/title` - the `localist_data/` is important to preface before the field name from Localist. The exception are the dates, noted below. Field names from Localist can be found in the events section of the [Localist API documentation](https://developer.localist.com/doc/api#events).

## Event Dates

Obviously one of the most important parts of the Localist event migration are the dates of the event. We have found the best way to support dates coming from Localist is to use the [Smart Date](https://www.drupal.org/project/smart_date) contrib module which is a dependency of this module. This is because Smart Date handles better reoccurring events, more formatting options, and all-day events.

Note the (truncated) code from the example:

`(migrations/localist_example_events.yml)`
```yml
source:
  fields:
    -
      name: event_dates
      label: 'Event dates'
      selector: instances
```

For dates coming from Localist, use simply `instances` for the selector and it will grab all future dates from the API. Currently, only future dates are supported our 364 days from the current date. In Drupal, the field the dates will go into must be a "Smart date range" field set to allow an unlimited amount of dates. This is to support all date instances of an event on one node.

## Group Migration Requirement

For the group migration, the migration destination must to be set to the `localist_groups` taxonomy vocabulary. Additionally, the `group_id` must go into a field called `field_localist_group_id` as this is what is expected from this module to sync groups correctly.

`(migrations/localist_groups.yml)`
```yml
source:
  fields:
    -
      name: group_id
      label: 'Group ID'
      selector: group/id
    -
      name: group_name
      label: 'Group name'
      selector: group/name
process:
  name: group_name
  field_localist_group_id: group_id
destination:
  plugin: 'entity:taxonomy_term'
  default_bundle: localist_groups
```

Aside from those requirements, additional group information from Localist can be migrated over via an overridden group migration. See [The Localist API Groups](https://developer.localist.com/doc/api#groups) for more fields that can come over into additional fields in this taxonomy vocabulary.

To override the group migration, copy the one that comes with this module at `migrations/localist_groups.yml` into your own custom module, change the ID, and add additional fields to sync from Localist. Then, add the new id of your migration into the "Group Migration" field on the settings form.

## Retrieving Custom Filters from Localist into a Drupal Taxonomy

Localist allows you to create filters for events for ease of grouping events and filtering. To get these filters over into Drupal, we recommend using a taxonomy vocabulary per filter. The event migration will use one of the custom process plugins (`extract_localist_filter`) to make this work. Here are the steps.

### Localist Setup

1. Filters will need to be [created in the Localist admin interface first](https://help.concept3d.com/hc/en-us/articles/11938623294611-Filters).
2. To obtain the filter machine name (in our case here it is `event_types`), you can load the API endpoint directly (`https://CALENDAR-URL/api/2/events/filters`) (See [Localist API documentation](https://developer.localist.com/doc/api#event-filters)) and the top-level key will be the name of the different filters available.
3. You may use a tool like [Postman](https://www.postman.com) to load your Localist API endpoints to verify field names and data that should be returned.

### Initial Taxonomy Setup

1. Set up your Drupal taxonomy vocabulary. For our example, we will use Event Type (`localist_event_type`).
2. In your event content type, create an entity reference field and point it to the vocabulary in step 1. Make sure this field can accept unlimited values as that is what Localist supports for filters.
3. In your own custom module as noted in the [Overriding Migrations](#overriding-migrations) section, create a dependency migration to handle the taxonomy terms you want to import:

`(custom taxonomy migration)`
```yml
id: localist_event_types
label: 'Localist event_types'
source:
  plugin: localist_url
  data_fetcher_plugin: http
  data_parser_plugin: json
  track_changes: true
  localist_endpoint: 'filters'
  item_selector: event_types
  fields:
    -
      name: event_type_id
      label: 'Event type ID'
      selector: id
    -
      name: event_type_name
      label: 'Event type name'
      selector: name
    -
      name: event_type_parent_id
      label: 'Event type parent ID'
      selector: parent_id
  ids:
    event_type_id:
      # This would be an int, but it is too long for the DB
      type: string
process:
  name: event_type_name
  parent:
    plugin: migration_lookup
    migration: localist_event_types
    source: event_type_parent_id

destination:
  plugin: 'entity:taxonomy_term'
  default_bundle: localist_event_type
  overwrite_properties:
    - name
```

4. Note the `item_selector: event_types` - this is what pulls from the API from Localist.
5. To make sure that a hierarchial list works correctly, note the use of the `selector: parent_id` and the `parent` under the process section of the migration.
6. Make sure the `destination` points to your newly created taxonomy vocabulary.
7. Finally, go into the Localist settings page and add your new custom migration ID (in our example `localist_event_types`) to the Dependency Migrations section of the settings and save.

### Using Custom Filters in an Event Migration

Now that we have the filter terms in Drupal as shown above, the next step is to connect those filters into our events.

1. Add the following to your custom event migration (note this example here is truncated, see the full example migration for all fields):

`(custom event migration)`
```yml
source:
  fields:
  -
      name: filters
      label: 'Localist filters'
      selector: localist_data/filters
process:
  field_localist_event_type:
    -
      plugin: extract_localist_filter
      source: filters
      filter: event_types
    -
      plugin: migration_lookup
      migration: localist_event_types
      no_stub: true
migration_dependencies:
  required:
    - localist_event_types
```

1. The change from a regular migration is the addition of the `extract_localist_filter` plugin which correctly connects the filters on each event to the dependency migration so it can attach the event types taxonomy terms to the corresponding Drupal event.
2. If not already done, enter your event migration ID into the "Event Migration" field in the settings form.
3. Run your event migration.

## Override Properties

Drupal migrations come with a way to specify if data already migrated will be overridden from the source or not. Depending on how you plan to use events in Drupal, overriding from source may or may not be what is desired.

If you always want the field data from Localist to take precedence and override any edits performed in Drupal, add the fields to the `override_properties` section of the `destination` part of the migration:

```yml
destination:
  plugin: 'entity:node'
  default_bundle: localist_event
  overwrite_properties:
    - title
    - field_localist_id
    - field_localist_description
    - field_localist_date
    - field_localist_place
```

# Helper Plugins and Methods

## Helper migration plugin - Photo description

To retrieve the image description from Localist (for example to use as alternative text for imported images), use the `get_localist_image_desc` helper plugin. This requires the additional field of the `photo_id`:

`(custom event migration)`
```yml
source:
  fields:
    -
      name: localist_image_id
      label: 'Localist image ID'
      selector: localist_data/photo_id
process:
  field_localist_event_image_alt:
    plugin: get_localist_image_desc
    source: localist_image_id
```

## Helper migration plugin - Group taxonomy reference

Groups are always imported into the `localist_groups` taxonomy, but in the examples, are not connected to events. To retrieve the group name and use it as an entity reference (similar to how filters are handled), use the `extract_localist_groups` helper plugin. This requires the setup of an entity reference field on the event content type.

`(custom event migration)`
```yml
source:
  fields:
    -
      name: localist_groups
      label: 'Localist groups'
      selector: localist_data/groups
process:
  field_localist_group:
    -
      plugin: extract_localist_groups
      source: localist_groups
    -
      plugin: migration_lookup
      migration: localist_groups
      no_stub: true
```

## Helper Method - Ticket Info

The `getTicketInfo` helper method has the ability to get real-time ticket information from Localist. To use, the Localist event ID is required to be saved in a field as part of the node. This is included with the example migration.

`(migrations/localist_example_events.yml)`
```yml
source:
  fields:
    -
      name: event_id
      label: 'Event ID'
      selector: localist_data/id
process:
  title: localist_title
  field_localist_id: event_id
```
The ticket helper can be called in a Drupal `preprocess` function or custom service via Dependency Injection:

```php
$localistManager = \Drupal::service('localist_drupal.manager');
// Get the localist event ID dynamically from the field value.
$localistEventID = 45696189005430;
kint($localistManager->getTicketInfo($localistEventID));
```

Note in the example, the event ID is hardcoded - in your custom module, the ID would come from the the localist ID field on the event node.

This function will return an array of tickets, with each ticket containing many fields, but the most relevant ones are:

| Key         | Type    | Description            |
| ----------- | ------- | ---------------------- |
| id          | integer | Ticket ID              |
| name        | string  | Ticket name            |
| description | string  | Ticket description     |
| price       | integer | Ticket price           |
| ticket_type | string  | Ticket type            |

# Advanced Usage
Once "Enable Localist sync" has been turned on and all preflight checks are complete, it is possible to turn off the sync on the settings page and manage migrations manually. It is still required to have the following in place:
1. A working endpoint base URL.
2. [Group migration requirements](#group-migration-requirement) and groups imported.
3. A Localist group must be selected.
4. If the sync is off, no migrations will be run via cron from this module and migrations must be done manually or via other automatic methods.

# Troubleshooting

The best way to troubleshoot this module is via regular Drupal migration troubleshooting steps. But here are some starting places:

1. [Drupal Debugging Migrations page](https://www.drupal.org/docs/drupal-apis/migrate-api/debugging-migrations)
2. The previously linked article has some great tips for [debugging migrations part 1](https://understanddrupal.com/lessons/how-debug-drupal-migrations-part-1/) and [debugging migrations part 2](https://understanddrupal.com/lessons/how-debug-drupal-migrations-part-2/).
3. Use `drush` to:
   1. Display migration status: `drush ms`
   2. Import migrations manually `drush mim <id_of_migration>`
   3. Rollback migrations: `drush mr <id_of_migration>`
   4. Reset failed migrations (review the status table (`drush ms`) to see any pending migrations): `drush mrs <id_of_migration>`
4. Overridden migrations must be in your own custom module and that module needs to be enabled.
5. Double and triple check for typos in the migration files. Make sure the field names, content types, taxonomy vocabularies, and machine names for the Localist API keys are correct.
6. Use a tool like [Postman](https://www.postman.com) to load your Localist API endpoints to verify field names and data that should be returned.
