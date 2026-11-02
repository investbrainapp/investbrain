# Import and Export

Investbrain can export everything in your account to a single Excel workbook and import that workbook back. Use it to:

- Keep regular backups of your portfolios and transactions
- Move your data between Investbrain Cloud and a self-hosted instance (in either direction), or from one self-hosted instance to another
- Bulk-load years of transaction history from a spreadsheet instead of typing it in
- Make bulk edits to existing transactions

Open **Import / Export Data** from the user menu to get started. On Investbrain Cloud, you need to verify your email address before this page is available.

<!-- SCREENSHOT: The Import / Export page showing the Import section (with the Download import template link) and the Export section. -->
<!-- ![The Import / Export page](images/import-export-page.png) -->

## Exporting your data

Click **Download Export**. You'll get a file named like `2026_11_01_investbrain_backup.xlsx` containing:

- Every portfolio you have access to, including portfolios shared with you
- Every transaction in those portfolios
- Daily change history (including any annotations)
- Your profile settings: name, locale, display currency, and which holdings have dividend reinvestment turned on

You can export up to three times per minute.

## Importing into Investbrain

Investbrain imports `.xlsx` workbooks of up to 2 MB. The easiest starting point is the import template: click **Download import template** on the Import / Export page to get an empty workbook with the right sheets and headings. An Investbrain export uses the same format, so you can also add rows to an existing export and import it again.

<!-- SCREENSHOT: The downloaded template open in Excel or LibreOffice with the Transactions sheet selected and the Portfolios, Transactions, Daily Changes, and Config tabs visible. -->
<!-- ![The import template opened in a spreadsheet app, showing its four sheets](images/import-export-template-workbook.png) -->

### Running an import

1. Click **Select a file** and choose your workbook.
2. Click **Import**.

A progress dialog shows each stage: portfolios, transactions, then daily changes. The import runs in the background, so you can click **Dismiss** and keep using Investbrain. You'll also get an email when it succeeds or fails. On a self-hosted instance, that email is only sent once you've [set up email](self-hosting.md#email).

<!-- SCREENSHOT: Import progress dialog part-way through, for example while importing transactions. -->
<!-- ![The import progress dialog](images/import-export-progress.png) -->

After the import finishes, Investbrain refreshes market data and dividends for your holdings, recalculates every holding, and rebuilds the daily history for each portfolio. On a large import this can take several minutes, so charts and totals may fill in gradually.

You can start up to three imports per minute.

### How imports work

- **Keep all four sheets, but only fill in the ones you need.** The workbook must contain the Portfolios, Transactions, Daily Changes, and Config sheets, but a sheet can be empty below its heading row. Most people only need the Transactions sheet. The Portfolios sheet is useful when you want the import to create or update portfolios. The Daily Changes and Config sheets are only needed to restore a backup.
- **Keep the sheet names and the heading row as they are.** Columns are matched by their heading, so their order doesn't matter and extra columns are ignored.
- **Rows are created or updated.** A row with a blank ID is created as a new record. A row whose ID matches an existing record updates that record. Importing never deletes anything, so rows you remove from the spreadsheet stay in Investbrain.
- **Each sheet is checked before it's saved.** Sheets are imported in order: Portfolios, then Transactions, then Daily Changes, then Config. If any row on a sheet has a problem, nothing from that sheet is saved, the import stops, and the progress dialog shows the error with its row number. Fix the spreadsheet and click **Try again**.
- **Sheets imported before the failure are kept.** For example, if the Transactions sheet has an error, the Portfolios sheet has already been saved. Re-importing updates rows that have an ID instead of duplicating them, but portfolio rows with a blank Portfolio ID are created again. Before you retry, either delete the portfolios the failed import created, or copy their new IDs into the Portfolios sheet.

## Portfolios sheet

| Column | Required | Description |
| --- | --- | --- |
| Portfolio ID | Optional | Leave blank to create a new portfolio. Use an existing ID to update that portfolio. You can also enter a new UUID of your own, so that the Transactions sheet can refer to the portfolio (see [Referring to portfolios](#referring-to-portfolios)). |
| Title | Required | The name of the portfolio. |
| Notes | Optional | Free text. |
| Wishlist | Optional | `TRUE` or `FALSE`. Defaults to `FALSE`. |
| Created, Updated | Optional | Included in exports for reference. Ignored on import. |

## Transactions sheet

| Column | Required | Description |
| --- | --- | --- |
| Transaction ID | Optional | Leave blank to add a new transaction. Use an existing ID to update that transaction. |
| Symbol | Required | The ticker as your market data provider lists it, such as `AAPL`, `BRK-B`, or `VOD.L`. Converted to upper case. |
| Portfolio ID | Required | The ID of a portfolio in your account, or in the Portfolios sheet of the same file. |
| Transaction Type | Required | `BUY` or `SELL`, in upper case. |
| Quantity | Required | Number of shares. Can't be negative. |
| Cost Basis | Optional | Price paid per share. Use `0` for shares you received for free, such as from a split. |
| Sale Price | Optional | Price received per share. Only used for `SELL` rows. |
| Currency | Required | Three-letter code of the currency the prices are in, such as `USD`. Use the currency the symbol trades in (see [Currencies](#currencies)). |
| Split | Optional | `TRUE` if the row records extra shares from a stock split. Defaults to `FALSE`. |
| Reinvested Dividend | Optional | `TRUE` if the row is a `BUY` paid for with a dividend. Defaults to `FALSE`. |
| Date | Required | Trade date as text, such as `2024-03-15`. |
| Created, Updated | Optional | Included in exports for reference. Ignored on import. |

Imported transactions are saved as they are. Unlike transactions you enter in the app, they aren't checked against your market data provider or your available shares, so the sections below explain what to double-check.

### Referring to portfolios

The Portfolio ID column must contain a portfolio's ID (a UUID such as `9d6c1f3e-5b1a-4c0e-8f5e-2a7b9c3d4e5f`). Portfolio titles aren't accepted. To get an ID:

- **For an existing portfolio**, open it in Investbrain and copy the ID from the address bar (`/portfolio/<id>`), or take it from an export.
- **For a new portfolio**, generate a UUID yourself (for example with `uuidgen` or an online UUID generator). Put it in the Portfolio ID column on the Portfolios sheet, and use the same value on the Transactions sheet.

You can only import into portfolios that you own or that have been shared with you with full access.

### Currencies

Enter prices in the currency the symbol trades in, and put that currency in the Currency column. For example, use `USD` for `AAPL` and `GBX` (pence) for most London-listed shares. Unlike the transaction form in the app, imports don't convert a price from another currency into the trading currency, so a price entered in the wrong currency shows up with the wrong value.

### Reinvested dividends

Record a reinvested dividend as a `BUY` row with Reinvested Dividend set to `TRUE`. Set Quantity to the number of shares bought and Cost Basis to the price paid per share.

You don't need rows for cash dividends. Investbrain works out dividends earned from your market data provider's dividend history and the shares you held on each date.

### Stock splits

Record a split as a `BUY` row with Split set to `TRUE` and a Cost Basis of `0`. Set Quantity to the number of extra shares you received. For example, a 2-for-1 split of 10 shares is a split row with a Quantity of 10.

Include rows for every split that happened while you held the shares. Investbrain detects splits automatically only for dates after the import, so it won't add earlier ones for you.

Reverse splits (which reduce the number of shares you hold) can't be imported as split rows, because quantities can't be negative. Instead, enter your purchases from before the split with the split-adjusted quantity and price. For example, for a 1-for-10 reverse split, 100 shares bought at $2 become 10 shares at $20.

### Sales

Imports don't check that a `SELL` row sells only shares the portfolio held on that date, so check your sales before importing.

If you leave Cost Basis blank on a `SELL` row, it's saved as `0`, which makes the realized gain wrong. To recalculate it from your average purchase cost after importing:

- **Any instance, including Investbrain Cloud** - open the holding, click the sale in **Recent activity**, and click **Update** without changing anything. Saving a sale recalculates its cost basis.
- **Self-hosted, many sales at once** - run:

  ```bash
  docker exec -it investbrain-app php artisan fix:cost-basis-for-sales --portfolio=<portfolio_id>
  ```

### Symbols

Symbols can contain letters, numbers, and `. - ^ = : / _`, with no spaces, up to 25 characters. They aren't checked against your market data provider during the import, so a mistyped symbol creates a holding with no price. After importing, check that every holding shows a name and a market value.

## Daily Changes sheet

This sheet stores your portfolios' daily history and is only needed to restore a backup. Market values are recalculated after the import, so only these columns are read:

| Column | Required | Description |
| --- | --- | --- |
| Date | Required | The day the row describes. |
| Portfolio ID | Required | The ID of the portfolio. You need full access to it. |
| Annotation | Optional | A note for that day. |

## Config sheet

This sheet stores your account settings and is only needed to restore a backup.

| Column | Required | Description |
| --- | --- | --- |
| Key | Required | One of `name`, `locale`, `display_currency`, or `reinvested_dividends`. Other keys are ignored. |
| Value | Required | The value for that setting. For `reinvested_dividends`, a JSON list of holdings, such as `[{"portfolio_id": "...", "symbol": "SCHD"}]`. |

## Formatting tips

- Type dates as text in `YYYY-MM-DD` format, such as `2024-03-15`, which is what exports use. If your spreadsheet app turns them into date cells, set the column's format to Text before typing.
- Write `BUY` and `SELL` in upper case.
- For true/false columns, use `TRUE`/`FALSE` or `1`/`0`. A blank cell means `FALSE`.
- Enter prices and quantities as plain numbers, without currency symbols such as `$` or `€` and without thousands separators.
- To be safe, convert formulas to plain values before importing (copy the cells, then paste values only).
