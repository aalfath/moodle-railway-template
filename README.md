[![Deploy on Railway](https://railway.com/button.svg)](https://railway.com/new/template/moodle-2?utm_medium=integration&utm_source=button&utm_campaign=moodle)

# Moodle on Railway

A Railway template for [Moodle LMS](https://moodle.org), the open-source learning platform, built from this repository.

**What it deploys**

| Service | Source | Data |
|---|---|---|
| Moodle | this repo's `Dockerfile`: Moodle **5.3.1** (official GitHub release tag, checked by sha256) on Moodle HQ's `moodlehq/moodle-php-apache:8.4-bookworm` image, pinned by digest | volume at `/var/www/moodledata` (uploaded files, course backups, sessions) |
| Postgres | Railway's PostgreSQL | volume at `/var/lib/postgresql/data` (all Moodle tables) |

**What the start script does** (`railway/start.sh`)

1. Waits for Postgres.
2. On an empty database, installs Moodle with the CLI installer. The admin user is `admin`, with the generated password in `MOODLE_ADMIN_PASSWORD`. Moodle's web installer is never reachable: until the install finishes, every page answers "Moodle is starting".
3. On an existing site, runs `admin/cli/upgrade.php --non-interactive`, so a version bump in this repo upgrades the database on the next deploy. It clears an upgrade lock left by a container that was stopped mid-upgrade.
   A first install that was stopped before it finished (for example by a redeploy in the first minutes) holds no user data yet: its tables are dropped and the install starts again.
4. Runs Moodle's cron every minute in the background, and starts Apache on `PORT` (8080).

`/healthz` answers 200 when PHP is up and the database is reachable. It's the Railway health check.

## Variables

| Variable | Default | What it is |
|---|---|---|
| `MOODLE_URL` | `https://${{RAILWAY_PUBLIC_DOMAIN}}` | The site address. Change it if you add a custom domain. |
| `MOODLE_DATABASE_URL` | `${{Postgres.DATABASE_URL}}` | Postgres connection over the private network. |
| `MOODLE_ADMIN_PASSWORD` | `${{secret(24)}}` | Password of the `admin` user, created on first boot. Read it in the Moodle service's Variables tab. Changing it later has no effect: change the password in Moodle. |
| `PORT` | `8080` | Port Apache listens on. |

Optional, read only on the first install: `MOODLE_ADMIN_USER` (default `admin`), `MOODLE_ADMIN_EMAIL`, `MOODLE_SITE_NAME`, `MOODLE_SITE_SHORTNAME`, `MOODLE_LANG` (default `en`). Outgoing mail (SMTP) is set in Moodle under Site administration → Server → Email.

After deploy: the first boot takes 3 to 4 minutes while Moodle installs. Then open the public URL and log in as `admin` with `MOODLE_ADMIN_PASSWORD` from the Moodle service's Variables tab. Change the password and set your email in Moodle after the first login.

## Updates

Each Moodle version bump is a commit to `main` with an entry in [CHANGELOG.md](CHANGELOG.md). Railway then shows an "update available" notice on projects deployed from this template, and you choose when to apply it. On the next start, the database upgrade runs on its own. Back up the Postgres volume before you apply a major version.

Plugins: the Moodle code lives in the image, so plugins installed through the web UI would be lost on the next deploy. Installing plugins from the web UI is turned off. Fork this repo and add them to the `Dockerfile` instead.

## Run it locally

```sh
docker build -t moodle-railway .
docker run -d --name pg -e POSTGRES_PASSWORD=pw postgres:17
docker run -p 8080:8080 --link pg \
  -e MOODLE_URL=http://localhost:8080 \
  -e MOODLE_DATABASE_URL=postgresql://postgres:pw@pg:5432/postgres \
  -e MOODLE_ADMIN_PASSWORD=change-me moodle-railway
```

## Licence

Moodle is GPL-3.0-or-later. The wrapper files in this repo are released under the same licence (see `LICENSE`).
