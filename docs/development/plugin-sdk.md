---
title: Plugin SDK
description: Composer-based plugins with a YAML manifest and mediated SDK access so extensions register routes, fields, and hooks without touching core code.
---

## Coming soon

The Plugin SDK lets third-party developers extend Signals Rental Framework without modifying core code, core database tables, or core migrations.

Plugins are Composer packages that implement the framework contract and declare a static YAML manifest — the framework validates permissions, dependencies, and compatibility at install time before any plugin code runs. All data access flows through mediated SDK query and action interfaces rather than direct Eloquent access.

This documentation will cover manifest structure, the `PluginRegistrar` fluent API, lifecycle (discover → register → activate → boot → disable), and how plugins register routes, custom fields, widgets, notifications, and permissions.
