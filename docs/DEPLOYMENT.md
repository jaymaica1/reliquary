# Reliquary Project Deployment Guide

This document provides instructions for deploying the Reliquary project to a production environment using Docker.

## Prerequisites

- A server with Docker and Docker Compose installed
- Access to a Docker registry (Docker Hub, GitHub Container Registry, etc.)
- GitHub token with appropriate permissions (if using the provided CI/CD pipeline)

## Deployment Options

### Option 1: Automatic Deployment with CI/CD

The project includes a GitHub Actions workflow that automatically builds and pushes Docker images to GitHub Container Registry (GHCR) when changes are pushed to the main branch.

1. **GitHub Secrets Configuration**

   The workflow uses the following GitHub secrets:

   - `GITHUB_TOKEN`: Automatically created and provided by GitHub for GitHub Actions workflows. Used for GitHub Container Registry authentication.

   - `WATCHTOWER_HTTP_API_TOKEN`: Token for authenticating with the Watchtower HTTP API. Must match the token configured in your production environment.

   - `PRODUCTION_URL`: URL or IP address of your production server where Watchtower is running.

   The workflow includes the necessary permissions for pushing to the GitHub Container Registry:
   ```yaml
   permissions:
     contents: read
     packages: write
   ```

2. **Push Changes to Main Branch**

   When you push changes to the main branch, the GitHub Actions workflow will automatically:
   - Build the App Docker image
   - Push it to GitHub Container Registry with appropriate tags
   - Trigger Watchtower on your production server to update containers with the latest images

3. **Deploy on Your Server**

   On your production server:

   ```bash
   # Get the production compose file from the repository
   curl -O https://raw.githubusercontent.com/cesarscur/reliquary/main/compose.prod.yaml
   mv compose.prod.yaml compose.yaml
   ```
   ```bash
   # Create a .env file with your production settings
   cat > .env << EOL
   DOCKER_REGISTRY=ghcr.io/your-github-username
   IMAGE_TAG=latest
   APP_SECRET=your-app-secret
   POSTGRES_DB=reliquary
   POSTGRES_USER=app
   POSTGRES_PASSWORD=your-secure-password
   APACHE_SSL_PORT=443
   MAILER_DSN=smtp://user:pass@smtp.example.com:25
   WATCHTOWER_HTTP_API_TOKEN=your-secure-token
   EOL

   # Pull the latest images and start the containers
   docker compose pull
   docker compose up -d

   # Import saints data
   docker compose exec app php bin/console app:import-saints

   # Note: Database migrations will run automatically when the container starts
   ```

### Option 2: Manual Deployment

If you prefer to build and deploy manually:

1. **Build the Images Locally**

   ```bash
   docker build -t ghcr.io/your-github-username/reliquary:latest -f docker/app/Dockerfile.prod .
   ```

2. **Push to Your Registry**

   ```bash
   # Login to GitHub Container Registry
   echo $GITHUB_TOKEN | docker login ghcr.io -u your-github-username --password-stdin

   # Push the image
   docker push ghcr.io/your-github-username/reliquary:latest
   ```

3. **Deploy on Your Server**

   Follow the same steps as in Option 1, step 3, including running the database schema update and saints import commands.

## Production Configuration

### Environment Variables

Create a `.env` file with the following variables:

#### Core Application Variables

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `APP_ENV` | Application environment | `prod` | Yes | `prod`, `dev`, `test` |
| `APP_SECRET` | Application secret key for encryption/security | - | Yes | `your-secure-32-char-secret-key` |

#### Database Configuration

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `DATABASE_URL` | PostgreSQL connection URL | - | Yes | `postgresql://app:password@database:5432/reliquary?serverVersion=16&charset=utf8` |
| `POSTGRES_DB` | PostgreSQL database name | `reliquary` | Yes | `reliquary` |
| `POSTGRES_USER` | PostgreSQL username | `app` | Yes | `app` |
| `POSTGRES_PASSWORD` | PostgreSQL password | - | Yes | `your-secure-password` |

#### MongoDB Configuration

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `MONGODB_URL` | MongoDB connection URL | - | Yes | `mongodb://admin:password@mongodb:27017` |
| `MONGODB_DATABASE` | MongoDB database name | `reliquary` | Yes | `reliquary` |
| `MONGO_ROOT_USERNAME` | MongoDB root administrator username | `admin` | Yes | `admin` |
| `MONGO_ROOT_PASSWORD` | MongoDB root administrator password | - | Yes | `SecureRootPass123!` |
| `MONGO_APP_USERNAME` | MongoDB application user username | `reliquary_user` | Yes | `reliquary_user` |
| `MONGO_APP_PASSWORD` | MongoDB application user password | - | Yes | `AppUserPass456!` |

#### AWS S3 (images and backups)

Production image storage uses S3 via Flysystem. The backup commands reuse the same credentials and region unless you add separate keys later. Backup uploads use **SSE-S3** (`AES256` on `PutObject`)—the same no-extra-charge server-side encryption family as typical object storage, not SSE-KMS.

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `AWS_ACCESS_KEY_ID` | IAM access key for S3 API calls | - | Yes (prod with S3) | `AKIA...` |
| `AWS_SECRET_ACCESS_KEY` | IAM secret key | - | Yes (prod with S3) | `...` |
| `AWS_REGION` | AWS region for S3 | - | Yes (prod with S3) | `us-east-1` |
| `AWS_S3_BUCKET` | Bucket for relic images (often fronted by CloudFront) | - | Yes (prod with S3) | `my-reliquary-assets` |
| `AWS_S3_PREFIX` | Optional key prefix inside the assets bucket | empty | No | `prod` |
| `AWS_ENDPOINT` | Custom endpoint (S3-compatible, LocalStack, etc.) | empty | No | `https://minio.example.com` |
| `AWS_CLOUDFRONT_DOMAIN` | Optional CloudFront hostname for public URLs | empty | No | `d123.cloudfront.net` |
| `AWS_BACKUP_BUCKET` | **Private** bucket for `app:backup:s3` / `app:restore:s3` artifacts | empty | Yes to use backup commands | `santasreliquias-backup` |
| `AWS_BACKUP_PREFIX` | S3 key prefix for backup folders (no leading slash) | `reliquary-backups` | No | `backup` |

Use a **dedicated backup bucket** that is **not** wired into your image CloudFront distribution, so backup objects stay private and you do not risk changing origin or behavior for public assets.

#### Mail Configuration

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `MAILER_DSN` | Mail service configuration | - | Yes | `smtp://user:pass@smtp.example.com:25` |

**Mail Service Examples:**
```
# SMTP
MAILER_DSN=smtp://user:pass@smtp.example.com:25
# Mailgun
MAILER_DSN=mailgun://KEY:DOMAIN@default
# SendGrid
MAILER_DSN=sendgrid://KEY@default
# Development (Mailpit)
MAILER_DSN=smtp://mailpit:1025
```

#### PII Protection and Security

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `ACCESS_LOG_PII_PROTECTION` | Controls PII protection level in access logs | `partial` | No | `none`, `partial`, `full` |

**PII Protection Levels:**
- `none`: Log all data (development only, not recommended for production)
- `partial`: Mask some sensitive data but keep useful information for debugging
- `full`: Anonymize or remove all PII data (recommended for production)

**Note:** In production environments (`APP_ENV=prod`), PII protection is always enforced regardless of this setting.

#### Deployment Configuration

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `DOCKER_REGISTRY` | Docker registry URL for images | - | Yes | `ghcr.io/your-github-username` |
| `IMAGE_TAG` | Docker image tag to deploy | `latest` | Yes | `latest`, `v1.2.3` |
| `APACHE_SSL_PORT` | Apache HTTPS port | `443` | No | `443` |
| `WATCHTOWER_HTTP_API_TOKEN` | Token for Watchtower auto-updates | - | No | `your-secure-watchtower-token` |

#### Optional Development/Monitoring Variables

| Variable | Description | Default | Required | Example |
|----------|-------------|---------|----------|---------|
| `BLACKFIRE_SERVER_ID` | Blackfire profiler server ID | - | No | `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx` |
| `BLACKFIRE_SERVER_TOKEN` | Blackfire profiler server token | - | No | `yyyyyyyy...` |

#### Example Production .env File

```bash
# Core Application
APP_ENV=prod
APP_SECRET=your-secure-32-character-secret-key

# PostgreSQL Database
DATABASE_URL=postgresql://app:SecureDbPass123!@database:5432/reliquary?serverVersion=16&charset=utf8
POSTGRES_DB=reliquary
POSTGRES_USER=app
POSTGRES_PASSWORD=SecureDbPass123!

# MongoDB Configuration
MONGODB_URL=mongodb://admin:SecureMongoRoot456!@mongodb:27017
MONGODB_DATABASE=reliquary
MONGO_ROOT_USERNAME=admin
MONGO_ROOT_PASSWORD=SecureMongoRoot456!
MONGO_APP_USERNAME=reliquary_user
MONGO_APP_PASSWORD=SecureAppUser789!

# Mail Service
MAILER_DSN=smtp://your-smtp-user:your-smtp-pass@smtp.your-provider.com:587

# PII Protection (optional - defaults to 'partial')
ACCESS_LOG_PII_PROTECTION=full

# Deployment
DOCKER_REGISTRY=ghcr.io/your-github-username
IMAGE_TAG=latest
APACHE_SSL_PORT=443

# Auto-update (optional)
WATCHTOWER_HTTP_API_TOKEN=your-secure-watchtower-token

# AWS S3 (production images — required when using cloud image storage)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_REGION=us-east-1
AWS_S3_BUCKET=your-assets-bucket

# AWS S3 backups (private bucket — optional until you use backup commands; keep separate from the image/CloudFront bucket)
AWS_BACKUP_BUCKET=santasreliquias-backup
AWS_BACKUP_PREFIX=backup
```

#### Security Recommendations

1. **Strong Passwords**: Use complex passwords with at least 12 characters, including uppercase, lowercase, numbers, and symbols
2. **Secret Rotation**: Rotate secrets regularly (recommended every 90 days)
3. **Environment Separation**: Use different credentials for development, staging, and production
4. **Secret Management**: Never commit real secrets to version control; use secure secret management solutions in production

### Database Management

For production, consider:

1. **Using a Managed Database Service**

   Instead of running PostgreSQL in a container, consider using a managed database service like AWS RDS, Google Cloud SQL, or DigitalOcean Managed Databases.

2. **Backups to a private S3 bucket (recommended)**

   Configure a **private** S3 bucket and IAM credentials, set `AWS_BACKUP_BUCKET` and optional `AWS_BACKUP_PREFIX` on the `app` service together with the existing `AWS_*` variables used for image storage.

   **Step-by-step: bucket `santasreliquias-backup`, prefix `backup/`**

   1. In the AWS console, open **S3** and choose **Create bucket**.
   2. **Bucket name:** `santasreliquias-backup` (globally unique; adjust if taken).
   3. **Region:** same as `AWS_REGION` for your app (so the existing `S3Client` and credentials stay valid).
   4. **Block Public Access:** leave all four options **on** (bucket must not be public).
   5. **Versioning:** optional; enable if you want extra protection against accidental overwrites.
   6. **Default encryption:** choose **Amazon S3 managed keys (SSE-S3)**. The app also sends `ServerSideEncryption: AES256` on upload; both align with SSE-S3 and avoid KMS charges.
   7. **Do not** add this bucket as a CloudFront origin. Keep CloudFront pointed only at your **images/assets** bucket so distribution config and cache behaviors stay unchanged.
   8. **IAM:** attach a least-privilege policy to the same user or role the app already uses for S3 (or a dedicated backup user). Example actions:
      - `s3:PutObject`, `s3:GetObject`, `s3:ListBucket` on `arn:aws:s3:::santasreliquias-backup` and `arn:aws:s3:::santasreliquias-backup/*`
      - Optionally `s3:DeleteObject` if you automate retention pruning.
   9. **Application env:** set `AWS_BACKUP_BUCKET=santasreliquias-backup` and `AWS_BACKUP_PREFIX=backup`. Backups are stored under keys like `backup/2026-04-12T15-30-45Z/…` (the app normalizes the prefix with a trailing slash).
   10. Optionally add a **lifecycle** rule (transition to Glacier, expire after N days).

   The production Docker image includes `pg_dump`, `pg_restore`, `mongodump`, and `mongorestore` so backups run inside the `app` container.

   **Console commands**

   ```bash
   # Preview dump commands and S3 keys (does not require AWS_BACKUP_BUCKET)
   docker compose exec app php bin/console app:backup:s3 --dry-run

   # Create PostgreSQL + MongoDB dumps and upload to S3 (requires AWS_BACKUP_BUCKET)
   docker compose exec app php bin/console app:backup:s3

   # Same, plus tar of public/uploads/images (can be large)
   docker compose exec app php bin/console app:backup:s3 --with-local-uploads
   ```

   **Restore (destructive)**

   Restore targets the databases in the current `DATABASE_URL` and `MONGODB_*` environment. Stop the app or put it in maintenance if you need a consistent cutover. **`--force` is required.**

   ```bash
   # Restore the latest backup under AWS_BACKUP_PREFIX
   docker compose exec app php bin/console app:restore:s3 --latest --force

   # Restore a specific backup folder id (timestamp folder under the prefix)
   docker compose exec app php bin/console app:restore:s3 --backup-id=2026-04-12T15-30-45Z --force

   # Optional: drop Mongo collections before restore; optional: pg_restore --clean --if-exists
   docker compose exec app php bin/console app:restore:s3 --latest --force --drop-mongo --clean-postgres

   # If the backup included uploads.tar.gz, extract into public/uploads/images
   docker compose exec app php bin/console app:restore:s3 --latest --force --with-local-uploads
   ```

   **Restore checklist**

   - Confirm you are pointed at the correct environment (`.env` / compose).
   - Prefer stopping web traffic or the `app` container during restore.
   - Restore order used by the command: PostgreSQL, then MongoDB.
   - After restore, verify data; run migrations if your process requires it (`bin/console doctrine:migrations:migrate`).

   **Scheduled backup (cron on the host)**

   ```bash
   0 3 * * * cd /path/to/compose && /usr/bin/docker compose exec -T app php bin/console app:backup:s3 >> /var/log/reliquary-backup.log 2>&1
   ```

   Ensure the cron environment provides the same `AWS_*` and `AWS_BACKUP_*` variables as production (or use a `.env` file that Compose loads).

3. **Manual backup and restore (without PHP)**

   Use these if you cannot run Symfony console but have the AWS CLI configured on the host (`aws configure` or instance role). Replace `BUCKET`, `PREFIX`, and credentials as appropriate.

   **PostgreSQL (custom format, gzip)**

   ```bash
   docker compose exec -T database pg_dump -U "${POSTGRES_USER}" -Fc "${POSTGRES_DB}" \
     | gzip -9 \
     | aws s3 cp - "s3://BUCKET/PREFIX/manual-$(date -u +%Y%m%dT%H%M%SZ)/postgres.dump.gz"
   ```

   **Download and restore PostgreSQL**

   Pipe the custom-format dump into `pg_restore` (reads from stdin when the archive argument is `-`):

   ```bash
   aws s3 cp "s3://BUCKET/PREFIX/manual-.../postgres.dump.gz" - \
     | gunzip -c \
     | docker compose exec -T database pg_restore -U "${POSTGRES_USER}" --no-owner --no-acl -d "${POSTGRES_DB}" -
   ```

   **MongoDB (archive, gzip)**

   ```bash
   docker compose exec -T mongodb mongodump \
     --uri="${MONGODB_URL}" \
     --db="${MONGODB_DATABASE}" \
     --archive --gzip \
     | aws s3 cp - "s3://BUCKET/PREFIX/manual-$(date -u +%Y%m%dT%H%M%SZ)/mongo.archive.gz"
   ```

   **Download and restore MongoDB**

   ```bash
   aws s3 cp "s3://BUCKET/PREFIX/manual-.../mongo.archive.gz" /tmp/mongo.archive.gz
   docker compose cp /tmp/mongo.archive.gz mongodb:/tmp/mongo.archive.gz
   docker compose exec mongodb mongorestore \
     --uri="${MONGODB_URL}" \
     --gzip --archive=/tmp/mongo.archive.gz \
     --nsInclude="${MONGODB_DATABASE}.*"
   ```

## MongoDB Configuration and Security

### MongoDB Setup

The Reliquary project uses MongoDB for access logging and security monitoring. MongoDB runs as a separate container alongside PostgreSQL.

#### MongoDB Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `MONGO_ROOT_USERNAME` | MongoDB root administrator username | `admin` |
| `MONGO_ROOT_PASSWORD` | MongoDB root administrator password | `SecureRootPass123!` |
| `MONGO_DB` | Default database name | `reliquary` |
| `MONGO_APP_USERNAME` | Application user username | `reliquary_user` |
| `MONGO_APP_PASSWORD` | Application user password | `AppUserPass456!` |

#### MongoDB Security Best Practices

1. **Strong Passwords**
   - Use complex passwords for both root and application users
   - Minimum 12 characters with uppercase, lowercase, numbers, and symbols
   - Rotate passwords regularly (every 90 days recommended)

2. **Network Security**
   - MongoDB port (27017) is not exposed externally in production
   - Only accessible through internal Docker network
   - Consider using MongoDB over TLS for additional security

3. **Access Control**
   - Separate root and application users with principle of least privilege
   - Application user has only `readWrite` permissions on the `reliquary` database
   - Regular audit of user permissions

4. **Data Retention and Privacy**
   - Access logs automatically expire after 1 year (configurable via TTL index)
   - IP addresses are hashed using SHA-256 for GDPR compliance
   - Session IDs are hashed to prevent session hijacking
   - User agents are sanitized to prevent XSS attacks

#### MongoDB Deployment Steps

1. **Initialize MongoDB**
   ```bash
   # Start MongoDB service
   docker compose up -d mongodb
   
   # Verify MongoDB is running
   docker compose logs mongodb
   ```

2. **Verify Database Setup**
   ```bash
   # Connect to MongoDB and verify setup
   docker compose exec mongodb mongosh -u admin -p
   # Enter your MONGO_ROOT_PASSWORD when prompted
   
   # Check database and collections
   use reliquary
   show collections
   db.access_logs.getIndexes()
   ```

3. **Test Access Logging**
   ```bash
   # Test the access logging functionality
   docker compose exec app php bin/console debug:container AccessLogService
   ```

#### Access Log Management

The system provides comprehensive access logging with the following features:

- **Real-time Monitoring**: All user actions are logged in real-time
- **Advanced Filtering**: Filter logs by user, action, severity, date range
- **Statistical Analysis**: Comprehensive analytics and reporting
- **Data Export**: CSV export functionality for compliance and analysis
- **Automated Cleanup**: Configurable data retention policies

#### Access Log Security Features

1. **Data Privacy**
   - IP addresses are hashed using SHA-256
   - Session IDs are hashed to prevent correlation attacks
   - User agents are sanitized and truncated
   - No sensitive data is stored in plain text

2. **Access Control**
   - Only users with `ROLE_ADMIN` can access logs
   - CSRF protection on all administrative actions
   - Audit trail of all access log management actions

3. **Data Integrity**
   - Comprehensive input validation
   - SQL injection prevention through ODM
   - XSS protection through output encoding

#### Monitoring and Maintenance

1. **Log Monitoring**
   - Access the admin interface at `/admin/access-logs`
   - Monitor failed login attempts and security events
   - Set up alerts for suspicious activity patterns

2. **Database Maintenance**
   ```bash
   # Check MongoDB status
   docker compose exec mongodb mongosh -u admin -p --eval "db.serverStatus()"
   
   # Monitor collection size
   docker compose exec mongodb mongosh -u admin -p --eval "db.access_logs.stats()"
   
   # Manual cleanup (if needed)
   docker compose exec app php bin/console app:cleanup-access-logs --days=365
   ```

3. **Backup Recommendations**
   ```bash
   # Create MongoDB backup
   docker compose exec mongodb mongodump -u admin -p --authenticationDatabase admin -d reliquary -o /data/backup/
   
   # Restore from backup
   docker compose exec mongodb mongorestore -u admin -p --authenticationDatabase admin /data/backup/reliquary/
   ```

#### Compliance and Legal Considerations

1. **GDPR Compliance**
   - Personal data (IP addresses, session IDs) is hashed
   - Data retention period is configurable and limited
   - Users can request data deletion through admin interface
   - Audit trail for all data processing activities

2. **Data Protection**
   - Access logs contain no plaintext personal information
   - Secure storage with proper access controls
   - Regular security assessments recommended

3. **Retention Policies**
   - Default retention: 1 year (configurable)
   - Automatic cleanup via TTL indexes
   - Manual cleanup tools available
   - Compliance reporting features

### SSL/TLS Configuration

The App container in this project is configured to serve HTTPS on port 443 with SSL/TLS support. The container generates a self-signed SSL certificate during build time, which is suitable for development and testing.

For production use:

1. **Replace Self-Signed Certificates**

   For production environments, you should replace the self-signed certificates with proper certificates from a trusted Certificate Authority (CA):

   - Mount your SSL certificates into the container:
     ```yaml
     volumes:
       - ./ssl/your-cert.crt:/etc/apache2/ssl/apache.crt
       - ./ssl/your-key.key:/etc/apache2/ssl/apache.key
     ```

2. **Let's Encrypt Integration**

   For automatic certificate management, consider using Let's Encrypt:

   - Set up a volume for Let's Encrypt certificates
   - Configure a renewal process (e.g., using certbot in a separate container)
   - Mount the certificates into the App container

## Maintenance

### Updating the Application

#### Automatic Updates with Watchtower

The production setup includes Watchtower, which automatically updates containers to the latest available image:

- Watchtower checks for updates once a day at midnight
- Only containers with the label `com.centurylinklabs.watchtower.enable=true` are updated
- Watchtower exposes an HTTP API for manual triggering of updates
- Old images are automatically cleaned up after updating

Required environment variables for Watchtower:
```
WATCHTOWER_HTTP_API_TOKEN=your-secure-token
```

To manually trigger an update via the Watchtower API:
```bash
curl -H "Authorization: Bearer your-secure-token" -X POST http://your-server:8080/v1/update
```

#### Manual Updates

If you prefer to update manually:

```bash
# Pull the latest images
docker compose pull

# Restart the containers
docker compose up -d

# Import saints data
docker compose exec app php bin/console app:import-saints

# Note: Database migrations will run automatically when the container restarts
```

### Monitoring

Consider adding monitoring tools:

- Prometheus for metrics collection
- Grafana for visualization
- Loki for log aggregation

### Scaling

For higher traffic loads:

1. **Horizontal Scaling**

   Deploy multiple instances of the App container behind a load balancer.

2. **Vertical Scaling**

   Increase the resources (CPU, memory) allocated to your containers.

## Troubleshooting

### Checking Logs

```bash
# View logs for all services
docker compose logs

# View logs for a specific service
docker compose logs app
```

### Common Issues

1. **Database Connection Issues**

   Check that the `DATABASE_URL` environment variable is correctly set and that the database is accessible.

2. **Permission Issues**

   Ensure that the volume mounts have the correct permissions.

3. **Image Pull Failures**

   Verify that your Docker registry credentials are correct and that the images exist in the registry.
