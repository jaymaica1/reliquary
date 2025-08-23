# Reliquary

## Docker Setup

This project includes a Docker setup for local development. The setup includes:

- PHP 8.2 with Apache
- PostgreSQL database
- Mailpit for email testing

### Requirements

- Docker
- Docker Compose

### Getting Started

1. Clone the repository
2. Build and start the Docker containers:

```bash
docker compose up -d
```

3. Install Composer dependencies:

```bash
docker compose exec php composer install
```

4. Access the application in your browser:

```
http://localhost:8080
```

### Services

- **Web Server**: http://localhost:8080
- **Database**: PostgreSQL (accessible via port 5432)
- **Mail Server**: Mailpit (accessible via http://localhost:8025)

### Common Commands

- Start the containers: `docker compose up -d`
- Stop the containers: `docker compose down`
- View logs: `docker compose logs -f`
- Access App container: `docker compose exec app bash`
- Run Symfony commands: `docker compose exec app bin/console <command>`

### Configuration

- PHP configuration can be modified in `docker/app/php.ini`
- Database configuration can be modified in `.env` file or by setting environment variables

## Production Setup

The production environment uses Docker Compose with the following services:
- App (PHP with Apache)
- PostgreSQL database
- Watchtower for automatic container updates

### Watchtower Configuration

Watchtower is configured to:
- Automatically update containers once a day at midnight
- Only update containers with the label `com.centurylinklabs.watchtower.enable=true`
- Expose an HTTP API for manual triggering of updates
- Clean up old images after updating

### Required Environment Variables

Add these to your production environment:
- `WATCHTOWER_HTTP_API_TOKEN`: Token for securing the Watchtower HTTP API

### GitHub Actions CI/CD

The CI/CD workflow automatically:
- Builds and pushes Docker images to GitHub Container Registry
- Triggers Watchtower to update containers on the production server

#### Required GitHub Secrets

Add these secrets to your GitHub repository:
- `WATCHTOWER_HTTP_API_TOKEN`: Same token as configured in your production environment
- `PRODUCTION_URL`: URL or IP address of your production server

## Latest Releases

For a complete list of releases and changes, please see the [CHANGELOG.md](CHANGELOG.md) file.


### To do
* [ ] Add GDPR
* [ ] Review app source
* [x] Created new private repo for prod secrets
*  [ ] Relic submitted successfully and awaiting approval: missing translation
*  [ ] Add Relics