# Strayt Tix - Production Deployment & Operations Guide

This guide details the complete production architecture, VPS provisioning steps, GitHub Actions CI/CD pipeline, driver-independent configuration, and disaster recovery procedures for **Strayt Tix** (`StraytTix-be`).

---

## 1. System Overview & Architecture

### Hardware Constraints
- **Host**: Bluehost Ubuntu VPS
- **CPU**: 1 vCPU
- **RAM**: 2 GB
- **Architecture**: x86_64

### Architectural Decisions for 2GB RAM
To guarantee stability without out-of-memory (OOM) crashes:
1. **No Compiling on the VPS**: Docker images are built and pushed to GitHub Container Registry (GHCR) using GitHub Actions runners. The VPS only pulls pre-built images.
2. **Driver Independence**: The app runs with zero external memory overhead by using:
   - `CACHE_STORE=database`
   - `QUEUE_CONNECTION=database`
   - `SESSION_DRIVER=file`
   Redis remains supported and can be enabled simply by switching `.env` values.
3. **PHP-FPM Ondemand Manager**: `pm = ondemand` with `pm.max_children = 5` ensures memory is freed when idle.
4. **Tuned MySQL 8.0**: InnoDB Buffer Pool is capped at `256M` (default MySQL 8 consumes >1GB). Port 3306 is strictly isolated on the private Docker bridge network `strayttix-internal`.
5. **Shared Storage**: Storage volumes (`app-storage`) persist logs and uploads, while `mysql-data` persists the database.

---

## 2. Bluehost VPS Initial Provisioning

Execute the following commands on a freshly installed Ubuntu LTS VPS:

### Step 1: Update Packages & Configure UFW Firewall
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl git ufw htop ca-certificates gnupg

# Allow SSH and Web traffic only
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
```

### Step 2: Install Docker Engine & Docker Compose
```bash
# Add Docker's official GPG key:
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

# Add the repository to Apt sources:
echo \
  "deb [arch="$(dpkg --print-architecture)" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  "$(. /etc/os-release && echo "$VERSION_CODENAME")" stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

### Step 3: Create Dedicated Deployer User
```bash
sudo adduser --disabled-password --gecos "" deployer
sudo usermod -aG docker deployer

# Setup SSH key authentication for deployer
sudo mkdir -p /home/deployer/.ssh
sudo cp /root/.ssh/authorized_keys /home/deployer/.ssh/
sudo chown -R deployer:deployer /home/deployer/.ssh
sudo chmod 700 /home/deployer/.ssh
sudo chmod 600 /home/deployer/.ssh/authorized_keys
```

### Step 4: Setup Deployment Directory
```bash
sudo mkdir -p /opt/strayttix
sudo chown -R deployer:deployer /opt/strayttix
```

---

## 3. GitHub Repository Secrets Setup

Navigate to **GitHub Repository -> Settings -> Secrets and variables -> Actions** and add the following repository secrets:

| Secret Name | Value Description | Example |
| :--- | :--- | :--- |
| `VPS_HOST` | VPS Public IPv4 Address or Domain | `192.0.2.1` |
| `VPS_USERNAME` | SSH user created in Step 3 | `deployer` |
| `VPS_SSH_KEY` | Private SSH key (ED25519 or RSA) matching VPS `authorized_keys` | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `VPS_SSH_PORT` | SSH Port (default `22`) | `22` |
| `GHCR_TOKEN` | GitHub Personal Access Token (PAT) with `read:packages` permission (or leave empty to default to `GITHUB_TOKEN`) | `ghp_xxxx` |

---

## 4. Production Environment Configuration (`.env`)

Create the production environment file on the VPS at `/opt/strayttix/.env`:

```ini
APP_NAME="Strayt Tix"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_PHP_ARTISAN_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://api.strayttix.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

# Database Configuration (Internal Docker Network)
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=strayttix
DB_USERNAME=strayttix_user
DB_PASSWORD=YOUR_STRONG_DATABASE_PASSWORD_HERE

# Driver-Independent Defaults (Low-memory footprint)
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=file

# Storage Disks
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=YOUR_AWS_OR_CLOUDFLARE_KEY
AWS_SECRET_ACCESS_KEY=YOUR_AWS_OR_CLOUDFLARE_SECRET
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=strayttix-production
AWS_URL=https://assets.strayttix.com

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@strayttix.com
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@strayttix.com"
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 5. Automated CI/CD Pipeline Workflow

The project uses two GitHub Actions workflows:

### A. Quality Assurance (`.github/workflows/ci.yml`)
- **Triggers**: Every pull request and push targeting `main`.
- **Steps**:
  1. Spins up MySQL 8.0 service.
  2. Validates `composer.json` & `composer.lock`.
  3. Checks code formatting via Laravel Pint (`vendor/bin/pint --test`).
  4. Runs security audit (`composer audit`).
  5. Executes automated tests using Pest PHP (`php artisan test --compact`).
- **Protection**: Merging into `main` is blocked if any check fails.

### B. Production Release (`.github/workflows/deploy.yml`)
- **Triggers**: Successful merge or direct push to `main`.
- **Steps**:
  1. Builds production multi-stage Docker image using GitHub Actions Buildx.
  2. Tags with full commit SHA and `latest`, pushing to GitHub Container Registry (GHCR).
  3. Connects to Bluehost VPS over SSH.
  4. Pulls newly published image.
  5. Creates an automatic gzip MySQL database backup in `/opt/strayttix/backups/`.
  6. Executes database migrations safely in a one-off task (`docker compose run --rm app php artisan migrate --force`).
  7. Replaces running containers (`app`, `queue`, `scheduler`, `nginx`).
  8. Refreshes config and route cache (`config:cache`, `route:cache`, `queue:restart`).
  9. Polls health check endpoint `http://127.0.0.1/up`.

---

## 6. Switching Drivers to Redis (Optional Future Scale)

The application is completely driver-independent. When you decide to scale or add a Redis service:

1. In `/opt/strayttix/docker-compose.prod.yml`, add a redis service or connect an external managed Redis instance.
2. Update `/opt/strayttix/.env`:
   ```ini
   REDIS_HOST=redis
   REDIS_PASSWORD=YOUR_REDIS_PASSWORD
   REDIS_PORT=6379

   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=redis
   ```
3. Restart containers:
   ```bash
   docker compose -f docker-compose.prod.yml up -d
   docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
   docker compose -f docker-compose.prod.yml exec -T queue php artisan queue:restart
   ```

No code modifications or Docker image rebuilds are needed.

---

## 7. Disaster Recovery & Rollback

### Automated Pre-Release Backups
A compressed database dump is created automatically before every migration in `/opt/strayttix/backups/`:
`db_backup_pre_<COMMIT_SHA>_<TIMESTAMP>.sql.gz`

### Running Manual Database Backups
```bash
/opt/strayttix/docker/scripts/backup.sh
```

### Performing a Rollback
If a newly deployed release exhibits bugs, rollback immediately to the previous commit SHA:

```bash
# Rollback code only:
/opt/strayttix/docker/scripts/rollback.sh <PREVIOUS_COMMIT_SHA>

# Rollback code AND restore database to pre-release state:
/opt/strayttix/docker/scripts/rollback.sh <PREVIOUS_COMMIT_SHA> /opt/strayttix/backups/db_backup_pre_<COMMIT_SHA>_<TIMESTAMP>.sql.gz
```

---

## 8. Maintenance Commands

```bash
# View live container status:
docker compose -f /opt/strayttix/docker-compose.prod.yml ps

# Tail application logs:
docker compose -f /opt/strayttix/docker-compose.prod.yml logs -f app

# Tail queue worker logs:
docker compose -f /opt/strayttix/docker-compose.prod.yml logs -f queue

# Monitor memory usage on VPS:
docker stats --no-stream
```
