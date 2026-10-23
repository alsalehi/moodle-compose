# TechHub Moodle 5.2.2 → 5.2.4 (PHP 8.4.26) on Dokploy

This repository is designed for an **in-place upgrade of the existing Moodle 5.2.2 site** to Moodle 5.2.4. It reuses the site's existing MariaDB data and Moodledata volumes. It does not install a new Moodle site.

The new Moodle core is baked into the versioned image from the official Moodle 5.2.4 archive and checked against Moodle's published SHA-256. PHP-FPM uses PHP 8.4.26 on Alpine; Nginx is a separate service and exposes only Moodle's `/public` webroot, as required by Moodle 5.1+.

> **Important:** Do not deploy until you have confirmed all existing volume names, made backups, and included every installed third-party/custom plugin's code in `moodle-plugins/`. Keep maintenance mode enabled while upgrading.

## Files

```text
.
├── Dockerfile
├── docker-compose.yml
├── .env.example
├── .gitignore
├── .dockerignore
├── README.md
├── docker/
│   ├── moodle-entrypoint.sh
│   ├── nginx.conf
│   ├── php.ini
│   └── www.conf
└── moodle-plugins/   # Third-party/custom plugin folders, in Moodle-relative paths
```

## 1. Confirm the currently running Moodle 5.2.2 containers and volumes

On the Dokploy host, run:

```sh
docker ps --format 'table {{.Names}}\t{{.Image}}'
```

Use the actual container names below:

```sh
docker exec CURRENT_MOODLE_CONTAINER php -r 'require "/var/www/html/version.php"; echo $release, PHP_EOL;'
docker inspect CURRENT_MARIADB_CONTAINER --format '{{range .Mounts}}{{printf "%s\t%s\t%s\n" .Name .Destination .Source}}{{end}}'
docker inspect CURRENT_MOODLE_CONTAINER --format '{{range .Mounts}}{{printf "%s\t%s\t%s\n" .Name .Destination .Source}}{{end}}'
```

Record the exact Docker volume names mounted at these destinations:

- MariaDB: `/var/lib/mysql`
- Moodledata: `/var/moodledata`
- Current Moodle 5.2.2 code: `/var/www/html`

Use those **exact actual volume names** for `MARIADB_DATA_VOLUME_NAME`, `MOODLE_DATA_VOLUME_NAME`, and `MOODLE_CURRENT_CODE_VOLUME_NAME`. Do not assume the names are literally `mariadb_data`, `moodle_data`, and `moodle_html`; Compose projects often prefix volume names.

The current code volume is mounted read-only in the new PHP container only so the entrypoint can copy its existing `config.php` into the new persistent configuration volume. It is **not** mounted over `/var/www/html`, so it cannot hide the Moodle 5.2.4 code baked into the new image.

## 2. Enable maintenance mode and back up before changing the deployment

Enable maintenance mode using the existing Moodle 5.2.2 container:

```sh
docker exec CURRENT_MOODLE_CONTAINER \
  php /var/www/html/admin/cli/maintenance.php --enable
```

Create a database dump. The following uses the existing MariaDB container's environment variables; adapt the container name if needed:

```sh
mkdir -p backups
docker exec CURRENT_MARIADB_CONTAINER sh -lc \
  'mariadb-dump --single-transaction --routines --triggers --default-character-set=utf8mb4 -uroot -p"$MARIADB_ROOT_PASSWORD" --databases "$MARIADB_DATABASE"' \
  > backups/moodle-before-5.2.4.sql
```

Back up Moodledata too. Replace `EXISTING_MOODLEDATA_VOLUME_NAME` with the exact volume name from step 1:

```sh
mkdir -p "$PWD/backups"
docker run --rm \
  --mount type=volume,src=EXISTING_MOODLEDATA_VOLUME_NAME,dst=/source,readonly \
  --mount type=bind,src="$PWD/backups",dst=/backup \
  alpine:3.24 sh -c 'tar -czf /backup/moodledata-before-5.2.4.tar.gz -C /source .'
```

Also snapshot the server if available. Save the previous Compose file and Dokploy environment variables securely. Pause any separate cron/scheduled-task runner during the upgrade. Do not start a second application against the same production volumes.

## 3. Copy your current installed plugin code into this repository

Moodle stores plugin settings and installed-version information in the database, but the PHP files themselves are part of the code tree. **Preserving the database and Moodledata alone does not preserve plugin code.** Add all non-core/custom plugin directories from your current 5.2.2 code tree to `moodle-plugins/` before building.

Examples of expected repository paths:

```text
moodle-plugins/local/aiassistant/...
moodle-plugins/mod/customactivity/...
moodle-plugins/theme/yourtheme/...
moodle-plugins/admin/tool/customtool/...
moodle-plugins/blocks/yourblock/...
moodle-plugins/question/type/yourquestiontype/...
```

From the Dokploy host, export only the actual third-party/custom plugin folders you have installed. Replace the example paths with real paths shown in your Moodle plugin overview:

```sh
docker exec CURRENT_MOODLE_CONTAINER sh -lc \
  'tar -czf /tmp/site-plugins.tgz -C /var/www/html local/aiassistant theme/yourtheme admin/tool/customtool'
docker cp CURRENT_MOODLE_CONTAINER:/tmp/site-plugins.tgz ./site-plugins.tgz
```

Transfer `site-plugins.tgz` to the machine where you maintain the GitHub working copy. Extract plugin folders into the repository's `moodle-plugins/` directory, preserving their relative paths:

```sh
tar -xzf site-plugins.tgz -C moodle-plugins/
```

Review the extracted paths carefully. **Do not copy entire core directories** such as `mod/`, `theme/`, `blocks/`, `auth/`, or `local/` from the old tree; that could overwrite Moodle 5.2.4 core code. Copy only the specific non-core plugin directories. Check every plugin for Moodle 5.2.4 and PHP 8.4 compatibility before proceeding. If a plugin uses custom patches inside Moodle core rather than a plugin directory, review and port those patches separately instead of copying old core files over the new core.

Commit the plugin folders to GitHub along with the Docker files so future builds are reproducible:

```sh
git add Dockerfile docker-compose.yml docker/ moodle-plugins/ README.md .gitignore .dockerignore .env.example
git commit -m "Prepare Moodle 5.2.4 upgrade from 5.2.2"
git push
```

Never commit `config.php`, credentials, database dumps, or the real `.env` file.

## 4. Set Dokploy environment variables

In the existing Dokploy Compose application's **Environment** section, define the variables in `.env.example`:

- Keep the current values for `MARIADB_ROOT_PASSWORD`, `MARIADB_USER`, `MARIADB_PASSWORD`, and `MARIADB_DATABASE`.
- Set the three volume-name variables to the **exact current volume names** from `docker inspect`.
- Keep `MOODLE_CONFIG_VOLUME_NAME=techhub_moodle_config` stable on future deployments. This is a new persistent volume for the old site's existing `config.php`, not a replacement database.
- `REDIS_DATA_VOLUME_NAME` is a new volume. Redis cache/session data can be rebuilt; if your current Redis held sessions, users may have to sign in again.

The already-initialized MariaDB data directory is authoritative; changing MariaDB's environment variables does not change credentials inside an existing database. Keep these values consistent with the existing `config.php`.

## 5. Point the existing Dokploy app to this repository

1. Update the **existing** Dokploy Compose application to use this GitHub repository and branch. Do not create a second active stack using the same production volumes.
2. Confirm the existing `dokploy-network` network is present.
3. Deploy/build the updated Compose app while maintenance mode is still enabled.
4. Configure `hu-techhub.org` in Dokploy to route to service **`nginx`**, container port **`80`**, with TLS enabled. Do not expose PHP-FPM, MariaDB, or Redis directly.
5. Confirm the new containers are healthy and inspect their logs before running the database upgrade.

The first PHP-container start imports the existing `config.php` once into `moodle_config`, then makes it available to the new Moodle code. If it fails because no config file is found, stop and correct `MOODLE_CURRENT_CODE_VOLUME_NAME`; do not run the Moodle installer and do not point a fresh install at the existing production database.

## 6. Run the Moodle 5.2.2 → 5.2.4 CLI upgrade

Run from the Compose project directory on the Dokploy host. If Dokploy manages the project in a generated directory, use its terminal/console and its correct Compose context.

```sh
# Confirm the new image uses the intended PHP version and extensions.
docker compose exec -T moodle php -v
docker compose exec -T moodle php -m

# Maintenance mode should remain enabled.
docker compose exec -T moodle \
  php /var/www/html/admin/cli/upgrade.php --non-interactive

docker compose exec -T moodle \
  php /var/www/html/admin/cli/purge_caches.php

# Review logs before reopening the site.
docker compose logs --tail=200 moodle nginx mariadb redis
```

If the upgrade succeeds and logs are clean, disable maintenance mode:

```sh
docker compose exec -T moodle \
  php /var/www/html/admin/cli/maintenance.php --disable
```

If plugin checks or the upgrade CLI reports an incompatible/missing plugin, **leave maintenance mode enabled**. Update or remove the incompatible plugin deliberately, rebuild/redeploy, then rerun the upgrade. Verify **Site administration → Notifications** and **Server → Environment**, then test login, courses, uploads/downloads, themes, scheduled tasks, and custom plugin functions.

## 7. Rollback

Do not delete the pre-upgrade database dump, Moodledata backup, or original 5.2.2 code volume after deployment. A code-only rollback is insufficient after the database schema has been upgraded.

If validation fails:

1. Keep maintenance mode enabled and stop the new PHP/Nginx services.
2. Restore the pre-upgrade SQL dump into an empty database (not on top of the upgraded schema). If restoring to the original database, first take a separate dump of the failed upgraded state and confirm the target database name before dropping anything.
3. Restore Moodledata if it was changed and the backup is needed.
4. Repoint Dokploy to the saved original deployment and 5.2.2 image/configuration, attaching the original MariaDB, Moodledata, and code volumes exactly as before.
5. Test the restored site, and only then disable maintenance mode.

Test your restore process on a staging copy before relying on it. Do not run the old 5.2.2 code against an already-upgraded 5.2.4 database.

## PHP extension build troubleshooting

The Redis PHP extension is pinned to `6.2.0` in the Dockerfile and Compose build arguments. This is compatible with PHP 8.4 and avoids relying on the PECL `6.3.0` package metadata, which has experienced an upstream metadata problem. The PHP base image already supplies `curl`, `mbstring`, `opcache`, and `sodium`; the Dockerfile checks these modules but does not try to compile them a second time. PHP extension compilation and Redis installation are separate build steps so Docker logs show which one fails.

If a build still fails, inspect the **first** `ERROR` or compiler/PECL error above BuildKit’s final `process ... did not complete successfully` line; that final line alone only identifies the failed Dockerfile step.

## Version pins and source verification

The current source archive is Moodle 5.2.4 from the official `stable502` download path, with its published SHA-256 pinned in both the Dockerfile defaults and Compose build arguments. The PHP runtime uses the requested `php:8.4.26-fpm-alpine` tag; the separate source-download stage uses Alpine 3.24. To upgrade later, deliberately update the Moodle version and its published checksum, PHP patch tag, and any plugin versions, then rebuild and run the Moodle CLI upgrade.

Official references:

- Moodle 5.2.4 release notes: https://moodledev.io/general/releases/5.2/5.2.4
- Official Moodle source checksums: https://download.moodle.org/releases/supported/
- Moodle 5.2 upgrading guide: https://docs.moodle.org/502/en/Upgrading
- PHP 8.4.26 release announcement: https://www.php.net/releases/8_4_26.php
