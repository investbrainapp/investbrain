# Developing Market Data Providers

Investbrain gets prices, fundamentals, dividends, splits, and price history through a small, pluggable interface. Adding support for a new data source means writing one PHP class. This guide explains how the provider system works, what your class has to return, and how to test it and contribute it back.

If you only want to choose or configure a provider, see [Market data providers](self-hosting.md#market-data-providers) instead. For setting up a development environment and opening pull requests in general, see [Contributing](contributing.md).

- [How providers work](#how-providers-work)
- [The interface](#the-interface)
- [Return types](#return-types)
- [Rules every provider must follow](#rules-every-provider-must-follow)
- [Building a provider step by step](#building-a-provider-step-by-step)
- [Testing your provider](#testing-your-provider)
- [Trying it in the app](#trying-it-in-the-app)
- [Contributing your provider](#contributing-your-provider)

## How providers work

```
app code  ──>  MarketDataInterface  ──>  FallbackInterface  ──>  yahoo ──> finnhub ──> ...
                (bound in AppServiceProvider)   (tries each provider in MARKET_DATA_PROVIDER order)
```

- Application code never calls a provider class directly. It asks the container for `App\Interfaces\MarketData\MarketDataInterface`, which [`AppServiceProvider`](../app/Providers/AppServiceProvider.php) binds to [`FallbackInterface`](../app/Interfaces/MarketData/FallbackInterface.php).
- `FallbackInterface` reads the comma-separated `MARKET_DATA_PROVIDER` setting, looks up each name in the `interfaces` map in [`config/investbrain.php`](../config/investbrain.php), and calls the same method on each provider in order until one returns without throwing.
- **Throwing an exception is how your provider says "I can't answer this."** The fallback logs the error, records it, and moves on to the next provider. If every provider fails, the fallback throws, except for `exists()`, which returns `false`.
- Every call is timed and recorded through the `MarketDataCallCompleted` event, so your provider shows up on the **Provider Health** page automatically. You don't need to add any logging or metrics code.

Here's where the app calls each method:

| Method | Called by | When |
| --- | --- | --- |
| `exists()` | `SymbolValidationRule` | When someone enters a symbol that isn't already in the `market_data` table |
| `quote()` | `MarketData::getMarketData()` | When a symbol's stored quote is older than `MARKET_DATA_REFRESH` minutes |
| `dividends()` | `Dividend::refreshDividendData()` | Three times a week, and after imports |
| `splits()` | `Split::refreshSplitData()` | Weekly |
| `history()` | `Portfolio::syncDailyChanges()` | When a portfolio's daily history is rebuilt (past-dated transactions, imports, `sync:daily-change`) |

## The interface

Your class implements [`MarketDataInterface`](../app/Interfaces/MarketData/MarketDataInterface.php):

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

Results are returned as small typed collections in [`app/Interfaces/MarketData/Types`](../app/Interfaces/MarketData/Types). You build them by passing an array to the constructor. Each key maps to a setter (`market_value` calls `setMarketValue()`), and the setters validate and normalize the values. Passing a value of the wrong type throws an `InvalidArgumentException`, and `null` values are skipped.

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

## Building a provider step by step

This example adds a fictional provider called **ExampleData**, with the config name `exampledata`.

### 1. Add a config file

Create `config/exampledata.php`:

```php
<?php

declare(strict_types=1);

return [
    'key' => env('EXAMPLEDATA_API_KEY'),
];
```

### 2. Write the provider class

Create `app/Interfaces/MarketData/ExampleDataMarketData.php`. The existing providers in that folder are good references: [`TwelveDataMarketData`](../app/Interfaces/MarketData/TwelveDataMarketData.php) and [`AlpacaMarketData`](../app/Interfaces/MarketData/AlpacaMarketData.php) use the Laravel HTTP client, and [`FakeMarketData`](../app/Interfaces/MarketData/FakeMarketData.php) shows the simplest possible implementation.

```php
<?php

declare(strict_types=1);

namespace App\Interfaces\MarketData;

use App\Interfaces\MarketData\Types\Dividend;
use App\Interfaces\MarketData\Types\Ohlc;
use App\Interfaces\MarketData\Types\Quote;
use App\Interfaces\MarketData\Types\Split;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ExampleDataMarketData implements MarketDataInterface
{
    protected string $baseUrl = 'https://api.exampledata.com/v1/';

    public function exists(string $symbol): bool
    {
        return (bool) $this->quote($symbol);
    }

    public function quote(string $symbol): Quote
    {
        $price = $this->client()->get('price', ['symbol' => $symbol])->throw()->json();

        if (empty(Arr::get($price, 'last'))) {
            throw new \Exception("Symbol {$symbol} was not found on ExampleData");
        }

        // fundamentals change slowly, so cache them for a day
        $profile = cache()->remember('exampledata-symbol-'.$symbol, 1440, function () use ($symbol) {
            return $this->client()->get('profile', ['symbol' => $symbol])->throw()->json();
        });

        return new Quote([
            'name' => Arr::get($profile, 'name'),
            'symbol' => $symbol,
            'currency' => Arr::get($profile, 'currency'),
            'market_value' => (float) Arr::get($price, 'last'),
            'fifty_two_week_high' => Arr::get($profile, 'high_52w'),
            'fifty_two_week_low' => Arr::get($profile, 'low_52w'),
            'market_cap' => Arr::get($profile, 'market_cap'),
            'dividend_yield' => Arr::get($profile, 'dividend_yield_percent'),
            'meta_data' => [
                'exchange' => Arr::get($profile, 'exchange'),
                'sector' => Arr::get($profile, 'sector'),
                'source' => 'exampledata',
            ],
        ]);
    }

    public function dividends(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection
    {
        $response = $this->client()->get('dividends', [
            'symbol' => $symbol,
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString(),
        ])->throw()->json('data', []);

        return collect($response)->map(fn (array $dividend) => new Dividend([
            'symbol' => $symbol,
            'date' => Carbon::parse($dividend['ex_date']),
            'dividend_amount' => (float) $dividend['amount'],
        ]));
    }

    public function splits(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection
    {
        $response = $this->client()->get('splits', [
            'symbol' => $symbol,
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString(),
        ])->throw()->json('data', []);

        return collect($response)->map(fn (array $split) => new Split([
            'symbol' => $symbol,
            'date' => Carbon::parse($split['date']),
            'split_amount' => $split['to_factor'] / $split['from_factor'],
        ]));
    }

    public function history(string $symbol, \DateTimeInterface $startDate, \DateTimeInterface $endDate): Collection
    {
        $response = $this->client()->get('history', [
            'symbol' => $symbol,
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString(),
        ])->throw()->json('data', []);

        return collect($response)->mapWithKeys(function (array $bar) use ($symbol) {
            $date = Carbon::parse($bar['date'])->toDateString();

            return [$date => new Ohlc([
                'symbol' => $symbol,
                'date' => $date,
                'open' => (float) $bar['open'],
                'high' => (float) $bar['high'],
                'low' => (float) $bar['low'],
                'close' => (float) $bar['close'],
            ])];
        });
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(15)
            ->withQueryParameters(['apikey' => config('exampledata.key')]);
    }
}
```

`->throw()` turns HTTP errors (401, 429, 500, and so on) into exceptions, which is exactly what the fallback needs.

### 3. Register the provider

Add it to the `interfaces` map in [`config/investbrain.php`](../config/investbrain.php). The array key is the name people put in `MARKET_DATA_PROVIDER`:

```php
'interfaces' => [
    'yahoo' => App\Interfaces\MarketData\YahooMarketData::class,
    // ...
    'exampledata' => App\Interfaces\MarketData\ExampleDataMarketData::class,
],
```

Use a short, lowercase name with no spaces or commas.

### 4. Document the settings

- Add `EXAMPLEDATA_API_KEY=` to [`.env.example`](../.env.example) under the other market data keys.
- Add a row to the provider table and the market data options table in [`docs/self-hosting.md`](self-hosting.md#market-data-providers).
- Add the provider to the list in the root [`README.md`](../README.md#market-data-providers).

## Testing your provider

Every provider PR needs tests. Tests must never call the real API: use `Http::fake()` with canned responses taken from the provider's documentation (with any real keys removed).

The test suite runs with `MARKET_DATA_PROVIDER=fake` (see [`phpunit.xml`](../phpunit.xml)), so the rest of the app uses `FakeMarketData` while your test calls your class directly.

Investbrain keeps its tests directly in the `tests` folder (not `tests/Feature`) under the `Tests` namespace. Create `tests/ExampleDataMarketDataTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests;

use App\Interfaces\MarketData\ExampleDataMarketData;
use App\Interfaces\MarketData\Types\Quote;
use Illuminate\Support\Facades\Http;

class ExampleDataMarketDataTest extends TestCase
{
    public function test_quote_maps_price_and_profile(): void
    {
        Http::fake([
            'api.exampledata.com/v1/price*' => Http::response(['last' => 231.45]),
            'api.exampledata.com/v1/profile*' => Http::response([
                'name' => 'Apple Inc.',
                'currency' => 'USD',
                'high_52w' => 260.10,
                'low_52w' => 164.08,
            ]),
        ]);

        $quote = (new ExampleDataMarketData)->quote('AAPL');

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertSame('Apple Inc.', $quote->name);
        $this->assertSame('USD', $quote->currency);
        $this->assertSame(231.45, $quote->market_value);
        $this->assertSame('exampledata', $quote->meta_data['source']);
    }

    public function test_quote_throws_for_unknown_symbol(): void
    {
        Http::fake(['api.exampledata.com/v1/price*' => Http::response([])]);

        $this->expectException(\Exception::class);

        (new ExampleDataMarketData)->quote('NOPE');
    }

    public function test_history_is_keyed_by_date(): void
    {
        Http::fake(['api.exampledata.com/v1/history*' => Http::response(['data' => [
            ['date' => '2026-10-30', 'open' => 1, 'high' => 2, 'low' => 1, 'close' => 1.5],
        ]])]);

        $history = (new ExampleDataMarketData)->history('AAPL', now()->subWeek(), now());

        $this->assertSame(1.5, $history['2026-10-30']->close);
    }
}
```

Cover at least:

- `quote()` maps fields correctly, including `currency`
- `quote()` throws for an unknown symbol and for an error response (for example `Http::response([], 429)`)
- `dividends()` and `splits()` return the right amounts, including the `to / from` split ratio
- `history()` is keyed by `Y-m-d` date and includes `close`

Run just your tests while you work:

```bash
php artisan test --compact --filter=ExampleDataMarketDataTest
```

The fallback behaviour itself is already covered by [`tests/FallbackInterfaceTest.php`](../tests/FallbackInterfaceTest.php), so you don't need to test that again.

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
- [ ] No new Composer dependency, or you've agreed one with the maintainers in an issue first
