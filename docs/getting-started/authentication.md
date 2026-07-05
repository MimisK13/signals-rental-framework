---
title: Authentication & Settings
description: Log in, reset your password, manage profile settings, configure SSO, and use magic-link login.
---

## Overview

Signals Rental Framework uses Laravel's built-in authentication with Sanctum for API tokens. All authentication pages are accessible without logging in, while settings pages require an active session.

## Login

Visit `/login` to sign in with your email and password. Check **Remember me** to stay signed in across browser sessions.

![The Signals login page](/docs/images/login.png)

Login is rate-limited to 5 attempts per minute per email/IP combination. After exceeding this limit, you'll be asked to wait before trying again.

## Forgot Password

If you've forgotten your password, click **Forgot your password?** on the login page or visit `/forgot-password` directly.

![The forgot password page](/docs/images/forgot.png)

Enter your email address and Signals will send a password reset link. For security, the same confirmation message is shown regardless of whether the email exists in the system.

The reset link expires after 60 minutes. Click the link in the email to set a new password with a minimum of 8 characters.

## Email Verification

When email verification is enabled, new users and users who change their email address will be prompted to verify before continuing. A verification email is sent automatically, and can be resent from the verification page.

## Two-Factor Authentication

Two-factor authentication (2FA) adds an extra layer of security by requiring a time-based one-time code from an authenticator app in addition to your password.

### Enabling 2FA

1. Go to **Settings → Profile** and scroll to the **Two-Factor Authentication** section.
2. Click **Enable** and confirm your password.
3. Scan the QR code with your authenticator app (Google Authenticator, Authy, 1Password, etc.), or enter the setup key manually.
4. Enter the 6-digit code from your app to confirm setup.

### Recovery Codes

After confirming 2FA, you'll be shown 8 recovery codes. Save these somewhere secure — they are the only way to regain access if you lose your authenticator app. Each code can only be used once.

You can view your current recovery codes or generate a fresh set from the 2FA section at any time.

### Login with 2FA

When 2FA is enabled, after entering your email and password you'll be redirected to a verification page. Enter the 6-digit code from your authenticator app to complete sign-in.

If you don't have access to your authenticator app, click **Use a recovery code instead** and enter one of your saved recovery codes.

### Disabling 2FA

Click **Disable** in the Two-Factor Authentication section and confirm your password. This immediately removes 2FA from your account.

## Settings

Once logged in, access your account settings from the user dropdown in the top-right corner. Settings are organised into three tabs.

![The settings page showing profile, password, and appearance tabs](/docs/images/settings.png)

### Profile

Update your display name and email address. If you change your email, you'll need to re-verify it before the change takes full effect.

The Profile page also contains the **Two-Factor Authentication** section (see above) and the option to **delete your account** — this permanently removes your user and all associated data. You must confirm with your current password.

### Password

Change your password by entering your current password and choosing a new one (minimum 8 characters). The new password must be confirmed.

### Appearance

Switch between **Light**, **Dark**, and **System** themes. The System option follows your operating system's preference and updates automatically.

## Password Confirmation

Certain sensitive actions require you to re-enter your password, even while logged in. This confirmation is cached in your session and won't be asked again for a short period.

## Logout

Log out from the user dropdown menu in the header. This ends your session and redirects you to the login page.

## Single Sign-On (SSO)

## Overview

**Single Sign-On (SSO)** lets your staff log in to Signals using their existing **Google** or **Microsoft 365** account instead of — or in place of — a password. SSO is a login-only feature: it does not create new user accounts. The member (user) record must already exist in Signals, and only users whose email address is matched to an active account will be allowed in. Unknown or unverified emails are denied.

SSO is powered by **Laravel Socialite** with the `google` and `microsoft` (Azure, `common` tenant) providers. Configuration and enforcement are controlled in two places:

| Setting location | Purpose |
|-----------------|---------|
| **Settings → Integrations → Single Sign-On** | Enable providers and supply OAuth credentials (self-hosted) |
| **Settings → Security → SSO Enforcement** | Require specific roles to use SSO instead of a password |

## How SSO Login Works

When a user clicks **Continue with Google** or **Continue with Microsoft** on the login page:

1. They are redirected to the provider's OAuth consent screen.
2. After they approve, the provider sends them back to Signals at `/auth/{provider}/callback`.
3. Signals checks that the returned email address is **verified** by the provider.
4. The verified email is matched against an existing, active Signals user:
   - **Matched** — the account is linked (or an existing link is reused) and the user is signed in.
   - **No match** — login is denied. The user must be invited and have an active account before using SSO.
5. If the user has **Two-Factor Authentication (2FA)** enabled, they are prompted for their one-time code before the login completes, exactly as with password login.

> **Note:** SSO replaces only the password step. 2FA is still required if enabled on the account.

## Enabling SSO — Self-Hosted

On a self-hosted Signals install, you register OAuth credentials with each provider and enter them in **Settings → Integrations → Single Sign-On**.

### Configuring Google

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and create (or select) a project.
2. Go to **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
3. Choose **Web application** as the application type.
4. Under **Authorised redirect URIs**, add:
   ```
   https://<your-domain>/auth/google/callback
   ```
5. Copy the **Client ID** and **Client Secret**.
6. In Signals, go to **Settings → Integrations → Single Sign-On → Google**.
7. Toggle **Enable Google login** on, paste in the Client ID and Client Secret, and save.

### Configuring Microsoft 365

1. Open the [Azure portal](https://portal.azure.com/) and go to **Azure Active Directory → App registrations → New registration**.
2. Under **Supported account types**, choose **Accounts in any organizational directory and personal Microsoft accounts** (the `common` tenant — allows any work, school, or personal Microsoft account; access is still gated by email-matching in Signals).
3. Under **Redirect URI**, select **Web** and enter:
   ```
   https://<your-domain>/auth/microsoft/callback
   ```
4. After registering, go to **Certificates & secrets → New client secret**. Copy the value immediately — it is not shown again.
5. Copy the **Application (client) ID** from the app's Overview page.
6. In Signals, go to **Settings → Integrations → Single Sign-On → Microsoft**.
7. Toggle **Enable Microsoft login** on, paste in the Client ID and Client Secret, and save.

> **Note:** Client secrets are **write-only**. They are stored encrypted at rest, are never returned in API responses, and are **never loaded back into the settings form** after saving — the field renders blank with a "Configured — leave blank to keep" indicator. Saving with the secret field left blank keeps the existing secret unchanged. To rotate a secret, paste the new value and save — the previous secret is overwritten.

## Allowed Email Domains

The **Allowed email domains** field (under **Settings → Integrations → Single Sign-On**) restricts which email domains may sign in via SSO. Enter your owned domains one per line (commas and spaces also work, e.g. `example.com`); the list is normalised to lowercase and de-duplicated on save.

When the list is **non-empty**, only users whose IdP email domain is on the list may sign in or be auto-linked — and this gate applies on **every** SSO login, including users who already have a linked identity (so a domain you later remove can no longer sign in). When the list is **empty (the default), any email domain is permitted.**

> **Strongly recommended for Microsoft.** Signals registers Microsoft with the multitenant `common` setting, so any work, school, or personal Microsoft account can complete the OAuth flow. The email claim returned by Microsoft is **not a trustworthy identity across arbitrary Microsoft tenants** (the "nOAuth" class of cross-tenant account-takeover issues). Configuring an allow-list of **your owned domains** closes this gap by ensuring only emails on domains you control can match a Signals account. Leaving the allow-list empty allows any domain and is not recommended when Microsoft sign-in is enabled.

The allow-list is **policy, not a credential**, so it is configurable on both self-hosted and Signals Cloud installs.

## Enabling SSO — Signals Cloud

On **Signals Cloud**, OAuth credentials are managed centrally by Signals. The Integrations settings page shows **only enable/disable toggles** for each provider — there are no credential fields. Flip the toggle for the provider you want to make available and save.

## Email Matching and Auto-Link

SSO login matches the verified email returned by the provider to an existing, active Signals user. If a match is found, an identity link is created automatically on first sign-in — no further action is required. Subsequent logins use the stored identity link directly, so login still works even if the user's email address later changes in Signals (the link persists by provider identity).

Reasons login will be denied:

| Reason | Resolution |
|--------|-----------|
| No Signals user with that email | [Invite the user](/docs/getting-started/authentication) to create their account first |
| User account is inactive | Reactivate the account in **Admin → Users** |
| Provider did not return a verified email | Use a provider account with a verified email address |
| Email domain not on the allow-list | Add the domain in **Settings → Integrations → Single Sign-On → Allowed email domains**, or sign in with an account on a permitted domain |
| The provider is disabled or not configured | Enable it in **Settings → Integrations → Single Sign-On** |

## Enforcing SSO per Role

Admins can require that members of specific roles use SSO and cannot sign in with a password. This is configured in **Settings → Security → SSO Enforcement**.

Select one or more roles from the **Require SSO for these roles** multiselect. Members with any of the selected roles will be blocked from password login and shown the SSO buttons with a message explaining why their password was not accepted.

**The Owner role is always exempt** from enforcement — this is a break-glass guarantee that prevents a complete lockout if SSO becomes unavailable. The Owner role cannot be selected in the enforcement picker.

> **Tip:** If you want to test enforcement before rolling it out broadly, add a low-privilege role first and verify the flow with a test user.

## Two-Factor Authentication and SSO

SSO does not bypass 2FA. If a user has 2FA enabled on their Signals account, they are redirected to the standard one-time code challenge after a successful SSO callback and must enter their code before the login completes.

Users can manage 2FA from **Settings → Profile → Two-Factor Authentication** regardless of whether they use SSO or password login.

## Permissions

SSO configuration requires administrator access. No new permissions are introduced — the Integrations and Security settings pages are gated on the existing admin/owner access model.

## Settings reference

| Setting key | Type | UI location |
|-------------|------|-------------|
| `sso.google_enabled` | bool | Settings → Integrations → Single Sign-On |
| `sso.microsoft_enabled` | bool | Settings → Integrations → Single Sign-On |
| `sso.google_client_id` | encrypted string (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.google_client_secret` | encrypted, write-only (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.microsoft_client_id` | encrypted string (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.microsoft_client_secret` | encrypted, write-only (self-hosted only) | Settings → Integrations → Single Sign-On |
| `sso.allowed_email_domains` | array (policy — self-hosted and Cloud) | Settings → Integrations → Single Sign-On |
| `security.sso_enforced_roles` | array | Settings → Security → SSO Enforcement |

On Signals Cloud, the credential keys (`*_client_id` / `*_client_secret`) are managed centrally via environment configuration and are not editable in the UI — only the enable toggles and the allow-list are shown.

## Routes

| Route | Description |
|-------|-------------|
| `GET /auth/google/redirect` | Redirects the user to Google's OAuth consent screen |
| `GET /auth/google/callback` | Handles Google's OAuth response and completes login |
| `GET /auth/microsoft/redirect` | Redirects the user to Microsoft's OAuth consent screen |
| `GET /auth/microsoft/callback` | Handles Microsoft's OAuth response and completes login |

Routes for unknown providers return a `404`.

## Magic-Link Login

## Overview

**Magic-link login** lets an existing Signals user sign in without their password by requesting a single-use link sent to their email address. The link is valid for **15 minutes** and can only be used once. If the user has Two-Factor Authentication (2FA) enabled — or 2FA is enforced on their role — the standard one-time-code challenge still runs after the link is consumed.

Magic-link login is **off by default**. An admin or owner enables it in **Settings → Security → Magic-Link Login**.

## How It Works

1. On the login page, the user clicks **Email me a login link** (visible only when the feature is enabled).
2. They enter their email address and submit the form.
3. Signals always responds with a neutral confirmation: *"If an account exists for that address, we've emailed a link."* — this message is shown whether or not an account was found, so the response never reveals whether an email address is registered.
4. If an active account exists, is eligible, and the feature is enabled, a single-use link is emailed to that address.
5. The user clicks the link in their email. Signals validates it and either:
   - **Signs them in** (if no 2FA), redirecting to the intended page, or
   - **Redirects to the 2FA challenge** (if 2FA is enabled on the account), where they enter their one-time code as normal.

> **Note:** Magic-link login replaces only the password step. 2FA is always required if it is enabled or enforced on the account.

## Enabling Magic-Link Login

Go to **Settings → Security → Magic-Link Login** and toggle **Allow magic-link login** on, then save. Only admins and owners can change this setting.

When the toggle is off, the **Email me a login link** affordance is hidden from the login page entirely and any outstanding links are rejected at consume time.

## Anti-Enumeration

Signals never reveals whether an email address corresponds to an account. The same neutral response is returned for every request — known account, unknown email, inactive account, or SSO-blocked role — and the email is dispatched asynchronously (queued) so response timing does not leak information.

## SSO-Enforced Roles

If a role has SSO enforcement enabled (configured in **Settings → Security → SSO Enforcement**), magic-link login is **blocked** for members of that role. The block is enforced at both the request step and again when the link is clicked, so disabling the feature or changing a user's enforcement status after a link is issued causes the link to be rejected.

**The Owner role is always exempt** from SSO enforcement and can always use magic-link login when the feature is enabled.

## Security

| Property | Detail |
|----------|--------|
| **Single-use** | Each link can be consumed exactly once. Requesting a new link invalidates any previously issued, unconsumed links for that account |
| **15-minute expiry** | Links expire 15 minutes after issue. Expired links are rejected with a generic error message |
| **Hashed token storage** | Only a SHA-256 hash of the token is stored in the database. The plaintext token travels only in the email. Lookup is by index on the hash — the plaintext itself is never compared in code |
| **Anti-enumeration** | The same neutral response is returned regardless of whether an account exists or the email is eligible |
| **Policy re-checked on click** | Feature-enabled, user-active, and SSO-enforcement checks are all repeated when the link is consumed — a policy change after issue invalidates the outstanding link |
| **2FA not bypassed** | Users with 2FA enabled are redirected to the standard one-time-code challenge after a successful link consume |
| **Rate-limited** | Two layers. The **request** step is throttled inside the action — up to 3 requests per email+IP and 10 per IP across all addresses within a 15-minute window, both of which silently no-op when tripped so they cannot leak account existence. The **consume** route is additionally rate-limited per IP via standard `throttle:6,1` middleware |

## Deployment Behind a Proxy or Load Balancer

> **Prerequisite for correct rate-limiting.** The request-step throttles are keyed on the client IP (`request()->ip()`). When Signals runs behind a reverse proxy, load balancer, or CDN, Laravel only resolves the **real client IP** if trusted-proxy support is configured — otherwise every request appears to come from the proxy's IP, which collapses the per-IP throttle across all users behind that proxy and weakens the anti-enumeration guarantees.

Configure trusted proxies in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->trustProxies(
        at: '*', // or the specific proxy / CIDR ranges you control
        headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
    );
})
```

Restrict `at:` to the proxy addresses you actually operate rather than `'*'` where possible. The same prerequisite applies to every IP-based throttle in Signals — password-login lockout, the magic-link consume route, and SSO.

## Settings Reference

| Setting key | Type | UI location |
|-------------|------|-------------|
| `security.magic_link_enabled` | bool | Settings → Security → Magic-Link Login |

## Routes

| Route | Description |
|-------|-------------|
| `GET /auth/magic-link/{token}` | Validates and consumes the magic-link token, then signs in the user or redirects to the 2FA challenge |
