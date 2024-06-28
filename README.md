### Table of Contents

- [Table of Contents](#table-of-contents)
- [What These Modules Do](#what-these-modules-do)
- [What is Localist?](#what-is-localist)
- [Module Requirements](#module-requirements)

### What These Modules Do
* Localist Drupal - Utilizes Drupal migrations to import events, groups, and taxonomy terms into Drupal. The module provides custom migration plugins and functions to aid with importing data from the Localist API. It also provides a basic group migration with an associated taxonomy vocabulary called Localist Groups.

* Localist Examples (optional) - Creates a content type called Localist Event, associated example fields, and a taxonomy vocabulary for Places and associated migrations.

Any of the migrations can be overridden in a custom module to import most data from the Localist API to any content type and field in Drupal. This includes custom Localist filters. Creating these custom migrations is documented below.

### What is Localist?
[Localist](https://www.localist.com) is an event management system that makes it easy to enter, find, view, and register for events for an organization. While Localist offers a very robust branded hosted solution for viewing, filtering, and finding events, they also have an API to be able to integrate these events on other platforms.

### Module Requirements

* Drupal Core Migrate
* [Migrate Plus](https://www.drupal.org/project/migrate_plus)
* [Migrate Tools](https://www.drupal.org/project/migrate_tools)
* Composer patches to be enabled in the root composer.json - @todo add specific details here
