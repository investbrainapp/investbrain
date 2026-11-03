---
title: "Developing Market Data Providers"
description: "Write, test, and contribute a new market data provider."
group: "Contributing"
order: 9
---
# Developing Market Data Providers

Investbrain gets prices, fundamentals, dividends, splits, and price history through a small, pluggable interface. Adding support for a new data source means writing one PHP class. This guide explains how the provider system works, what your class has to return, and how to test it and contribute it back.

If you only want to choose or configure a provider, see [Market data providers](self-hosting.md#market-data-providers) instead. For setting up a development environment and opening pull requests in general, see [Contributing](contributing.md).

- [How providers work](#how-providers-work)
- [The interface](#the-interface)
- [Return types](#return-types)
- [Rules every provider must follow](#rules-every-provider-must-follow)
- [Trying it in the app](#trying-it-in-the-app)
- [Contributing your provider](#contributing-your-provider)

## How providers work

Application code never calls a provider class directly. It asks the container for `App\Interfaces\MarketData\MarketDataInterface`, which [`AppServiceProvider`](https://github.com/investbrainapp/investbrain/blob/main/app/Providers/AppServiceProvider.php) binds to [`FallbackInterface`](https://github.com/investbrainapp/investbrain/blob/main/app/Interfaces/MarketData/FallbackInterface.php).
- `FallbackInterface` reads the comma-separated `MARKET_DATA_PROVIDER` setting, looks up each name in the `interfaces` map in [`config/investbrain.php`](https://github.com/investbrainapp/investbrain/blob/main/config/investbrain.php), and calls the same method on each provider in order until one returns without throwing.

```
app code  ──>  MarketDataInterface  ──>  FallbackInterface  ──>  yahoo ──> finnhub ──> ...
                (bound in AppServiceProvider)   (tries each provider in MARKET_DATA_PROVIDER order)
```

Here's where the app calls each method:

| Method | Called by | When |
| --- | --- | --- |
| `exists()` | `SymbolValidationRule` | When someone enters a symbol that isn't already in the `market_data` table |
| `quote()` | `MarketData::getMarketData()` | When a symbol's stored quote is older than `MARKET_DATA_REFRESH` minutes |
| `dividends()` | `Dividend::refreshDividendData()` | Three times a week, and after imports |
| `splits()` | `Split::refreshSplitData()` | Weekly |
| `history()` | `Portfolio::syncDailyChanges()` | When a portfolio's daily history is rebuilt (past-dated transactions, imports, `sync:daily-change`) |

## The interface

Your class implements [`MarketDataInterface`](https://github.com/investbrainapp/investbrain/blob/main/app/Interfaces/MarketData/MarketDataInterface.php):

```php
interface MarketDataInterface
{
    public function exists(string $symbol): bool;

    public function quote(string $symbol): Quote;

    public function dividends(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;

    public function splits(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;

    public function history(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection;
}
```

All five methods are required. If your data source doesn't offer one of them (for example, no split data), throw an exception from that method so the fallback can try the next provider.

The start and end dates the app passes are Carbon instances, so you can call `toDateString()` or read `->timestamp` on them.

## Return types

Results are returned as small typed collections in [`app/Interfaces/MarketData/Types`](https://github.com/investbrainapp/investbrain/tree/main/app/Interfaces/MarketData/Types). You build them by passing an array to the constructor. Each key maps to a setter (`market_value` calls `setMarketValue()`), and the setters validate and normalize the values. Passing a value of the wrong type throws an `InvalidArgumentException`, and `null` values are skipped.

### `Quote`

Returned by `quote()`. This is what's stored in the `market_data` table and shown on holding pages.

| Key | Type | Notes |
| --- | --- | --- |
| `symbol` | string | **Required.** Return the symbol you were asked for. |
| `market_value` | int or float | **Required.** Current price per share, in `currency`. |
| `currency` | string | **Required.** ISO 4217 code of the price. Common provider quirks (`US`, `CA`, `GBp`) are normalized for you, and the value is upper-cased. Use `GBX` for London prices quoted in pence. |
| `name` | string | Company or fund name. Empty values are ignored. |
| `fifty_two_week_high`, `fifty_two_week_low` | number | 52-week range, in `currency`. |
| `forward_pe`, `trailing_pe` | number | Price-to-earnings ratios. |
| `market_cap` | number | In whole currency units (not millions). |
| `book_value` | number | Per share. |
| `dividend_yield` | number | **As a percentage**, so `0.45` means 0.45% and `3.2` means 3.2%. |
| `last_dividend_date` | date | |
| `last_dividend_amount` | number | Per share. |
| `meta_data` | array | Optional keys: `sector`, `industry`, `country`, `exchange`, `description`, `asset_type`, `first_trade_year`, `source`. Set `source` to your provider's config name. Missing keys default to `null`. |

### `Dividend`

`dividends()` returns a `Collection` of `Dividend` objects, one per payment between the start and end dates.

| Key | Type | Notes |
| --- | --- | --- |
| `symbol` | string | |
| `date` | string or DateTime | The ex-dividend date. |
| `dividend_amount` | int or float | **Per share**, in the security's trading currency. |

### `Split`

`splits()` returns a `Collection` of `Split` objects.

| Key | Type | Notes |
| --- | --- | --- |
| `symbol` | string | |
| `date` | string or DateTime | The date the split took effect. |
| `split_amount` | int or float | **New shares per old share.** A 4-for-1 split is `4`; a 1-for-10 reverse split is `0.1`. If your source gives "from" and "to" factors, use `to / from`. |

### `Ohlc`

`history()` returns a `Collection` of `Ohlc` objects **keyed by date in `Y-m-d` format**:

```php
collect([
    '2026-10-30' => new Ohlc(['symbol' => 'AAPL', 'date' => '2026-10-30', 'close' => 231.45]),
    '2026-10-31' => new Ohlc(['symbol' => 'AAPL', 'date' => '2026-10-31', 'close' => 233.10]),
]);
```

| Key | Type | Notes |
| --- | --- | --- |
| `symbol` | string | |
| `date` | string or DateTime | |
| `close` | int or float | **Required by the app.** Closing price for the day. |
| `open`, `high`, `low` | int or float | Optional. |

The date keys matter: the daily history code looks up each day with `$history["$date"]->close` and steps back up to five days to cover weekends and holidays. Return one entry per trading day; you don't need to fill in non-trading days.

## Rules every provider must follow

- **Throw when you can't answer.** Unknown symbol, empty response, rate limit, network error: throw an exception with a clear message. Never return a `Quote` with a zero or empty price, because the app would store it as real data and the fallback wouldn't try another provider.
- **`exists()` must be cheap and honest.** Most providers implement it as `return (bool) $this->quote($symbol);`. Returning `true` for a symbol your source doesn't know makes the app accept invalid symbols.
- **Read settings with `config()`, never `env()`.** Put your keys in a `config/<provider>.php` file. `env()` returns `null` once the configuration is cached.
- **Cache slow-changing data.** Prices change often but fundamentals don't. The existing providers cache fundamentals for a day with `cache()->remember('<prefix>-symbol-'.$symbol, 1440, ...)` and only fetch the live price on every call. This matters a lot on free plans with small rate limits.
- **Use the trading currency.** All prices, 52-week values, and dividend amounts must be in the currency you return in `Quote::currency`. Investbrain does all conversion itself.
- **Leave out what you don't have.** If your source doesn't provide a field, don't set it. Don't guess.
- **Don't add dependencies lightly.** Prefer Laravel's built-in [HTTP client](https://laravel.com/docs/http-client) (`Illuminate\Support\Facades\Http`) over a vendor SDK. It needs no new package and is easy to fake in tests. If you really need a new Composer package, open an issue to discuss it first.

## Trying it in the app

With a [development environment](contributing.md#setting-up-a-development-environment) running, put your provider first and keep a known-good provider behind it:

```bash
# .env
MARKET_DATA_PROVIDER=exampledata,yahoo
EXAMPLEDATA_API_KEY=your-real-key
```

Call it directly from the command line:

```bash
php artisan tinker --execute 'dump(app(App\Interfaces\MarketData\ExampleDataMarketData::class)->quote("AAPL"));'
```

Or call it through the fallback, the way the app does:

```bash
php artisan tinker --execute 'dump(app(App\Interfaces\MarketData\MarketDataInterface::class)->quote("AAPL"));'
```

Then exercise it end to end:

1. Create a portfolio and add a buy transaction for a symbol, dated a few months back.
2. Check the holding page shows a price, fundamentals, and dividends.
3. Run `php artisan refresh:market-data --force`, `php artisan refresh:dividend-data --force`, and `php artisan refresh:split-data --force`.
4. Open **Provider Health** to confirm your provider's calls are succeeding (and see the error messages when they aren't).
5. Check `storage/logs/laravel.log` for `Failed calling method ... (exampledata)` lines.

Try a non-US symbol too, to make sure currencies come through correctly.

## Contributing your provider

When your provider works, open a pull request. Follow the general [pull request guidelines](contributing.md#making-a-pull-request), and include in the description:

- A link to the provider's API documentation and pricing page, and whether it has a free tier
- Which methods are fully supported, and which throw because the provider doesn't offer that data
- Any rate limits people should know about
- The symbols and markets you tested with

Before you submit, check that:

- [ ] The class is in `app/Interfaces/MarketData/` and implements `MarketDataInterface`
- [ ] Credentials are read through a `config/<provider>.php` file
- [ ] The provider is registered in `config/investbrain.php`
- [ ] `.env.example`, `docs/self-hosting.md`, and `README.md` are updated
- [ ] Tests use `Http::fake()` and pass with `php artisan test --compact`
- [ ] Code is formatted with `vendor/bin/pint --dirty`
