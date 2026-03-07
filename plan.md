## Bewdley Email System Rollout Plan

## Objective

Build a staging-first email system for Bewdley Farm Shop that preserves WooCommerce transactional emails, adds newsletter capability, and stays fully reversible and production-safe.

## Confirmed Assumptions

- Current subscribers came through WooCommerce checkout.
- There are no current popup-origin subscribers to import.
- Popup signup will be implemented as part of the new email setup.
- Policy/ToC acceptance already exists for legacy clients.
- Business decision: proceed with legacy subscriber sending without consent verification as a rollout blocker.

## Non-Negotiable Constraints

- Do not edit WordPress core.
- Do not edit Bricks parent theme.
- Prefer `wp-content/themes/bricks-child/` and custom plugin code in `wp-content/plugins/`.
- Avoid destructive DB operations and bulk deletions.
- Preserve WooCommerce checkout and transactional flow.
- Keep secrets out of code and repo.
- Do not use default PHP mail/hosting mail for newsletter campaigns; use FluentSMTP + SES for marketing sends.

## Phase 0: Safety And Baseline

1. Confirm project guardrails from `AGENTS.md`, `TASKS.md`, and `.github/copilot-instructions.md`.
2. Remove or hard-disable `fw-status-check.php` (plaintext DB credentials + deprecated `mysql_*`).
3. Verify no diagnostics exposing credentials are web-accessible.
4. Create and use a dedicated feature branch for all email-system changes.

## Phase 1: Discovery (Operational Baseline)

1. Audit WooCommerce checkout and legacy field structure for data mapping:

- exact text shown
- checked/unchecked default state
- optional/required behavior

2. Place two test orders (opt-in yes/no) and identify exact consent storage:

- meta key name(s)
- stored value format(s)
- source table/object (order meta, user meta, customer data)

3. Capture current transactional email baseline:

- from name/address
- reply handling
- smoke check delivery to Gmail and Outlook test inboxes

4. Decision gate:

- consent verification is deferred by business decision for historical list sending.
- if key mapping is unclear, proceed with controlled legacy import and enforce unsubscribe/suppression controls.

## Phase 2: Local Build Foundation

1. Install and configure FluentCRM and FluentSMTP locally.
2. Create custom integration plugin (recommended `bewdley-custom`) in `wp-content/plugins/`.
3. Add checkout-to-CRM sync logic for opted-in customers only:

- trigger on suitable order lifecycle event
- idempotent upsert to avoid duplicates
- deterministic tags/lists aligned with `TASKS.md`

4. Implement popup signup feature as new capability:

- Bricks popup/form setup
- explicit marketing consent field
- route submissions to FluentCRM
- deduplicate against existing checkout-imported contacts
- explicit consent capture for all new popup subscribers

## Phase 3: External Dependencies (Parallel)

1. Provision staging site in Flywheel and confirm access.
2. Confirm Fasthosts DNS change path and permissions.
3. Set up AWS SES:

- choose and lock region
- verify domain identity
- enable DKIM
- add SPF and DMARC (monitoring mode initially)
- request production access if sandboxed

4. Create least-privilege IAM sending credentials and store securely outside code.

## Phase 4: Staging Integration

1. Deploy plugin/code changes to staging.
2. Configure FluentSMTP with SES on staging.
3. Validate transactional sender behavior:

- target sender: `orders@bewdleyfarmshop.co.uk`
- preserve WooCommerce transactional flow end-to-end

4. Validate marketing sender behavior:

- target sender: `hello@bewdleyfarmshop.co.uk`
- if dual-sender adds risk, use phased fallback and document it

5. Validate Reply-To behavior:

- `bewdleyfarmshop@btconnect.com`

## Phase 5: Historical Import And Data Quality

1. Export historical subscribers from approved WooCommerce/customer source (legacy client-approved list).
2. Clean and deduplicate data before import.
3. Import into FluentCRM with stable tags/lists.
4. Verify unsubscribe behavior and suppression handling for future sends.
5. Run regression checks for checkout UX and Bricks frontend.

## Phase 6: UAT And Go-Live

1. Execute staging UAT checklist:

- WordPress test email
- WooCommerce order emails
- sender display and Reply-To
- checkout consent path
- popup signup path
- subscriber accuracy and unsubscribe
- no PHP warnings/errors

2. Run deliverability smoke checks in Gmail and Outlook.
3. Warm up the new sending path before full-list newsletters:

- first send: ~50-100 contacts
- second send: ~200-300 contacts
- then move to full list if bounce/complaint rates remain healthy

4. Go live only after staging pass and rollback readiness.
5. Prepare a simple client handover checklist:

- duplicate last campaign
- edit copy/images
- send internal test
- run final pre-send check (links, footer, unsubscribe)
- send campaign

## Phase 7: Post-Go-Live Hardening (Recommended)

1. Implement SES bounce and complaint tracking using SNS topics.
2. Connect SES event destinations to the WordPress/FluentCRM bounce handler flow.
3. Confirm bounced/complaining addresses are suppressed from future sends.
4. Keep a concise weekly newsletter operations runbook up to date.

## Phase 8: Future Feature - Consent Verification And Preference Controls

1. Backfill explicit consent evidence model for historical contacts where practical:

- consent source
- consent timestamp
- consent text/version reference

2. Add preference controls for subscribers:

- marketing opt-in/opt-out state
- channel/list preferences if needed
- self-service unsubscribe and re-subscribe path

3. Enforce explicit-consent capture for all new entry points:

- popup form
- checkout marketing field
- any future landing page forms

4. Add periodic compliance review process:

- quarterly data hygiene and suppression audit
- remove/retag records with unclear consent evidence
- update consent copy when legal text changes

## Verification Gates

1. Security gate: `fw-status-check.php` removed/inaccessible and no plaintext DB creds in tracked files.
2. Discovery gate: exact checkout consent key/value and behavior documented with evidence.
3. Local gate: opted-in orders create/update FluentCRM contacts; opt-out does not subscribe.
4. Popup gate: new popup captures explicit consent and syncs cleanly to FluentCRM.
5. Staging mail gate: SES send path works and WooCommerce transactional flow is preserved.
6. Compliance gate: unsubscribe works; suppression and complaint handling are functional.
7. Release gate: staging UAT completed and signed off.
8. Warm-up gate: staged send batches complete without abnormal bounce/complaint rates.
9. Operations gate: client handover checklist tested and documented.
10. Future feature gate: explicit consent evidence and preference controls are implemented before any policy-tightening phase.

## Files And Areas In Scope

- `AGENTS.md`
- `TASKS.md`
- `.github/copilot-instructions.md`
- `fw-status-check.php`
- `wp-content/themes/bricks-child/functions.php`
- `wp-content/themes/bricks-child/woocommerce/emails/admin-cancelled-order.php`
- `wp-content/plugins/` (new custom integration plugin)

## Exclusions

- WordPress core edits
- Bricks parent theme edits
- WooCommerce core/plugin internals edits
- destructive customer/order/subscriber data operations

## Appendix A: Legacy Plan Notes (No-Loss Archive)

This appendix preserves the full earlier planning notes so everything stays in one document.

Yes. Here is the practical step-by-step implementation plan I would use for this WordPress + Bricks + Local setup, with the assumption that we want a simple client-facing experience, a WordPress-managed email system, and Amazon SES for future-proof sending.
Goal
Set up a newsletter/marketing email system on the WordPress site that:
stores and manages subscribers inside WordPress

sends reliably through Amazon SES

is ready for weekly campaigns

scales beyond 1,000 subscribers without forcing a platform switch

avoids using normal hosting email/PHP mail for bulk sends, which FluentCRM itself advises against for deliverability and hosting resource reasons

Recommended stack
For the build itself:
FluentCRM for subscriber management, campaigns, and automations inside WordPress

FluentSMTP for the sending connection

Amazon SES as the mail infrastructure

DNS authentication using DKIM, SPF, and ideally DMARC for deliverability and domain trust

Phase 1: Pre-flight checks

1. Confirm the sending domain
   Before touching WordPress, decide:
   which domain the emails will come from

which sender address will be used

Best practice:
use something like hello@farmshopdomain.co.uk or news@farmshopdomain.co.uk

do not send campaigns from a personal Gmail address

This matters because Amazon SES requires verified identities, and domain authentication is central to deliverability 2. Confirm DNS access
You will need access to wherever the domain DNS is managed:
Cloudflare

123 Reg

GoDaddy

Fasthosts

Namecheap

hosting DNS panel, etc.

Without DNS access, SES verification and authentication cannot be completed. 3. Decide what data already exists
Check whether the 600 subscribers already exist:
only in WordPress user accounts

in WooCommerce customers

in a form plugin list

in a CSV export

in Mailchimp/Brevo/other legacy tool

in a custom table

This determines import strategy.

Phase 2: Local development prep 4. Build and test on Local first
Since the site is running in Local, use Local for:
plugin installation

CRM structure setup

campaign design

list/tag structure

form placement planning

UI review inside Bricks

Important limitation:
Local cannot be the final sending environment for Amazon SES production sending. The real send tests and DNS-authenticated mail flow must be done on the live/staging domain, because SES identity verification and DKIM/SPF rely on the real domain and public DNS. That part cannot be completed meaningfully in Local alone. This follows from SES identity and authentication requirements. 5. Take a full backup before plugin changes
Before installing anything on the real site later:
full database backup

full files backup

note plugin versions

note current email/contact form behavior

This is especially important if WooCommerce transactional emails are already live.

Phase 3: WordPress CRM setup 6. Install FluentCRM on Local
Inside WordPress:
install FluentCRM

activate it

do the basic setup wizard

define the business name and sender identity placeholders

Purpose:
create the internal subscriber and campaign structure

7. Create the list architecture
   For a local farm shop, keep this simple. I would create:
   Lists
   Newsletter

Customers

Website Signups

Tags
Online Customer

In-Store Customer

Phone Order Customer

Seasonal Offers

Events Interest

Do not over-engineer this. Start with the minimum useful segmentation. 8. Plan automations only if needed
At first, I would keep automations minimal:
optional welcome email after signup

optional tag assignment from signup forms

Weekly newsletters can be sent manually at the beginning. That is simpler and safer.

Phase 4: Subscriber collection and forms 9. Identify existing signup points
Audit all places where subscribers may enter the system:
homepage signup section

footer signup form

checkout opt-in

contact page form

popups, if any

10. Decide the source of truth
    The source of truth should be the WordPress email system you are setting up now, not scattered plugin lists.
11. Connect signup forms
    In Bricks, place the signup forms where needed and connect them to the newsletter system.
    Typical locations:
    homepage hero or below hero

footer

checkout opt-in

seasonal landing pages

12. Add consent language
    Because this is UK email marketing, make sure signup forms include clear consent wording.
    Example:
    “Sign up to receive farm shop updates, seasonal offers and news by email.”
    Do not use pre-ticked consent boxes.

Phase 5: Amazon SES setup 13. Create the AWS account / SES access
Create or log into AWS and open Amazon SES. 14. Choose the SES region
Pick one SES region and stay consistent, because FluentSMTP requires the correct access keys and region to match the SES setup. FluentSMTP’s own documentation calls out From Email, Access Key, Secret Key, and Region as required settings. 15. Verify the domain in Amazon SES
In SES:
create a domain identity

use Easy DKIM

add the DNS records Amazon provides

AWS documents that creating a domain identity includes DKIM-based verification and that Easy DKIM adds DKIM signing for sent mail. 16. Add SPF / custom MAIL FROM if needed
At minimum, complete the recommended authentication records properly.
AWS documents SPF and custom MAIL FROM as part of stronger email authentication and DMARC alignment. 17. Add DMARC
This is strongly recommended, even if starting with monitoring mode.
A basic starting record could be a monitoring policy rather than immediate rejection. AWS notes DMARC works with SPF and DKIM and is best used with both. 18. Request production access if SES is sandboxed
If the account is still in SES sandbox, move it out before real newsletter sending. FluentSMTP’s Amazon SES guide explicitly includes moving out of sandbox mode as part of the setup flow. 19. Create IAM credentials
Create a dedicated IAM user/key pair for SES sending, following the FluentSMTP SES setup path. FluentSMTP documents using Access Key, Secret Key, and region from AWS for the connection.

Phase 6: WordPress mail connection 20. Install FluentSMTP
Install and activate FluentSMTP on the WordPress site. FluentSMTP is specifically designed to intercept wp_mail and connect WordPress to a mail provider for reliable delivery. 21. Connect FluentSMTP to Amazon SES
In FluentSMTP:
choose Amazon SES

enter From Email

enter From Name

paste Access Key

paste Secret Key

choose the SES region

This is the documented connection flow. 22. Send test emails
Send test emails to:
Gmail

Outlook

iCloud if possible

the client’s own mailbox

Check:
inbox vs spam

sender name

branding

link tracking behavior

From address consistency

Phase 7: Link the CRM to the sender 23. Confirm FluentCRM sees the sending connection
FluentCRM’s documentation notes that once FluentSMTP is installed and configured, the available email delivery connection appears inside FluentCRM’s SMTP/email service settings. 24. Set default sender details
Inside the CRM settings:
From Name = Farm shop brand name

From Email = chosen domain email

reply-to address if needed

Keep this brand-consistent.

Phase 8: Import and clean subscriber data 25. Export the current subscriber list
Get the 600 contacts into CSV format if possible.
Minimum fields:
email

first name if available

source if known

consent status if known

26. Clean the data before import
    Remove:
    duplicates

obvious typos

role accounts if inappropriate

unsubscribed contacts from any previous system

addresses without valid consent

27. Import into the CRM
    Import the list into the main newsletter list and apply basic tags where possible.
    Do not dump everyone into complex segments on day one.

Phase 9: Newsletter template build 28. Design one reusable branded template
Create one solid base newsletter template for the client.
Suggested structure for a farm shop:
logo/header

intro message

this week’s highlights

featured products

seasonal news or local updates

order/how-to-buy section

social/contact block

unsubscribe/footer

29. Keep the template lightweight
    Avoid over-designed layouts.
    Use:
    clean single-column design

readable fonts

large buttons

compressed images

mobile-friendly spacing

This improves clickability and deliverability.

Phase 10: Compliance and footer setup 30. Add required footer content
Every campaign should include:
business name

contact details or postal identity

why recipient is receiving the email

unsubscribe link

31. Verify unsubscribe flow
    Test unsubscribe fully.
    This is non-negotiable.
32. Decide whether to set up bounce handling
    If you want a stronger production setup, configure bounce handling as well. FluentCRM documents bounce handling with Amazon SES and notes this requires verified identities and additional setup.
    For launch:
    optional but recommended if you want cleaner long-term list hygiene

Phase 11: Live environment deployment 33. Push from Local to live/staging carefully
Because Local is your build environment, deploy in this order:
plugin stack

CRM configuration

form changes

template

subscriber import

SES connection on live

final test sends

Do not try to finalize SES sending on Local and assume it will translate directly. 34. Test every email path on live
Test:
newsletter form signup

opt-in capture

confirmation/welcome if enabled

WooCommerce transactional emails

contact form emails

campaign send

You want to ensure FluentSMTP does not unintentionally break other mail flows.

Phase 12: First campaign and handover 35. Send an internal test campaign
Before mailing the full list, send to:
yourself

client

one or two extra mailboxes

Check:
subject line

preview text

mobile view

links

images

footer

unsubscribe

36. Send the first real campaign
    Use a small staged rollout if you want to be extra safe:
    send to a segment first

then full list

37. Create a simple client handover
    Give the client a basic non-technical workflow:
    log in

open newsletter section

duplicate last campaign

edit text and images

preview

send

Keep their documentation extremely simple.

Suggested implementation order for you
Here is the shortest correct order:
confirm sender domain and DNS access

install FluentCRM locally

create lists/tags

design signup form locations in Bricks

create reusable newsletter template

prepare CSV/import structure

create AWS SES account and verify domain

add DKIM/SPF/DMARC records

move SES out of sandbox if needed

create IAM credentials

install FluentSMTP on live/staging

connect SES in FluentSMTP

verify WordPress test emails

import subscribers

connect signup forms

test unsubscribe and footer

send internal test campaign

launch first live newsletter

Important implementation notes
Do not send newsletters through normal hosting mail
FluentCRM explicitly warns against using hosting/PHP mail for this because of deliverability and resource concerns.
Domain authentication is not optional
DKIM and SPF are core to inbox placement, and AWS documents both as part of SES authentication. DMARC is strongly recommended on top.
Keep Local for build, not final mail validation
The real deliverability proof only happens on the public domain with live DNS and SES verified identity.

My recommendation for your next move
Start with this exact practical milestone plan:
Milestone 1
Audit current subscriber sources, confirm sender address, confirm DNS access.
Milestone 2
Set up CRM structure and newsletter template in Local.
Milestone 3
Set up Amazon SES, DNS authentication, and WordPress sending connection on live.
Milestone 4
Import the 600 subscribers, test the form flow, and send the first internal campaign.

Why Bounce and Complaint Tracking Matters
When you send emails, three things can happen:
Event
Meaning
Delivery
Email reached the inbox
Bounce
Email address does not exist or mailbox is full
Complaint
Recipient marked the email as spam

If you keep sending to addresses that bounce or complain, email providers will lower your reputation and your emails will start going to spam.
Amazon SES allows you to track these events automatically.

What This Setup Does
Once configured:
Amazon SES reports bounces

Amazon SES reports spam complaints

WordPress / FluentCRM can automatically stop sending to those addresses

This protects your sending reputation.

Step-by-Step Setup (Important)

1. Create an SNS Topic
   In AWS:
   AWS Console
   -> Simple Notification Service (SNS)
   -> Topics
   -> Create topic
   Create two topics:
   ses-bounces
   ses-complaints
   Type: Standard

2. Create Subscriptions
   Inside each topic:
   Create a subscription.
   Protocol:
   HTTPS
   Endpoint:
   Your WordPress site webhook endpoint.
   FluentCRM normally provides a bounce handler endpoint like:
   https://yourdomain.com/?fluentcrm=bounce_handler
   (This depends on configuration.)

3. Connect SNS to Amazon SES
   Go to:
   Amazon SES
   -> Configuration Sets
   -> Event Destinations
   Add:
   Bounce notifications

Complaint notifications

Choose the SNS topics you created.

4. Enable Feedback Forwarding (Optional)
   Inside SES identity settings:
   Disable:
   Email feedback forwarding
   Because SNS will handle it automatically.

Result
Now if someone:
marks the email as spam

has a dead email address

Amazon SES will report it and your system can stop sending to them.
This protects your domain reputation.

Why This Is Critical for Your Client
Your client already has:
600 subscribers
weekly emails
Even 1% spam complaints can hurt deliverability.
Tracking complaints early keeps things clean.

Real-World Example
Without bounce tracking:
You keep sending emails to bad addresses -> reputation drops -> Gmail spam folder.
With bounce tracking:
Bad addresses are removed automatically -> reputation stays high -> inbox delivery improves.

One Extra Tip (Very Important)
Before sending the first real newsletter, do this:
Warm up the sending domain
First campaign:
Send to 50-100 contacts
Second campaign:
Send to 200-300
Third campaign:
Send to the full list
This helps email providers trust the new sending domain.

Final Architecture (Best Practice)
WordPress
↓
FluentCRM
↓
FluentSMTP
↓
Amazon SES
↓
SNS (Bounce & Complaint tracking)
This is a very solid professional email infrastructure.
