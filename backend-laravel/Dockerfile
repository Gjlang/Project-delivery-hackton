# Build context is the repo root (see docker-compose.yml) so this Dockerfile
# can also pull in the sibling playwright-worker/ directory.

# --- Stage 1: build frontend assets -----------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY backend-laravel/package.json backend-laravel/package-lock.json ./
RUN npm ci

COPY backend-laravel/resources ./resources
COPY backend-laravel/vite.config.js backend-laravel/tailwind.config.js backend-laravel/postcss.config.js ./
RUN npm run build

# --- Stage 2: install PHP dependencies ----------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY backend-laravel/composer.json backend-laravel/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# --- Stage 3: build the Playwright worker --------------------------------
# Laravel shells out to this as a child process (stdin/stdout JSON) for the
# website-testing feature -- never exposed as a network service, so it's
# built here and just carried into the runtime image as a local binary/CLI.
FROM node:20-slim AS playwright-worker

WORKDIR /worker

COPY playwright-worker/package.json playwright-worker/package-lock.json ./
RUN npm ci

COPY playwright-worker/tsconfig.json ./
COPY playwright-worker/src ./src
RUN npm run build

# Reinstall production-only deps for the runtime copy (drops typescript/@types).
RUN rm -rf node_modules && npm ci --omit=dev

# --- Stage 4: runtime image ----------------------------------------------
# serversideup/php bundles php-fpm + nginx + supervisord for exactly this
# use case, and its entrypoint listens on $PORT out of the box -- matches
# Cloud Run's convention (or any platform that injects PORT).
FROM serversideup/php:8.2-fpm-nginx

USER root

# Node.js runtime (to run the built worker) + the OS libraries Playwright's
# bundled Chromium needs -- `playwright install-deps` knows the exact set
# for whatever Playwright version is pinned in playwright-worker/package.json.
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data backend-laravel /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

COPY --chown=www-data:www-data --from=playwright-worker /worker /var/www/playwright-worker

# Pin the browser install path explicitly -- without this, Playwright installs
# to the *build* user's home (root, since we're root here) but the app runs
# as www-data at runtime and would look in a different (empty) home dir.
ENV PLAYWRIGHT_BROWSERS_PATH=/var/www/playwright-worker/.browsers
RUN npx --prefix /var/www/playwright-worker playwright install --with-deps chromium \
    && chown -R www-data:www-data /var/www/playwright-worker/.browsers

RUN php artisan package:discover --ansi \
    && php artisan storage:link || true

USER www-data
