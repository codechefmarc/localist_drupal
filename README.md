# Localist Integration with Drupal
This repository contains code examples and documentation on how to integrate the Localist API with Drupal 10/11 to import events.

### Table of Contents

- [Localist Integration with Drupal](#localist-integration-with-drupal)
    - [Table of Contents](#table-of-contents)
    - [What This Repository Contains](#what-this-repository-contains)
    - [What is Localist?](#what-is-localist)
    - [Module Requirements](#module-requirements)
    - [Module Overview](#module-overview)

### What This Repository Contains

This repository has what looks like a full Drupal module, however it is not complete and cannot simply be installed and enabled. This is because there are specific decisions that need to be made first by site builders and developers. These decisions will be noted in this documentation. Then, a developer can use the decisions to set up Drupal fields and modify the code noted here to create a working migration.

### What is Localist?
[Localist](https://www.localist.com) is an event management system that makes it easy to enter, find, view, and register for events for an organization. While Localist offers a very robust branded hosted solution for viewing, filtering, and finding events, they also have an API to be able to integrate these events on other platforms.

### Module Requirements

* Drupal Core Migrate
* [Migrate Plus](https://www.drupal.org/project/migrate_plus)
* [Migrate Tools](https://www.drupal.org/project/migrate_tools)

### Module Overview
This module contains a basic Drupal module structure with the following:

1.
