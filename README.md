<p align="center"><a href="https://investbra.in" target="_blank"><img src="https://raw.githubusercontent.com/investbrainapp/investbrain/main/investbrain-logo.png" width="400" alt="Investbrain Logo"></a></p>

## About Investbrain

Investbrain helps you manage and track the performance of your investments.

<p align="center"><a href="https://investbra.in" target="_blank"><img src="https://raw.githubusercontent.com/investbrainapp/investbrain/main/screenshot.png" width="100%" alt="Investbrain Screenshot"></a></p>

## Under the hood

Investbrain is a Laravel PHP web application that leverages the Livewire and Mary UI frameworks for its frontend. Out of the box, we feature two market data providers: Yahoo Finance and Alpha Vantage. But we also offer an extensible market data provider interface for intrepid developers to create their own! We also have robust support for i18n, a11y, and dark mode. 

## Installation

For ease of installation, we _highly recommend_ installing Investbrain using the provided [Docker Compose](https://github.com/investbrainapp/investbrain/blob/main/docker-compose.yml) file, which downloads all the necessary dependencies and seamlessly builds everything you need to get started quickly!

Before getting started, you should already have the following installed on your machine: [Docker Engine](https://docs.docker.com/engine/install/), [Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git), and a wild sense of adventure.

Ready? Let's get started! 

First, you can clone this repository:

```bash
git clone https://github.com/investbrainapp/investbrain.git . && cd investbrain
```

Then, build the Docker image and bring up the container (this will take a few minutes):

```bash
docker composer up
```

In the previous setep, all of the default configurations are set automatically. This includes creating a .env file and setting the required Laravel `APP_KEY`. 

If everything worked as expected, you should now be able to access Investbrain in the browser at:

```bash
http://localhost:8000/register
```

Congrats! You've just installed Investbrain!

## Configuration (optional)

There are several configurations available when installing using the recommended [Docker method](#Installation). These options are configurable using an environment file. Changes can be made in your [.env](https://github.com/investbrainapp/investbrain/blob/main/.env.example) file before installation. 

| Option      | Description      | Default      |
| ------------- | ------------- | ------------- |
| APP_URL | The URL where your Investbrain installation will be accessible | http://localhost |
| APP_PORT | The HTTP port exposed by the NGINX container | 8000 |
| DB_HOST | The location of your database host where Investbrain is installed  | investbrain-mysql |
| DB_DATABASE | The name of the database where Investbrain is installed  | investbrain |
| DB_USERNAME | Your database username | investbrain |
| DB_PASSWORD | Your database password | investbrain |

> Note: These options affect the [docker-compose.yml](https://github.com/investbrainapp/investbrain/blob/main/docker-compose.yml) file, so if you decide to make any changes to these default configurations, you'll have to restart the Docker containers before your changes take effect.

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
