# AGENTS.md

## Project Identity

This is a WordPress ecommerce website for a local UK farm shop.

- Site: Bewdley Farm Shop
- Domain: `bewdleyfarmshop.co.uk`
- WordPress: 6.9.1
- PHP: 8.2.29
- Server: Nginx
- Ecommerce: WooCommerce active
- Builder: Bricks Builder (latest)
- Development environment: Local
- Staging: will be created before live email rollout
- Hosting: Flywheel
- DNS provider: Fasthosts
- Workflow: Git + VS Code + Bricks visual builder

## Branch Strategy And Environment Sync

Current branch policy (agreed 2026-03-10):

- `main` is the live/production branch
- `dev` is the active development branch

Important Local/Flywheel note:

- Local "Pull" syncs files + database from the selected Flywheel environment and is not Git-aware
- the checked out Git branch only determines where those file changes are recorded

Interim safe workflow for staging sync:

- do not pull staging directly into `dev` when `dev` has unmerged local work
- create a temporary sync branch from `dev` (for example `sync/staging-YYYY-MM-DD`)
- run Local pull on that sync branch
- commit pulled changes on the sync branch
- merge/reconcile with `dev`, test, then merge back to `dev`

Open decision required:

- define one permanent branch policy for staging-related work, either:
- option A: keep using temporary `sync/staging-*` branches
- option B: introduce a dedicated long-lived `staging` branch

Until this decision is finalized, use option A.

## Current Objective

Implement a reliable, scalable email system that supports:

- WooCommerce transactional emails
- weekly marketing newsletters
- subscriber management inside WordPress
- branded domain-based sending
- future scaling beyond 1,000 subscribers

The client should not be burdened with technical jargon. Internal implementation details should remain internal unless explicitly requested.

## Business Context

The client:

- is a local farm shop
- already has about 600 subscribers
- wants to send weekly updates and offers
- currently uses `bewdleyfarmshop@btconnect.com`
- currently collects signups through WooCommerce checkout
- has consent language in place, but wording and storage location still need verification
- currently stores potential subscriber data somewhere in WooCommerce-related data
- already has working WordPress transactional emails

## Preferred Technical Stack

Unless the developer explicitly overrides this, use:

- FluentCRM for subscriber management and campaigns
- FluentSMTP for email transport
- Amazon SES for sending infrastructure
- branded domain sender identities under `bewdleyfarmshop.co.uk`
- BTConnect address as Reply-To where appropriate

## Sender Strategy

### Transactional emails

Preferred visible sender:

- `orders@bewdleyfarmshop.co.uk`

Typical use:

- order confirmations
- account emails
- password reset
- WooCommerce notifications

### Marketing emails

Preferred visible sender:

- `hello@bewdleyfarmshop.co.uk`

Typical use:

- weekly newsletters
- seasonal offers
- promotions

### Reply handling

Unless otherwise instructed, replies should route to:

- `bewdleyfarmshop@btconnect.com`

Use Reply-To where appropriate.

### Important clarification

A sending address under the domain is not automatically a real mailbox. Do not assume inbox hosting exists unless explicitly confirmed.

## Client Communication Rules

When writing anything client-facing:

- avoid plugin names unless necessary
- avoid AWS / SES / SMTP / DNS jargon
- avoid implementation details
- focus on outcomes:
  - reliable email delivery
  - professional appearance
  - one-time setup
  - low ongoing cost
  - easy future use

## AI Agent Permissions

AI agents may:

- inspect and edit files
- propose plugin configuration steps
- create code snippets
- modify theme or plugin integration code if needed
- suggest Bricks implementation steps

AI agents must NOT:

- run destructive database operations
- bulk-delete customers, orders, subscribers, posts, or users
- alter WooCommerce order data without explicit approval
- disable key plugins without checking impact
- expose secrets or credentials in code or logs
- assume Local, staging, and Flywheel production behave identically

## Database Safety Rules

Treat the database as protected.

- Avoid destructive SQL
- Avoid irreversible migrations without approval
- Avoid deleting subscriber data
- Avoid rewriting WooCommerce customer records unless explicitly required
- Prefer imports, mappings, tagging, and non-destructive sync approaches

## Working Style

Agents must behave like senior WordPress developers.

Priorities:

1. preserve site stability
2. preserve transactional email functionality
3. preserve WooCommerce checkout and order flow
4. improve deliverability
5. minimize technical debt
6. keep changes reversible
7. keep implementation practical for Local + staging + Flywheel

## Bricks Builder Rules

- Prefer Bricks-native solutions where stable
- Respect existing templates and structure
- Avoid unnecessary custom code if Bricks can handle the UI
- If forms are used, verify whether Bricks form actions are sufficient or whether WooCommerce checkout is the actual source of signups
- Do not break visual layout while adding signup forms or newsletter UI

## WooCommerce Rules

WooCommerce is active and current signups appear to come from checkout.

Agents must:

- preserve current order email functionality
- test WooCommerce transactional email flow after mail changes
- verify exactly how checkout newsletter opt-in is stored before importing contacts
- assume not every WooCommerce customer should be mailed unless consent status is confirmed
- keep transactional and marketing concerns separate

## Email Deliverability Priorities

Optimize for:

- authenticated domain sending
- sender consistency
- low spam risk
- root-domain safe configuration
- stable reply handling
- unsubscribe support
- future scaling
- complaint and bounce awareness where applicable

Do not recommend normal PHP mail or generic hosting mail for bulk newsletters.

## Environment Awareness

### Local

Use Local for:

- plugin installation
- UI setup
- CRM structure
- template preparation
- integration planning
- code changes
- non-production tests

### Staging

Use staging for:

- SES integration
- DNS-dependent mail validation
- sender identity testing
- WooCommerce transactional mail tests
- FluentCRM / FluentSMTP configuration validation
- campaign test sends

### Live

Use live only after staging passes email tests.

Do not assume SES verification can be finalized meaningfully in Local alone.

## DNS and Hosting Notes

- DNS is managed at Fasthosts
- Hosting is Flywheel
- Hosting panel access exists
- Root domain sending is acceptable unless changed later

### Flywheel SSH Capability (Project Note)

- Potential SSH access string provided by user:
- `ssh bewdleyfarmshop90+bewdley-farm-shop@ssh.getflywheel.com`
- Treat this as an environment capability, not a blanket guarantee for every environment.
- Verify SSH is enabled for the specific target (staging or production) before relying on it.
- Intended use: non-destructive diagnostics/log inspection and deployment-adjacent checks.
- Reference: `https://getflywheel.com/wordpress-support/ssh-key-access-and-management/`

Agents must clearly distinguish between:

- tasks requiring WordPress admin access
- tasks requiring Flywheel/hosting access
- tasks requiring Fasthosts DNS access
- tasks requiring AWS account access

## AWS / SES Notes

An AWS account does not yet exist for the client.

Agents may propose:

- account creation steps
- SES identity verification
- DKIM / SPF / DMARC setup
- IAM credential creation
- sending configuration
- complaint/bounce tracking setup

But must not assume credentials exist.

## Subscriber Source Rules

Current marketing signups appear to come from WooCommerce checkout.

Agents should assume the likely path is:

- identify the exact checkout opt-in field
- verify consent wording
- verify where opt-in value is stored
- export or sync only opted-in contacts
- import into FluentCRM
- tag or list them non-destructively
- avoid duplicates

Do not assume all WooCommerce customers should automatically become newsletter recipients.

## Testing Checklist

No email-related work is complete until all applicable checks pass:

- WordPress test email sends
- WooCommerce order emails still work
- transactional sender displays correctly
- marketing sender displays correctly
- Reply-To routes to BTConnect correctly
- checkout signup flow works
- imported subscribers are correct
- unsubscribe works
- no Bricks layout regressions
- no PHP errors or warnings
- no plugin conflicts
- inbox placement is acceptable in at least Gmail and Outlook tests

## Required Response Format for Agent Help

When assisting the developer, respond in this structure:

1. Goal
2. Prerequisites
3. Exact implementation steps
4. Files or settings to edit
5. Testing steps
6. Risks
7. Rollback notes

## Primary Task Context

The current project task is to implement an email solution so that:

- newsletters can be sent weekly
- subscribers can be managed inside WordPress
- emails appear professionally branded to `bewdleyfarmshop.co.uk`
- transactional emails can use `orders@bewdleyfarmshop.co.uk`
- marketing emails can use `hello@bewdleyfarmshop.co.uk`
- replies can still go to `bewdleyfarmshop@btconnect.com`
- WooCommerce transactional emails remain intact
- the solution is scalable and low-cost

## Missing Information Rule

If important technical details are missing, agents should ask concise questions before making assumptions. The highest-priority missing details are:

- exact WooCommerce checkout consent field name
- exact consent wording shown to customers
- where opt-in value is stored
- whether a dedicated staging URL will be used before live rollout
- whether a real mailbox under the domain will ever be needed
