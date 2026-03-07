## Email Discovery Checklist (Phase 1)

Use this checklist to collect the minimum facts required before import/sync implementation.

Business note:

- Historical list sending is approved by business decision.
- Consent verification is not a blocker for legacy import at this stage.
- New popup subscribers must still use explicit consent capture.

## 1) Activate Audit Plugin

1. In WordPress admin, go to `Plugins`.
2. Activate `Bewdley Custom`.
3. Go to `Tools > Email Consent Audit`.
4. Record the top candidate keys from:

- `Order Meta (Classic)`
- `Order Meta (HPOS)` (if present)
- `User Meta`

Evidence to capture:

- Candidate meta key names
- Sample values (for yes/no meaning)
- Approximate counts per key

## 2) Checkout Field And UX Audit (Non-Blocking)

1. Open checkout as a customer.
2. Identify newsletter/marketing checkbox or field text.
3. Record (for mapping/reference):

- exact consent wording
- default state (checked/unchecked)
- optional/required state

## 3) Controlled Test Orders

1. Place one test order with consent checked.
2. Place one test order with consent unchecked.
3. Reopen `Tools > Email Consent Audit` and compare key/value changes.

Expected output:

- exact key storing consent
- exact value for opted-in and opted-out

## 4) Transactional Email Baseline

1. Trigger a WooCommerce order email.
2. Record:

- From Name
- From Email
- Reply-To behavior (if visible)
- inbox/spam result in Gmail and Outlook

## 5) Decision Gate

Proceed to import/sync implementation when these are known:

- source table/key mapping is sufficient for extraction
- dedupe approach is defined
- transactional baseline is documented

Consent verification is tracked as follow-up, not a current blocker.

## Notes

- This phase is read-only and non-destructive.
- Historical import can proceed based on approved legacy-client decision.
- Enforce unsubscribe and suppression before first broad campaign.
- Keep explicit consent capture mandatory for new popup subscribers.
