# Project overview

This repository is a WordPress site for Bewdley Farm Shop.

## Active stack

- WordPress 6.9.1
- PHP 8.2.29
- Nginx
- WooCommerce active
- Bricks Builder active
- Bricks Child Theme is used for custom theme work
- Local development via Local
- Hosting via Flywheel
- DNS via Fasthosts
- Editing workflow via VS Code

## Code ownership rules

Treat this as a WordPress site where custom development should be limited to code we control.

### Preferred edit locations

- `wp-content/themes/bricks-child/`
- `wp-content/plugins/` for custom site plugins created for this project
- project documentation files such as `AGENTS.md` and `TASKS.md`

### Avoid editing

- WordPress core files
- `wp-content/themes/bricks/` parent theme
- commercial plugin internals unless explicitly required
- WooCommerce core plugin files
- generated/cache/upload files

## Current implementation priorities

The current project focus includes:

- WooCommerce setup
- Bricks Builder implementation
- safe customizations through child theme or custom plugin
- email/newsletter system planning and integration
- preserving transactional email functionality
- preparing safe staging-first deployment workflow

## Environment workflow

### Local

Use Local for:

- code changes
- child theme updates
- custom plugin development
- Bricks layout work
- safe non-production testing

### Staging

Use staging before live for:

- WooCommerce validation
- mail integration testing
- plugin interaction testing
- deliverability-related checks
- final QA

### Production

Do not assume untested changes should go directly live.

## WooCommerce conventions

- Preserve checkout and order flow
- Preserve transactional email behavior unless explicitly changing it
- Prefer WooCommerce hooks and filters over core edits
- If interacting with WooCommerce internals, prefer public APIs and standard hooks

## Bricks conventions

- Use Bricks Child Theme for custom PHP/CSS/JS
- Do not modify the Bricks parent theme directly
- Respect Bricks template structure
- Remember that many Bricks layouts and template contents are stored in the database, not only in files

## Database safety

- Avoid destructive database operations
- Do not bulk-delete customers, orders, subscribers, or content
- Prefer reversible and minimal changes
- If data mapping or migration is needed, explain the exact impact first

## AI editing behavior

When making edits:

- prefer small, localized changes
- explain file targets before major edits
- use WordPress best practices
- prefer hooks, filters, and custom plugin code over invasive edits
- avoid hardcoding secrets or environment-specific credentials
- do not edit `wp-config.php` unless explicitly required

## Client/business context

This is a local farm shop website. Client-facing outputs should avoid technical jargon unless explicitly requested. Focus on practical business outcomes, clear UX, maintainability, and low-risk implementation.

## Important reference files

Check these first before proposing project-specific changes:

- `AGENTS.md`
- `TASKS.md`
- `wp-content/themes/bricks-child/`
- any custom plugin created for this project

## If something is unclear

Ask concise questions about:

- whether the change belongs in Bricks Child Theme or a custom plugin
- whether the change affects WooCommerce checkout or email flow
- whether the change should be tested on staging first
