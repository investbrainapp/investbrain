# Account Settings

Open the user menu at the bottom of the sidebar to reach your account settings:

- **Manage Profile** - profile information, locale and display currency, password, two-factor authentication, and account deletion
- **Connections** - social logins and authorized apps
- **API Tokens** - personal access tokens for the [REST API](api.md)
- **Import / Export Data** - see [Import and export](import-export.md)
- **Provider Health** - administrators only, see [Monitoring market data providers](self-hosting.md#monitoring-market-data-providers)

## Profile information

Under **Manage Profile** you can change your name and email address and upload a profile photo (or remove the current one). Click **Save** when you're done.

## Locale and display currency

The **Locale Options** section on **Manage Profile** controls how Investbrain looks for you.

| Setting | What it does |
| --- | --- |
| **Locale** | Sets the interface language (English, French, or Spanish) and the regional format for numbers, dates, and currency symbols. For example, **English (United Kingdom)** and **English (United States)** use the same language but format numbers differently. |
| **Display Currency** | The currency used for dashboard and portfolio totals, summary cards, and performance charts. Amounts are converted from each security's trading currency using daily exchange rates. |

<!-- SCREENSHOT: Locale Options section of Manage Profile with the Display Currency drop-down open. -->
<!-- ![The Locale Options section with Locale and Display Currency](images/account-settings-locale-currency.png) -->

Changing your display currency doesn't change any stored data. It only changes how totals are presented to you, so you can switch back and forth freely. Individual holdings in the holdings table keep showing their own trading currency. See [Multiple currencies](user-guide.md#multiple-currencies) for the full picture.

Your display currency is also the default currency when you create a new transaction from the Transactions page or a portfolio page.

## Updating your password

Under **Update Password**, enter your current password and the new password twice.

If you signed up with a social login, your account doesn't have a password yet. Use **Forgot your password?** on the login page to set one by email.

## Two-factor authentication

Two-factor authentication (2FA) adds a one-time code from your phone to the sign-in process. Any authenticator app that supports time-based codes works, such as Google Authenticator, 1Password, Authy, or Bitwarden.

### Turning on 2FA

1. Go to **Manage Profile** and find **Two Factor Authentication**.
2. Click **Enable**. You'll be asked to confirm your password.
3. Scan the QR code with your authenticator app, or type in the **Setup Key** shown under it.
4. Enter the 6-digit code from your app in the **Code** field and click **Confirm**.
5. Investbrain shows a list of **recovery codes**. Store them somewhere safe, such as a password manager.

<!-- SCREENSHOT: The 2FA setup step with QR code, setup key, and Code field. Use a throwaway account; never publish a real QR code or setup key. -->
<!-- ![Two-factor authentication setup with QR code and confirmation code field](images/account-settings-2fa-setup.png) -->

2FA isn't active until you confirm a code in step 4. If you leave the page before confirming, setup is cancelled and you can start again.

### Signing in with 2FA

After entering your email and password you'll be asked for the current code from your authenticator app. If you don't have your phone, choose the option to use a recovery code instead. Each recovery code works only once.

### Managing recovery codes

With 2FA turned on, click **Show Recovery Codes** to view your remaining codes, or **Regenerate Recovery Codes** to replace them all with a new set. Regenerating invalidates every old code, so do it if you think your codes have been exposed or you've used most of them.

### Turning off 2FA

Click **Disable** and confirm your password.

If you've lost both your authenticator and your recovery codes, your instance administrator can reset 2FA for you. See [Locked out by two-factor authentication](troubleshooting.md#locked-out-by-two-factor-authentication).

## Social logins

If your administrator has enabled social logins, you can sign in with GitHub, Google, Facebook, or LinkedIn instead of (or as well as) your password.

<!-- SCREENSHOT: Connections page Social Logins section with one connected provider and one with a Connect button. -->
<!-- ![The Social Logins section of the Connections page](images/account-settings-social-logins.png) -->

### Connecting an account

1. Go to **Connections** in the user menu.
2. Under **Social Logins**, click **Connect** next to the provider.
3. Approve the request on the provider's site.

You'll return to the Connections page with a confirmation. From then on you can use **Login with ...** on the login page. A social account can only be connected to one Investbrain user; if it's already connected to someone else you'll see an error.

### Signing in with a provider for the first time

If you click **Login with ...** on the login page without having connected that provider:

- **No Investbrain account uses that email** - a new account is created and you're signed in.
- **An Investbrain account already uses that email** - for your security, Investbrain emails a verification link to that address instead of signing you in. Click the link to connect the provider. Until then, the provider shows as **Pending email verification** on the Connections page.

### Disconnecting an account

Click **Disconnect** next to the provider and confirm. You can reconnect at any time. Make sure you have a password set (or another provider connected) before disconnecting your only way to sign in.

Administrators: see [Social login](self-hosting.md#social-login) for setup.

## Authorized apps

The **Authorized Apps** section on the **Connections** page lists every app and AI assistant you've given access to your account through OAuth, such as an AI assistant connected through the [MCP server](mcp.md). For each one you'll see its name, what it's allowed to do, and when you first authorized it.

<!-- SCREENSHOT: Authorized Apps section with at least one MCP client (for example Claude) listed with its scope and Revoke link. -->
<!-- ![The Authorized Apps section listing a connected AI assistant](images/account-settings-authorized-apps.png) -->

Click **Revoke** to cut off an app's access immediately. The app will need to ask for your permission again to reconnect.

Personal API tokens aren't listed here. Manage those on the **API Tokens** page (see [Creating an API token](api.md#creating-an-api-token)).

## Deleting your account

At the bottom of **Manage Profile**, click **Delete Account** and enter your password to confirm. Your account, profile photo, API tokens, connected social accounts, and AI chat history are permanently deleted, your access to every portfolio is removed, and you're signed out.

This can't be undone. [Export your data](import-export.md#exporting-your-data) first if you might want it later.
