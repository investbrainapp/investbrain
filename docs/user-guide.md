# User Guide

This guide covers the core of Investbrain: portfolios, transactions, and holdings, plus the features built on top of them.

- [Key concepts](#key-concepts)
- [Creating an account and signing in](#creating-an-account-and-signing-in)
- [The dashboard](#the-dashboard)
- [Portfolios](#portfolios)
- [Sharing portfolios](#sharing-portfolios)
- [Transactions](#transactions)
- [Holdings](#holdings)
- [Dividends](#dividends)
- [Stock splits](#stock-splits)
- [Multiple currencies](#multiple-currencies)
- [Performance charts](#performance-charts)
- [Search](#search)
- [AI chat](#ai-chat)
- [Appearance and language](#appearance-and-language)

## Key concepts

Investbrain is built around three things:

| Concept | What it is |
| --- | --- |
| **Portfolio** | A container for investments, usually one per brokerage account (for example "Fidelity IRA" or "Joint Brokerage"). |
| **Transaction** | A single buy or sell of a security on a given date. Transactions are the source of truth for everything else. |
| **Holding** | A position in one security within one portfolio. Holdings are created and updated automatically from your transactions. You never create a holding by hand. |

Market data (prices, 52-week ranges, fundamentals), dividends, and splits are pulled from the market data provider configured for your instance and applied to your holdings automatically.

## Creating an account and signing in

Open `/register` on your Investbrain instance to create an account with your name, email, and a password. If you've [turned off registration](self-hosting.md#installation), you can still add people by [sharing a portfolio](#sharing-portfolios) with them: they get an invitation email with a link to set their password.

If you've [set up social login](self-hosting.md#social-login), the login page shows a **Login with ...** button for each provider (GitHub, Google, Facebook, or LinkedIn). See [Social logins](account-settings.md#social-logins) for how linking works.

Other sign-in features:

- **Forgot password** - request a reset link from the login page. Your instance must have email configured for the link to be delivered.
- **Two-factor authentication** - if you have turned it on, you'll be asked for a code from your authenticator app (or a recovery code) after entering your password. See [Two-factor authentication](account-settings.md#two-factor-authentication).
- Login attempts are limited to five per minute for each email and IP address.

## The dashboard

The dashboard is the first thing you see after signing in. It rolls up every portfolio you have access to, **except wishlists**.

- **Performance chart** - your combined market gain over time. You can switch to market value or cost basis and pick a time range (1 month, 3 months, year to date, 1 year, 3 years, all time).
- **Summary cards** - market gain/loss, total cost basis, total market value, realized gain/loss, and dividends earned. These are shown in your [display currency](account-settings.md#locale-and-display-currency).
- **My portfolios** - every portfolio you own or that has been shared with you. Wishlists are marked with a badge.
- **Recent activity** - your latest transactions across all portfolios.
- **Top performers** - your best performing holdings.

A brand new account shows two shortcuts: **Import / Export Data** and **Create your first portfolio!**

<!-- SCREENSHOT: Dashboard with a few portfolios: performance chart, the five summary cards, My portfolios, Recent activity, and Top performers. -->
<!-- ![The Investbrain dashboard](images/user-guide-dashboard.png) -->

## Portfolios

### Creating a portfolio

Click **Create Portfolio** in the sidebar (or the button on an empty dashboard) and fill in:

| Field | Notes |
| --- | --- |
| Title | Required, at least 5 characters. |
| Notes | Optional free-form text, for example the account number or brokerage. |
| Wishlist | Optional. See [Wishlists](#wishlists). |

You become the **owner** of every portfolio you create.

### The portfolio page

Each portfolio has its own page with:

- A performance chart and the same five summary cards as the dashboard, scoped to this portfolio
- A **Holdings** table (see [Holdings](#holdings))
- **Recent activity** for the portfolio
- **Top performers** within the portfolio
- An **AI chat** window, if you've [enabled AI chat](self-hosting.md#ai-chat)

<!-- SCREENSHOT: A portfolio page showing the toolbar (title, pencil icon, Create Transaction), chart, summary cards, Holdings table, and Recent activity. -->
<!-- ![A portfolio page with its chart, summary cards, and holdings table](images/user-guide-portfolio-page.png) -->

### Editing or deleting a portfolio

Click the pencil icon next to the portfolio title to open **Manage Portfolio**. From here you can change the title, notes, and wishlist setting, manage who has access, or click **Delete Portfolio**.

Deleting a portfolio permanently deletes all of its transactions, holdings, and history. There is no undo, so consider [exporting a backup](import-export.md#exporting-your-data) first.

You need full access to a portfolio to edit or delete it. If you only have read-only access you'll see an eye icon instead of the pencil.

### Wishlists

Turn on **Wishlist** to treat a portfolio as a watchlist of ideas rather than real money. A wishlist works like any other portfolio (you can add transactions to it to model hypothetical positions), but:

- Its holdings are left out of the dashboard totals and the dashboard performance chart
- It is labeled with a **Wishlist** badge in the sidebar, on the dashboard, and on the portfolio page

## Sharing portfolios

You can share a portfolio with anyone by email, which is handy for a spouse, a financial advisor, or an accountant.

1. Open the portfolio and click the pencil icon to open **Manage Portfolio**.
2. Under **People with access**, click **Add People**.
3. Enter the person's email address.
4. Optionally turn on **Grant full access**.
5. Click **Share**.

<!-- SCREENSHOT: Manage Portfolio drawer with People with access (owner plus one read-only and one full-access user) and the Share Portfolio dialog open. -->
<!-- ![The Share Portfolio dialog and the People with access list](images/user-guide-portfolio-sharing.png) -->

The person receives an email invitation. If they don't have an Investbrain account yet, one is created for them and the invitation link lets them set a password. Your instance needs working email for invitations to arrive.

### Access levels

| Level | Can view the portfolio | Can add, edit, and delete transactions | Can edit portfolio details, manage sharing, and delete the portfolio |
| --- | --- | --- | --- |
| Owner | Yes | Yes | Yes |
| Full access | Yes | Yes | Yes |
| Read only | Yes | No | No |

From **People with access** you can switch someone between **Read only** and **Full access** at any time, or click the **x** next to their name to remove them. Removed users lose access immediately. The owner can't be removed.

### Leaving a shared portfolio

Portfolios shared with you show the owner's name as a badge next to the title. Click it to see your access level, then click **Leave Portfolio**. You'll lose access until the owner shares it with you again.

## Transactions

Every holding, gain, and chart in Investbrain is calculated from your transactions, so keeping them accurate matters.

### Recording a transaction

You can create a transaction from:

- A portfolio page (**Create Transaction**)
- A holding page (**Create transaction**, with the symbol filled in for you)
- The **Transactions** page in the sidebar (you'll also pick which portfolio it belongs to)

| Field | Notes |
| --- | --- |
| Portfolio | Only shown on the Transactions page. Lists portfolios you have full access to. |
| Symbol | The ticker symbol, for example `AAPL` or `VOO`. It must be recognized by your market data provider. Symbols are always stored in upper case. |
| Transaction Type | **Buy** or **Sell**. |
| Transaction Date | The trade date. Can't be in the future. |
| Quantity | Number of shares or units. Must be greater than zero. Fractional quantities are allowed. |
| Cost Basis | Buys only. The price paid **per share**. |
| Sale Price | Sells only. The price received **per share**. |
| Currency | The currency you paid or received, picked from the drop-down next to the price. Defaults to your display currency (or, on a holding page, the security's trading currency). See [Multiple currencies](#multiple-currencies). |

<!-- SCREENSHOT: Create Transaction dialog for a Buy, with symbol, date, quantity, cost basis filled in and the currency drop-down visible. -->
<!-- ![The Create Transaction form](images/user-guide-transaction-create.png) -->

When you save a buy or sell, Investbrain:

- Creates the holding if this is the first transaction for that symbol in the portfolio
- Recalculates the holding's quantity, average cost basis, realized gains, and dividends earned
- Updates the portfolio's daily history so the performance chart reflects the change

### Rules for sells

- You can't sell more shares than you owned **as of the sale date**. Buys on the same day as the sale count toward what you owned.
- You don't enter a cost basis for a sale. Investbrain uses the average cost basis of all your buys of that symbol in that portfolio up to the sale date, and uses it to calculate the realized gain.

### Editing, moving, and deleting transactions

To edit a transaction, open its holding page and click the transaction in **Recent activity**. (Clicking a transaction on the dashboard or a portfolio page takes you to the holding page first.) You can change any field, including the portfolio, which moves the transaction to another portfolio. Moving requires full access to both portfolios. Use **Delete Transaction** to remove it.

After an edit or delete, the affected holding (or holdings, if you changed the symbol) is recalculated automatically.

### The Transactions page

**Transactions** in the sidebar lists every transaction across all of your portfolios, 10 per page and newest first. You can:

- Sort by any column
- Filter by transaction type (buy or sell) and by portfolio
- Click a row to jump to that holding

<!-- SCREENSHOT: Transactions page with the filter panel open and at least one SPLIT or REINVEST badge visible in the Type column. -->
<!-- ![The Transactions page with filters](images/user-guide-transactions-page.png) -->

The **Type** column shows **BUY** and **SELL**, plus two special labels for transactions Investbrain created for you:

- **SPLIT** - shares added because of a [stock split](#stock-splits)
- **REINVEST** - shares bought by [reinvesting a dividend](#reinvesting-dividends)

## Holdings

A holding is your position in one symbol within one portfolio. You don't create holdings yourself; they appear when you record the first transaction for a symbol.

### The holdings table

The **Holdings** table on a portfolio page shows, for each holding:

- Symbol and name
- Quantity
- Average cost basis and total cost basis
- Current market value per share and total market value
- Market gain/loss (unrealized), with an up or down badge
- Realized gain/loss
- Dividends earned
- 52-week low and high
- Number of transactions

Values in this table are shown in each security's own trading currency.

By default the table only shows **open positions** (quantity above zero). Turn off the **Open positions** filter to include holdings you've completely sold. You can also build custom filters on the symbol, name, or market value, and sort by any column. Click a row to open the holding.

<!-- SCREENSHOT: Holdings table with several rows, showing gain/loss badges and the Open positions filter. -->
<!-- ![The holdings table on a portfolio page](images/user-guide-holdings-table.png) -->

### The holding page

Each holding has a page with:

- **Market data** - current price and your position's performance
- **Fundamentals** - market cap, forward and trailing P/E, book value, 52-week range, dividend yield, and the last dividend date, when your market data provider supplies them
- **Recent activity** - every transaction for this symbol in this portfolio
- **Dividends** - dividends paid while you held the position, with the amount you received
- **Splits** - stock splits since you started holding it
- **AI chat**, if you've [enabled it](self-hosting.md#ai-chat)

<!-- SCREENSHOT: A holding page for a dividend-paying stock so the Market data, Fundamentals, Recent activity, Dividends, and Splits cards all have content. -->
<!-- ![A holding page with market data, fundamentals, activity, dividends, and splits](images/user-guide-holding-page.png) -->

### Holding options

Click the pencil icon next to the holding name to open **Holding Options**. The only option today is [Reinvest Dividends](#reinvesting-dividends).

## Dividends

Investbrain downloads dividend history for every symbol you hold and works out how much you earned from how many shares you owned on each dividend date. Dividend data is refreshed automatically three times a week (Monday, Wednesday, and Friday). Dividends earned show up on the holding page, in the holdings table, and in the dividends total on the portfolio page and dashboard.

### Reinvesting dividends

Turn on **Reinvest Dividends** in a holding's options to have Investbrain create a buy transaction for each new dividend. The number of shares bought is the dividend amount multiplied by the shares you owned on the dividend date, divided by the current price. These transactions have a cost basis of zero and are labeled **REINVEST**.

Reinvestment applies to dividends discovered after you turn the option on. It doesn't go back and create transactions for dividends that were already recorded.

## Stock splits

Investbrain checks for new stock splits once a week. When a split is found for a symbol you hold, a buy transaction is created for the extra shares (for a 1:4 split, owning 10 shares adds 30 shares at zero cost). These transactions are labeled **SPLIT**, and the split appears in the holding's **Splits** card.

## Multiple currencies

Investbrain tracks prices in the currency each security trades in, and converts using daily exchange rates.

- **Trading currency** - each symbol has a trading currency from your market data provider (for example USD for `AAPL` or GBP for a London-listed stock). The holdings table and holding page show prices and gains in this currency.
- **Transaction currency** - when you record a transaction you can enter the price in any supported currency. If it differs from the trading currency, Investbrain converts it using the exchange rate on the transaction date.
- **Display currency** - dashboard and portfolio totals and performance charts are converted into your personal display currency. Change it under [Locale and display currency](account-settings.md#locale-and-display-currency).

Exchange rates are refreshed daily. Some exchanges quote prices in minor units; Investbrain understands GBX (British pence), ZAC (South African cents), and RMB (an alias of CNY) and adjusts them automatically.

## Performance charts

The dashboard and every portfolio page include a performance chart built from a daily snapshot of each portfolio's market value and cost basis.

- Choose the time range from the drop-down: 1 month, 3 months, year to date, 1 year, 3 years, or all time.
- The chart shows **Market Gain** by default. Click **Market Value** or **Cost Basis** in the legend to show those lines too.
- Snapshots are captured every weekday at the time set by `DAILY_CHANGE_TIME` (11 PM by default, see [Configuration](self-hosting.md#options)).
- When you add or change a transaction dated in the past, the portfolio's history is rebuilt from the market data provider so the chart stays accurate.

## Search

Press `/` anywhere in the app, or click the search box in the top bar, to search your portfolios by title and your open holdings by symbol or company name. Use the arrow keys to move through results and Enter to open one.

<!-- SCREENSHOT: Search dialog open with a query that returns both a portfolio and a holding. -->
<!-- ![The search dialog showing portfolio and holding results](images/user-guide-search.png) -->

## AI chat

Once you've [enabled AI chat](self-hosting.md#ai-chat), every portfolio and holding page shows an **AI Chat** window. The assistant is given the details of that portfolio or holding (quantities, cost basis, market value, gains, and dividends) so you can ask questions like:

- "Which holding is most successful in this portfolio?"
- "Is my portfolio diverse enough?"
- "What are the key risks for this holding?"

Suggested prompts are shown to get you started. Your conversation for each portfolio or holding is saved for you, and other people with access to the same portfolio can't see it.

<!-- SCREENSHOT: AI chat window on a portfolio page with the suggested prompts and one short question and answer. -->
<!-- ![The AI chat window on a portfolio page](images/user-guide-ai-chat.png) -->

AI-generated advice can be wrong. Treat it as a thought partner, not a recommendation, and consult a licensed investment advisor before making decisions.

## Appearance and language

- **Light and dark mode** - use the theme toggle in the top bar.
- **Sidebar** - collapse or expand it with the menu button in the top bar.
- **Language and number formatting** - set your locale in [account settings](account-settings.md#locale-and-display-currency). Investbrain is available in English, French, and Spanish.
