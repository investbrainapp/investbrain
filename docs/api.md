# REST API

Investbrain has a JSON REST API for reading and managing your portfolios, transactions, and holdings, and for looking up market data. Use it to build scripts, sync data from a brokerage, feed a spreadsheet, or integrate with other tools.

- [Creating an API token](#creating-an-api-token)
- [Making requests](#making-requests)
- [Pagination, sorting, filtering, and search](#pagination-sorting-filtering-and-search)
- [Endpoints](#endpoints)
- [Errors](#errors)
- [OpenAPI specification](#openapi-specification)

## Creating an API token

The API authenticates with personal access tokens.

1. Sign in to Investbrain and open **API Tokens** from the user menu (or go to `/user/api-tokens`). On Investbrain Cloud, you need to verify your email address before this page is available.
2. Under **Create API Token**, enter a **Token Name** that reminds you what it's for, such as "Home spreadsheet" or "Brokerage sync script".
3. Click **Create**.
4. Copy the token from the dialog. **It's only shown once.** If you lose it, delete it and create a new one.

<!-- SCREENSHOT: API Tokens page with the new-token dialog open. Use a token you revoke right after, or blur most of it. -->
<!-- ![The API Token dialog shown after creating a token](images/api-token-created.png) -->

The token acts as you: it can see and change everything your account can, across every portfolio you have access to. Keep it secret and don't commit it to source control.

### Managing tokens

The **Manage API Tokens** section on the same page lists your active tokens and when each was created. Click **Delete** next to a token to revoke it immediately. Any script using it will start receiving `401 Unauthorized` responses.

API tokens work only with the REST API. They can't be used with the [MCP server](mcp.md), which uses OAuth instead.

## Making requests

**Base URL**

| Where you use Investbrain | Base URL |
| --- | --- |
| Investbrain Cloud | `https://investbra.in/api` |
| Self-hosted | `https://<your-investbrain-host>/api` (for a default local install, `http://localhost:8000/api`) |

The examples on this page use Investbrain Cloud. If you self-host, replace `https://investbra.in` with your own address.

**Headers**

Send your token as a bearer token, and ask for JSON so errors are returned as JSON too:

```
Authorization: Bearer <your-token>
Accept: application/json
```

Send request bodies as JSON with `Content-Type: application/json`.

**Example: check that your token works**

```bash
curl https://investbra.in/api/me \
  -H "Authorization: Bearer $INVESTBRAIN_TOKEN" \
  -H "Accept: application/json"
```

```json
{
  "id": "3f8e2b4c-...",
  "name": "Jane Doe",
  "email": "jane@example.com",
  "profile_photo_url": "https://...",
  "options": {
    "display_currency": "USD",
    "locale": "en_US"
  },
  "created_at": "2025-01-04T18:22:10.000000Z",
  "updated_at": "2026-10-30T09:12:45.000000Z"
}
```

Single records are returned as a plain JSON object. Lists are wrapped in `data` with pagination details (see below).

## Pagination, sorting, filtering, and search

The three list endpoints (`GET /portfolio`, `GET /transaction`, and `GET /holding`) share the same query parameters.

| Parameter | Example | Description |
| --- | --- | --- |
| `page` | `page=2` | Page number, starting at 1. |
| `itemsPerPage` | `itemsPerPage=50` | Results per page. Default 15, maximum 100. |
| `sort` | `sort=-date,symbol` | Comma-separated columns. Prefix with `-` for descending. |
| `filter[<name>]` | `filter[symbol]=AAPL,MSFT` | Exact match. Separate values with commas to match any of them. For true/false filters, use `true`/`false` or `1`/`0`. |
| `search` | `search=apple` | Case-insensitive partial match on the endpoint's search columns. |

Using a sort or filter that isn't supported for that endpoint returns a `422` validation error listing the allowed values.

Without `sort`, results come back in no particular order. If you page through results, pass `sort` so the order stays the same from page to page.

List responses look like this (`links` and `meta` are shortened here):

```json
{
  "data": [ { "...": "..." } ],
  "links": {
    "first": "https://investbra.in/api/transaction?page=1",
    "last": "https://investbra.in/api/transaction?page=4",
    "prev": null,
    "next": "https://investbra.in/api/transaction?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 15,
    "total": 52
  }
}
```

The `links` keep your other query parameters, so you can follow `next` until it's `null` to read every page.

## Endpoints

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/me` | Your user profile and preferences |
| `GET` | `/portfolio` | List portfolios |
| `POST` | `/portfolio` | Create a portfolio |
| `GET` | `/portfolio/{id}` | Get a portfolio |
| `PUT` | `/portfolio/{id}` | Update a portfolio |
| `DELETE` | `/portfolio/{id}` | Delete a portfolio |
| `GET` | `/transaction` | List transactions |
| `POST` | `/transaction` | Create a transaction |
| `GET` | `/transaction/{id}` | Get a transaction |
| `PUT` | `/transaction/{id}` | Update a transaction |
| `DELETE` | `/transaction/{id}` | Delete a transaction |
| `GET` | `/holding` | List holdings |
| `GET` | `/holding/{portfolio_id}/{symbol}` | Get a holding |
| `PUT` | `/holding/{portfolio_id}/{symbol}` | Update a holding's options |
| `GET` | `/market-data/{symbol}` | Get current market data for a symbol |

All IDs are UUIDs. The API follows the same permissions as the web app: you can read any portfolio you have access to, but creating, changing, or deleting anything requires [full access](user-guide.md#access-levels) to the portfolio.

### Portfolios

**List** `GET /portfolio`

Returns portfolios you own or that are shared with you, each including its owner, transactions, and holdings (with each holding's performance).

- Sort: `title`, `wishlist`, `created_at`, `updated_at`
- Filter: `title`, `wishlist`, `holdings.symbol` (portfolios holding any of the given symbols)
- Search: title and notes

```bash
curl "https://investbra.in/api/portfolio?filter[wishlist]=0&sort=title" \
  -H "Authorization: Bearer $INVESTBRAIN_TOKEN" \
  -H "Accept: application/json"
```

**Create** `POST /portfolio`

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `title` | string | Yes | 5 to 255 characters |
| `notes` | string | No | |
| `wishlist` | boolean | No | Defaults to `false` |

Returns `201 Created` with the new portfolio. You become its owner.

```bash
curl -X POST https://investbra.in/api/portfolio \
  -H "Authorization: Bearer $INVESTBRAIN_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"title": "Fidelity Roth IRA", "notes": "Account ending 1234"}'
```

```json
{
  "id": "9d6c1f3e-5b1a-4c0e-8f5e-2a7b9c3d4e5f",
  "title": "Fidelity Roth IRA",
  "notes": "Account ending 1234",
  "wishlist": false,
  "owner": { "id": "...", "name": "Jane Doe", "email": "jane@example.com", "...": "..." },
  "transactions": [],
  "created_at": "2026-11-01T14:03:11.000000Z",
  "updated_at": "2026-11-01T14:03:11.000000Z"
}
```

**Get** `GET /portfolio/{id}`

Returns the portfolio with its owner and transactions. Unlike the list, single-portfolio responses (including those from create and update) don't include `holdings`. Use `GET /holding?filter[portfolio_id]=<id>` for those, or the portfolio list for holdings with performance.

**Update** `PUT /portfolio/{id}` - send only the fields you want to change (`title`, `notes`, `wishlist`).

**Delete** `DELETE /portfolio/{id}` - returns `204 No Content`. This permanently deletes the portfolio with all of its transactions and holdings.

### Transactions

**List** `GET /transaction`

Returns transactions across all portfolios you have access to.

- Sort: `symbol`, `transaction_type`, `quantity`, `cost_basis`, `sale_price`, `date`, `created_at`, `updated_at`
- Filter: `symbol`, `portfolio_id`, `transaction_type`, `split`, `reinvested_dividend`
- Search: symbol

```bash
# All AAPL sells in one portfolio, newest first
curl "https://investbra.in/api/transaction?filter[portfolio_id]=9d6c1f3e-...&filter[symbol]=AAPL&filter[transaction_type]=SELL&sort=-date" \
  -H "Authorization: Bearer $INVESTBRAIN_TOKEN" \
  -H "Accept: application/json"
```

**Create** `POST /transaction`

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `portfolio_id` | UUID | Yes | You need full access to this portfolio. |
| `symbol` | string | Yes | Must be recognized by Investbrain's market data provider. |
| `transaction_type` | string | Yes | `BUY` or `SELL`, in upper case |
| `date` | string | Yes | `YYYY-MM-DD`, not in the future |
| `quantity` | number | Yes | Greater than 0. For sells, can't exceed the shares owned on that date. |
| `currency` | string | Yes | Currency of the price. Converted to the security's trading currency if different. One of `AUD`, `BRL`, `CAD`, `CHF`, `CNY`, `CZK`, `DKK`, `EUR`, `GBP`, `HKD`, `INR`, `JPY`, `KRW`, `NOK`, `NZD`, `SEK`, `SGD`, `USD`, or `ZAR`. Minor-unit codes such as `GBX` aren't accepted here; send the price in `GBP` instead. |
| `cost_basis` | number | For `BUY` | Price paid per share |
| `sale_price` | number | For `SELL` | Price received per share |

Returns `201 Created` with the new transaction.

A few things to know:

- **Always send `cost_basis` with a buy and `sale_price` with a sell.** Validation doesn't check for them yet. A buy without `cost_basis` fails with a `500` server error instead of a `422`, and a sell without `sale_price` is saved without one, which makes its realized gain wrong.
- **For sells, the cost basis is calculated for you** from the average cost of earlier buys. Any `cost_basis` you send with a sell is ignored.
- **`split` and `reinvested_dividend` are read-only.** Investbrain sets them on the transactions it creates for splits and dividend reinvestment, and ignores them in requests.

```bash
curl -X POST https://investbra.in/api/transaction \
  -H "Authorization: Bearer $INVESTBRAIN_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "portfolio_id": "9d6c1f3e-5b1a-4c0e-8f5e-2a7b9c3d4e5f",
    "symbol": "VOO",
    "transaction_type": "BUY",
    "date": "2026-10-15",
    "quantity": 10,
    "currency": "USD",
    "cost_basis": 512.34
  }'
```

```json
{
  "id": "4b0f7c1a-...",
  "symbol": "VOO",
  "portfolio_id": "9d6c1f3e-5b1a-4c0e-8f5e-2a7b9c3d4e5f",
  "transaction_type": "BUY",
  "quantity": 10,
  "currency": "USD",
  "cost_basis": 512.34,
  "sale_price": null,
  "split": false,
  "reinvested_dividend": false,
  "date": "2026-10-15",
  "created_at": "2026-11-01T14:10:02.000000Z",
  "updated_at": "2026-11-01T14:10:02.000000Z"
}
```

The `currency` returned is always the security's trading currency, and `cost_basis` and `sale_price` are in that currency.

**Get** `GET /transaction/{id}`

**Update** `PUT /transaction/{id}` - send only the fields you want to change. The same validation rules apply. Moving a transaction to another portfolio (changing `portfolio_id`) requires full access to both.

**Delete** `DELETE /transaction/{id}` - returns `204 No Content`.

Creating, updating, or deleting a transaction recalculates the affected holding automatically.

### Holdings

Holdings are created from transactions, so there is no endpoint to create or delete them.

**List** `GET /holding`

- Sort: `symbol`, `quantity`, `average_cost_basis`, `total_cost_basis`, `realized_gain_dollars`, `dividends_earned`, `created_at`, `updated_at`
- Filter: `symbol`, `portfolio_id`, `reinvest_dividends`
- Search: symbol

The list includes fully sold positions (quantity `0`). Sort or check `quantity` if you only want open positions.

In list results, `total_market_value`, `market_gain_dollars`, and `market_gain_percent` are currently always `null`. To get them, fetch the holding on its own (below), or use the portfolio list (`GET /portfolio`), which includes each portfolio's holdings with their performance.

**Get** `GET /holding/{portfolio_id}/{symbol}`

Use the symbol in upper case (`VOO`, not `voo`). Returns `404` if the portfolio has no holding for that symbol, and `403` if you don't have access to the portfolio.

```bash
curl https://investbra.in/api/holding/9d6c1f3e-5b1a-4c0e-8f5e-2a7b9c3d4e5f/VOO \
  -H "Authorization: Bearer $INVESTBRAIN_TOKEN" \
  -H "Accept: application/json"
```

```json
{
  "id": "e2a1...",
  "portfolio_id": "9d6c1f3e-5b1a-4c0e-8f5e-2a7b9c3d4e5f",
  "symbol": "VOO",
  "quantity": 10,
  "currency": "USD",
  "reinvest_dividends": false,
  "average_cost_basis": 512.34,
  "total_cost_basis": 5123.4,
  "realized_gain_dollars": 0,
  "dividends_earned": 0,
  "splits_synced_at": "2026-11-01T14:10:02.000000Z",
  "total_market_value": 5250.1,
  "market_gain_dollars": 126.7,
  "market_gain_percent": 2.47,
  "created_at": "2026-11-01T14:10:02.000000Z",
  "updated_at": "2026-11-01T14:10:02.000000Z"
}
```

**Update** `PUT /holding/{portfolio_id}/{symbol}`

| Field | Type | Notes |
| --- | --- | --- |
| `reinvest_dividends` | boolean | Turn [dividend reinvestment](user-guide.md#reinvesting-dividends) on or off |

### Market data

**Get** `GET /market-data/{symbol}`

Returns the latest market data for any symbol Investbrain's market data provider recognizes, whether or not you hold it. Use the symbol in upper case. Returns `404` if the symbol can't be found.

If Investbrain's stored price for the symbol is older than its refresh interval (30 minutes by default), this request fetches a fresh quote from the market data provider first, so it can take a moment. The response doesn't include the symbol's currency; the holding and transaction endpoints return it as `currency`.

```json
{
  "symbol": "AAPL",
  "name": "Apple Inc.",
  "market_value": 231.45,
  "fifty_two_week_low": 164.08,
  "fifty_two_week_high": 260.1,
  "last_dividend_date": "2026-08-11T00:00:00.000000Z",
  "last_dividend_amount": 0.26,
  "dividend_yield": 0.45,
  "market_cap": 3480000000000,
  "trailing_pe": 35.2,
  "forward_pe": 30.1,
  "book_value": 4.43,
  "created_at": "2025-01-04T18:22:10.000000Z",
  "updated_at": "2026-11-01T14:00:03.000000Z"
}
```

Fields the provider doesn't supply are `null`.

## Errors

| Status | Meaning |
| --- | --- |
| `200 OK`, `201 Created`, `204 No Content` | Success. `201` is returned when a portfolio or transaction is created, and `204` after a delete. |
| `401 Unauthorized` | Missing, invalid, expired, or revoked token. |
| `403 Forbidden` | You don't have access to that portfolio, or you only have read-only access and tried to change something. |
| `404 Not Found` | The record doesn't exist, or the symbol isn't recognized. |
| `422 Unprocessable Content` | Validation failed. See the `errors` object for details. |

Validation errors look like this:

```json
{
  "message": "The quantity must not be greater than the available quantity.",
  "errors": {
    "quantity": ["The quantity must not be greater than the available quantity."]
  }
}
```

If you get an HTML login page instead of a JSON error, add the `Accept: application/json` header.

## OpenAPI specification

A machine-readable OpenAPI 3 description of the API is kept in the repository at [`openapi.json`](../openapi.json). Import it into Postman, Insomnia, Bruno, or the [Swagger Editor](https://editor.swagger.io/) to browse endpoints and try requests. The spec defines two servers: Investbrain Cloud (`https://investbra.in/api`) and a self-hosted server where you fill in your own host. If the spec and this page ever disagree, please [open an issue](https://github.com/investbrainapp/investbrain/issues).
