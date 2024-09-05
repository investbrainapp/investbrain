<p align="center"><a href="https://investbra.in" target="_blank"><img src="https://raw.githubusercontent.com/investbrainapp/investbrain/main/investbrain-logo.png" width="400" alt="Investbrain Logo"></a></p>

## About Investbrain

Investbrain helps you manage and track the performance of your investments.

<p align="center"><a href="https://investbra.in" target="_blank"><img src="https://raw.githubusercontent.com/investbrainapp/investbrain/main/screenshot.png" width="100%" alt="Investbrain Screenshot"></a></p>

## Under the hood

Investbrain is a Laravel PHP web application that leverages the Livewire and Mary UI frameworks for its frontend. As far as persistent data storage, any relational database should work (but we generally recommend SQLite or MySQL). There are out of the box market data providers for Yahoo Finance, Alpha Vantage, and an extensible market data provider interface. We also have robust i18n, a11y, and dark mode support. 

## Installation

For ease of installation, we highly recommend installing Investbrain in a Docker container using the provided Docker Compose option, which downloads all the necessary dependencies and builds everything you need to get started quickly!

Before getting started, you should already have the following installed on your machine: Docker Desktop or CLI, Git, and a wild sense of adventure.

Ready? Let's get started! 

First, you can clone this repository:

```bash
git clone https://github.com/investbrainapp/investbrain.git .
```

Once the repository is cloned, enter the directory:

```bash
cd investbrain
```

And bring up the container (this will take a few minutes):

```bash
docker composer up
```

In the previous setep, all of the default configurations are set. This includes creating a .env file and setting the required `APP_KEY`. 

If everything worked as expected, you should now be able to access Investbrain in the browser at:

```bash
http://localhost:8000/register
```

Congrats! You've just installed Investbrain!

## Configuration

There are several available configuration options when installing with the recommended [Docker method](#Installation). These options are configurable using an environment file, and you can see which options are configurable by reviewing the [docker-compose.yml](https://github.com/investbrainapp/investbrain/blob/main/docker-compose.yml) file. Changes can be made in the [.env.example](https://github.com/investbrainapp/investbrain/blob/main/.env.example) file before installation.

If you make any updates to the default configurations, make sure you restart the Docker containers in order for those changes to take effect.

## Updating

To update Investbrain using the recommended [Docker installation](#Installation) method, you just need to stop the running containers:

```bash
docker compose stop
```

Then pull the latest updates from this repository using git:

```bash
git pull
```

Then bring the containers back up!

```bash
docker compose up
```

Easy as that!

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

If you discover a security vulnerability within Investbrain, please create an issue in the [Github repository](https://github.com/investbrainapp/investbrain). All security vulnerabilities will be promptly addressed.

## License

Investbrain is open-sourced software licensed under the [CC-BY-NC 4.0](https://github.com/investbrainapp/investbrain?tab=License-1-ov-file).
