# Clyvero VPS deployment: follow these steps in order

This is the start-to-finish walkthrough for someone setting up the server for the
first time. Do not merge the deployment pull request until you finish steps 1
through 9. The first merge to `main` in step 10 will start the first deployment.

You do not need to buy a domain yet. This guide creates temporary HTTPS addresses
from your VPS IP address. For example, if the server IP is `12.34.56.78`, the
addresses will be:

```text
https://app.12-34-56-78.sslip.io
https://api.12-34-56-78.sslip.io
```

The first address is the website. The second is the Laravel API. Later, you can
replace both addresses with a domain you buy without deleting the database.

## Before you begin

You will work in four places. Every command block in this guide identifies its
location:

| Label | Where to run it |
| --- | --- |
| **WINDOWS PC** | PowerShell on the computer containing `C:\project\clyvero` |
| **VPS ROOT** | The SSH window logged in as `root`, or after running `sudo -i` |
| **VPS DEPLOY** | The SSH window logged in as the new `deploy` user |
| **GITHUB WEBSITE** | A page in your web browser on github.com |

Have these values ready:

| Placeholder | What it means | Example only |
| --- | --- | --- |
| `YOUR_VPS_IP` | Public IPv4 shown by your VPS provider | `12.34.56.78` |
| `YOUR_EMAIL` | Email used for HTTPS certificate notices | `you@gmail.com` |
| SMTP settings | Mail server, port, username, password and sender | Supplied by your email provider |

Whenever a command contains `YOUR_VPS_IP` or `YOUR_EMAIL`, replace it with your
real value. Do not type the placeholder literally. Do not copy the `$` prompt
character from examples; copy only the command.

The recommended server is Ubuntu 24.04 LTS, x86_64/amd64, with at least 2 vCPU,
4 GB RAM, 60 GB storage and a public IPv4 address. GitHub will build the images,
so PHP, Node.js, PostgreSQL and Redis do not need to be installed directly on the
VPS. They run inside Docker containers.

## Step 1 — Create your personal SSH key on Windows

An SSH key lets your computer log in to the VPS securely. This first key is for
you, the administrator. GitHub Actions will get a different key later.

Open PowerShell on your **WINDOWS PC** and run:

```powershell
Test-Path "$env:USERPROFILE\.ssh\clyvero-admin"
```

If it prints `False`, create the key:

```powershell
ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\clyvero-admin" -C "clyvero administrator"
```

It asks for a passphrase twice. Choose a passphrase you can remember. The command
creates two files:

- `clyvero-admin` is the private key. Never send or upload it.
- `clyvero-admin.pub` is the public key. It is safe to place on the VPS.

If `Test-Path` printed `True`, do not overwrite the existing key.

Display the public key:

```powershell
Get-Content "$env:USERPROFILE\.ssh\clyvero-admin.pub"
```

Keep this PowerShell window open. You will copy that one-line result in step 3.

## Step 2 — Log in to the new VPS and update Ubuntu

Your VPS provider will give you either a `root` login or an `ubuntu` login.

On your **WINDOWS PC**, use one of these commands:

```powershell
ssh root@YOUR_VPS_IP
```

Or, if the provider says the initial username is `ubuntu`:

```powershell
ssh ubuntu@YOUR_VPS_IP
```

The first connection shows a host fingerprint. Compare it with the fingerprint
shown in your VPS provider's control panel or web console. Type `yes` only when it
matches. Enter the password or use the key supplied by your provider.

If you logged in as `ubuntu`, become root now:

```bash
sudo -i
```

The prompt should now end in `#`. Run the next commands in this same **VPS ROOT**
window:

```bash
cat /etc/os-release
uname -m
apt-get update
apt-get upgrade -y
apt-get install -y ca-certificates curl openssl openssh-server openssh-client ufw dnsutils nano cron util-linux
timedatectl set-timezone Africa/Douala
systemctl enable --now ssh cron
```

What these commands do:

- `cat /etc/os-release` identifies Ubuntu. Confirm it says Ubuntu 24.04.
- `uname -m` identifies the processor. Confirm it prints `x86_64`.
- `apt-get update` refreshes the package list.
- `apt-get upgrade` installs security and operating-system updates.
- `apt-get install` installs SSH, firewall, DNS, editing and backup tools.
- The last two commands set Cameroon time and ensure SSH/cron start after reboot.

If Ubuntu says a reboot is required, run:

```bash
reboot
```

Your SSH session will close. Wait about one minute, reconnect using the beginning
of this step, and become root again before continuing.

Checkpoint: continue only if `uname -m` printed `x86_64` and the commands completed
without an unresolved error.

## Step 3 — Create the deployment user and configure the firewall

The application and GitHub Actions will use an account named `deploy`. This keeps
routine deployment access separate from the initial root account.

In the **VPS ROOT** window, run:

```bash
adduser --disabled-password --gecos '' deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
nano /home/deploy/.ssh/authorized_keys
```

Nano opens an empty file. Return to the PowerShell window from step 1, copy the
complete `ssh-ed25519 ... clyvero administrator` public-key line, and paste it into
nano. It must remain one line.

Save and close nano:

1. Press `Ctrl+O`.
2. Press `Enter` to confirm the filename.
3. Press `Ctrl+X`.

Back in the **VPS ROOT** window, apply secure permissions and firewall rules:

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
ufw status verbose
```

Type `y` if `ufw enable` asks for confirmation. These rules allow SSH, normal web
traffic, HTTPS, and optional HTTP/3. They block unsolicited access to other host
ports.

Also open your VPS provider's browser-based firewall/security-group page. Allow:

- TCP port 22 from the internet. GitHub-hosted runners need to reach this port.
- TCP port 80 from the internet.
- TCP port 443 from the internet.
- UDP port 443 from the internet if you want HTTP/3; this one is optional.

If your provider uses a different SSH port, allow that port instead of 22 and use
the same number for `VPS_PORT` in step 8. Do not enable UFW until the correct SSH
port has been allowed.

Checkpoint: leave the root window open. Open a second PowerShell window on your
**WINDOWS PC** and test the new account:

```powershell
ssh -i "$env:USERPROFILE\.ssh\clyvero-admin" -o IdentitiesOnly=yes deploy@YOUR_VPS_IP
```

Success means the prompt changes to something like `deploy@server:~$`. Keep this
new deploy session open. If login fails, fix the public key or permissions from
the root window before continuing.

## Step 4 — Install Docker on the VPS

Docker runs the seven Clyvero services: frontend, API, queue worker, scheduler,
PostgreSQL, Redis and Caddy. Use Docker's official Ubuntu repository.

Return to the **VPS ROOT** window and run this whole block:

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

The repository file is created using Ubuntu's detected codename and architecture.
The `hello-world` command downloads a small test image. Success includes the text
`Hello from Docker!`. The final commands allow `deploy` to operate Docker and
create Clyvero's persistent server directories.

Adding a user to the Docker group does not affect an already-open SSH session.
Close the **VPS DEPLOY** session created in step 3:

```bash
exit
```

Reconnect from your **WINDOWS PC**:

```powershell
ssh -i "$env:USERPROFILE\.ssh\clyvero-admin" -o IdentitiesOnly=yes deploy@YOUR_VPS_IP
```

In that new **VPS DEPLOY** session, verify Docker access:

```bash
id
docker info > /dev/null && echo 'Docker access is working'
docker compose version
```

Checkpoint: continue only when you see `Docker access is working`, `id` includes
the `docker` group, and Compose prints a version.

## Step 5 — Create temporary domain names from the VPS IP

The application requires HTTPS. `sslip.io` provides DNS names that automatically
resolve to an IP embedded in the name, so you can obtain HTTPS before buying a
domain.

In the **VPS DEPLOY** session, replace the two placeholders and run:

```bash
VPS_IP='YOUR_VPS_IP'
CONTACT_EMAIL='YOUR_EMAIL'
IP_LABEL="${VPS_IP//./-}"
APP_DOMAIN="app.${IP_LABEL}.sslip.io"
API_DOMAIN="api.${IP_LABEL}.sslip.io"
printf 'Website: https://%s\nAPI: https://%s\n' "$APP_DOMAIN" "$API_DOMAIN"
dig +short "$APP_DOMAIN" A
dig +short "$API_DOMAIN" A
```

Example: with IP `12.34.56.78`, `IP_LABEL` becomes `12-34-56-78`.

The last two commands must each print your VPS IP. Write down the two hostnames
printed after `Website:` and `API:`. You need them in steps 6 and 8.

Keep this SSH window open. The variables exist only in this shell and are used by
the commands in step 6.

Checkpoint: do not continue until both `dig` commands return your VPS IP.

## Step 6 — Create the secret production configuration

The file `/srv/clyvero/shared/production.env` contains database passwords,
Laravel's encryption key, URLs and email credentials. It stays on the VPS and is
never committed to GitHub.

First, copy the prepared template. Open a separate **WINDOWS PC** PowerShell window:

```powershell
scp -i "$env:USERPROFILE\.ssh\clyvero-admin" -o IdentitiesOnly=yes "C:\project\clyvero\deploy\production.env.example" deploy@YOUR_VPS_IP:/srv/clyvero/shared/production.env
```

This should finish with `100%`. Run it only during the initial setup. Running it
again later would overwrite production settings with placeholders.

Return to the **VPS DEPLOY** window from step 5. Generate three independent secrets
and replace the template URLs:

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

Inside nano, locate these lines and replace every `REPLACE_...` value with the
settings from your email provider:

```text
MAIL_HOST=REPLACE_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=REPLACE_SMTP_USERNAME
MAIL_PASSWORD='REPLACE_SMTP_PASSWORD'
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=REPLACE_SENDER_EMAIL
```

Why email is required: platform-owner login uses a one-time code, and password
reset also sends mail. Use an SMTP provider account that permits sending from
`MAIL_FROM_ADDRESS`. For Gmail, this generally means an app password rather than
your normal Google password. If the SMTP password contains `$`, `#`, spaces or
other punctuation, keep the single quotes around it.

Do not change these security settings:

```text
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
ADMIN_LOGIN_OTP_ENABLED=true
REDIS_QUEUE_RETRY_AFTER=180
```

Save with `Ctrl+O`, Enter, then close with `Ctrl+X`.

Check the file without displaying its secrets:

```bash
if grep -Eq 'REPLACE_|YOUR_|yourdomain\.com|example\.com' /srv/clyvero/shared/production.env; then
  echo 'STOP: production.env still contains a placeholder'
else
  echo 'Production configuration is ready'
fi
stat -c '%A %U:%G %n' /srv/clyvero/shared/production.env
```

Success means you see `Production configuration is ready`, and `stat` begins with
`-rw------- deploy:deploy`.

Do not run the next command because it would reveal every secret:
`cat /srv/clyvero/shared/production.env`.

Store an encrypted backup of this file in a password manager. The Laravel
`APP_KEY` must survive a server replacement, or existing encrypted data may become
unreadable.

## Step 7 — Give GitHub Actions its own SSH key

GitHub Actions needs to connect to the VPS after tests pass. Create a second key
with no passphrase because an automated workflow cannot type one.

On your **WINDOWS PC**, check whether it already exists:

```powershell
Test-Path "$env:USERPROFILE\.ssh\clyvero-actions"
```

If it prints `False`, run:

```powershell
ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\clyvero-actions" -C "github-actions clyvero"
```

Press Enter at both passphrase prompts to leave this automation key without a
passphrase. If `Test-Path` printed `True`, keep the existing pair and do not
overwrite it.

Display only its public key:

```powershell
Get-Content "$env:USERPROFILE\.ssh\clyvero-actions.pub"
```

In the **VPS DEPLOY** session, open the authorized-key file:

```bash
nano /home/deploy/.ssh/authorized_keys
```

Do not remove the personal key from step 3. Add a new second line consisting of
the word `restrict`, one space, and the complete GitHub Actions public key. It
should look like this:

```text
restrict ssh-ed25519 AAAAC3... github-actions clyvero
```

The word `restrict` disables SSH forwarding and other features that the workflow
does not need. It still permits the deployment commands. Save and exit nano, then
run:

```bash
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys
```

Test the automation key from your **WINDOWS PC**:

```powershell
ssh -i "$env:USERPROFILE\.ssh\clyvero-actions" -o IdentitiesOnly=yes deploy@YOUR_VPS_IP "docker info > /dev/null && echo GitHub-key-works"
```

Checkpoint: continue only when it prints `GitHub-key-works` without asking for an
SSH key passphrase.

## Step 8 — Add four GitHub variables and two GitHub secrets

The local Git remote already points to:

```text
https://github.com/Yemel-art/CLYVERO-ERP.git
```

You do not need to clone the repository onto the VPS. The workflow transfers the
tested application images and deployment files to the server.

First obtain the VPS's real SSH host key. Use the VPS provider's browser console,
or your still-open **VPS ROOT** session, and run:

```bash
awk '{print "clyvero-vps " $1 " " $2}' /etc/ssh/ssh_host_ed25519_key.pub
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

The first command prints a line beginning with `clyvero-vps ssh-ed25519`. Save the
whole first line. The second command prints a fingerprint you can compare with
the one seen when you first connected. Do not use `ssh-keyscan` from an unverified
network as your source of trust.

Now use the **GITHUB WEBSITE**:

1. Open <https://github.com/Yemel-art/CLYVERO-ERP/settings/secrets/actions>.
2. Select the **Variables** tab.
3. Click **New repository variable** four times and create these exact names:

| Variable name | Value to enter |
| --- | --- |
| `VPS_HOST` | Your VPS IP, such as `12.34.56.78` |
| `VPS_USER` | `deploy` |
| `VPS_PORT` | `22`, unless your SSH server uses a different port |
| `API_DOMAIN` | The API hostname from step 5, such as `api.12-34-56-78.sslip.io` |

Enter only the hostname for `API_DOMAIN`: no `https://`, no trailing slash, and
no `/api/v1`.

Then select the **Secrets** tab and create these exact repository secrets:

| Secret name | Value to enter |
| --- | --- |
| `VPS_SSH_KEY` | Complete private `clyvero-actions` key, including BEGIN and END lines |
| `VPS_KNOWN_HOSTS` | Complete `clyvero-vps ssh-ed25519 ...` line printed above |

To copy the private Actions key on your **WINDOWS PC**:

```powershell
Get-Content -Raw "$env:USERPROFILE\.ssh\clyvero-actions" | Set-Clipboard
```

Paste it into the `VPS_SSH_KEY` value and save. Clear your clipboard immediately:

```powershell
Set-Clipboard -Value ''
```

Do not paste the `.pub` public key into `VPS_SSH_KEY`; GitHub needs the private
key. Do not paste the private key anywhere except GitHub's encrypted secret field.

Checkpoint: the GitHub page should list four variable names and two secret names.
GitHub will not show secret values again; that is normal.

## Step 9 — Confirm GitHub Actions is allowed

On the **GITHUB WEBSITE**:

1. Open <https://github.com/Yemel-art/CLYVERO-ERP/settings/actions>.
2. Under **Actions permissions**, enable Actions for the repository.
3. Allow the actions used by the workflow: `actions/checkout`,
   `actions/setup-node`, and `shivammathur/setup-php`.
4. Save the setting.

The workflow only requests read access to repository contents. A pull request runs
tests but does not deploy. A push or merged pull request to `main` runs tests and,
only after they all pass, deploys to the VPS.

Checkpoint: steps 1–9 are the entire server/GitHub preparation. Do not merge until
all their checkpoints pass.

## Step 10 — Commit the deployment files with Git Bash and merge the pull request

The project worktree currently contains unrelated staged files and generated
uploads. The `git restore --staged -- .` command below removes everything from
Git's staging area without deleting any working file. Then the explicit `git add`
command stages only the deployment files.

On your **WINDOWS PC**, close other Git tools that may be editing this repository,
then open **Git Bash**. Git Bash normally opens in your Windows home directory.
Move to the project and verify the location:

```bash
cd /c/project/clyvero
pwd
git status
```

`pwd` must print `/c/project/clyvero`. Do not continue if it prints another
directory.

At the time this guide was prepared, Git reported unmerged paths for Dockerfiles
and unrelated files staged by another operation. If `git status` still contains
an **Unmerged paths** section, stop here and finish or deliberately abort that
existing merge/rebase operation first. Do not choose a side blindly: those changes
may belong to other work. The deployment commit cannot be created from an
unresolved index.

For this repository, there is no active merge or rebase. Clear the current index
before creating the deployment branch:

```bash
git restore --staged -- .
git status --short
```

This command changes only Git's staging area. It does not delete, overwrite, or
discard the files in the project directory. The `DU` entries should disappear,
while modified and untracked working files remain visible.

If `git restore --staged -- .` reports that the three Docker files are unmerged,
reset the index entries to `HEAD` and try again:

```bash
git reset HEAD -- backend/.dockerignore backend/Dockerfile.production frontend/.dockerignore
git restore --staged -- .
git status --short
```

This is an index-only reset. It preserves the contents of all three working files.
Do not add `--hard` to either command.

If `git restore --staged -- .` reports that the three Docker files are unmerged,
reset the index entries to `HEAD` and try again:

```bash
git reset HEAD -- backend/.dockerignore backend/Dockerfile.production frontend/.dockerignore
git restore --staged -- .
git status --short
```

This is an index-only reset. It preserves the contents of all three working files.
Do not add `--hard` to either command.

Create the deployment branch:

```bash
git switch -c codex/ubuntu-vps-cicd
```

If Git says the branch already exists, run this instead:

```bash
git switch codex/ubuntu-vps-cicd
```

Stage only the deployment files. Copy this complete block into Git Bash:

```bash
git add .gitattributes .github/workflows/ci.yml .gitignore DEPLOYMENT.md compose.vps.yml deploy/Caddyfile deploy/production.env.example docs/UBUNTU_VPS_GITHUB_ACTIONS.md docs/VPS_SETUP_WALKTHROUGH.md scripts/backup-vps.sh scripts/deploy-vps.sh scripts/vps-compose.sh backend/.dockerignore backend/Dockerfile.production frontend/.dockerignore frontend/public/.gitkeep
git diff --cached --name-only
git diff --cached --stat
```

The `--name-only` output should contain exactly these deployment files:

```text
.gitattributes
.github/workflows/ci.yml
.gitignore
DEPLOYMENT.md
backend/.dockerignore
backend/Dockerfile.production
compose.vps.yml
deploy/Caddyfile
deploy/production.env.example
docs/UBUNTU_VPS_GITHUB_ACTIONS.md
docs/VPS_SETUP_WALKTHROUGH.md
frontend/.dockerignore
frontend/public/.gitkeep
scripts/backup-vps.sh
scripts/deploy-vps.sh
scripts/vps-compose.sh
```

The order may differ. The list must not contain any of these:

- `.env`, `.env.before-*`, passwords, tokens or private keys
- files inside `.codex-backups`
- uploaded student, teacher or school images
- generated `node_modules`, `.next`, `vendor`, `tsbuildinfo` or testing files

Do not run `git add .` or `git add -A`. Those commands would stage unrelated
environment backups and uploaded images currently present in the worktree.

Run these checks in Git Bash:

```bash
git diff --cached --check
git diff --cached
```

The first command should print nothing. The second shows the full changes; scroll
through them and confirm there are no passwords, SMTP credentials, SSH private
keys or real production secrets. `deploy/production.env.example` must contain only
`REPLACE_...` placeholders.

If the staged diff contains only the deployment work, commit and push:

```bash
git commit -m "Add Ubuntu VPS deployment with GitHub Actions"
git push -u origin codex/ubuntu-vps-cicd
```

If Git asks you to configure your identity, replace the example values with your
real name and the email used by your GitHub account, then retry the commit:

```bash
git config --global user.name "Your Real Name"
git config --global user.email "your-github-email@example.com"
git commit -m "Add Ubuntu VPS deployment with GitHub Actions"
```

If the push asks for authentication or rejects the stored login, run this in Git
Bash, choose `GitHub.com`, `HTTPS`, and browser authentication, then retry:

```bash
gh auth login
git push -u origin codex/ubuntu-vps-cicd
```

On the **GITHUB WEBSITE**:

1. Open <https://github.com/Yemel-art/CLYVERO-ERP/compare>.
2. Set base branch to `main` and compare branch to `codex/ubuntu-vps-cicd`.
3. Create the pull request.
4. Wait for every check to pass.
5. Merge the pull request into `main`.

The merge creates the first deployment automatically. Do not manually run Docker
Compose before this. The workflow will build the images, copy them to the server,
create the database tables, initialize roles/permissions, start all services and
check both public HTTPS addresses.

## Step 11 — Watch the first deployment

On the **GITHUB WEBSITE**, open:

<https://github.com/Yemel-art/CLYVERO-ERP/actions>

Open the workflow named **CI and VPS deployment** for the merge commit. It has
three jobs:

1. `backend` installs PHP dependencies, audits them and runs Laravel tests.
2. `frontend` installs Node dependencies, audits, lints, type-checks, tests and builds.
3. `production-images` builds and smoke-tests production containers, then deploys.

The deployment happens only if all three jobs succeed. Image transfer may take
several minutes. Caddy also needs time to request the first HTTPS certificates.

If a step becomes red, click that step and read its final error lines. Do not keep
merging new commits until you know why it failed. The troubleshooting and recovery
sections in [the operations guide](UBUNTU_VPS_GITHUB_ACTIONS.md) cover common cases.

Checkpoint: the workflow must show a green check mark before continuing.

## Step 12 — Verify the live application and create the first owner

Connect from your **WINDOWS PC**:

```powershell
ssh -i "$env:USERPROFILE\.ssh\clyvero-admin" -o IdentitiesOnly=yes deploy@YOUR_VPS_IP
```

In the **VPS DEPLOY** session, inspect the release:

```bash
readlink -f /srv/clyvero/current
bash /srv/clyvero/current/scripts/vps-compose.sh ps
bash /srv/clyvero/current/scripts/vps-compose.sh exec -T app php artisan migrate:status
API_DOMAIN="$(sed -n 's/^API_DOMAIN=//p' /srv/clyvero/shared/production.env)"
APP_DOMAIN="$(sed -n 's/^APP_DOMAIN=//p' /srv/clyvero/shared/production.env)"
curl --fail --show-error "https://${API_DOMAIN}/up"
curl --fail --show-error -I "https://${APP_DOMAIN}/login"
```

What success looks like:

- `readlink` prints a release directory ending in a 40-character Git commit SHA.
- Compose shows the services running; `app`, `frontend`, `postgres` and `redis`
  should be healthy.
- Migrations show `Ran`.
- The API health request succeeds.
- The frontend request returns HTTP `200` or another normal successful response.

Now create the platform owner. This is intentionally interactive and is never
stored in GitHub Actions:

```bash
bash /srv/clyvero/current/scripts/vps-compose.sh exec app php artisan clyvero:platform-admin
```

Enter your real email, first name, last name and a strong password. Password input
is hidden. The command should finish with `Platform administrator is ready`.

Open this address in your browser, replacing the example with the website hostname
from step 5:

```text
https://app.12-34-56-78.sslip.io/owner/login
```

Sign in with the owner account and confirm the OTP arrives by email. From the owner
area, create/provision your first school and its administrator. Normal school users
use `/login`, while the platform owner uses `/owner/login`.

Checkpoint: deployment is complete only after you can sign in, receive OTP email,
and open the application in the browser.

## Step 13 — Enable daily backups

The deployment script automatically makes a backup before each upgrade. Add a
daily backup as well.

In the **VPS DEPLOY** session, first create and verify one backup manually:

```bash
flock -w 600 /srv/clyvero/.deploy.lock bash /srv/clyvero/current/scripts/backup-vps.sh
ls -lt /srv/clyvero/backups
```

You should see a new timestamped directory. Then open the deploy user's cron file:

```bash
crontab -e
```

If asked for an editor, choose nano. Add this one line at the bottom:

```cron
15 2 * * * /usr/bin/flock -w 600 /srv/clyvero/.deploy.lock /bin/bash /srv/clyvero/current/scripts/backup-vps.sh >> /srv/clyvero/backups/backup.log 2>&1
```

Save and exit. This runs a backup every day at 02:15 Africa/Douala time. Confirm
the entry:

```bash
crontab -l
```

These backups remain on the same VPS, so they do not protect you if the entire VPS
is lost. Periodically copy the newest timestamped directory to your Windows PC:

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\ClyveroBackups"
scp -r -i "$env:USERPROFILE\.ssh\clyvero-admin" -o IdentitiesOnly=yes deploy@YOUR_VPS_IP:/srv/clyvero/backups/TIMESTAMPED_DIRECTORY "$env:USERPROFILE\ClyveroBackups\"
```

Replace `TIMESTAMPED_DIRECTORY` with a real name shown by `ls -lt`. Store the copy
in encrypted off-site storage. Also store a protected copy of `production.env`.

## Step 14 — When you buy a real domain

Suppose you buy `myschool.com`. In the DNS control panel provided by the registrar,
create these records:

| Type | Name | Value |
| --- | --- | --- |
| A | `app` | Your VPS public IPv4 |
| A | `api` | Your VPS public IPv4 |

Wait until these commands, run in **VPS DEPLOY**, print your VPS IP:

```bash
dig +short app.myschool.com A
dig +short api.myschool.com A
```

Then follow step 12, **Add a purchased domain later**, in the
[operations guide](UBUNTU_VPS_GITHUB_ACTIONS.md). That procedure updates the VPS
URLs and the GitHub `API_DOMAIN` variable, then rebuilds the frontend. It preserves
the PostgreSQL and upload volumes.

## Important production rules

- Never run `docker compose down -v`; `-v` deletes persistent volumes.
- Never run `php artisan migrate:fresh` or `migrate:refresh` in production.
- Never commit `production.env`, a private SSH key, uploaded photos or backup files.
- Never regenerate `APP_KEY` during a routine deployment.
- Use `/srv/clyvero/current/scripts/vps-compose.sh` for service commands so Compose
  always uses the correct project, release and environment files.
- Do not create a public `storage` symlink manually. The application serves uploads
  through signed API URLs.

For logs, failed jobs, backups, rollback, restore and domain-change commands, use
the [complete operations guide](UBUNTU_VPS_GITHUB_ACTIONS.md).
