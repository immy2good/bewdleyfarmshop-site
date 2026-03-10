# TASKS.md

## Objective

Set up a WordPress-based email system for Bewdley Farm Shop that:

- preserves WooCommerce transactional emails
- adds a scalable newsletter system
- imports only consented subscribers
- uses branded domain-based senders
- routes replies to BTConnect
- is tested first on staging

## Branching And Deployment Workflow Decisions (2026-03-10)

Confirmed:

- `main` is the live/production branch
- `dev` is the development branch

Pending decision:

- choose a permanent branch strategy for staging sync and staging-bound work

Interim rule (active now):

- when pulling from Flywheel staging via Local, use a temporary sync branch created from `dev`
- example naming: `sync/staging-YYYY-MM-DD`
- do conflict resolution and validation on the sync branch before merging back into `dev`

## Current Status Snapshot (2026-03-09)

Phase/stage:

- Stage 3 closeout complete; staging mail validation passed

Confirmed complete:

- SES production access granted
- SES SMTP credentials created and stored securely
- FluentSMTP configured on staging
- Gmail and Outlook test sends passed
- WooCommerce transactional email test passed

Next active phase:

- Phase 7: Signup capture going forward
- Next task: Task 7.1 - Connect checkout opt-in to FluentCRM

Immediate next actions:

- verify checkout opt-in consent key/value mapping in production flow
- confirm opted-in orders create/update a single FluentCRM contact
- confirm non-opted-in orders do not subscribe contacts

---

# Phase 1: Discovery and audit

## Task 1.1 - Verify checkout consent wording

Goal:
Confirm that checkout newsletter consent is explicit enough for marketing use.

Actions:

- Go to WooCommerce checkout page in WordPress and complete a test checkout flow
- Capture the exact consent wording shown to customers
- Confirm whether checkbox is optional or pre-checked
- Confirm whether wording clearly refers to marketing/newsletters/offers

Output:

- exact consent text
- screenshot or note
- whether consent is acceptable for email marketing import

## Task 1.2 - Find where the consent value is stored

Goal:
Identify the exact field/meta key used for the newsletter opt-in.

Actions:

- Place a test order with opt-in checked
- Place another test order with opt-in unchecked
- Inspect order meta, customer meta, user meta, and checkout field storage
- Search in database or with code safely if needed
- Determine exact key name and stored values

Possible locations:

- order meta
- user meta
- customer meta
- custom plugin field
- WooCommerce checkout custom field

Output:

- meta key / field name
- stored values for yes/no
- whether this can be queried reliably

## Task 1.3 - Identify current transactional sender behavior

Goal:
Document how emails currently appear to customers.

Actions:

- Trigger a WooCommerce test order email
- Note current From Name
- Note current From Email
- Note Reply-To behavior if visible
- Check inbox vs spam in Gmail and Outlook

Output:

- baseline transactional email behavior
- before-change reference

---

# Phase 2: Local setup

## Task 2.1 - Create git branch

Goal:
Isolate email system work.

Suggested branch:

- `feature/email-system-setup`

## Task 2.2 - Install required plugins locally

Goal:
Prepare the local environment.

Plugins:

- FluentCRM
- FluentSMTP

Actions:

- install and activate both locally
- do not remove existing plugins
- document any visible conflicts

## Task 2.3 - Define CRM structure

Goal:
Create a minimal, practical marketing structure.

Suggested initial lists:

- Newsletter
- Customers

Suggested initial tags:

- Woo Checkout Opt-In
- Existing Customer
- Manual Signup
- Seasonal Offers

Actions:

- create only what is necessary
- avoid over-segmentation

## Task 2.4 - Prepare newsletter template

Goal:
Build one reusable farm shop newsletter template.

Suggested sections:

- logo/header
- short intro
- featured products
- seasonal offer block
- call to order
- contact info
- unsubscribe footer

Actions:

- keep layout simple and mobile-friendly
- use existing brand style
- do not over-design

---

# Phase 3: Staging preparation

## Task 3.1 - Create staging site

Goal:
Do not test SES for the first time on production.

Actions:

- create staging via Flywheel
- confirm WordPress admin access
- confirm staging URL
- push local changes when ready

## Task 3.2 - Confirm DNS access path

Goal:
Prepare for SES verification.

Actions:

- log into Fasthosts
- confirm ability to add TXT/CNAME/MX if needed
- document where records will be added

## Task 3.3 - Validate Flywheel SSH access (if enabled)

Goal:
Confirm whether SSH can be used for staging diagnostics and deployment support.

Provided connection string:

- `ssh bewdleyfarmshop90+bewdley-farm-shop@ssh.getflywheel.com`

Actions:

- verify SSH is enabled for the target environment (staging first)
- validate key-based access and shell login success
- confirm allowed commands and filesystem scope
- document any SSH restrictions and safe usage boundaries

Output:

- SSH availability status by environment
- confirmed diagnostic workflows that can use SSH

---

# Phase 4: AWS / SES setup

## Task 4.1 - Create AWS account for client

Goal:
Set up a dedicated sending infrastructure.

Actions:

- create AWS account
- enable MFA
- store credentials securely
- use a shared client-safe access process

## Task 4.2 - Set up Amazon SES

Goal:
Prepare domain-authenticated sending.

Actions:

- open Amazon SES
- choose a region and keep it consistent
- verify domain identity for `bewdleyfarmshop.co.uk`
- enable Easy DKIM
- collect DNS records required by SES

Output:

- SES domain identity created
- DKIM records ready

## Task 4.3 - Add DNS authentication records

Goal:
Authenticate sending domain.

Actions:

- add SES-provided DNS records in Fasthosts
- add SPF if needed
- add DMARC record in monitoring mode first

Output:

- domain verification in SES
- DKIM passing
- SPF/DMARC present

## Task 4.4 - Move SES out of sandbox

Goal:
Allow real sending.

Actions:

- submit production access request if sandboxed
- explain business use as legitimate transactional + newsletter sending

## Task 4.5 - Create IAM credentials for sending

Goal:
Generate credentials for FluentSMTP.

Actions:

- create least-privilege IAM user for SES sending
- store key and secret securely
- do not hardcode in files

---

# Phase 5: Staging WordPress mail configuration

## Task 5.1 - Configure FluentSMTP on staging

Goal:
Connect WordPress sending to SES.

Actions:

- install/activate FluentSMTP on staging
- connect using SES credentials
- set From Name
- set From Email
- send test emails

## Task 5.2 - Set transactional sender

Goal:
Brand WooCommerce emails properly.

Preferred:

- From Email: `orders@bewdleyfarmshop.co.uk`
- From Name: `Bewdley Farm Shop`

Reply-To:

- `bewdleyfarmshop@btconnect.com` if needed

Actions:

- configure sender behavior
- test WooCommerce order emails

## Task 5.3 - Set marketing sender

Goal:
Separate marketing branding from order emails if supported by final setup.

Preferred:

- From Email: `hello@bewdleyfarmshop.co.uk`
- From Name: `Bewdley Farm Shop`

Reply-To:

- `bewdleyfarmshop@btconnect.com`

Actions:

- configure within FluentCRM where possible
- verify outgoing campaign preview

Note:
If the final configuration cannot safely split senders without unnecessary complexity, keep one sender temporarily and document it.

---

# Phase 6: Subscriber import logic

## Task 6.1 - Build safe export of opted-in customers

Goal:
Export only customers with valid consent.

Actions:

- query WooCommerce data using confirmed opt-in field
- export email, first name, last name if available
- exclude unsubscribed / non-opted-in records
- deduplicate list before import

Output:

- clean CSV of consented contacts

Status update (2026-03-09):

- skipped by project decision (historical import not required in current rollout)

## Task 6.2 - Import into FluentCRM

Goal:
Seed the newsletter system.

Actions:

- import clean CSV
- assign Newsletter list
- tag as Woo Checkout Opt-In
- verify sample records after import

Status update (2026-03-09):

- skipped by project decision (depends on Task 6.1 historical export)

## Task 6.3 - Avoid duplicate capture

Goal:
Prevent duplicate contact creation.

Actions:

- check FluentCRM duplicate handling
- document how repeated checkout signups will behave

---

# Phase 7: Signup capture going forward

## Task 7.1 - Connect checkout opt-in to FluentCRM

Goal:
Ensure future opted-in customers flow into the newsletter system automatically.

Status (2026-03-09): COMPLETE

- Block checkout opt-in field registered via WooCommerce Additional Fields API
- Classic checkout fallback also in place
- Checkbox pre-ticked by default, customer can uncheck
- Consent stored as `_bewdley_marketing_optin` = `yes` / `no` on order meta
- Validated on staging: opted-in orders sync to FluentCRM, non-opted-in do not

## Task 7.2 - Optional additional signup form

Goal:
Allow newsletter signups outside checkout if desired later.

Actions:

- decide whether homepage/footer form is needed
- if yes, place via Bricks with clear consent copy
- connect to Newsletter list

---

# Phase 8: Deliverability protection

## Task 8.1 - Configure bounce/complaint awareness

Goal:
Protect domain reputation.

Actions:

- review SES complaint and bounce options
- add SNS-based complaint handling if feasible in this phase
- otherwise document as phase-2 enhancement

## Task 8.2 - Warm up sending

Goal:
Avoid blasting full list from a fresh sending setup.

Suggested rollout:

- send first campaign to small internal/test group
- then a limited segment
- then full list

---

# Phase 9: Testing

## Task 9.1 - Transactional testing

Test:

- new order email
- customer processing/completed order email
- password reset email
- contact form / admin notification if relevant

Verify:

- sender branding
- reply path
- inbox placement
- formatting intact

## Task 9.2 - Marketing testing

Test:

- test campaign to internal addresses
- sender appears correctly
- Reply-To goes to BTConnect
- unsubscribe link works
- mobile rendering is good
- Gmail and Outlook placement acceptable

## Task 9.3 - Data testing

Test:

- only opted-in contacts imported
- checkout opt-in creates or updates contact correctly
- no duplicate explosions
- no non-consented customers imported

---

# Phase 10: Production rollout

## Task 10.1 - Push validated setup to live

Goal:
Deploy only after staging passes.

Actions:

- back up live site
- deploy plugin/config/code changes carefully
- re-test live with internal emails before full send

## Task 10.2 - Launch first real campaign

Goal:
Controlled first send.

Actions:

- use final approved template
- send to limited batch first if prudent
- monitor complaints, bounces, and inbox placement
- then expand to full list

---

# Open items still requiring confirmation

1. exact checkout consent wording
2. exact opt-in meta key / storage location
3. whether separate transactional and marketing senders are fully supported in chosen configuration without extra complexity
4. whether homepage/footer signup form should be added later
