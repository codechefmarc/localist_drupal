# Localist Integration with Drupal
This repository contains code examples and documentation on how to integrate the Localist API with Drupal 10/11 to import events.

### Table of Contents

- [Localist Integration with Drupal](#localist-integration-with-drupal)
    - [Table of Contents](#table-of-contents)
    - [What This Repository Contains](#what-this-repository-contains)
    - [What is Localist?](#what-is-localist)
    - [What This Module Does](#what-this-module-does)
    - [Module Requirements](#module-requirements)
    - [Module Overview](#module-overview)

### What This Repository Contains

This repository has what looks like a full Drupal module, however it is not complete and cannot simply be installed and enabled. This is because there are specific decisions that need to be made first by site builders and developers. These decisions will be noted in this documentation. Then, a developer can use the decisions to set up Drupal fields and modify the code noted here to create a working migration.

### What is Localist?
[Localist](https://www.localist.com) is an event management system that makes it easy to enter, find, view, and register for events for an organization. While Localist offers a very robust branded hosted solution for viewing, filtering, and finding events, they also have an API to be able to integrate these events on other platforms.

### What This Module Does
* Localist Drupal - Utilizes Drupal migrations to import events, groups, and taxonomy terms into Drupal. The module provides custom migration plugins and functions to aid with importing data from the Localist API. It also provides a basic group migration with an associated taxonomy vocabulary called Localist Groups.

* Localist Examples (optional) - Creates a content type called Localist Event, associated example fields, and a taxonomy vocabulary for Places and associated migrations.

Any of the migrations can be overridden in a custom module to import most data from the Localist API to any content type and field in Drupal. This includes custom Localist filters. Creating these custom migrations is documented below.

### Module Requirements

* Drupal Core Migrate
* [Migrate Plus](https://www.drupal.org/project/migrate_plus)
* [Migrate Tools](https://www.drupal.org/project/migrate_tools)
* Composer patches to be enabled in the root composer.json - @todo add specific details here

### Module Overview
This module contains a basic Drupal module structure with the following:

1.
