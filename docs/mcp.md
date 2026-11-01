# MCP Server

Investbrain includes a built-in [Model Context Protocol](https://modelcontextprotocol.io) (MCP) server. MCP is an open standard that lets AI assistants use outside tools. Once connected, an assistant such as Claude, ChatGPT, Cursor, or VS Code can look up your portfolios, record transactions, and check market data for you, in plain conversation.

- [What an assistant can do](#what-an-assistant-can-do)
- [Before you start](#before-you-start)
- [Connecting an assistant](#connecting-an-assistant)
- [Authorizing access](#authorizing-access)
- [Example prompts](#example-prompts)
- [Available tools](#available-tools)
- [Revoking access](#revoking-access)
- [How authentication works](#how-authentication-works)
- [Troubleshooting](#troubleshooting)

## What an assistant can do

Everything happens on your behalf, with your permissions. An assistant can:

- Read your profile, portfolios, transactions, and holdings
- Create, update, and delete portfolios and transactions
- Turn dividend reinvestment on or off for a holding
- Look up current market data for any symbol

It can only see portfolios you have access to, and it can only change portfolios where you have [full access](user-guide.md#access-levels). Shared portfolios where you're read-only stay read-only.

## Before you start

You need the address of your MCP endpoint. It's your Investbrain address followed by `/mcp`:

```
https://<your-investbrain-host>/mcp
```

Where the assistant runs matters:

- **Cloud assistants** (Claude on the web, desktop, or mobile, and ChatGPT) connect from the provider's servers. Your Investbrain instance must be reachable from the public internet over HTTPS.
- **Local assistants** (Claude Code, Cursor, VS Code, and other tools running on your own computer) connect from your machine, so a local address such as `http://localhost:8000/mcp` works.

You also need to set `APP_URL` to that public address. See [MCP and OAuth requirements](self-hosting.md#mcp-and-oauth-requirements).

## Connecting an assistant

Menus change between versions, so check your assistant's documentation if a step doesn't match exactly. In every case you only need the endpoint URL: there's no API key to paste, because sign-in happens through OAuth in your browser.

### Claude (web, desktop, and mobile)

1. Open **Settings** and go to **Connectors**.
2. Choose **Add custom connector**.
3. Give it a name, such as "Investbrain", and paste your MCP endpoint URL.
4. Click **Add**, then **Connect**. Claude opens Investbrain in your browser so you can [authorize access](#authorizing-access).

On Team and Enterprise plans, an organization owner may need to add the connector first.

### Claude Code

```bash
claude mcp add --transport http investbrain https://investbrain.example.com/mcp
```

Then run `/mcp` inside Claude Code, select **investbrain**, and choose to authenticate. Your browser opens so you can authorize access.

### ChatGPT

Custom MCP connectors are available in ChatGPT's developer mode on supported plans. Turn on developer mode in **Settings**, create a new connector with your MCP endpoint URL, choose OAuth as the authentication method, and follow the prompts to authorize.

### Cursor

Add the server to `~/.cursor/mcp.json` (for all projects) or `.cursor/mcp.json` (for one project):

```json
{
  "mcpServers": {
    "investbrain": {
      "url": "https://investbrain.example.com/mcp"
    }
  }
}
```

Cursor will prompt you to log in the first time it uses the server.

### VS Code

Add the server to `.vscode/mcp.json` in your workspace, or to your user configuration:

```json
{
  "servers": {
    "investbrain": {
      "type": "http",
      "url": "https://investbrain.example.com/mcp"
    }
  }
}
```

Start the server from the file or the MCP servers list and follow the sign-in prompt.

### Other clients

Any client that supports remote MCP servers over Streamable HTTP with OAuth should work. Point it at your `/mcp` endpoint. The client registers itself with Investbrain automatically.

To test the server without an assistant, you can use the [MCP Inspector](https://modelcontextprotocol.io/docs/tools/inspector):

```bash
npx @modelcontextprotocol/inspector
```

Choose the Streamable HTTP transport, enter your endpoint URL, and connect.

## Authorizing access

When you connect, your assistant sends you to Investbrain in the browser:

1. Sign in if you aren't already (including your 2FA code, if you use it).
2. Investbrain shows a consent screen: **Authorize _client name_**, with the email of the account being shared and a list of what the app will be able to do (**View and manage your portfolios, transactions, and holdings**).
3. Click **Authorize** to grant access, or **Cancel** to refuse.

<!-- SCREENSHOT: The Authorize consent screen for an MCP client such as Claude, showing the client name, account email, and permission list. -->
<!-- ![The Investbrain authorization screen for an MCP client](images/mcp-consent-screen.png) -->

You're then sent back to your assistant, which can start using the tools.

Check the email on the consent screen before authorizing. If you have more than one Investbrain account, make sure you're signed in to the right one.

## Example prompts

Once connected, just ask in plain language:

- "What portfolios do I have in Investbrain?"
- "Show me my holdings in the Roth IRA portfolio, sorted by unrealized gain."
- "Which of my holdings have the highest dividend yield?"
- "I bought 15 shares of MSFT at $410.20 yesterday in my Brokerage portfolio. Record it."
- "Record a sale of 5 shares of NVDA at $135 on October 3 in my Fidelity portfolio."
- "Turn on dividend reinvestment for SCHD in my Roth IRA."
- "Create a wishlist portfolio called 'Ideas to research' and add a hypothetical buy of 10 shares of ASML today."
- "What's the current price and 52-week range of COST?"

Most assistants ask for confirmation before using tools that change data. Read what the assistant is about to do, especially before it deletes anything.

## Available tools

| Tool | Type | Description | Inputs |
| --- | --- | --- | --- |
| `get-current-user` | Read | Your profile, display currency, and locale | none |
| `list-portfolios` | Read | Your portfolios, with owners, transactions, and holdings | `page`, `items_per_page` |
| `show-portfolio` | Read | One portfolio | `portfolio_id` |
| `create-portfolio` | Write | Create a portfolio | `title` (5 to 255 characters), `notes`, `wishlist` |
| `update-portfolio` | Write | Update a portfolio | `portfolio_id`, plus any of `title`, `notes`, `wishlist` |
| `delete-portfolio` | Destructive | Permanently delete a portfolio and everything in it | `portfolio_id` |
| `list-transactions` | Read | Your transactions across all portfolios | `page`, `items_per_page` |
| `show-transaction` | Read | One transaction | `transaction_id` |
| `create-transaction` | Write | Record a buy or sell | `portfolio_id`, `symbol`, `transaction_type`, `date`, `quantity`, `currency`, `cost_basis` (buys), `sale_price` (sells) |
| `update-transaction` | Write | Update a transaction | `transaction_id`, plus any transaction field |
| `delete-transaction` | Destructive | Permanently delete a transaction | `transaction_id` |
| `list-holdings` | Read | Your holdings across all portfolios | `page`, `items_per_page` |
| `show-holding` | Read | One holding | `portfolio_id`, `symbol` |
| `update-holding` | Write | Change holding options | `portfolio_id`, `symbol`, `reinvest_dividends` |
| `show-market-data` | Read | Price, market cap, dividend yield, and other data for a symbol | `symbol` |

List tools return 15 items per page by default, up to 100. Responses use the same fields as the [REST API](api.md#endpoints), and the tools enforce the same validation and permission rules as the web app. For example, a sell can't exceed the shares you owned on that date, and dates can't be in the future.

Tools are annotated as read-only, idempotent, or destructive so assistants can decide when to ask you for confirmation.

## Revoking access

Go to **Connections** in the Investbrain user menu. Every assistant you've authorized is listed under **Authorized Apps** with what it can do and when you authorized it. Click **Revoke** to cut off access immediately. The assistant will need to go through authorization again to reconnect.

<!-- SCREENSHOT: Reuses the Authorized Apps screenshot from account-settings.md. No separate capture needed. -->
<!-- ![The Authorized Apps section listing a connected AI assistant](images/account-settings-authorized-apps.png) -->

## How authentication works

This section covers the technical details, in case you're debugging a client or building your own.

- The MCP endpoint is protected by OAuth 2.1 (Laravel Passport). Requests need a bearer access token that carries the `mcp:use` scope.
- Clients discover the authorization server automatically through standard metadata at `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server`. An unauthenticated request to `/mcp` returns `401` with a `WWW-Authenticate` header pointing at the resource metadata.
- Clients register themselves using dynamic client registration at `/oauth/register`, then use the authorization code flow with PKCE (`S256`).
- Personal access tokens from the **API Tokens** page don't carry the `mcp:use` scope, so they're rejected by the MCP endpoint with `403 Forbidden`. Use OAuth for MCP and API tokens for the REST API.
- Signing keys for OAuth tokens are generated automatically on first start of the Docker container and stored in the `storage` volume.

## Troubleshooting

**The assistant can't reach the server, or authorization fails partway through.**
For cloud assistants, your instance must be on the public internet over HTTPS, and `APP_URL` must be set to that public HTTPS address. The OAuth metadata is built from `APP_URL`, so a wrong value sends the assistant to an address it can't reach. See [MCP and OAuth requirements](self-hosting.md#mcp-and-oauth-requirements).

**`403 Forbidden` from `/mcp`.**
The token doesn't have the `mcp:use` scope. This happens if you paste a personal API token into the client. Remove any manual token or header and let the client use OAuth.

**The assistant used to work but now gets `401 Unauthorized`.**
Access may have been revoked under **Authorized Apps**, or the token expired and couldn't be refreshed. Reconnect or re-authenticate the server in your assistant.

**The assistant says it doesn't have permission to change a portfolio.**
You have read-only access to that portfolio. Ask the owner for full access.

For more, see [Troubleshooting](troubleshooting.md#mcp-clients-cant-connect).
