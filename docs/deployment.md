# Deployment Guide (Forge, Vapor, or Any Laravel Host)

## 1. Environment Setup

Required env values:
- APP_ENV=production
- APP_DEBUG=false
- APP_URL=https://your-domain
- DB_CONNECTION=sqlite (production runs one SQLite file; see docs/deployment-cpanel.md)
- QUEUE_CONNECTION=database (the cPanel host has no Redis)
- BROADCAST_CONNECTION=null (no websocket server)
- DOTFORMS_QUEUE_NOTIFICATIONS=notifications
- DOTFORMS_QUEUE_AI=ai
- OPENAI_API_KEY=...
- OPENAI_MODEL=gpt-4.1-mini
- DOTFORMS_UPLOAD_DISK=public (or s3)

Optional for Gemini:
- GEMINI_API_KEY=...

## 2. Build and Migrate

Run during deploy:
- composer install --no-dev --optimize-autoloader
- php artisan migrate --force
- npm ci
- npm run build
- php artisan optimize

## 3. Queue Workers

Dot.Forms uses queues for notifications and AI jobs.

Start workers (example):
- php artisan queue:work --queue=notifications,ai,default --tries=3 --timeout=90

For Forge:
- Configure one daemon with the command above.

For Vapor:
- Ensure queue workers are enabled in vapor.yml and queue names include notifications and ai.

## 4. Scheduler

Dot.Forms schedules forms:close-expired every minute.

Enable scheduler:
- * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1

This command closes published forms past their configured close_at setting.

## 5. Storage

If using local/public disk:
- php artisan storage:link

If using S3:
- Set AWS_* env variables and DOTFORMS_UPLOAD_DISK=s3.

## 6. Security and Operations

Recommended:
- Force HTTPS at load balancer or web server
- Set trusted proxies and secure cookies
- Enable log shipping/monitoring
- Run php artisan config:cache and php artisan route:cache after deploy (when compatible)

## 7. Smoke Checks

After deploy:
- Open dashboard and team forms pages
- Create a draft form and publish it
- Submit test response (including file upload)
- Verify email notification and webhook behavior
- Verify analytics dashboard loads charts
