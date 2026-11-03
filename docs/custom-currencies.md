---
title: "Custom Currencies"
description: "Add extra ISO 4217 currencies supported by Frankfurter when they are not in the seeded list."
group: "Running your own instance"
order: 32
updated_at: "2026-11-04"
---
# Custom Currencies

Investbrain ships with a seeded list of common currencies and pulls FX rates from [Frankfurter](https://frankfurter.dev/). You can add any other ISO 4217 currency that Frankfurter supports by inserting a row into the `currencies` table. There is no UI for this yet.

For how base currency and display currency work, see [Currency](self-hosting.md#currency) and [Locale and display currency](account-settings.md#locale-and-display-currency).

## 1. Confirm the currency is on Frankfurter

Check the [Frankfurter currencies list](https://api.frankfurter.app/currencies) (for example `PLN` for Polish złoty).

## 2. Insert the currency

Using the Docker Compose stack:

```bash
docker exec -it investbrain-pgsql psql -U investbrain -d investbrain -c \
  "INSERT INTO currencies (currency, label, created_at, updated_at) VALUES ('PLN', 'Polish Złoty', NOW(), NOW()) ON CONFLICT (currency) DO NOTHING;"
```

Or via Tinker:

```bash
docker exec -it investbrain-app php artisan tinker --execute="App\Models\Currency::firstOrCreate(['currency' => 'PLN'], ['label' => 'Polish Złoty']);"
```

## 3. Refresh exchange rates

```bash
docker exec -it investbrain-app php artisan refresh:currency-data
```

After that, the new currency appears in default-currency settings and on the create-transaction form. You can also delete unused rows from `currencies` if you only want a smaller list.

Currency aliases (for example `GBX` → `GBP`) are configured separately in `config/investbrain.php` under `currency_aliases`.
