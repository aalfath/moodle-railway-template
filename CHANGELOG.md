# Changelog

Breaking changes are listed first in each entry.

## 5.3.1 (2026-11-16)

- First release: Moodle 5.3.1 (Build 20261112) on PHP 8.4 (`moodlehq/moodle-php-apache:8.4-bookworm@sha256:3e910075c08dc73c8e18732f5e80c8fb9a60ae72eaf52b1ee198bb510e4fd9d6`), PostgreSQL, CLI install with a generated admin password, automatic database upgrade on start, cron every minute, `/healthz` health check. Recovers on its own from a first install or an upgrade that a redeploy interrupted. Starts Apache with only the prefork MPM. README: Deploy on Railway button for the published template `moodle-2`.
