# Phase 3 Runbook: AWS SES Setup (Staging-First)

## Purpose

This runbook is the detailed implementation guide for Phase 3 (External Dependencies), focused on Amazon SES setup for `bewdleyfarmshop.co.uk`, DNS authentication, and staging validation.

Use this on staging first. Do not switch live sending until all verification gates pass.

## Scope

This runbook covers:

- choosing and locking SES region
- verifying SES domain identity
- enabling DKIM
- adding SPF and DMARC records
- requesting SES production access if sandboxed
- creating least-privilege SMTP credentials
- configuring FluentSMTP on staging
- validating delivery and sender behavior

This runbook does not cover final live cutover.

## Environment Targets

- WordPress staging URL: `https://bfs.flywheelstaging.com`
- Sender domain: `bewdleyfarmshop.co.uk`
- Transactional sender target: `orders@bewdleyfarmshop.co.uk`
- Marketing sender target: `hello@bewdleyfarmshop.co.uk`
- Reply-To target: `bewdleyfarmshop@btconnect.com`
- DNS provider: Fasthosts
- Hosting: Flywheel

## Prerequisites

1. AWS account is created and accessible.
2. MFA is enabled on root/admin AWS users.
3. Fasthosts DNS access is confirmed.
4. Staging site is accessible and stable.
5. `FluentSMTP` is installed/active on staging.

## Step 1: Choose and Lock SES Region

1. Sign in to AWS Console.
2. Open Amazon SES.
3. Choose one region and keep it consistent for all SES assets.
4. Record chosen region in project notes.

Recommended: pick one EU region for this UK business and do not mix regions.

Evidence to capture:

- chosen region
- screenshot of SES console region selector

## Step 2: Create SES Domain Identity

1. In SES, go to `Configuration` -> `Identities`.
2. Click `Create identity`.
3. Select identity type `Domain`.
4. Enter domain: `bewdleyfarmshop.co.uk`.
5. Enable `Easy DKIM`.
6. Complete creation.

SES will generate DNS records (verification and DKIM).

Evidence to capture:

- identity status page showing pending verification
- list of required DNS records

## Step 3: Add SES DNS Records in Fasthosts

1. Log in to Fasthosts DNS manager for `bewdleyfarmshop.co.uk`.
2. Add all SES-provided records exactly as shown (host/name/value/type).
3. Keep TTL default or 300 seconds if allowed.

### Required record groups

1. SES verification record (typically TXT)
2. DKIM records (typically 3 CNAME records)

Do not alter trailing dots unless Fasthosts UI requires removal.

Evidence to capture:

- screenshot of each record added in Fasthosts
- timestamp of changes

## Step 4: Ensure SPF and DMARC Are Present

### SPF

1. Find existing SPF TXT record on root (`@`).
2. If no SPF record exists, add:

`v=spf1 include:amazonses.com ~all`

3. If SPF already exists, merge `include:amazonses.com` into existing SPF record.
4. Never publish multiple SPF TXT records for the same hostname.

### DMARC (monitoring mode first)

Add TXT record on `_dmarc` with a monitoring policy:

`v=DMARC1; p=none; rua=mailto:bewdleyfarmshop@btconnect.com; fo=1`

You can tighten policy later after stable sending and reporting.

Evidence to capture:

- final SPF value
- final DMARC value

## Step 5: Wait for DNS Propagation and Verify in SES

1. Return to SES identity screen.
2. Refresh until:

- identity status = `Verified`
- DKIM status = `Verified`

3. If not verified after expected propagation window:

- re-check record typos
- re-check hostnames and record type
- verify no duplicate conflicting records

Gate to proceed:

- domain verified
- DKIM verified

## Step 6: Check SES Account State (Sandbox vs Production)

1. In SES, confirm account sending status for chosen region.
2. If sandboxed, submit production access request.
3. Use a clear business case:

- WooCommerce transactional emails
- weekly farm shop newsletters
- permission-based list management

Do not proceed to broad sends while sandboxed.

Evidence to capture:

- sandbox/production status
- support case ID if submitted

## Step 7: Create Least-Privilege SMTP Credentials

1. In SES, open SMTP settings and create SMTP credentials.
2. Follow SES flow to create IAM user for SMTP sending.
3. Store credentials in secure password manager.
4. Do not commit credentials to git or hardcode in plugin files.

Capture securely:

- SMTP username
- SMTP password
- SES SMTP host for selected region
- SES port to use (587 TLS recommended)

## Step 8: Configure FluentSMTP on Staging

1. Open staging WP admin: `https://bfs.flywheelstaging.com/wp-admin`.
2. Go to `FluentSMTP` settings.
3. Select mailer: Amazon SES SMTP path (or SES API if intentionally chosen).
4. Enter:

- SMTP host (region-specific SES endpoint)
- port (587)
- encryption TLS
- SMTP username/password
- from email (start with transactional identity)
- from name

5. Save settings.

Recommended initial sender settings:

- From Email: `orders@bewdleyfarmshop.co.uk`
- From Name: `Bewdley Farm Shop`
- Reply-To: `bewdleyfarmshop@btconnect.com`

## Step 9: Run Staging Mail Validation

1. Send FluentSMTP test email to:

- one Gmail inbox
- one Outlook inbox

2. Place one staging WooCommerce test order.
3. Confirm transactional email is received.
4. Validate headers and sender behavior:

- from address correct
- reply-to correct
- no obvious spam warnings

If successful, test marketing sender profile separately:

- From Email: `hello@bewdleyfarmshop.co.uk`
- same Reply-To
- send a small internal campaign test only

## Step 10: Stage 3 Completion Gate

Stage 3 is complete only when all are true:

1. Staging site stable and operational.
2. SES region selected and locked.
3. SES domain identity verified.
4. DKIM verified.
5. SPF record valid and singular.
6. DMARC monitoring record live.
7. SMTP credentials created and stored securely.
8. FluentSMTP test pass to Gmail and Outlook.
9. WooCommerce transactional test email pass on staging.

## Troubleshooting

### Identity not verifying

- Re-check DNS record names and values exactly.
- Ensure records are on correct domain zone.
- Confirm no extra quotes/whitespace.
- Confirm only one SPF record exists.

### Staging sends fail

- Re-check SES region in SMTP host.
- Re-check SMTP username/password.
- Re-check firewall/host restrictions.
- Review FluentSMTP logs and Flywheel logs.

### Gmail/Outlook spam placement

- verify DKIM/SPF/DMARC status
- reduce send volume during initial warm-up
- ensure sender/subject consistency
- avoid link-heavy or image-only content

## Security Rules

1. Never paste SMTP or IAM secrets into repo files.
2. Never share secrets in public tickets/screenshots.
3. Rotate SMTP credentials if exposed.
4. Use MFA on all AWS admin users.

## Audit Log Template (Fill During Execution)

- SES region selected:
- SES identity created at:
- DNS records added at:
- Identity verified at:
- DKIM verified at:
- SES sandbox status:
- SMTP credentials created at:
- FluentSMTP configured on staging at:
- Gmail test result:
- Outlook test result:
- Woo transactional test result:
- Stage 3 gate status: Pass / Blocked

## Stage 3 Closure (2026-03-09)

- SES region selected: `eu-west-2`
- SES sandbox status: Production access granted
- SMTP credentials created: Yes (stored securely)
- FluentSMTP configured on staging: Yes
- Gmail test result: Pass
- Outlook test result: Pass
- Woo transactional test result: Pass
- Stage 3 gate status: Pass

## Handover Note

Stage 3 is complete. Proceed with Phase 4 staging integration closure tasks and Phase 5 historical import/data quality, then run Phase 6 UAT before any production mail cutover.
