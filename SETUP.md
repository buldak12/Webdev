# VapeShop PH - Setup Guide

This guide will help you set up the VapeShop project on a new machine.

## Prerequisites

- **PHP 8.1+** (with extensions: pdo_mysql, curl, openssl, mbstring, xml)
- **Composer** (PHP package manager)
- **MySQL 8.0+** or MariaDB
- **WAMP/XAMPP/Laragon** (recommended for Windows) or native PHP

---

## Step 1: Get the Project Files

### Option A: Copy the folder directly
1. Copy the entire `landing-page-web` folder to your new PC
2. **Delete these folders** before copying (they will be regenerated):
   - `vendor/` (large, will reinstall)
   - `var/` (cache and logs)

### Option B: Clone from Git
```bash
git clone <your-repository-url> landing-page-web
cd landing-page-web
```

---

## Step 2: Install PHP Dependencies

Open terminal in the project folder and run:

```bash
composer install
```

This will download all required packages into the `vendor/` folder.

---

## Step 3: Configure Environment

Create your local environment file:

```bash
copy .env .env.local
```

Edit `.env.local` and update these values for your PC:

```env
# Database Configuration
DATABASE_URL="mysql://USERNAME:PASSWORD@127.0.0.1:3306/vapeshop_db?serverVersion=8.0"

# Mailer (Brevo SMTP)
MAILER_DSN=smtp://YOUR_LOGIN:YOUR_SMTP_KEY@smtp-relay.brevo.com:587

# Google OAuth (get from Google Cloud Console)
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
```

---

## Step 4: Create Database

```bash
# Create the database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction
```

---

## Step 5: Fix SSL Certificates (Windows Only)

If you're on Windows and get SSL certificate errors:

1. Download CA certificates:
   - Go to https://curl.se/ca/cacert.pem
   - Save as `C:\wamp64\bin\php\php8.x.x\extras\ssl\cacert.pem`

2. Edit your `php.ini` and add/uncomment:
   ```ini
   curl.cainfo = "C:/wamp64/bin/php/php8.x.x/extras/ssl/cacert.pem"
   openssl.cafile = "C:/wamp64/bin/php/php8.x.x/extras/ssl/cacert.pem"
   ```

3. Restart your web server (Apache/WAMP)

---

## Step 6: Set Up Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create a new project or select existing
3. Go to **Credentials** → **Create Credentials** → **OAuth 2.0 Client ID**
4. Set Application type: **Web application**
5. Add **Authorized redirect URIs**:
   ```
   http://127.0.0.1:8001/oauth/google/check
   http://127.0.0.1:8001/oauth/google/customer/check
   ```
   (Replace `8001` with your port)

6. Copy the Client ID and Client Secret to your `.env.local`

---

## Step 7: Set Up Brevo Email (Optional)

1. Sign up at [brevo.com](https://www.brevo.com) (free tier: 300 emails/day)
2. Go to **Settings** → **SMTP & API**
3. Copy your SMTP credentials
4. Update `.env.local`:
   ```env
   MAILER_DSN=smtp://YOUR_LOGIN:YOUR_SMTP_KEY@smtp-relay.brevo.com:587
   ```

---

## Step 8: Clear Cache & Start Server

```bash
# Clear cache
php bin/console cache:clear

# Start the development server
symfony server:start
# OR
php -S 127.0.0.1:8001 -t public
```

---

## Step 9: Initial Setup (First Run)

1. Open your browser: `http://127.0.0.1:8001/setup`
2. Create the initial admin account
3. Login at `http://127.0.0.1:8001/login`

---

## Common Commands

```bash
# Clear cache
php bin/console cache:clear

# Run migrations
php bin/console doctrine:migrations:migrate

# Create new migration after entity changes
php bin/console make:migration

# List all routes
php bin/console debug:router

# Check container services
php bin/console debug:container
```

---

## Project Structure

```
landing-page-web/
├── config/             # Symfony configuration
├── migrations/         # Database migrations
├── public/             # Web root (index.php, assets)
├── src/
│   ├── Controller/     # HTTP controllers
│   ├── Entity/         # Doctrine entities
│   ├── Form/           # Form types
│   ├── Repository/     # Database repositories
│   ├── Security/       # Authenticators
│   └── Service/        # Business logic services
├── templates/          # Twig templates
├── tests/              # PHPUnit tests
├── .env                # Default environment config
└── composer.json       # PHP dependencies
```

---

## User Roles

| Role | Access |
|------|--------|
| `ROLE_CUSTOMER` | Customer dashboard, orders, cart |
| `ROLE_STAFF` | Staff dashboard, order fulfillment |
| `ROLE_ADMIN` | Full admin access |

---

## Troubleshooting

### "SSL certificate problem" error
→ Follow Step 5 to configure SSL certificates

### "redirect_uri_mismatch" on Google login
→ Add your exact redirect URI to Google Cloud Console (Step 6)

### Emails not sending
→ Check MAILER_DSN in `.env.local` and ensure Brevo credentials are correct

### Database connection error
→ Verify DATABASE_URL in `.env.local` and ensure MySQL is running

---

## Support

For issues, check the Symfony documentation: https://symfony.com/doc/current/index.html
