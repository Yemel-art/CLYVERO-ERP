# Clyvero: fresh Ubuntu VPS + GitHub Actions

> **Start here:** If this is your first server deployment, follow the clearer
> [step-by-step walkthrough](VPS_SETUP_WALKTHROUGH.md) first. Return to this file
> for operations, troubleshooting, rollback, restore, and changing domains.

This guide deploys the whole project to one VPS. You can start without buying a
domain, using two temporary HTTPS hostnames derived from the VPS's public IPv4
address. Buy a domain later and follow step 12; your database and uploads stay put.

The repository is already connected to **Yemel-art/CLYVERO-ERP** and its default
branch is **main**. The workflow in `.github/workflows/ci.yml` runs tests, dependency
audits, frontend lint/type checks, and production image builds. A successful push
to `main`, including a merged pull request, then deploys that exact commit over SSH.
Pull requests run checks without deploying. Manual runs are supported on `main`.

These files are prepared locally. You still need to run the server commands, add
the GitHub variables/secrets, and commit/push the files before merging. No server
or GitHub secret has been configured on your behalf.

## 1. What to install and what you need

Use a **fresh Ubuntu 24.04 LTS x86_64/amd64 VPS with a public IPv4 address**.
Ubuntu 26.04 also supports Docker, but these instructions target 24.04. This
pipeline builds amd64 images; do not choose an ARM/aarch64 VPS for this setup.

Plan for **2 vCPU, 4 GB RAM, and 60 GB SSD** as a starting allocation, then measure
actual usage. This is a sizing estimate, not a benchmark. GitHub does the builds;
the VPS needs disk space for the running images, a previous release, incoming
image archives, uploads, PostgreSQL, and backups.

| Installed on Ubuntu | Purpose |
| --- | --- |
| Docker Engine, Docker CLI, containerd | Run the application containers |
| Docker Buildx and Compose plugins | Docker installation tooling and service management |
| OpenSSH server/client | Administrator access and deployment transfers |
| curl, CA certificates, OpenSSL | Installation, health checks, random secrets |
| UFW | Host firewall |
| dnsutils, nano, cron, util-linux | DNS checks, configuration editing, backups, deployment locking |

| Runs inside Docker | Project requirement |
| --- | --- |
| Laravel 12, PHP 8.4, PHP-FPM and Nginx | Backend API |
| Composer dependencies and PHP extensions | bcmath, gd, intl, opcache, pcntl, pdo_pgsql, redis, zip; base image supplies other core extensions |
| Next.js 15, React 19, Node.js 22 | Frontend production server |
| PostgreSQL 16 | Database |
| Redis 7 | Cache, sessions, queued jobs |
| Laravel queue worker and scheduler | Background processes |
| Caddy 2 | Public reverse proxy, HTTPS certificates and renewal |

The containers provide PHP, Composer, Node.js, PostgreSQL, Redis and web servers;
you do not install separate copies of them on Ubuntu. Caddy handles certificates,
so no host Certbot installation is needed.

You also need your VPS provider's initial root/admin login, access to this GitHub
repository's settings, and working SMTP credentials for OTP and password-reset
emails. A domain is not required. An existing mailbox with SMTP/app-password
support can be used while you have no domain. Use your provider's exact settings.

## 2. Log in, update Ubuntu, and prepare an administrator key

Commands marked **LOCAL POWERSHELL** run on your Windows computer. Commands marked
**VPS** run inside an SSH session to Ubuntu. Replace `YOUR_VPS_IP` with the actual
public IPv4 address; never use the documentation example `203.0.113.10` as a real IP.

**LOCAL POWERSHELL** — create your personal administrator key once. Choose a
passphrase when prompted. If the filename already exists, keep that key instead
of overwriting it.

```powershell
ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\clyvero-admin" -C "clyvero administrator"
Get-Content "$env:USERPROFILE\.ssh\clyvero-admin.pub"
ssh root@YOUR_VPS_IP
```

Verify the SSH host fingerprint using the provider's web console before accepting
it. If the provider gives you an `ubuntu` user, connect as that user and run
`sudo -i` first. The next block assumes a root shell.

**VPS, root:**

```bash
cat /etc/os-release
uname -m
apt-get update
apt-get upgrade -y
apt-get install -y ca-certificates curl openssl openssh-server openssh-client ufw dnsutils nano cron util-linux
timedatectl set-timezone Africa/Douala
systemctl enable --now ssh cron
adduser --disabled-password --gecos '' deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
nano /home/deploy/.ssh/authorized_keys
```

Paste the **public** key from `clyvero-admin.pub` into that file as a single line.
In nano: Ctrl+O, Enter to save, then Ctrl+X to exit.

```bash
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw enable
ufw status
```

This assumes SSH port 22. If you changed SSH's port, allow that port before
enabling UFW and later set GitHub's `VPS_PORT` to the same value. In your provider's
network firewall, also allow SSH and TCP 80/443; UDP 443 is optional HTTP/3.
The GitHub-hosted runner must be able to reach SSH; a provider rule that allows
only your home IP will block deployments. Leave outbound DNS, HTTPS, and your
SMTP provider's port available.

Only Caddy publishes container ports. Database, Redis, PHP/Nginx, and Node ports
are internal. Docker-published ports can bypass UFW, so keep that mapping as
provided. See [Docker's firewall explanation](https://docs.docker.com/engine/network/packet-filtering-firewalls/).

Keep the root session open. **In a second LOCAL POWERSHELL window**, verify access:

```powershell
ssh -i "$env:USERPROFILE\.ssh\clyvero-admin" deploy@YOUR_VPS_IP
```

Do not disable your original provider/root login until this works. If Ubuntu
requests a reboot after upgrades, reboot from the root session and reconnect
before continuing. This guide does not change your existing SSH authentication
policy or grant the deploy user passwordless sudo.

## 3. Install Docker

**VPS, root** — use Docker's official Ubuntu APT repository:

```bash
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
. /etc/os-release
tee /etc/apt/sources.list.d/docker.sources > /dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: ${UBUNTU_CODENAME:-$VERSION_CODENAME}
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
systemctl enable --now docker
docker run --rm hello-world
docker compose version
usermod -aG docker deploy
install -d -m 750 -o deploy -g deploy /srv/clyvero
install -d -m 700 -o deploy -g deploy /srv/clyvero/shared /srv/clyvero/incoming /srv/clyvero/releases /srv/clyvero/backups
```

These commands follow [Docker's Ubuntu installation instructions](https://docs.docker.com/engine/install/ubuntu/).
The Docker group gives the deploy user control of the host through Docker; treat
its SSH key as a production administrator credential.

Log out of the deploy session and reconnect so its new group membership applies:

**LOCAL POWERSHELL:**

```powershell
ssh -i "$env:USERPROFILE\.ssh\clyvero-admin" deploy@YOUR_VPS_IP
```

**VPS, deploy:**

```bash
id
docker info > /dev/null
df -h /srv/clyvero
free -h
```

## 4. Choose temporary HTTPS addresses

**VPS, deploy** — replace the IP and email before running this block:

```bash
VPS_IP='YOUR_VPS_IP'
CONTACT_EMAIL='YOUR_REAL_EMAIL_ADDRESS'
IP_LABEL="${VPS_IP//./-}"
APP_DOMAIN="app.${IP_LABEL}.sslip.io"
API_DOMAIN="api.${IP_LABEL}.sslip.io"
printf 'Frontend: https://%s\nAPI: https://%s\n' "$APP_DOMAIN" "$API_DOMAIN"
dig +short "$APP_DOMAIN" A
dig +short "$API_DOMAIN" A
```

Both DNS results must contain your VPS IPv4 address. No account or DNS record
creation is needed: [sslip.io resolves hostnames containing IP addresses](https://sslip.io/).
Caddy can obtain certificates for these public hostnames when the VPS is reachable
on 80/443. Certificate issuance can take a few minutes. Temporary hostnames depend
on this external DNS service and its certificate limits; if issuance is
rate-limited, see troubleshooting below. Do not open the application using a
bare IP: use the printed HTTPS hostname.

Keep this deploy shell open for step 5; it holds those four shell variables.

## 5. Create the production environment file

**LOCAL POWERSHELL** — copy the supplied template from this project to the VPS:

```powershell
scp -i "$env:USERPROFILE\.ssh\clyvero-admin" C:\project\clyvero\deploy\production.env.example deploy@YOUR_VPS_IP:/srv/clyvero/shared/production.env
```

Do this only for the first setup. Never overwrite an established production file
with the template or regenerate its application/database keys during an upgrade.

**VPS, deploy**, in the shell from step 4:

```bash
chmod 600 /srv/clyvero/shared/production.env
sed -i 's/\r$//' /srv/clyvero/shared/production.env
DB_SECRET="$(openssl rand -hex 32)"
REDIS_SECRET="$(openssl rand -hex 32)"
LARAVEL_KEY="$(openssl rand -base64 32)"
sed -i \
  -e "s|^APP_DOMAIN=.*|APP_DOMAIN=${APP_DOMAIN}|" \
  -e "s|^API_DOMAIN=.*|API_DOMAIN=${API_DOMAIN}|" \
  -e "s|^ACME_EMAIL=.*|ACME_EMAIL=${CONTACT_EMAIL}|" \
  -e "s|^APP_URL=.*|APP_URL=https://${API_DOMAIN}|" \
  -e "s|^FRONTEND_URL=.*|FRONTEND_URL=https://${APP_DOMAIN}|" \
  -e "s|^CORS_ALLOWED_ORIGINS=.*|CORS_ALLOWED_ORIGINS=https://${APP_DOMAIN}|" \
  -e "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=${APP_DOMAIN}|" \
  -e "s|^APP_KEY=.*|APP_KEY=base64:${LARAVEL_KEY}|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_SECRET}|" \
  -e "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_SECRET}|" \
  /srv/clyvero/shared/production.env
unset DB_SECRET REDIS_SECRET LARAVEL_KEY
nano /srv/clyvero/shared/production.env
```

Replace every `REPLACE_...` mail setting with real SMTP details. Port 587 with
STARTTLS is the template's configuration. Verify your VPS provider allows outbound
SMTP on that port. `MAIL_FROM_ADDRESS` must be a sender your provider authorizes.
Enclose passwords containing `$`, `#`, or spaces in single quotes so Docker Compose
does not interpolate them. Do not paste this file into GitHub or chat.

Keep `ADMIN_LOGIN_OTP_ENABLED=true`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`,
and `REDIS_QUEUE_RETRY_AFTER=180`. The queue timeout is 120 seconds; the retry delay
must be longer to avoid processing the same job twice.

Check for unfinished setup without printing passwords:

```bash
if grep -Eq 'REPLACE_|YOUR_|yourdomain\.com|example\.com' /srv/clyvero/shared/production.env; then
  echo 'STOP: edit production.env and replace all placeholders.'
else
  echo 'No template placeholders remain.'
fi
```

Store an encrypted copy of this file in your password manager or backup system.
It contains the `APP_KEY` needed to preserve existing encrypted data and signed
URLs. It remains on the server across deployments and is excluded from Git.

## 6. Create the GitHub Actions SSH key

Use a separate key for automation. The VPS does **not** need to clone GitHub:
Actions checks out the repository and transfers the built images and deployment
configuration for the exact commit. There is no VPS-to-GitHub deploy key or
registry token to configure.

**LOCAL POWERSHELL**, once:

```powershell
ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\clyvero-actions" -C "github-actions clyvero"
Get-Content "$env:USERPROFILE\.ssh\clyvero-actions.pub"
```

At both passphrase prompts, press Enter to leave the automation key unencrypted;
the workflow cannot answer a passphrase prompt. Keep the personal administrator
key separate and passphrase protected.

**VPS, deploy:**

```bash
nano ~/.ssh/authorized_keys
```

Keep your existing administrator key line. Add another line containing `restrict`
followed by one space and the complete automation **public** key, for example:

```text
restrict ssh-ed25519 AAAA... github-actions clyvero
```

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

**VPS, root**, obtain a trusted host-key entry directly from the server:

```bash
awk '{print "clyvero-vps " $1 " " $2}' /etc/ssh/ssh_host_ed25519_key.pub
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

Save the **first command's entire output line** for `VPS_KNOWN_HOSTS` below. It
starts `clyvero-vps ssh-ed25519 ...`. The fingerprint output is for verification;
it is not the value of that secret. The workflow checks this key on every SSH
connection; it does not blindly trust a key fetched during deployment.

**LOCAL POWERSHELL** — test the automation login, verifying the fingerprint:

```powershell
ssh -i "$env:USERPROFILE\.ssh\clyvero-actions" -o IdentitiesOnly=yes deploy@YOUR_VPS_IP "docker info > /dev/null && echo Deployment-access-OK"
```

## 7. Add the GitHub repository variables and secrets

Open [the repository's Actions settings](https://github.com/Yemel-art/CLYVERO-ERP/settings/actions).
Enable GitHub Actions and allow the actions used by this workflow
(`actions/checkout`, `actions/setup-node`, and `shivammathur/setup-php`). The workflow
needs only read access to repository contents.

Open **Settings → Secrets and variables → Actions**:
[repository configuration](https://github.com/Yemel-art/CLYVERO-ERP/settings/secrets/actions).
Use **repository** variables/secrets; no GitHub Environment is required.

Under **Variables → New repository variable**, add:

| Exact name | Value |
| --- | --- |
| `VPS_HOST` | Your public VPS IPv4 address, without `http://` |
| `VPS_USER` | `deploy` |
| `VPS_PORT` | `22`, unless you deliberately use another SSH port |
| `API_DOMAIN` | The API hostname printed in step 4, e.g. `api.203-0-113-10.sslip.io`; no scheme, slash, or `/api/v1` |

`APP_DOMAIN` lives in the VPS environment file; no GitHub variable for it is needed.
The workflow builds the three `NEXT_PUBLIC_API_*` values from `API_DOMAIN`.

Under **Secrets → New repository secret**, add:

| Exact name | Value |
| --- | --- |
| `VPS_SSH_KEY` | The complete private `clyvero-actions` key, including its BEGIN/END lines |
| `VPS_KNOWN_HOSTS` | The complete `clyvero-vps ssh-ed25519 ...` line from step 6 |

**LOCAL POWERSHELL** — copy the private key directly to the clipboard:

```powershell
Get-Content -Raw "$env:USERPROFILE\.ssh\clyvero-actions" | Set-Clipboard
```

Paste it only into GitHub's `VPS_SSH_KEY` secret field, save the secret, and clear
the clipboard:

```powershell
Set-Clipboard -Value ''
```

Keep the private key outside the project. Do not commit it or add the production
environment file to GitHub. GitHub makes secrets available only when the workflow
explicitly references them; see [GitHub's secrets documentation](https://docs.github.com/en/actions/concepts/security/secrets).

## 8. Commit the setup, open a PR, and merge to main

Complete steps 2–7 **before merging**. You do not manually launch the application
first: the first successful merge deploys it, creates the database schema, and
initializes roles and permissions.

**LOCAL POWERSHELL:**

```powershell
Set-Location C:\project\clyvero
git remote -v
git status --short
git restore --staged -- .
git switch -c codex/ubuntu-vps-cicd
git add .gitattributes .github/workflows/ci.yml .gitignore compose.vps.yml deploy/Caddyfile deploy/production.env.example scripts/deploy-vps.sh scripts/vps-compose.sh scripts/backup-vps.sh docs/UBUNTU_VPS_GITHUB_ACTIONS.md DEPLOYMENT.md backend/.dockerignore backend/Dockerfile.production frontend/.dockerignore frontend/public/.gitkeep
git diff --cached --stat
git diff --cached
git commit -m "Add full VPS deployment with GitHub Actions"
git push -u origin codex/ubuntu-vps-cicd
```

Environment backup files and uploaded student/teacher photos were found staged
during preparation. `git restore --staged -- .` above **only unstages** files; it
does not delete or undo working files. The following `git add` stages the explicit
deployment files. Review the staged diff before committing and include other
intended application source changes separately. Do not add environment backups,
private keys, uploaded photos, `.codex-backups`, or generated testing/build data.
If another task is changing this repository, finish or pause that work before this
commit so that its staged files are not accidentally published.

If you already have a feature branch containing your application changes, use
that branch instead of creating another. If Git needs your identity, set your
real `user.name` and `user.email`, then retry the commit. If pushing requires
authentication, sign in using Git Credential Manager or `gh auth login`; the
existing local GitHub CLI login was not usable when inspected.

Open [a pull request](https://github.com/Yemel-art/CLYVERO-ERP/compare) from this branch
into `main`. Wait for the backend, frontend, and production-image checks to pass,
then merge. Prefer requiring these checks in your `main` branch protection/ruleset.
Do not enable a required manual deployment approval if you want unattended merges.

Watch [GitHub Actions](https://github.com/Yemel-art/CLYVERO-ERP/actions), workflow
**CI and VPS deployment**, especially the `production-images` job. A merge creates
a push to `main`, which triggers deployment as defined by
[GitHub's workflow triggers](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/trigger-a-workflow).
Direct pushes to `main` also deploy. CI failures intentionally prevent deployment.

The deployment performs these steps:

1. Build Laravel and Next.js images on GitHub using the commit SHA as their tag.
2. Transfer images and that commit's deployment configuration over verified SSH.
3. Validate server settings, import images, and start PostgreSQL/Redis if needed.
4. Stop application processes and back up the database and uploads (including an
   initial empty snapshot on a fresh install).
5. Apply pending migrations; initialize only roles/permissions on the first install.
6. Start the API, frontend, queue, scheduler, and Caddy.
7. Check Laravel database/cache access and both public HTTPS endpoints.
8. Update `/srv/clyvero/current` only after success; retain the previous release.

There is a maintenance interruption during upgrades while backups/migrations and
container replacement run. This is a single-server deployment, not zero downtime.
Deployments are serialized; a newer merge does not cancel a running migration.
GitHub may replace an older pending run with the newest one if several commits
arrive while a run is active; the newest successful `main` revision is the target.

## 9. Verify the first release and create your owner account

**VPS, deploy**, after the workflow succeeds:

```bash
readlink -f /srv/clyvero/current
bash /srv/clyvero/current/scripts/vps-compose.sh ps
bash /srv/clyvero/current/scripts/vps-compose.sh exec -T app php artisan migrate:status
bash /srv/clyvero/current/scripts/vps-compose.sh logs --tail=100 app queue scheduler caddy
API_DOMAIN="$(sed -n 's/^API_DOMAIN=//p' /srv/clyvero/shared/production.env)"
APP_DOMAIN="$(sed -n 's/^APP_DOMAIN=//p' /srv/clyvero/shared/production.env)"
curl --fail --show-error "https://${API_DOMAIN}/up"
curl --fail --show-error -I "https://${APP_DOMAIN}/login"
bash /srv/clyvero/current/scripts/vps-compose.sh exec app php artisan clyvero:platform-admin
```

The final command is interactive: enter your real email, name, and a strong
password when prompted. The password is hidden. Do not use the default demo
seeders: `DatabaseSeeder` creates demonstration school/admin records. The pipeline
uses only `RoleSeeder` and `PermissionSeeder` once.

Open `https://YOUR_APP_HOSTNAME/owner/login`, sign in, and verify receipt of the OTP.
Use the platform dashboard to provision your school and its administrator.
Regular school users sign in at `/login`. Test password reset, school creation,
student import, image upload/view, PDF generation, and at least one school login
before admitting users. `/up` and container health alone do not test SMTP or all
business operations. Owner creation is the one-time interactive step after deploy;
the pipeline never stores an owner password.

The uploaded-media directory is persisted, but the current application intentionally
serves media through expiring signed URLs. Do not manually add a `public/storage`
symlink or expose the upload volume through Caddy.

## 10. Backups and routine operations

The new deployment uses the **clyvero-vps** Compose project and its named volumes.
Use `scripts/vps-compose.sh`, `scripts/backup-vps.sh`, and this guide. The older
`backend/compose.production.yml`, `backup-production.sh`, and
`restore-production.sh` belong to a different installation layout.

**VPS, deploy** — make an online backup now:

```bash
flock -w 600 /srv/clyvero/.deploy.lock bash /srv/clyvero/current/scripts/backup-vps.sh
ls -lt /srv/clyvero/backups
crontab -e
```

Add this single cron line for 02:15 server time daily (Africa/Douala in step 2):

```cron
15 2 * * * /usr/bin/flock -w 600 /srv/clyvero/.deploy.lock /bin/bash /srv/clyvero/current/scripts/backup-vps.sh >> /srv/clyvero/backups/backup.log 2>&1
```

Backups include a PostgreSQL custom-format dump, the full `storage/app` volume,
checksums, and the release SHA. Database dumps are transactionally consistent;
online upload archives can change during copying. Pre-upgrade backups are taken
with application processes stopped. For a matching database/upload recovery point
on demand, stop `caddy frontend scheduler queue app`, run the backup under the
lock, and start those services again.

**LOCAL POWERSHELL** — copy a selected backup off the VPS (replace the timestamp
directory with one printed by `ls`):

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\ClyveroBackups"
scp -r -i "$env:USERPROFILE\.ssh\clyvero-admin" deploy@YOUR_VPS_IP:/srv/clyvero/backups/BACKUP_DIRECTORY "$env:USERPROFILE\ClyveroBackups\"
```

Local VPS backups are not protection from VPS loss. Keep these copies in encrypted
off-site storage, and keep `production.env` separately in encrypted storage. The
off-site destination and its credentials remain your choice; this setup does not
silently configure an external backup account. Rehearse a restore on another VPS
before relying on backups. Monitor disk space; backups and previous image tags are
retained until you explicitly remove them after confirming off-site copies.

Useful **VPS, deploy** commands:

```bash
bash /srv/clyvero/current/scripts/vps-compose.sh ps
bash /srv/clyvero/current/scripts/vps-compose.sh logs --tail=100 -f app queue caddy
bash /srv/clyvero/current/scripts/vps-compose.sh exec -T app php artisan queue:failed
bash /srv/clyvero/current/scripts/vps-compose.sh exec -T app php artisan schedule:list
docker stats --no-stream
docker system df
df -h
tail -50 /srv/clyvero/backups/backup.log
```

The scheduler has no business schedules registered yet in `backend/routes/console.php`;
an empty `schedule:list` is expected until tasks are added. Services restart after
server reboot through Docker's restart policies. Container logs rotate at 10 MB
with five files per service. Monitor external uptime, failed jobs, backup success,
and disk usage through your chosen monitoring system.

Never run `docker compose down -v`, `docker volume prune`, `migrate:fresh`, or
`migrate:refresh` against production. They can destroy persistent data.

For an environment-only edit, edit the shared file, then force recreation so all
containers receive the new values and rebuild their Laravel config cache:

```bash
nano /srv/clyvero/shared/production.env
flock -w 600 /srv/clyvero/.deploy.lock bash /srv/clyvero/current/scripts/vps-compose.sh up -d --force-recreate --wait app queue scheduler caddy
```

Do not rotate `APP_KEY` casually. Changing DB passwords in the file does not change
the password in an existing PostgreSQL data volume; database credential rotation
requires a coordinated database operation. Domain changes also need a frontend
rebuild, as covered next.

## 11. Failed deployment and recovery

If checks or image builds fail, production is not touched. If deployment fails
after the old services stop, it stays failed and may leave the site offline.
There is no automatic database rollback: migrations may have changed data, and
blindly starting older code or reversing migrations could make that worse.

Find the full 40-character commit SHA in the failed GitHub run. **VPS, deploy:**

```bash
FAILED_SHA='PASTE_FULL_40_CHARACTER_COMMIT_SHA'
export CLYVERO_RELEASE_DIR="/srv/clyvero/releases/${FAILED_SHA}"
bash "${CLYVERO_RELEASE_DIR}/scripts/vps-compose.sh" ps
bash "${CLYVERO_RELEASE_DIR}/scripts/vps-compose.sh" logs --tail=150 app queue scheduler caddy
bash "${CLYVERO_RELEASE_DIR}/scripts/vps-compose.sh" run --rm --no-deps -T -e AUTORUN_ENABLED=false app php artisan migrate:status
unset CLYVERO_RELEASE_DIR
```

Correct the issue and use GitHub **Re-run all jobs**. A migration that already
succeeded is not repeated. For a code issue, prefer merging a corrective commit.
Re-running all jobs is needed after a domain variable changes so the frontend is
rebuilt. To trigger a fresh deployment without a code change, use Actions →
CI and VPS deployment → Run workflow → select `main`.

If you have verified that the current schema is compatible with the old code,
you can start the last known successful release. After a failed deployment,
`current` still points to that release. After a successful deployment you want to
undo, `previous` points to the preceding release. Choose deliberately:

```bash
RECOVERY_RELEASE="$(readlink -f /srv/clyvero/current)"
# For a rollback after a successful deployment, use this instead:
# RECOVERY_RELEASE="$(readlink -f /srv/clyvero/previous)"
test -f "${RECOVERY_RELEASE}/.release.env"
export CLYVERO_RELEASE_DIR="$RECOVERY_RELEASE"
flock -w 600 /srv/clyvero/.deploy.lock bash "${RECOVERY_RELEASE}/scripts/vps-compose.sh" up -d --force-recreate --wait
unset CLYVERO_RELEASE_DIR
```

Check both public URLs before marking recovery successful. If you chose `previous`,
update the pointer after validation:

```bash
ln -sfn "$RECOVERY_RELEASE" /srv/clyvero/current-next
mv -Tf /srv/clyvero/current-next /srv/clyvero/current
```

For an incompatible migration, restore the matching pre-upgrade backup. **This
replaces database contents and uploads**, losing writes since that backup. Pause
merges/manual deployments, use the correct backup/release, and perform this only
as a deliberate recovery operation. The following is one continuous **VPS,
deploy** shell session; take a safety backup first:

```bash
set -Eeuo pipefail
BACKUP='/srv/clyvero/backups/CHOSEN_BACKUP_DIRECTORY'
test -s "$BACKUP/database.dump"
test -s "$BACKUP/uploads.tar.gz"
(cd "$BACKUP" && sha256sum -c SHA256SUMS)
RESTORE_SHA="$(sed -n 's/^IMAGE_TAG=//p' "$BACKUP/release.env")"
export CLYVERO_RELEASE_DIR="/srv/clyvero/releases/$RESTORE_SHA"
test -f "$CLYVERO_RELEASE_DIR/compose.vps.yml"
docker image inspect "clyvero-backend:$RESTORE_SHA" "clyvero-frontend:$RESTORE_SHA" > /dev/null
exec 9>/srv/clyvero/.deploy.lock
flock -w 600 9
COMPOSE=(bash "$CLYVERO_RELEASE_DIR/scripts/vps-compose.sh")
"${COMPOSE[@]}" stop caddy frontend scheduler queue app
bash "$CLYVERO_RELEASE_DIR/scripts/backup-vps.sh"
read -r -p 'Type RESTORE clyvero to replace the production database and uploads: ' CONFIRM
```

Continue only when you intentionally entered `RESTORE clyvero`:

```bash
if [[ "$CONFIRM" == 'RESTORE clyvero' ]]; then
  (
  set -Eeuo pipefail
  "${COMPOSE[@]}" exec -T postgres sh -ec 'test "$POSTGRES_DB" = clyvero; dropdb --force --if-exists -U "$POSTGRES_USER" "$POSTGRES_DB"; createdb -U "$POSTGRES_USER" -O "$POSTGRES_USER" "$POSTGRES_DB"'
  "${COMPOSE[@]}" exec -T postgres sh -ec 'exec pg_restore --exit-on-error --no-owner --no-privileges -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < "$BACKUP/database.dump"
  "${COMPOSE[@]}" run --rm --no-deps -T --user root --entrypoint sh app -ec 'find /var/www/html/storage/app -mindepth 1 -delete; tar -C /var/www/html/storage -xzf -; chown -R www-data:www-data /var/www/html/storage/app' < "$BACKUP/uploads.tar.gz"
  "${COMPOSE[@]}" exec -T redis sh -ec 'REDISCLI_AUTH="$REDIS_PASSWORD" redis-cli FLUSHALL'
  "${COMPOSE[@]}" up -d --force-recreate --wait
  )
fi
flock -u 9
exec 9>&-
unset CLYVERO_RELEASE_DIR
```

Redis is dedicated to this application; clearing it discards queued work and old
sessions/cache after restore. If any command fails, stop and inspect it before
continuing. Verify the recovered website, set `current` to the recovered release
as shown above, and only then resume deployments. Keep `APP_KEY` from that
installation; a backup restore onto a replacement VPS also needs that environment
file, the matching source release, and the retained image tags.

If you decline the restore confirmation, the application remains stopped. Use
the compatible-release recovery procedure above when you are ready to reopen it.

## 12. Add a purchased domain later

Example: you buy `myschool.com`. In its DNS dashboard create:

| Type | Name | Value |
| --- | --- | --- |
| A | `app` | Your VPS public IPv4 |
| A | `api` | Your VPS public IPv4 |

Start with DNS-only records if your DNS provider offers a proxy. Do not add an
AAAA record unless IPv6 actually reaches this VPS. Do not change IPs or volumes.

**VPS, deploy** — replace the example names:

```bash
NEW_APP_DOMAIN='app.myschool.com'
NEW_API_DOMAIN='api.myschool.com'
dig +short "$NEW_APP_DOMAIN" A
dig +short "$NEW_API_DOMAIN" A
```

Wait until both resolve to your VPS. Then pause merges briefly and update the
server file:

```bash
cp /srv/clyvero/shared/production.env /srv/clyvero/shared/production.env.before-domain-change
chmod 600 /srv/clyvero/shared/production.env.before-domain-change
sed -i \
  -e "s|^APP_DOMAIN=.*|APP_DOMAIN=${NEW_APP_DOMAIN}|" \
  -e "s|^API_DOMAIN=.*|API_DOMAIN=${NEW_API_DOMAIN}|" \
  -e "s|^APP_URL=.*|APP_URL=https://${NEW_API_DOMAIN}|" \
  -e "s|^FRONTEND_URL=.*|FRONTEND_URL=https://${NEW_APP_DOMAIN}|" \
  -e "s|^CORS_ALLOWED_ORIGINS=.*|CORS_ALLOWED_ORIGINS=https://${NEW_APP_DOMAIN}|" \
  -e "s|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=${NEW_APP_DOMAIN}|" \
  /srv/clyvero/shared/production.env
```

In GitHub repository variables, change **API_DOMAIN** to `api.myschool.com`.
Leave `VPS_HOST`, SSH secrets, `APP_KEY`, and database/Redis credentials unchanged.
Run the workflow manually on `main`, or merge a new commit. Next.js embeds the API
URL during its build: restarting old containers alone will not switch that URL.

Caddy obtains/renews certificates for the new names automatically; see
[Caddy's HTTPS behavior](https://caddyserver.com/docs/automatic-https).
After the run succeeds:

```bash
curl --fail --show-error "https://${NEW_API_DOMAIN}/up"
curl --fail --show-error -I "https://${NEW_APP_DOMAIN}/login"
```

Sign in at the new address and test email/reset links and uploaded media. Users
need to sign in on the new origin; saved links to the temporary hostnames may no
longer work. The old temporary hostnames are not automatically redirected by this
configuration. Your PostgreSQL and upload volumes remain the same.

## Troubleshooting reference

| Symptom | Check/action |
| --- | --- |
| `Set ... repository variable/secret` | Exact names in step 7; use repository settings, not an unused GitHub Environment |
| `Permission denied (publickey)` | VPS_USER, matching public/private key pair, file permissions, empty automation-key passphrase |
| `Host key verification failed` | Use the complete host-key line from the VPS console, starting `clyvero-vps`; after a VPS rebuild verify its new fingerprint and update the secret |
| SSH timeout | IP/port, provider firewall, UFW, SSH service; GitHub runners must reach SSH |
| Docker socket permission denied | `usermod -aG docker deploy`, then reconnect; ensure Docker is running |
| Architecture check fails | This workflow targets an x86_64 VPS; use amd64 hardware or deliberately add an ARM image build |
| Certificate/HTTPS failure | DNS must resolve to this IP, TCP 80/443 must be public, no conflicting web server, inspect Caddy logs |
| sslip.io certificate rate limit | Try equivalent `.nip.io` names in the VPS file and GitHub API_DOMAIN, then rebuild; or use your purchased domain. Do not bypass TLS verification |
| Browser uses the wrong API/CORS error | GitHub API_DOMAIN and VPS API_DOMAIN/APP_URL must match; FRONTEND_URL/CORS_ALLOWED_ORIGINS must match the browser origin; rebuild after changes |
| Login OTP/reset email absent | SMTP credentials/sender authorization, spam folder, provider outbound-port restrictions; inspect app/queue logs and queue:failed |
| Blank database/no owner account | Check migrate:status and initial role seeding, then run clyvero:platform-admin; never demo-seed production |
| Image transfer or backup runs out of disk | `df -h` and `docker system df`; copy backups off-site and deliberately remove obsolete files/tags while keeping current/previous |
| Backend/frontend tests or audits fail | Open the failing GitHub step and fix the reported issue before deployment; quality gates are intentionally not bypassed |

Local validation can check configuration and frontend code, but a successful
first GitHub run plus the live checks in step 9 are required to verify Linux image
builds, VPS access, DNS, certificate issuance, SMTP, and the live database together.
