# Investbrain Documentation

Investbrain is an open-source investment tracker. It consolidates portfolios from your different brokerages, keeps market data up to date, tracks dividends and splits, and reports gains and losses in the currency you prefer.

This folder documents everything Investbrain can do, from day-to-day use to running your own instance.

## Two ways to use Investbrain

| | Investbrain Cloud | Self-hosted |
| --- | --- | --- |
| **Where** | [investbra.in](https://investbra.in) | Your own server, NAS, or computer |
| **Setup** | Sign up at [investbra.in/register](https://investbra.in/register). Nothing to install. | Run the official Docker image with the [supported compose file](self-hosting.md) |
| **Hosting, updates, market data, and email** | Handled for you | Managed by you |
| **Configuration** | Managed by Investbrain | You choose the market data providers, AI chat provider, social logins, and every other [option](self-hosting.md#options) |

Both run the same open-source app, so everything in the user guide, account settings, import and export, API, and MCP pages applies to both. Where something differs, the docs say so. You can move between them at any time by [exporting your data](import-export.md#exporting-your-data) from one and importing it into the other.

## Using Investbrain

- [User guide](user-guide.md) - the dashboard, portfolios, sharing, transactions, holdings, dividends, splits, search, and AI chat
- [Account settings](account-settings.md) - profile, locale and display currency, password, two-factor authentication, social logins, authorized apps, and deleting your account
- [Import and export](import-export.md) - backing up your data, moving between Investbrain Cloud and a self-hosted instance, and bulk-loading transactions from a spreadsheet

## Integrations

- [REST API](api.md) - creating an API token and calling the Investbrain API
- [MCP server](mcp.md) - connecting AI assistants such as Claude, ChatGPT, Cursor, or VS Code to your Investbrain account

## Running your own instance

These pages are for self-hosting. You don't need them if you use Investbrain Cloud.

- [Self-hosting](self-hosting.md) - installing with the supported Docker Compose file, configuration, reverse proxies, updates, backups, and command line utilities
- [Troubleshooting](troubleshooting.md) - fixes for the most common problems (mostly for self-hosted instances)

## Developing Investbrain

- [Contributing](contributing.md) - how the project is built, setting up a development environment, coding conventions, and making pull requests
- [Developing market data providers](market-data-providers.md) - writing, testing, and contributing a new market data provider

## Getting help

If something isn't covered here, search the [GitHub issues](https://github.com/investbrainapp/investbrain/issues) or open a new one. For help with your Investbrain Cloud account, [contact Investbrain](https://investbra.in/contact). Security issues should be reported privately through [GitHub security advisories](https://github.com/investbrainapp/investbrain/security/advisories/new).
