<p align="center"><a href="https://investbra.in" target="_blank"><img src="https://raw.githubusercontent.com/investbrainapp/investbrain/main/investbrain-logo.png" width="400" alt="Investbrain Logo"></a></p>

## About Investbrain

Investbrain is a smart open-source investment tracker that helps you manage, track, and make informed decisions about your investments.

<p align="center"><a href="https://investbra.in" target="_blank"><img src="https://raw.githubusercontent.com/investbrainapp/investbrain/main/screenshot.png" width="100%" alt="Investbrain Screenshot"></a></p>

## Table of contents
- [Under the hood](#under-the-hood)
- [Install (self hosting)](#self-hosting)
- [Chat with your holdings](#chat-with-your-holdings)
- [Market data providers](#market-data-providers)
- [Configuration](#configuration)
- [Updating](#updating)
- [Command line utilities](#command-line-utilities)
- [Troubleshooting](#troubleshooting)
- [Testing](#testing)

## Under the hood

Investbrain is a Laravel PHP web application that leverages Livewire and Tailwind for its frontend. Most databases should work, including MySQL and SQLite. Out of the box, we feature three market data providers: [Yahoo Finance](https://finance.yahoo.com/), [Finnhub](https://finnhub.io/pricing-stock-api-market-data), and [Alpha Vantage](https://www.alphavantage.co/support/). But we also offer an extensible market data provider interface for intrepid developers to create their own! We also offer an integration with OpenAI's LLMs for our ["chat with your holdings"](#chat-with-your-holdings) capability. Finally, of course we have robust support for i18n, a11y, and dark mode. 

## Self hosting

For ease of installation, we _highly recommend_ installing Investbrain using the provided [Docker Compose](https://github.com/investbrainapp/investbrain/blob/main/docker-compose.yml) file, which downloads all the necessary dependencies and seamlessly builds everything you need to get started quickly! 

Before getting started, you should already have the following installed on your machine: [Docker Engine](https://docs.docker.com/engine/install/), [Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git), and a wild sense of adventure.

Ready? Let's get started! 

First, you can clone this repository:

```bash
git clone https://github.com/investbrainapp/investbrain.git && cd investbrain
```

Then, build the Docker image and bring up the container (this will take a few minutes):

```bash
docker compose up
```

In the previous step, all of the default configurations are set automatically. This includes creating a .env file and setting the required Laravel `APP_KEY`. 

If everything worked as expected, you should now be able to access Investbrain in the browser at. You should create an account by visiting:

```bash
http://localhost:8000/register
```

Congrats! You've just installed Investbrain!

## Chat with your holdings

Investbrain offers an AI powered chat assistant that is grounded on *your* investments. This enables you to use AI as a thought partner when making investment decisions. 

When self-hosting, you can enable the chat assistant by configuring your OpenAI Secret Key and Organization ID in your [.env](https://github.com/investbrainapp/investbrain/blob/main/.env.example) file. Navigate to OpenAI to [create your keys](https://platform.openai.com/api-keys).

Always keep in mind the limitations of large language models. When in doubt, consult a licensed investment advisor. 

## Market data providers

Investbrain includes an extensible market data provider interface that allows you to retrieve stock market data from multiple providers, such as Yahoo Finance, Alpha Vantage, or Finnhub. The interface includes a built-in fallback mechanism to ensure reliable data access, even if a provider fails.

### Configuration

You can specify the market data provider you want to use in your .env file:

```bash
MARKET_DATA_PROVIDER=yahoo
```

You can also use Investbrain's built-in fallback mechanism to ensure reliable data access. If any provider fails, Investbrain will automatically attempt to retrieve data from the next available provider, continuing through your configured providers until one returns successfully.

Your selected providers should be listed in your .env file. Each should be separated by a comma:

```bash
MARKET_DATA_PROVIDER=yahoo,alphavantage
```

In the above example, Yahoo Finance will be attempted first and the Alpha Vantage provider will be used as the fallback. If Yahoo Finance fails to retrieve market data, the application will automatically try Alpha Vantage.

### Custom providers

If you wish to create your own market data provider, you can create your own implementation of the [MarketDataInterface](https://github.com/investbrainapp/investbrain/blob/main/app/Interfaces/MarketData/MarketDataInterface.php). You can refer to any existing market data implementation as an example.

Once you've created your market data implementation, be sure add your custom provider to the Investbrain configuration file, under the interfaces section:

```php

'interfaces' => [
    //                       *  *  *
    'custom_provider' => \App\Services\CustomProviderMarketData::class,
    //                       *  *  *
],
```

And add your custom provider to your .env file:

```bash
MARKET_DATA_PROVIDER=yahoo,alphavantage,custom_provider
```

Feel free to submit a PR with any custom providers you create.

## Configuration

There are several optional configurations available when installing using the recommended [Docker method](#self-hosting). These options are configurable using an environment file. Changes can be made in your [.env](https://github.com/investbrainapp/investbrain/blob/main/.env.example) file before installation. 

| Option      | Description      | Default      |
| ------------- | ------------- | ------------- |
| APP_URL | The URL where your Investbrain installation will be accessible | http://localhost |
| APP_PORT | The HTTP port exposed by the NGINX container | 8000 |
| MARKET_DATA_PROVIDER | The market data provider to use (either `yahoo`, `alphavantage`, or `finnhub`) | yahoo |
| ALPHAVANTAGE_API_KEY | If using the Alpha Vantage provider | `null` |
| FINNHUB_API_KEY | If using the Finnhub provider | `null` |
| MARKET_DATA_REFRESH | Cadence to refresh market data in minutes | 30 |
| APP_TIMEZONE | Timezone for the application, including daily change captures | UTC |
| AI_CHAT_ENABLED | Whether to enable AI chat features | `false` |
| OPENAI_API_KEY | OpenAI secret key (required for AI chat) | `null` |
| OPENAI_ORGANIZATION | OpenAI org id (required for AI chat) | `null` |
| DAILY_CHANGE_TIME | The time of day to capture daily change | 23:00 |
| REGISTRATION_ENABLED | Whether to enable registration of new users | `true` |



> Note: These options affect the [docker-compose.yml](https://github.com/investbrainapp/investbrain/blob/main/docker-compose.yml) file, so if you decide to make any changes to these default configurations, you'll have to restart the Docker containers before your changes take effect.

## Updating

To update Investbrain using the recommended [Docker installation](#self-hosting) method, you just need to stop the running containers:

```bash
docker compose stop
```

Then pull the latest updates from this repository using git:

```bash
git pull
```

Finally bring the containers back up!

```bash
docker compose up
```

Easy as that!

## Command line utilities

Investbrain comes bundled with several helpful command line utilities to make managing your portfolios and holdings more efficient. Keep in mind these commands are extremely powerful and can make irreversable changes to your holdings. 

To run these commands, you can use `docker exec` like this:

```bash
docker exec -it investbrain-app php artisan <replace with command you want to run>
```

Just to be safe, we recommend backing up your portfolios before using these commands:

| Command      | Description      |
| ------------- | ------------- |
| refresh:market-data | Refreshes market data with your configured market data provider. |
| refresh:dividend-data | Refreshes dividend data with your configured market data provider. Will also re-calculate your total dividends earned for each holding. |
| refresh:split-data | Refreshes splits data with your configured market data provider. Will also create new transactions to account for any splits. |
| capture:daily-change | Captures a snapshot of each portfolio's daily performance. |
| sync:daily-change | Re-calculates daily snapshots of your portfolio's daily performance. Useful to fill in gaps in your portfolio charts. (Note: this is an extremely resource intensive query.) |
| sync:holdings | Re-calculates performance of holdings with related transactions (i.e. dividends, realized gains, etc). |

## Troubleshooting

If you are facing issues with Investbrain, it can be handy to monitor the application's logs:

```bash
docker exec -it investbrain-app cat storage/logs/laravel.log
```
or you can live monitor logs using `tail`:

```bash
docker exec -it investbrain-app tail -f storage/logs/laravel.log
```

### Common issues

<details>

**<summary>Market data not refreshing on fresh install</summary>**

If you're unable to refresh market data out of the box (i.e. your market data provider is set to Yahoo), there is a chance Yahoo is being blocked by a firewall or adblocker.  Pihole is known to block `fc.yahoo.com` which is the domain used to query Yahoo. 

Once you whitelist `fc.yahoo.com` in pihole, your market data should begin populating!

</details>

## Testing

Investbrain has a robus PHPUnit test suite that creates an in-memory SQLite database and runs any queued jobs synchronously using Laravel's array driver. You can run the entire Investbrain test suite from within the Docker container by running:

```bash
docker exec -it investbrain-app php artisan test
```

## Contributing

We appreciate any contributions to Investbrain! Please open a pull request on our [Github repository](https://github.com/investbrainapp/investbrain). Here are some ideas for first time contributors:

- Improve our documentation
- Create new market data providers
- Enhance the user interface
- Additional translations
- Fix bugs

When you submit a contribution, don't forget to include passing tests with your PR!

## Code of Conduct

We ask that you be kind and polite when interacting with the Investbrain community. 

## Security Vulnerabilities

If you discover a security vulnerability within Investbrain, please submit your report via [Github](https://github.com/investbrainapp/investbrain/security/advisories/new). All security vulnerabilities will be promptly addressed. We ask that you keep any suspected vulnerabilities private and confidential until they have been appropriately addressed.

## License

Investbrain is open-sourced software licensed under the [CC-BY-NC 4.0](https://github.com/investbrainapp/investbrain?tab=License-1-ov-file).
