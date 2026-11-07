---
title: "Single Sign-On (OIDC)"
description: "Let users sign in with your own identity provider, such as Authelia, Authentik, or Keycloak, through OpenID Connect."
group: "Running your own instance"
order: 33
updated_at: "2026-11-07"
---
# Single Sign-On (OIDC)

If you run your own identity provider, users can sign in to Investbrain with it through OpenID Connect (OIDC). A **Login with ...** button appears on the login page, and users can link it to an existing account from **Connections** (see [Social logins](account-settings.md#social-logins)).

Any provider that supports OIDC discovery and the authorization code flow should work. This page has step-by-step settings for [Authelia](#authelia), [Authentik](#authentik), and [Keycloak](#keycloak).

## Before you start

- **Serve Investbrain over HTTPS** behind a [reverse proxy](self-hosting.md#running-behind-a-reverse-proxy-https). Investbrain builds the callback URL from the incoming request, so the proxy must forward the `Host` header and send `X-Forwarded-Proto: https`. Otherwise Investbrain sends an `http://` callback URL and the provider rejects it.
- **Investbrain must be able to reach your provider.** Besides the browser redirects, the Investbrain container calls the provider directly to read its settings, exchange the login code, and fetch the user's profile. The provider's public URL has to resolve from inside the container, and its HTTPS certificate has to be trusted there. A self-signed certificate or a private CA won't work unless the container trusts it.
- **Users need an email address in the provider.** Investbrain uses it to find or create their account.

## What Investbrain needs from the provider

Whichever provider you use, create a client (sometimes called an application or relying party) with:

| Setting | Value |
| --- | --- |
| Client type | Confidential (has a client secret) |
| Grant type / flow | Authorization code |
| Redirect URI | `https://<your-investbrain-host>/auth/oidc/callback` |
| Scopes | `openid`, `profile`, `email` |
| PKCE | Supported (S256). Investbrain always sends it, so you can require it. |
| Client authentication | `client_secret_basic` is preferred. `client_secret_post` works if it's all the provider supports. |
| Userinfo response | Plain JSON (not signed or encrypted) |

The redirect URI must match exactly, including `https`, the hostname, and any port.

## Investbrain settings

Add these to the `app` service's environment, then [apply the change](self-hosting.md#applying-configuration-changes):

```yaml
      ENABLED_LOGIN_PROVIDERS: "oidc"
      OIDC_ISSUER: "https://auth.example.com"
      OIDC_CLIENT_ID: "investbrain"
      OIDC_CLIENT_SECRET: "..."
      OIDC_NAME: "Authelia"
```

| Option | Description | Default |
| --- | --- | --- |
| `OIDC_ISSUER` | The provider's issuer URL. Investbrain reads `<issuer>/.well-known/openid-configuration` to find everything else. | none |
| `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET` | The client's ID and its plain-text secret. | none |
| `OIDC_NAME` | Name on the **Login with ...** button. | `SSO` |
| `OIDC_BUTTON_COLOR` | Background color of the button. | `#4b5563` |
| `SSO_AUTO_PROVISION` | Create an account the first time someone signs in and no account has their email. Set `false` to only allow existing users. | `true` |

To check the issuer, open `<issuer>/.well-known/openid-configuration` in a browser. You should see a JSON document whose `issuer` value matches `OIDC_ISSUER` (a trailing slash difference is fine).

You can keep other providers enabled alongside it, for example `ENABLED_LOGIN_PROVIDERS: "oidc,github"`.

## Authelia

Add a client under `identity_providers.oidc.clients` in Authelia's `configuration.yml`. This assumes you've already set up Authelia's [OpenID Connect provider](https://www.authelia.com/configuration/identity-providers/openid-connect/provider/) (its `hmac_secret` and `jwks`).

1. Generate a client secret. Authelia stores only a hash of it:

   ```bash
   docker run --rm authelia/authelia:latest authelia crypto hash generate pbkdf2 --variant sha512 --random --random.length 72 --random.charset rfc3986
   ```

   It prints a **Random Password** (the plain-text secret for Investbrain) and a **Digest** (the hash for Authelia).
2. Add the client:

   ```yaml
   identity_providers:
     oidc:
       clients:
         - client_id: 'investbrain'
           client_name: 'Investbrain'
           client_secret: '$pbkdf2-sha512$...'  # the Digest
           public: false
           authorization_policy: 'two_factor'
           consent_mode: 'implicit'
           require_pkce: true
           pkce_challenge_method: 'S256'
           redirect_uris:
             - 'https://investbrain.example.com/auth/oidc/callback'
           scopes:
             - 'openid'
             - 'profile'
             - 'email'
           response_types:
             - 'code'
           grant_types:
             - 'authorization_code'
           token_endpoint_auth_method: 'client_secret_basic'
           access_token_signed_response_alg: 'none'
           userinfo_signed_response_alg: 'none'
   ```

   Keep `userinfo_signed_response_alg: 'none'`: Investbrain reads the profile as plain JSON. `consent_mode: 'implicit'` skips Authelia's consent screen; remove it if you want users to approve the first sign-in.
3. Restart Authelia.
4. In Investbrain, set:

   ```yaml
         OIDC_ISSUER: "https://auth.example.com"
         OIDC_CLIENT_ID: "investbrain"
         OIDC_CLIENT_SECRET: "<the Random Password>"
         OIDC_NAME: "Authelia"
   ```

   The issuer is Authelia's base URL, with no path.

## Authentik

Menu and button names vary a little between Authentik versions.

1. In the admin interface, go to **Applications > Applications** and create a new application with a provider (**Create with provider** in recent versions).
2. Give the application a name and a slug, such as `investbrain`. The slug becomes part of the issuer URL.
3. Choose **OAuth2/OpenID Provider** as the provider type, and set:
   - **Authorization flow:** `default-provider-authorization-implicit-consent` to skip the consent screen, or the explicit-consent flow if you want users to approve the first sign-in
   - **Client type:** Confidential
   - **Redirect URIs:** `Strict`, `https://investbrain.example.com/auth/oidc/callback`
   - **Scopes:** keep the defaults, which include `openid`, `email`, and `profile`
4. Save, then open the provider and copy the **Client ID**, the **Client Secret**, and the **OpenID Configuration Issuer**.
5. Under the application's **Policy / Group / User Bindings**, bind the users or groups who should be allowed to sign in. Without bindings, any Authentik user can use the application.
6. In Investbrain, set:

   ```yaml
         OIDC_ISSUER: "https://authentik.example.com/application/o/investbrain/"
         OIDC_CLIENT_ID: "<Client ID>"
         OIDC_CLIENT_SECRET: "<Client Secret>"
         OIDC_NAME: "Authentik"
   ```

   Use the **OpenID Configuration Issuer** exactly as Authentik shows it. By default each application has its own issuer that includes its slug.

## Keycloak

These steps are for the current admin console (Keycloak 19 and later).

1. Select the realm your users are in, go to **Clients**, and click **Create client**.
2. **General settings:** set **Client type** to `OpenID Connect` and **Client ID** to `investbrain`.
3. **Capability config:** turn **Client authentication** on. Under **Authentication flow**, keep **Standard flow** checked. You can uncheck **Direct access grants**.
4. **Login settings:** set **Valid redirect URIs** to `https://investbrain.example.com/auth/oidc/callback`. Save.
5. Optional: on the client's **Advanced** tab, under **Advanced settings**, set **Proof Key for Code Exchange Code Challenge Method** to `S256` to require PKCE.
6. Copy the **Client secret** from the **Credentials** tab.
7. Make sure your users have an email address set (**Users > user > Details**). The `profile` and `email` client scopes are assigned to new clients by default.
8. In Investbrain, set:

   ```yaml
         OIDC_ISSUER: "https://keycloak.example.com/realms/<realm>"
         OIDC_CLIENT_ID: "investbrain"
         OIDC_CLIENT_SECRET: "<Client secret>"
         OIDC_NAME: "Keycloak"
   ```

   Keycloak 16 and older include `/auth` in the path: `https://keycloak.example.com/auth/realms/<realm>`.

## How accounts are matched

- **First sign-in, new email:** Investbrain creates an account using the name and email from the provider, unless `SSO_AUTO_PROVISION` is `false`. In that case the user is sent back to the login page and no account is created.
- **First sign-in, email already has an account:** Investbrain emails a confirmation link to that address and links the provider only after the link is clicked, so [email](self-hosting.md#email) needs to work. Signed-in users can skip this by connecting the provider from **Connections**.
- **Later sign-ins:** accounts are linked by the user's ID at the provider (the `sub` claim), not by email. Changing an email in the provider doesn't break the link. If you change `OIDC_ISSUER` to a different provider, users have to connect their accounts again.

## Troubleshooting

When a sign-in fails, users see **Could not login using ...** and the cause is written to `storage/logs/laravel.log`. Common causes:

- **"redirect_uri mismatch" or similar at the provider:** the redirect URI doesn't match exactly. Check `https` vs `http` (see [Before you start](#before-you-start)), the hostname, and the port.
- **The discovery document's issuer doesn't match `OIDC_ISSUER`:** copy the issuer exactly as the provider shows it, or as it appears in the provider's `.well-known/openid-configuration`.
- **Connection or certificate errors in the log:** the Investbrain container can't reach the provider, or doesn't trust its certificate. Test from inside the container with `docker compose exec app curl -I https://auth.example.com/.well-known/openid-configuration`.
- **"invalid_client" or 401 from the token endpoint:** the client secret is wrong, or the provider expects a different client authentication method. In Authelia, make sure `OIDC_CLIENT_SECRET` is the plain-text secret, not the digest.
- **"Your ... account didn't share an email address":** the user has no email in the provider, or the client isn't allowed the `email` scope.

For other social login problems, see [Troubleshooting](troubleshooting.md).
