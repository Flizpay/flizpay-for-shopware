---
name: Shopware 6 Payment Plugin - Extension Partner Onboarding (Step 3+) + Review-Run Checklist
purpose: Deterministic checklist for an AI/automation review run before Shopware Store submission.
scope: Shopware 6 **PLUGIN** payment extension (NOT an app).
start_step: 3

# Fill these for the review run
variables:
  COMPANY_PREFIX: "<e.g. ABC>"
  PLUGIN_TECHNICAL_NAME: "<e.g. AbcPaymentGateway>"
  COMPOSER_PACKAGE_NAME: "<e.g. abc/shopware6-payment>"
  EXTENSION_VERSION: "<e.g. 1.2.3>"
  MONETIZED_WITH_FEES: "<true|false>"   # transaction fees / service fees / subscription / downstream costs
  SUPPORT_EMAIL: "<support@company.tld>"
  PRIVACY_POLICY_URL: "<https://...>"
---

# Overview
This document begins at **Step 3** of onboarding (Extension Partner profile) and continues through store submission readiness.

## Assumptions (already done)
- You can log in to your Shopware Account.
- Basic profile completion is done.
- **Master Data** is complete (legal company name, address, contact data, tax/VAT info).

## Intended use in an AI review run
- Treat each section as a checklist.
- For each checkbox, mark **PASS/FAIL** and record **evidence** (file path, screenshot, log snippet, CLI output).

---

# Step 3 - Complete the Extension Partner profile (manufacturer profile)

## Goal
Your **manufacturer / Extension Partner profile** is complete and eligible for store publishing.

## Inputs
- Manufacturer logo (prefer square; clean background)
- Partner description in **English** (EN)
- Partner description in **German** (DE)
- Support contact info (at minimum: email)

## Actions
1. Open: **Shopware Account -> Extension Partner -> Extension Partner profile**.
2. Upload logo.
3. Fill EN and DE descriptions.
4. Add support email and (optional) support hours.

## Evidence to capture
- Screenshot showing profile status + logo + EN + DE text.

## PASS criteria
- Logo present
- EN + DE descriptions present
- Company details match Master Data (legal name alignment)

## FAIL criteria
- Missing logo
- Only one language provided
- Inconsistent legal company name

---

# Step 4 - Choose the Extension Partner prefix (critical)

## Goal
Set a **prefix** that identifies your company across extensions and matches your plugin naming.

## Why this matters
- The prefix affects technical identifiers and is difficult to change later.

## Prefix selection rules (recommended)
- Short (3-6 letters)
- Unique and brand-aligned
- Not generic (avoid PAY/SHOP/etc.)
- Consistent with:
  - `COMPANY_PREFIX`
  - plugin technical name
  - Composer vendor/package naming

## Actions
1. Decide the final prefix.
2. Align naming:
   - `PLUGIN_TECHNICAL_NAME` includes the prefix (store publishing expectation).
   - Composer vendor/package name is consistent with your brand/vendor.

## Evidence to capture
- The chosen prefix as displayed in the Shopware Account.
- Plugin repository evidence:
  - plugin root folder name
  - technical name in `composer.json` / plugin class naming

## PASS criteria
- Prefix is set and consistent across account + codebase naming.

## FAIL criteria
- Prefix differs from plugin naming
- Prefix conflicts with another vendor

---

# Step 5 - Accept the Extension Partner contract and obtain approval

## Goal
The Extension Partner contract is accepted by you and approved/accepted by Shopware.

## Actions
1. In the Extension Partner area, locate the contract.
2. Review and accept.
3. Verify Shopware approval/acceptance status.

## Evidence to capture
- Screenshot showing contract status (accepted/approved).

## PASS criteria
- Status indicates Shopware acceptance/approval.

## FAIL criteria
- Contract accepted by you but still pending Shopware approval (publishing options blocked).

---

# Step 6 - Configure commission payout details

## Goal
Shopware can pay out commissions for store sales.

## Actions
1. Open: **Commissions -> Change commission account**.
2. Enter payout recipient + payment/bank details.
3. Save.

## Evidence to capture
- Screenshot confirming commission payout details are configured (redact sensitive numbers).

## PASS criteria
- Payout method/account configured.

## FAIL criteria
- Missing/invalid payout details
- Recipient name does not match legal entity

---

# Payment-plugin gate - Technology partner agreement (may be required)

## When to set `MONETIZED_WITH_FEES = true`
If your payment plugin involves ANY of:
- transaction fees
- service fees
- subscriptions
- downstream costs charged to merchants

## Action
- Start/confirm the technology partner agreement process early.

## Review-run output
- If `MONETIZED_WITH_FEES = true`, record:
  - contact initiated (yes/no)
  - agreement status (unknown/pending/active)

---

# Store listing and compliance data (prepare BEFORE upload)

## 1) Required/expected listing assets
- Manufacturer logo (Step 3)
- Extension preview icon: `src/Resources/config/plugin.png` (112x112)
- Store images (recommended; localize if you localize marketing assets)

### Evidence
- `src/Resources/config/plugin.png` exists and is 112x112
- Store media folders (if used) exist and are populated

## 2) Listing text
- Extension description in EN and DE (recommended for acceptance)
- Clear feature list
- Clear setup instructions
- Clear support contact (email + how to reach support)

## 3) Data protection (privacy / GDPR)
For payment plugins, assume personal data is processed.
Prepare:
- `PRIVACY_POLICY_URL`
- Subprocessor list (who else touches data)

### Typical subprocessors for payments (examples)
- payment gateway / acquirer
- backend hosting provider (if you run an API)
- fraud/risk scoring provider (if used)
- ticketing/support platform (if it processes merchant/customer data)

---

# Developer implementation checklist (payment plugin specific)

## A) Payment handler implementation
- Implement a payment handler using the current Shopware 6 payment handling approach.
- Register the handler service with the `shopware.payment.method` tag.

### Evidence
- `services.xml` / `services.yaml` snippet showing the service tag.
- Handler class path and name.

## B) Install/uninstall lifecycle
- Install: create/register the payment method.
- Uninstall: **do not delete** the payment method; deactivate it to avoid order consistency issues.

### Evidence
- Installation code (installer) creates payment method.
- Uninstall code deactivates payment method (no hard deletion).

## C) Transaction + state handling
- Transaction state transitions reflect real outcomes:
  - success -> paid
  - user cancel -> cancelled
  - failure/decline -> failed (or appropriate)
- Idempotent handling for repeated callbacks.

### Evidence
- Unit/integration test references OR manual test notes with logs.

## D) Logging and secret handling
- Never log card data, API secrets, tokens.
- Log enough context for debugging (order/transaction IDs) without sensitive payloads.

---

# Packaging and pre-submission checks (recommended for review run)

## Recommended tool: shopware-cli

## A) Build assets
```bash
shopware-cli extension build <path-to-extension>
```

## B) Create store-ready zip
```bash
shopware-cli extension zip <path-to-extension>
```

## C) Validate
```bash
shopware-cli extension validate <path-or-zip>
# optionally for CI: shopware-cli extension validate --full <path-or-zip>
```

## D) Changelog requirement
- Ensure the zip contains a `CHANGELOG*.md` with an entry for `EXTENSION_VERSION`.

## PASS criteria
- build succeeds
- zip is generated
- validate passes (no critical failures)
- changelog present and updated

## FAIL criteria
- missing `plugin.png`
- missing changelog entry for the uploaded version
- validate errors

---

# Upload workflow (CLI-oriented)

## Authenticate
```bash
shopware-cli account login
```

## Upload the release
```bash
shopware-cli account producer extension upload <zip-path>
```

## Optional: sync store listing metadata
```bash
shopware-cli account producer extension info pull <path-to-extension>
shopware-cli account producer extension info push <path-to-extension>
```

---

# AI review-run checklist (copy/paste)

## Account readiness
- [ ] Step 3: Extension Partner profile complete (logo + EN + DE)  
      Evidence: ______________________________
- [ ] Step 4: Vendor prefix chosen and consistent (`COMPANY_PREFIX`)  
      Evidence: ______________________________
- [ ] Step 5: Contract accepted and approved by Shopware  
      Evidence: ______________________________
- [ ] Step 6: Commission payout configured  
      Evidence: ______________________________
- [ ] If monetized (`MONETIZED_WITH_FEES=true`): tech partner agreement status recorded  
      Evidence: ______________________________

## Store compliance
- [ ] `src/Resources/config/plugin.png` exists and is 112x112  
      Evidence: ______________________________
- [ ] Store listing text prepared in EN + DE  
      Evidence: ______________________________
- [ ] Privacy policy + subprocessors prepared  
      Evidence: ______________________________

## Code behavior (payment)
- [ ] Payment handler registered (`shopware.payment.method` tag)  
      Evidence: ______________________________
- [ ] Install adds payment method  
      Evidence: ______________________________
- [ ] Uninstall deactivates (does NOT delete) payment method  
      Evidence: ______________________________
- [ ] Transaction state transitions correct + idempotent  
      Evidence: ______________________________
- [ ] Logging does not leak secrets/sensitive data  
      Evidence: ______________________________

## Packaging
- [ ] `shopware-cli extension build` passes  
      Evidence: ______________________________
- [ ] `shopware-cli extension zip` generates store-ready archive  
      Evidence: ______________________________
- [ ] `shopware-cli extension validate` passes  
      Evidence: ______________________________
- [ ] `CHANGELOG*.md` present and includes `EXTENSION_VERSION` entry  
      Evidence: ______________________________
