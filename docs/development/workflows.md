---
title: Workflows
description: No-code automation that chains triggers, steps, and conditions to customise rental business processes without writing code.
---

## Coming soon

The workflow engine will let rental companies automate business processes without writing code — defining rules like “when this happens, do that, then that” through a visual step builder in the admin UI.

Each workflow combines a single **trigger** (domain events, schedules, inbound webhooks, or manual UI actions), an ordered sequence of **steps** (actions, branching conditions, delays, and human-review halts), and a **context** object that carries data from the trigger through every step.

This documentation will cover trigger types, step handlers, template syntax for referencing prior step output, and how workflows extend the same event bus that powers audit logs and webhooks.
