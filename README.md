# Flux License

License management for Flux ERP.

## Maintenance window

`flux-license:maintenance-begin` takes an instance offline without breaking running work:

1. puts the app into maintenance mode (`--retry`, `--secret`)
2. pauses all queues (`queue:pause` for every connection, `horizon:pause` when Horizon is installed), running jobs are allowed to finish
3. interrupts a running `schedule:run`
4. waits until no job is reserved any more (`--timeout`, default 300 seconds)

If jobs are still running after the timeout, the command exits non-zero and leaves the instance down and paused.

`flux-license:maintenance-end` resumes the queues (and Horizon), brings the app up and, with `--health-url`, fails unless that URL answers with a 2xx status. Both commands are idempotent.

### Deploy script (Forge)

```bash
cd $FORGE_SITE_PATH
$FORGE_PHP artisan flux-license:maintenance-begin --retry=60
trap '$FORGE_PHP artisan flux-license:maintenance-end --health-url="https://$FORGE_SITE_NAME/up"' EXIT

git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan octane:reload   # only on Octane sites
```

The `trap` brings the instance back even when a step fails.

### Server snapshot

```bash
php artisan flux-license:maintenance-begin
# take the snapshot here, an online snapshot pauses the VM
sudo chronyc makestep || sudo systemctl restart systemd-timesyncd   # correct the clock after the pause
php artisan flux-license:maintenance-end --health-url=https://example.com/up
```
