# Deploying to HostGator cPanel - MobLand

Deployment target: `myapp.algelowb.com` on HostGator shared hosting (server `gator2021.hostgator.com`).

## 1. SSH access

1. cPanel → **SSH Access** → **Manage SSH Keys** → **Generate a New Key**
   - Name: `laravel_deploy`
   - Set a passphrase (cPanel requires one — min length 5, strength ≥ 65)
   - Type: RSA, size 4096 → **Generate Key**
2. Under **Public Keys**, find the new key → **Manage** → **Authorize**
3. Under **Private Keys**, find the new key → **View/Download Key** → save locally, e.g. `~/.ssh/laravel_deploy`
4. Lock down permissions locally:
   ```
   chmod 600 ~/.ssh/laravel_deploy
   ```
5. Find SSH port (cPanel → SSH Access main page) — HostGator commonly uses `22`, not `22`.
6. Connect:
   ```
   ssh -p 22 -i ~/.ssh/laravel_deploy -o IdentitiesOnly=yes <cpanel_username>@gator2021.hostgator.com
   ```
   Confirm the cPanel username from the shell prompt after login (`user@host [~]#`) — it may differ from what you expect.

## 2. Install Composer (no root access needed)

```
cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
mkdir -p ~/bin
php composer-setup.php --install-dir=$HOME/bin --filename=composer
php -r "unlink('composer-setup.php');"
echo 'export PATH=$HOME/bin:$PATH' >> ~/.bash_profile
source ~/.bash_profile
composer -V
```

Note: PATH changes don't always persist across new SSH sessions on jailshell — if `composer` isn't found later, either re-source `~/.bash_profile` or call it directly: `php ~/bin/composer ...`.

## 3. Check PHP version compatibility

```
php -v
composer show --direct  # or check composer.json's "require.php"
```

If the app's `composer.lock` requires a newer PHP than the server's default CLI version, list available versions:
```
ls /opt/cpanel/ | grep -i php
```
Available here: `ea-php80` through `ea-php85`. Use the full binary path to run a specific version:
```
/opt/cpanel/ea-phpXX/root/usr/bin/php ...
```

## 4. Create the subdomain

1. cPanel → **Domains** → **Create A New Domain**
2. Domain: `myapp.algelowb.com`
3. **Document Root**: set explicitly to `myapp.algelowb.com/public` (must point at Laravel's `public/` folder, never the app root — otherwise `.env` and source become publicly browsable)
4. Submit

## 5. Set the correct PHP version for the domain

By default a new domain inherits the system default PHP version, which may be older than what the app requires.

1. cPanel → **MultiPHP Manager**
2. Select the domain checkbox → choose the required PHP version from the dropdown (e.g. `ea-php84`) → **Apply**

## 6. Create the database

1. cPanel → **MySQL Database Wizard**
2. Create database (cPanel prefixes the name, e.g. `wedo_laravel_app`)
3. Create a user with a strong password, attach with **ALL PRIVILEGES**
4. Record: database name, username, password

## 7. Clone the repo

cPanel pre-creates the document root folder (with placeholder files) when you create a domain — `git clone` will refuse to clone into a non-empty directory. Clone into a temp location instead, then move everything (including dotfiles) into the target:

```
cd ~
rm -rf myapp.algelowb.com    # only if it just has cPanel's placeholder content
mkdir myapp.algelowb.com
git clone https://github.com/oopsaleem/laravel-app.git /tmp/laravel-clone
shopt -s dotglob
mv /tmp/laravel-clone/* myapp.algelowb.com/
rmdir /tmp/laravel-clone
```

Verify `public/index.php` and `.htaccess` exist, and `.git/` is present at the app root.

## 8. Install PHP dependencies

```
# depends on the php version. e.g. ea-php84
cd ~/myapp.algelowb.com
/opt/cpanel/ea-php84/root/usr/bin/php ~/bin/composer install --no-dev --optimize-autoloader
```

## 9. Configure environment

```
cp .env.example .env
```

Edit `.env` (via `nano .env` or cPanel File Manager) and set:
```
APP_NAME="..."
APP_ENV=production
APP_DEBUG=false
APP_URL=https://myapp.algelowb.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=<prefixed_db_name>
DB_USERNAME=<prefixed_db_user>
DB_PASSWORD=<db_password>
```

## 10. Generate key and migrate

```
/opt/cpanel/ea-php84/root/usr/bin/php artisan key:generate
/opt/cpanel/ea-php84/root/usr/bin/php artisan migrate --force
```

## 11. Build and upload frontend assets

Shared hosting typically has no Node/npm (`which node npm` returns nothing). Build locally instead:

```
npm install
npm run build
```

This produces `public/build/`. Upload it via SFTP using the same SSH key and port:

```bash
sftp -P 22 -i ~/.ssh/laravel_deploy -o IdentitiesOnly=yes wedo@gator2021.hostgator.com
cd myapp.algelowb.com/public
put -r build
```
### 🔁 CI/CD deploy
1. Run npm run build.
2. Upload `public/build/`.
3. If you changed PHP, pull the changes on the server and run `php artisan config:clear`.  
## Gotchas hit during this deployment

- `git@github.com:...` SSH clone URLs need a deploy key on GitHub; for public repos, use the `https://` clone URL instead to skip that setup.
- `mv -f * ../` from inside a cloned subfolder does **not** merge into an existing same-named directory (e.g. `public/`) — it nests the source dir inside it (`public/public`). Also, bare `*` doesn't match dotfiles (`.git`, `.env.example`, etc.) unless `shopt -s dotglob` is set. Cloning into a temp dir and moving everything at once avoids both problems.
- `composer.lock` can require a newer PHP than `composer.json`'s stated constraint suggests — check the actual error from `composer install`, not just `composer.json`.
- New cPanel subdomains inherit the account's default PHP version; override it per-domain in **MultiPHP Manager**.
