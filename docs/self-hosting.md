# Self-Hosting

The supported way to run Investbrain is with the official Docker image and the [`docker-compose.yml`](../docker-compose.yml) file in this repository. It bundles everything you need: the web app, a background queue, a task scheduler, PostgreSQL, and Redis.

- [Requirements](#requirements)
- [What's in the compose file](#whats-in-the-compose-file)
- [Installation](#installation)
- [What happens on startup](#what-happens-on-startup)
- [Configuration](#configuration)
- [Running behind a reverse proxy (HTTPS)](#running-behind-a-reverse-proxy-https)
- [MCP and OAuth requirements](#mcp-and-oauth-requirements)
- [Market data providers](#market-data-providers)
- [Monitoring market data providers](#monitoring-market-data-providers)
- [Currency](#currency)
- [AI chat](#ai-chat)
- [Social login](#social-login)
- [Email](#email)
- [Scheduled tasks](#scheduled-tasks)
- [Updating](#updating)
- [Backing up and restoring](#backing-up-and-restoring)
- [Command line utilities](#command-line-utilities)

## Requirements

- A Linux, macOS, or Windows machine (or a NAS or VPS) with [Docker Engine](https://docs.docker.com/engine/install/) and the Docker Compose plugin (`docker compose`)
- Outbound internet access, so Investbrain can reach your market data provider and the exchange rate service
- Ports `8000` (web) available on the host. The default compose file also publishes PostgreSQL on `5432` (see [Hardening](#hardening))

## What's in the compose file

| Service | Container | Image | Purpose |
| --- | --- | --- | --- |
| `app` | `investbrain-app` | `investbrainapp/investbrain:latest` | Nginx, PHP-FPM, the scheduler, and two queue workers, all managed by Supervisor inside one container |
| `pgsql` | `investbrain-pgsql` | `postgres:15-alpine` | The database |
| `redis` | `investbrain-redis` | `redis:alpine` | Cache, sessions, and the job queue |

Three named volumes keep your data across restarts and upgrades:

| Volume | Mounted at | Contains |
| --- | --- | --- |
| `investbrain-storage` | `/var/app/storage` in `app` | Logs, uploads, the auto-generated `APP_KEY` (`storage/app/.key`), and the OAuth signing keys (`storage/oauth-*.key`) |
| `investbrain-pgsql` | `/var/lib/postgresql/data` in `pgsql` | All portfolio, transaction, and user data |
| `investbrain-redis` | `/data` in `redis` | Cache and queued jobs |

The `investbrain-storage` volume holds your encryption key and OAuth keys. Back it up along with the database (see [Backing up and restoring](#backing-up-and-restoring)).

## Installation

**1. Download the compose file**

```bash
mkdir investbrain && cd investbrain
curl -O https://raw.githubusercontent.com/investbrainapp/investbrain/main/docker-compose.yml
```

**2. Review the settings**

Open `docker-compose.yml` and adjust the `environment` section of the `app` service. At a minimum:

- Set `APP_URL` to the address you'll use to reach Investbrain, for example `http://192.168.1.50:8000` or `https://investbrain.example.com`.
- Change `DB_PASSWORD` from the default. If you change any of `DB_DATABASE`, `DB_USERNAME`, or `DB_PASSWORD`, make the same change to `POSTGRES_DB`, `POSTGRES_USER`, and `POSTGRES_PASSWORD` in the `pgsql` service (they must match, and they only take effect the first time the database volume is created).

See [Configuration](#configuration) for everything else you can set.

**3. Start Investbrain**

```bash
docker compose up -d
```

The first start pulls the images and runs database migrations, which can take a few minutes. Follow along with:

```bash
docker compose logs -f app
```

When you see Supervisor start, Investbrain is ready.

**4. Create your account**

Visit `http://localhost:8000/register` (or your `APP_URL` followed by `/register`) and create an account.

**The first account registered becomes the administrator.** Only administrators can see the [Provider Health](#monitoring-market-data-providers) page.

**5. Optional: turn off registration**

If you're the only user, or you'd rather invite people by [sharing a portfolio](user-guide.md#sharing-portfolios), set `REGISTRATION_ENABLED: false` and [apply the change](#applying-configuration-changes). Shared users can still set a password through their invitation link.

## What happens on startup

Every time the `app` container starts, its entrypoint script:

1. Creates any missing folders under `storage` and fixes their permissions.
2. Checks for an `APP_KEY`. If none is set in the environment and `storage/app/.key` doesn't exist, it generates one, saves it to `storage/app/.key`, and prints it in the log inside a box of asterisks.
3. Generates OAuth signing keys (`storage/oauth-private.key` and `storage/oauth-public.key`) if they don't exist. These are used for API tokens and MCP authorization.
4. Runs database migrations, retrying every 5 seconds for up to a minute while the database starts. If the database still isn't reachable, the container exits.
5. Clears and rebuilds caches.
6. Starts Supervisor, which runs Nginx, PHP-FPM, the scheduler, and two queue workers.

The image also has a health check that requests `/up` every 30 seconds. `docker ps` shows the container as `healthy` once it's serving requests.

## Configuration

### Where to put settings

You can configure Investbrain in one of two ways. **Pick one; don't mix them.**

- **The `environment` block** in `docker-compose.yml` (recommended). Everything lives in one file.
- **An env file.** Create a `.env` file next to `docker-compose.yml` (use [`.env.example`](../.env.example) as a starting point), remove the `environment` block from the `app` service, and add `env_file: .env` in its place.

### Applying configuration changes

Settings are read when the container starts. After changing them, recreate the container:

```bash
docker compose up -d
```

`docker compose restart` is **not** enough when you edit `docker-compose.yml`, because it restarts the existing container with its old environment. `docker compose up -d` recreates the container with the new values.

### Options

**Application**

| Option | Description | Default |
| --- | --- | --- |
| `APP_URL` | The full public address of your instance. Used in emails, invitation links, and generated URLs. | `http://localhost` (`http://localhost:8000` in the compose file) |
| `ASSET_URL` | The base address for CSS, JavaScript, and images. Set it to the same value as `APP_URL` if styling is broken when you use a domain name. | Same host as the request |
| `APP_KEY` | Encryption key. Leave unset to have one generated and stored in the storage volume. If you set it yourself, generate it with `openssl rand -base64 32` and prefix it with `base64:`. Never change it once you have data. | Generated on first start |
| `APP_TIMEZONE` | Time zone for the scheduler, including when daily snapshots are captured. Use a [tz database name](https://en.wikipedia.org/wiki/List_of_tz_database_time_zones) such as `America/New_York`. | `UTC` |
| `APP_DEBUG` | Show detailed error pages. Only turn this on briefly while troubleshooting. | `false` |
| `REGISTRATION_ENABLED` | Allow new people to register. | `true` |
| `SESSION_LIFETIME` | Minutes of inactivity before you're signed out. | `120` |

**Market data**

| Option | Description | Default |
| --- | --- | --- |
| `MARKET_DATA_PROVIDER` | One provider, or a comma-separated list for automatic fallback. See [Market data providers](#market-data-providers). | `yahoo` |
| `MARKET_DATA_REFRESH` | How many minutes before a symbol's price is considered stale and refreshed. | `30` |
| `DAILY_CHANGE_TIME` | Time of day (24-hour, in `APP_TIMEZONE`) to capture each portfolio's daily snapshot on weekdays. | `23:00` |
| `ALPHAVANTAGE_API_KEY` | Required for the `alphavantage` provider. | none |
| `FINNHUB_API_KEY` | Required for the `finnhub` provider. | none |
| `ALPACA_API_KEY`, `ALPACA_API_SECRET` | Required for the `alpaca` provider. | none |
| `TWELVEDATA_API_SECRET` | Required for the `twelvedata` provider. | none |

**Currency**

| Option | Description | Default |
| --- | --- | --- |
| `BASE_CURRENCY` | The currency Investbrain stores converted values in internally. See [Currency](#currency) before changing it. | `USD` |

**AI chat**

| Option | Description | Default |
| --- | --- | --- |
| `AI_CHAT_ENABLED` | Show the AI chat window on portfolio and holding pages. | `false` |
| `CHAT_PROVIDER` | One of `openai`, `anthropic`, `gemini`, `azure`, `groq`, `xai`, `deepseek`, `mistral`, `ollama`. | `openai` |
| `CHAT_MODEL` | The model to use. Leave unset to use the provider's default. | Provider default |
| `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `GROQ_API_KEY`, `XAI_API_KEY`, `DEEPSEEK_API_KEY`, `MISTRAL_API_KEY` | API key for the provider you chose. | none |
| `AZURE_OPENAI_API_KEY`, `AZURE_OPENAI_URL`, `AZURE_OPENAI_API_VERSION`, `AZURE_OPENAI_DEPLOYMENT` | Settings for Azure OpenAI. | none |
| `OLLAMA_BASE_URL`, `OLLAMA_API_KEY` | Address (and optional key) of your Ollama or other OpenAI-compatible server. | `http://localhost:11434` |

**Social login**

| Option | Description | Default |
| --- | --- | --- |
| `ENABLED_LOGIN_PROVIDERS` | Comma-separated list of `github`, `google`, `facebook`, `linkedin-openid`. | none |
| `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | GitHub OAuth app credentials. | none |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google OAuth client credentials. | none |
| `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET` | Facebook app credentials. | none |
| `LINKEDIN_CLIENT_ID`, `LINKEDIN_CLIENT_SECRET` | LinkedIn app credentials. | none |

**Email**

| Option | Description | Default |
| --- | --- | --- |
| `MAIL_MAILER` | `smtp` to send real email, or `log` to write emails to the log file instead. | `log` |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | SMTP server settings. | none |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Sender address and name. | `hello@example.com`, `Investbrain` |

**Database and Redis**

These are already set correctly in the compose file. Only change them if you know you need to.

| Option | Default in compose file |
| --- | --- |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST`, `DB_PORT` | `investbrain-pgsql`, `5432` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `investbrain` |
| `REDIS_HOST` | `investbrain-redis` |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` | `redis` |

Keep `CACHE_STORE` set to `redis`. Investbrain uses tagged caching, which the `file` and `database` cache stores don't support.

### Changing the port

The web port is set by the `ports` mapping of the `app` service. To serve on port 8080 instead of 8000, change:

```yaml
    ports:
      - 8080:80
```

and update `APP_URL` to match, then run `docker compose up -d`.

### Storing data in a host folder

By default `storage` lives in a named Docker volume. To keep it in a folder on the host instead (handy for backups), swap the volume line in the `app` service for a bind mount, as shown in the comments of the compose file:

```yaml
    volumes:
      - /path/to/storage:/var/app/storage
```

The container fixes the folder's permissions on startup.

### Hardening

The default compose file is designed to get you running quickly. Before exposing Investbrain to the internet:

- **Change the database password** (see step 2 of [Installation](#installation)).
- **Stop publishing PostgreSQL to the host.** The `pgsql` service has `ports: - "5432:5432"`, which makes the database reachable from outside Docker. Investbrain connects over the internal Docker network and doesn't need it. Remove those two lines unless you need direct database access.
- **Serve over HTTPS** through a reverse proxy (below).
- **Turn off registration** if you don't want strangers creating accounts.

## Running behind a reverse proxy (HTTPS)

Investbrain serves plain HTTP on port 80 inside the container. To use HTTPS and a domain name, put a reverse proxy such as Caddy, Nginx, Traefik, or Nginx Proxy Manager in front of it.

Your proxy must:

1. Forward the original `Host` header.
2. Send `X-Forwarded-Proto: https` for HTTPS requests. Investbrain uses it to know the request was secure and to build `https://` links. Only send this header when the client really connected over HTTPS.

Then set in Investbrain:

```yaml
      APP_URL: "https://investbrain.example.com"
      ASSET_URL: "https://investbrain.example.com"
```

**Caddy example**

```
investbrain.example.com {
    reverse_proxy localhost:8000
}
```

Caddy obtains a certificate automatically and sends the `Host` and `X-Forwarded-Proto` headers by default.

**Nginx example**

```nginx
server {
    listen 443 ssl;
    server_name investbrain.example.com;

    ssl_certificate     /etc/ssl/certs/investbrain.pem;
    ssl_certificate_key /etc/ssl/private/investbrain.key;

    client_max_body_size 10m;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

If the proxy runs as another container, you can attach it to the `investbrain-network` network and proxy to `http://investbrain-app:80` instead of publishing port 8000 on the host.

## MCP and OAuth requirements

The [MCP server](mcp.md) uses OAuth so AI assistants can connect without a pasted key. For that to work:

- **Use a public HTTPS address for cloud assistants.** Claude (web, desktop, and mobile) and ChatGPT connect from their providers' servers, so `https://your-host/mcp` must be reachable from the internet with a valid certificate. Local assistants such as Claude Code, Cursor, and VS Code can use `http://localhost:8000/mcp`.
- **Set `APP_URL` to that public address** and make sure your proxy forwards `Host` and `X-Forwarded-Proto` (see above). The OAuth discovery metadata tells clients where to send users to sign in. If it points at an internal or `http://` address, authorization fails.
- **Keep the storage volume.** The OAuth signing keys are created on first start and stored in `storage`. If they're lost, every API token and authorized assistant stops working and users have to reconnect.

You can check the metadata from any machine:

```bash
curl https://investbrain.example.com/.well-known/oauth-authorization-server
```

Every URL in the response should start with your public HTTPS address.

## Market data providers

Investbrain gets prices, fundamentals, dividends, and splits from a market data provider.

| Provider | `MARKET_DATA_PROVIDER` value | API key needed |
| --- | --- | --- |
| [Yahoo Finance](https://finance.yahoo.com/) | `yahoo` | No |
| [Twelve Data](https://twelvedata.com) | `twelvedata` | `TWELVEDATA_API_SECRET` |
| [Finnhub](https://finnhub.io/) | `finnhub` | `FINNHUB_API_KEY` |
| [Alpaca](https://alpaca.markets/) | `alpaca` | `ALPACA_API_KEY`, `ALPACA_API_SECRET` |
| [Alpha Vantage](https://www.alphavantage.co/) | `alphavantage` | `ALPHAVANTAGE_API_KEY` |

Yahoo Finance is the default and needs no key. Not every provider supplies every field (for example fundamentals or dividend history), and free tiers often have tight rate limits.

### Fallback

List several providers, separated by commas, and Investbrain tries them in order until one succeeds:

```yaml
      MARKET_DATA_PROVIDER: "yahoo,finnhub,alphavantage"
      FINNHUB_API_KEY: "..."
      ALPHAVANTAGE_API_KEY: "..."
```

Here Yahoo Finance is tried first, then Finnhub if Yahoo fails, then Alpha Vantage.

### Custom providers

Developers can add a provider by implementing [`MarketDataInterface`](../app/Interfaces/MarketData/MarketDataInterface.php) (use one of the existing implementations in the same folder as a guide), registering it under `interfaces` in [`config/investbrain.php`](../config/investbrain.php), and adding its key to `MARKET_DATA_PROVIDER`. Pull requests for new providers are welcome.

## Monitoring market data providers

Administrators can open **Provider Health** from the user menu. For the last 24 hours, 7 days, or 30 days it shows:

- Total calls, success rate, how often a fallback provider was used, and average response time
- A chart of calls and failures over time
- A breakdown per provider and method, with the last failure
- The most recent failures with the symbol and error message

Use it to spot a provider that's failing or rate limiting you. Old failure records are cleaned up automatically.

The first registered user is the administrator. To make another user an administrator, set their `admin` flag in the database:

```bash
docker exec -it investbrain-pgsql psql -U investbrain -d investbrain \
  -c "UPDATE users SET admin = true WHERE email = 'someone@example.com';"
```

## Currency

Investbrain converts between currencies using daily exchange rates from the [Frankfurter](https://frankfurter.dev/) service, refreshed once a day.

Values are stored in each security's trading currency and also converted into a single **base currency** (`BASE_CURRENCY`, default `USD`). Each user then picks their own [display currency](account-settings.md#locale-and-display-currency), so you don't need to change the base currency for users who prefer euros, pounds, or anything else.

If you do want a different base currency, set `BASE_CURRENCY` **before** adding any data. Changing it later leaves previously stored values converted with the old base currency.

## AI chat

To turn on the [AI chat](user-guide.md#ai-chat) assistant:

```yaml
      AI_CHAT_ENABLED: true
      CHAT_PROVIDER: anthropic
      ANTHROPIC_API_KEY: "sk-ant-..."
      # CHAT_MODEL: "..."  # optional, defaults to the provider's default model
```

Swap in the provider and matching key you want to use (see the [AI chat options](#options)).

**Ollama and other OpenAI-compatible servers**

```yaml
      AI_CHAT_ENABLED: true
      CHAT_PROVIDER: ollama
      OLLAMA_BASE_URL: "http://host.docker.internal:11434"
      CHAT_MODEL: "llama3.1"
```

Remember that `localhost` inside the container refers to the container itself. Use your host's LAN address, `host.docker.internal` (Docker Desktop, or Linux with an `extra_hosts: ["host.docker.internal:host-gateway"]` entry), or the service name if Ollama runs in the same compose project.

Questions and portfolio details are sent to the provider you configure. Choose a provider whose data policy you're comfortable with, or run a local model.

## Social login

Users can sign in with GitHub, Google, Facebook, or LinkedIn once you register an OAuth app with each provider.

1. Create an OAuth app (sometimes called a client or application) in the provider's developer console:
   - GitHub: **Settings > Developer settings > OAuth Apps**
   - Google: **Google Cloud Console > APIs & Services > Credentials**
   - Facebook: **Meta for Developers > My Apps**
   - LinkedIn: **LinkedIn Developers > My apps** (enable **Sign In with LinkedIn using OpenID Connect**)
2. Set the authorized callback (redirect) URL to:

   ```
   https://<your-investbrain-host>/auth/<provider>/callback
   ```

   where `<provider>` is `github`, `google`, `facebook`, or `linkedin-openid`. It must match exactly, including `http` vs `https` and any port.
3. Add the credentials and enable the provider:

   ```yaml
         ENABLED_LOGIN_PROVIDERS: "github,google"
         GITHUB_CLIENT_ID: "..."
         GITHUB_CLIENT_SECRET: "..."
         GOOGLE_CLIENT_ID: "..."
         GOOGLE_CLIENT_SECRET: "..."
   ```

4. [Apply the change](#applying-configuration-changes).

**Login with ...** buttons appear on the login page, and users can link accounts from **Connections** (see [Social logins](account-settings.md#social-logins)).

When someone signs in with a provider using an email that already belongs to an Investbrain account, Investbrain emails a verification link before linking them, so [email](#email) must be working for that case.

## Email

Out of the box, `MAIL_MAILER` is `log`, which means emails are written to `storage/logs/laravel.log` instead of being sent. Investbrain sends email for:

- Password reset links
- Portfolio sharing invitations (including the link new users need to set a password)
- Social login verification links
- Import success and failure notices
- Email address verification

To send real email, configure an SMTP server:

```yaml
      MAIL_MAILER: smtp
      MAIL_HOST: smtp.example.com
      MAIL_PORT: 587
      MAIL_USERNAME: "investbrain@example.com"
      MAIL_PASSWORD: "..."
      MAIL_ENCRYPTION: tls
      MAIL_FROM_ADDRESS: "investbrain@example.com"
      MAIL_FROM_NAME: "Investbrain"
```

Emails are sent by the background queue workers, which run inside the `app` container automatically.

## Scheduled tasks

The scheduler runs inside the `app` container. You don't need to set up cron.

| Task | When | What it does |
| --- | --- | --- |
| Refresh market data | Every minute on weekdays | Refreshes prices for symbols with open positions that are older than `MARKET_DATA_REFRESH` minutes |
| Capture daily change | Weekdays at `DAILY_CHANGE_TIME` | Saves a snapshot of each portfolio's value for the performance charts |
| Refresh dividend data | Daily on Monday, Wednesday, and Friday | Downloads new dividends, updates dividends earned, and creates reinvestment transactions |
| Refresh split data | Weekly | Downloads new splits and creates split transactions |
| Refresh currency data | Daily | Downloads the latest exchange rates |
| Sync holdings | Yearly | Reconciles every holding with its transactions and dividends |
| Prune old records | Daily | Deletes old provider failure records |

Times are in `APP_TIMEZONE`.

## Updating

```bash
docker compose pull
docker compose up -d
```

This downloads the latest images and recreates the containers. Database migrations run automatically on startup. Your data is kept in the volumes.

It's a good idea to [back up](#backing-up-and-restoring) before updating. The running version is printed at the top of the `app` container's log on startup.

Don't change the major version of the `postgres` image (for example from `15` to `16`) on an existing database volume. PostgreSQL can't read data files from a different major version. To upgrade PostgreSQL, dump the database, start a fresh volume with the new version, and restore.

## Backing up and restoring

There are two complementary ways to back up.

**In-app export (per user)**

Each user can download a full backup of their own data from [Import / Export](import-export.md). This is portable between instances and versions but doesn't include passwords, 2FA settings, API tokens, or other users.

**Full instance backup (administrators)**

Back up both the database and the storage volume.

```bash
# Database
docker exec investbrain-pgsql pg_dump -U investbrain -d investbrain -Fc > investbrain-$(date +%F).dump

# Storage volume (contains APP_KEY and OAuth keys)
docker run --rm -v investbrain_investbrain-storage:/data -v "$PWD":/backup alpine \
  tar czf /backup/investbrain-storage-$(date +%F).tar.gz -C /data .
```

Docker prefixes volume names with the compose project name (the folder name by default), so the storage volume might be called `investbrain_investbrain-storage`. Run `docker volume ls` to check.

To restore the database into a running instance:

```bash
docker exec -i investbrain-pgsql pg_restore -U investbrain -d investbrain --clean --if-exists < investbrain-2026-11-01.dump
```

Restore the storage archive the same way in reverse, before starting the `app` container. If you restore the database without the original `APP_KEY`, encrypted values such as 2FA secrets can't be read, so keep the two backups together.

## Command line utilities

Investbrain includes command line utilities for maintenance. They can make large, irreversible changes, so back up first. Run them inside the `app` container:

```bash
docker exec -it investbrain-app php artisan <command>
```

Add `--help` to any command to see its options.

| Command | Description |
| --- | --- |
| `refresh:market-data` | Refresh prices for all open positions. `--force` ignores the refresh interval, `--user=<id>` limits it to one user's holdings. |
| `refresh:dividend-data` | Refresh dividend data and recalculate dividends earned. `--force` refreshes every holding, `--user=<id>` limits it to one user. |
| `refresh:split-data` | Refresh split data and create transactions for new splits. `--force` refreshes every holding. |
| `refresh:currency-data` | Download the latest exchange rates. |
| `capture:daily-change` | Capture today's snapshot for every portfolio now. |
| `sync:daily-change <portfolio_id>` | Rebuild a portfolio's entire daily history from market data. Useful for filling gaps in charts. Resource intensive. `--force` skips the confirmation. |
| `sync:holdings` | Recalculate every holding from its transactions and dividends. `--user=<id>` limits it to one user. |
| `fix:cost-basis-for-sales` | Recalculate the cost basis of sell transactions. `--portfolio=<id>` or `--user=<id>` limits the scope. |

Find a portfolio's ID in the address bar of its page (`/portfolio/<id>`).

For more help, see [Troubleshooting](troubleshooting.md).
