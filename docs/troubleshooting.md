# Troubleshooting

Start with the logs. Most problems leave a clear message in one of them.

```bash
# Container startup, scheduler, and queue worker output
docker compose logs -f app

# Application log
docker exec -it investbrain-app tail -f storage/logs/laravel.log

# Database
docker compose logs pgsql
```

For a quick status check:

```bash
docker compose ps
```

The `app` container should show `healthy` after a minute or so.

## Installation and startup

### "Database is not ready after one minute. Exiting..."

The `app` container waits up to a minute for PostgreSQL, then gives up. Check the database log with `docker compose logs pgsql`.

- **`password authentication failed for user "investbrain"`** - the credentials in the `app` service don't match the database. PostgreSQL only reads `POSTGRES_USER`, `POSTGRES_PASSWORD`, and `POSTGRES_DB` the **first time** its volume is created, so changing them later has no effect. Either change the `DB_*` values back to the originals, or, on a fresh install with no data you need, delete the volume and start again:

  ```bash
  docker compose down
  docker volume rm investbrain_investbrain-pgsql   # check the exact name with: docker volume ls
  docker compose up -d
  ```

- **`database files are incompatible with server`** - the `postgres` image's major version was changed on an existing volume. Switch back to the original version (`postgres:15-alpine`). See [Updating](self-hosting.md#updating) for how to upgrade PostgreSQL safely.
- **The database is just slow to start** (common on a Raspberry Pi or NAS). Run `docker compose up -d` again once the `pgsql` container is up.

### "Bind for 0.0.0.0:8000 failed: port is already allocated"

Something else on the host is using that port. Either stop it or [change Investbrain's port](self-hosting.md#changing-the-port). The same applies to `5432` if you already run PostgreSQL on the host; in that case remove the `ports` lines from the `pgsql` service (Investbrain doesn't need them).

### I can't find my APP_KEY

If you didn't set `APP_KEY`, one was generated on first start and saved to `storage/app/.key` in the storage volume. To see it:

```bash
docker exec -it investbrain-app cat storage/app/.key
```

Keep a copy somewhere safe. If you ever move to an env file, copy this value into `APP_KEY` so it doesn't change.

### "The MAC is invalid" or "The payload is invalid" errors

The encryption key changed after data was created. This happens if `APP_KEY` was edited, or the storage volume holding the generated key was deleted while the database was kept. Restore the original key (from a backup of `storage/app/.key` or your old configuration).

If the original key is gone for good, clear the browser cookies for the site and sign in again. Users who had two-factor authentication turned on will need it [reset](#locked-out-by-two-factor-authentication), and API tokens and MCP connections should be recreated.

### Permission denied errors in the log

The container fixes permissions on `storage` every time it starts, so a restart usually resolves this. If you use a bind mount on a host with SELinux (Fedora, RHEL, and similar), add `:z` to the mount so the container can write to it:

```yaml
      - /path/to/storage:/var/app/storage:z
```

## Configuration

### My configuration changes aren't taking effect

Run `docker compose up -d` after editing `docker-compose.yml` or your env file. `docker compose restart` keeps the old environment. See [Applying configuration changes](self-hosting.md#applying-configuration-changes).

Also make sure you're using either the `environment` block or an env file, not both. If you have both, check which one actually contains the value you changed.

### Styling is broken and images are huge

The page is loading without its CSS, usually because the browser can't fetch the assets from the address Investbrain is generating. This typically happens when you reach Investbrain through a domain name or a reverse proxy.

<!-- SCREENSHOT: What the app looks like when CSS fails to load (for example with ASSET_URL set to a wrong address), so readers can recognize the problem. -->
<!-- ![Investbrain with missing styles, showing oversized images and unstyled text](images/troubleshooting-broken-styling.png) -->

- Set `ASSET_URL` (and `APP_URL`) to the exact address you type into the browser, including `https://` and any port.
- If you use HTTPS through a proxy, make sure the proxy sends `X-Forwarded-Proto: https`. Without it, Investbrain generates `http://` asset links, and the browser blocks them as mixed content.
- Check the browser's developer console for the URL that failed to load. It tells you which address Investbrain thinks it's on.

### Links point to http:// even though I use HTTPS (or the other way around)

Investbrain decides between `http://` and `https://` from the `X-Forwarded-Proto` header your proxy sends.

- If links are `http://` behind an HTTPS proxy, add `X-Forwarded-Proto: https` to your proxy configuration (see [Running behind a reverse proxy](self-hosting.md#running-behind-a-reverse-proxy-https)).
- If links are `https://` but you serve plain HTTP, make sure nothing in front of Investbrain sets `X-Forwarded-Proto`. Any value of that header, even `http`, makes Investbrain treat the request as secure.

Links in emails (password resets and invitations) are built from `APP_URL`, so check that too.

### Registering is disabled and I can't create the first account

`REGISTRATION_ENABLED` is `false`. Set it to `true`, [apply the change](self-hosting.md#applying-configuration-changes), register, and then turn it off again.

## Market data

### Market data doesn't load on a fresh install

The default provider, Yahoo Finance, is often blocked by ad blockers and DNS filters. Pi-hole, AdGuard Home, and similar tools commonly block `fc.yahoo.com`, which Investbrain needs. Allow-list `fc.yahoo.com` (and if needed `query1.finance.yahoo.com` and `query2.finance.yahoo.com`) and data should begin populating.

To confirm the container can reach the internet at all:

```bash
docker exec -it investbrain-app php artisan refresh:market-data --force
```

The output lists each symbol and any error.

### "The symbol provided (XYZ) is not valid"

Your market data provider doesn't recognize the symbol. Check the format your provider expects. Exchange suffixes vary: for example, Yahoo Finance uses `VOD.L` for London and `SHOP.TO` for Toronto. Symbols that work with one provider may not work with another.

### Prices aren't updating

- Prices refresh on **weekdays only**, and a symbol is only refreshed once its price is older than `MARKET_DATA_REFRESH` minutes (30 by default).
- Only symbols with open positions (quantity above zero) are refreshed automatically.
- Check **Provider Health** for failures or rate limiting. Free tiers of Alpha Vantage, Finnhub, and Twelve Data have low limits; add a [fallback provider](self-hosting.md#fallback) to spread the load.
- Check that the scheduler is running: `docker compose logs app` should show scheduled commands running every minute.

### The performance chart is empty or has gaps

- Snapshots are taken on weekdays at `DAILY_CHANGE_TIME`. A brand new portfolio won't have a chart line until its history has been built.
- Adding or editing a past-dated transaction rebuilds that portfolio's history automatically, but it relies on the market data provider for historical prices. If the provider failed at the time, gaps can remain.
- Rebuild a portfolio's history manually:

  ```bash
  docker exec -it investbrain-app php artisan sync:daily-change <portfolio_id> --force
  ```

  Find the portfolio ID in the address bar (`/portfolio/<id>`). This is resource intensive for portfolios with long histories.

### Dividends or splits are missing

Dividend data refreshes Monday, Wednesday, and Friday, and split data weekly. To refresh immediately:

```bash
docker exec -it investbrain-app php artisan refresh:dividend-data --force
docker exec -it investbrain-app php artisan refresh:split-data --force
```

Not every provider supplies dividend and split history. Yahoo Finance does.

### Holding totals look wrong

Recalculate holdings from their transactions:

```bash
docker exec -it investbrain-app php artisan sync:holdings
```

If realized gains on sales look off (for example after importing old data), recalculate the cost basis of sales:

```bash
docker exec -it investbrain-app php artisan fix:cost-basis-for-sales --portfolio=<portfolio_id>
```

## Accounts and email

### Password reset, invitation, or verification emails never arrive

By default `MAIL_MAILER` is `log`, so emails are written to the log instead of being sent. In a pinch you can find the link at the end of the log right after triggering the email:

```bash
docker exec -it investbrain-app tail -n 150 storage/logs/laravel.log
```

The link may be wrapped across lines with `=` characters at the line ends; remove those (and any `=3D`, which stands for `=`) before pasting it into your browser.

To send real email, [configure SMTP](self-hosting.md#email). If you've configured SMTP and email still doesn't arrive, look for mail errors in `storage/logs/laravel.log` and check your spam folder.

### Locked out by two-factor authentication

First try one of your recovery codes on the 2FA prompt. If you've lost those too, turn off 2FA for the account directly in the database:

```bash
docker exec -it investbrain-pgsql psql -U investbrain -d investbrain -c \
  "UPDATE users SET two_factor_secret = NULL, two_factor_recovery_codes = NULL, two_factor_confirmed_at = NULL WHERE email = 'you@example.com';"
```

The user can then sign in with just their password and set up 2FA again.

### Forgot your password and email doesn't work

Reset the password from the command line:

```bash
docker exec -it investbrain-app php artisan tinker --execute \
  'App\Models\User::where("email", "you@example.com")->first()->forceFill(["password" => Hash::make("a-new-strong-password")])->save();'
```

Sign in with the new password and change it from **Manage Profile** right away.

### "Too many login attempts"

Logins are limited to five attempts per minute for each email and IP address. Wait a minute and try again.

### Social login fails with "redirect_uri mismatch" or "Could not login using ..."

- The callback URL registered with the provider must be exactly `https://<your-host>/auth/<provider>/callback` (`github`, `google`, `facebook`, or `linkedin-openid`), including the scheme and port.
- Check that the client ID and secret are correct and that the provider is listed in `ENABLED_LOGIN_PROVIDERS`.
- If a user sees **Account already exists. Check your email...**, an account with that email already exists. They need to click the link in the verification email (so email must be working), or sign in with their password and connect the provider from **Connections** instead.

## Import and export

### My import failed

The progress dialog shows the error. Common causes:

- **The file isn't `.xlsx` or is larger than 2 MB.** Save it as an Excel workbook, or split it into several smaller files.
- **A validation error on a row**, such as a missing symbol, a lower-case `buy` or `sell`, a price with a currency symbol, or a portfolio title where an ID belongs. The error includes the row number. Check the columns against the [Transactions sheet](import-export.md#transactions-sheet) and the [formatting tips](import-export.md#formatting-tips).
- **A sheet is missing or renamed.** The workbook needs all four sheets (Portfolios, Transactions, Daily Changes, and Config), even if some are empty. Using **Download import template** is the easiest way to get the sheets and headings right.
- **"You do not have access to that portfolio."** A row references a portfolio ID that doesn't exist yet or that you don't have full access to. Add the portfolio to the Portfolios sheet or create it first (see [Referring to portfolios](import-export.md#referring-to-portfolios)).
- **"Hang on! You're doing that too much."** You can start three imports or exports per minute. Wait and try again.

Before you retry, check whether sheets imported before the error created any portfolios. Portfolio rows with a blank ID are created again on every import, so delete the extras or copy their IDs into the Portfolios sheet. See [How imports work](import-export.md#how-imports-work).

### The import finished but totals and charts are empty

After an import, Investbrain refreshes market data, dividends, and holdings, then rebuilds each portfolio's history in the background. On a large import this can take a while. Watch progress with `docker compose logs -f app`. If nothing happens, make sure the queue workers are running (they appear in the log as `queue-worker` processes).

## API

### 401 Unauthorized

- Check the header is exactly `Authorization: Bearer <token>`.
- The token may have been deleted on the **API Tokens** page, or have expired. Create a new one.
- If the OAuth keys in `storage` were regenerated (for example the storage volume was recreated), every existing token is invalid. Create new tokens.

### I get an HTML page instead of JSON

Add `Accept: application/json` to your request. Without it, some errors (like an invalid token) return a redirect to the login page instead of a JSON error.

### 403 Forbidden

You have read-only access to that portfolio, or no access at all. Changes require full access.

### 422 "The filter field may only contain..." or "The sort field may only contain..."

You used a filter or sort column that isn't supported for that endpoint. The error message lists the allowed values. See [Pagination, sorting, filtering, and search](api.md#pagination-sorting-filtering-and-search).

## MCP clients can't connect

- **`404 Not Found` from `/mcp`** (or from the `/.well-known/oauth-...` discovery URLs) means the MCP server is turned off. Set `MCP_SERVER_ENABLED` to `true` and [apply the change](self-hosting.md#applying-configuration-changes). See [Turning off the MCP server](self-hosting.md#turning-off-the-mcp-server).
- **Cloud assistants (Claude, ChatGPT)** need your instance on the public internet over HTTPS with a valid certificate. A `localhost` or LAN address only works for assistants running on your own machine.
- **Check the OAuth metadata** from outside your network:

  ```bash
  curl https://investbrain.example.com/.well-known/oauth-authorization-server
  ```

  Every URL in the response should start with your public HTTPS address. If they show `http://`, an internal hostname, or the wrong port, fix `APP_URL` and your proxy's `Host` and `X-Forwarded-Proto` headers.
- **`403 Forbidden`** means the client sent a token without the `mcp:use` scope, usually a personal API token pasted into the client. Remove it and let the client sign in with OAuth.
- **It worked before and now doesn't** - access may have been revoked under **Connections > Authorized Apps**, or the OAuth keys in `storage` were regenerated. Reconnect the assistant.

See the [MCP guide](mcp.md) for setup steps.

## AI chat

### The AI chat window doesn't appear

Set `AI_CHAT_ENABLED: true` and [apply the change](self-hosting.md#applying-configuration-changes). The window only appears on portfolio and holding pages, not the dashboard.

### The assistant returns errors

- Check that `CHAT_PROVIDER` matches the API key you set (for example `anthropic` with `ANTHROPIC_API_KEY`), and that the key is valid and has credit.
- If you set `CHAT_MODEL`, make sure it's a model your provider offers.
- For Ollama, `OLLAMA_BASE_URL` must be reachable **from inside the container**. `http://localhost:11434` points at the container itself. See [AI chat](self-hosting.md#ai-chat) for alternatives.
- The error details are in `storage/logs/laravel.log`.

## Still stuck?

Search the [GitHub issues](https://github.com/investbrainapp/investbrain/issues) or open a new one. Include:

- What you were doing and what happened
- The relevant lines from `docker compose logs app` and `storage/logs/laravel.log` (remove any secrets)
- The Investbrain version shown at the top of the startup log
- How you're running it (Docker Compose, reverse proxy, host OS)
