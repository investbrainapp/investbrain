<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * SQL fragments shared by the performance, dividend and currency queries, written once so
 * they read (and behave) the same on PostgreSQL and SQLite.
 */
class Sql
{
    /**
     * Quantity of a transaction, positive for a buy and negative for a sale. Summed over a
     * holding's transactions it gives the shares owned.
     */
    public static function signedQuantity(string $table = 'transactions'): string
    {
        return "CASE {$table}.transaction_type WHEN 'BUY' THEN {$table}.quantity WHEN 'SELL' THEN -{$table}.quantity ELSE 0 END";
    }

    /**
     * The calendar date of a date column.
     *
     * SQLite has no date type, and the same table can hold 'Y-m-d' and 'Y-m-d H:i:s' text depending
     * on whether the row was written by Eloquent or as a plain string, so comparing columns as text
     * fails on the same day. PostgreSQL stores real dates, so the column is left bare to keep its index.
     */
    public static function date(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "DATE({$column})" : $column;
    }

    /**
     * Join condition for the currency rate on a given date, for use with leftJoin('currency_rates as cr', ...).
     * A missing rate is NULL, so callers wrap the rate in COALESCE(cr.rate, 1).
     */
    public static function onCurrencyRate(string $dateColumn, string $currency, string $alias = 'cr'): \Closure
    {
        return function (JoinClause $join) use ($dateColumn, $currency, $alias) {
            $join->where("{$alias}.currency", '=', $currency);

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $join->on("{$alias}.date", '=', $dateColumn);

                return;
            }

            // rates are stored as 'Y-m-d' or (by Eloquent) 'Y-m-d 00:00:00'; matching both spellings keeps the
            // (date, currency) key usable, which DATE(cr.date) = DATE(...) and a range on cr.date don't
            $join->whereRaw("{$alias}.date IN (DATE({$dateColumn}), DATE({$dateColumn}) || ' 00:00:00')");
        };
    }
}
