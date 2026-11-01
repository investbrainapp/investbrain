# Import and Export

Investbrain can export everything in your account to a single Excel workbook and import that workbook back. Use it to:

- Keep regular backups of your portfolios and transactions
- Move your data from one Investbrain instance to another
- Bulk-load years of transaction history from a spreadsheet instead of typing it in
- Make bulk edits to existing transactions

Open **Import / Export Data** from the user menu to get started.

## Exporting your data

Click **Download Export**. You'll get a file named like `2026_11_01_investbrain_backup.xlsx` containing:

- Every portfolio you have access to, including portfolios shared with you
- Every transaction in those portfolios
- Daily change history (including any annotations)
- Your profile settings: name, locale, display currency, and which holdings have dividend reinvestment turned on

You can export up to three times per minute.

## The workbook format

The easiest way to build an import file from scratch is to click **Download import template** on the Import / Export page. It gives you an empty workbook with the right sheets and column headings.

The workbook has four sheets. Sheet names and column headings must match exactly. You can leave out a sheet you don't need, and empty rows are skipped.

### Portfolios

| Column | Required | Notes |
| --- | --- | --- |
| Portfolio ID | No | A UUID. If it matches a portfolio you have full access to, that portfolio is updated. If it's blank or new, a portfolio is created. |
| Title | Yes | |
| Notes | No | |
| Wishlist | No | `TRUE`/`FALSE` or `1`/`0`. Defaults to false. |
| Created, Updated | No | Included in exports for reference. Ignored on import. |

### Transactions

| Column | Required | Notes |
| --- | --- | --- |
| Transaction ID | No | A UUID. If it matches an existing transaction, that transaction is updated. If blank, a new transaction is created. |
| Symbol | Yes | Ticker symbol, up to 25 characters (letters, numbers, and `. - ^ = : / _`). Converted to upper case. |
| Portfolio ID | Yes | The UUID of the portfolio the transaction belongs to. You need full access to it. |
| Transaction Type | Yes | `BUY` or `SELL`. |
| Quantity | Yes | Number of shares, zero or more. |
| Cost Basis | No | Price paid per share (for buys). |
| Sale Price | No | Price received per share (for sells). |
| Currency | Yes | Three-letter currency code of the cost basis or sale price, for example `USD`. |
| Split | No | `TRUE` if this transaction represents a stock split. |
| Reinvested Dividend | No | `TRUE` if this transaction represents a reinvested dividend. |
| Date | Yes | The trade date, for example `2024-03-15`. |
| Created, Updated | No | Included in exports for reference. Ignored on import. |

### Daily Changes

This sheet carries the annotations on your daily history. Market values are recalculated after import, so only these columns are read:

| Column | Required | Notes |
| --- | --- | --- |
| Date | Yes | |
| Portfolio ID | Yes | You need full access to the portfolio. |
| Annotation | No | |

### Config

Two columns, **Key** and **Value**. Recognized keys are `name`, `locale`, `display_currency`, and `reinvested_dividends` (a JSON list of `{"portfolio_id": "...", "symbol": "..."}` entries). Unrecognized keys are ignored.

## Importing data

1. Click **Select a file** and choose your `.xlsx` workbook (2 MB maximum).
2. Click **Import**.

A progress dialog shows each stage: portfolios, transactions, then daily changes. The import runs in the background, so you can click **Dismiss** and keep using Investbrain. You'll also receive an email when it succeeds or fails (if your instance has email configured).

After the import finishes, Investbrain automatically refreshes market data and dividends for your holdings, recalculates every holding, and rebuilds the daily history for each portfolio. On a large import this can take several minutes, so charts and totals may fill in gradually.

You can start up to three imports per minute.

### How records are matched (upserts)

Imports are "upserted": rows with an ID that already exists update that record, and rows without a matching ID create a new one. This means you can:

- **Restore a backup** - import an export file and every portfolio and transaction is recreated with its original ID.
- **Bulk edit** - export, change values in the spreadsheet, and import it again. Matching rows are updated in place.
- **Add new history** - add rows with a blank Transaction ID and they're created as new transactions.

Importing never deletes anything. Rows you remove from the spreadsheet stay in Investbrain.

### Bulk-loading transactions into a new portfolio

Every transaction row needs a Portfolio ID, so the portfolio has to have an ID before its transactions can reference it. Either:

- Create the portfolio in Investbrain first, then copy its ID from the address bar (`/portfolio/<id>`) into your Transactions sheet, or
- Generate a UUID yourself (for example with an online UUID generator or `uuidgen`), put it in the **Portfolio ID** column on the Portfolios sheet, and use the same value on the Transactions sheet.

### Things to know

- Transaction imports are trusted as-is. Unlike transactions entered in the app, imported rows aren't checked against your market data provider for valid symbols or against your available quantity for sells. Double-check sells in your spreadsheet.
- If any row fails validation or references a portfolio you don't have full access to, the import stops and the progress dialog shows the error. Fix the spreadsheet and click **Try again**.
- If a transaction's currency differs from the instance's base currency, Investbrain downloads the historical exchange rates for the date range in the file before importing.
- Imports run on the background queue. On a self-hosted instance, the queue worker runs inside the Investbrain container automatically.
