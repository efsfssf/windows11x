#!/usr/bin/env bash
# Run on the target VPS as root. Contains no server or owner credentials.
set -Eeuo pipefail
umask 077
DOMAIN=dandomi.tech
TARGET_IP=155.212.217.163
BASE=/var/www/windows11x
WEB=$BASE/public_html
PRIVATE=$BASE/windows11x-private
REPO=https://github.com/efsfssf/windows11x.git
SOURCE_URL=${ATLAS_SOURCE_URL:-http://dandomi.tech/api/index.php?action=data}
[[ $EUID == 0 ]] || { echo 'Run as root'; exit 1; }
[[ -r /etc/debian_version ]] || { echo 'This installer requires Debian or Ubuntu. No changes made.'; exit 1; }
[[ -t 0 ]] || { echo 'Download this script, then run bash install.sh in an interactive SSH terminal.'; exit 1; }
[[ ! -e $BASE && ! -e /etc/nginx/sites-enabled/windows11x && ! -e /etc/nginx/sites-available/windows11x ]] || { echo 'An installation already exists. Stopping to protect existing data.'; exit 1; }
if command -v ss >/dev/null && ss -H -lntp '( sport = :80 or sport = :443 )' | grep -v nginx | grep -q .; then
  echo 'Ports 80/443 are used by another service. Stopping without replacing it.'; exit 1
fi
WORK=$(mktemp -d /root/windows11x-migration.XXXXXX)
echo "Migration checkpoint: $WORK"
echo 'Keep editing on the old site paused until DNS cutover and the final data copy.'
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx php-fpm php-cli php-mbstring php-curl curl ca-certificates git python3 certbot python3-certbot-nginx rsync
php -r 'if (PHP_VERSION_ID < 80100) exit(1);'
VERSION=$(php -r 'echo PHP_MAJOR_VERSION,".",PHP_MINOR_VERSION;')
systemctl enable --now "php${VERSION}-fpm" nginx
SOCKET="/run/php/php${VERSION}-fpm.sock"
[[ -S $SOCKET ]] || { echo 'PHP-FPM socket not found'; exit 1; }
git clone --depth 1 "$REPO" "$WORK/repo"
# Transfer the LIVE server data, not the initial JSON committed to GitHub.
curl --fail --show-error --silent --max-time 90 "$SOURCE_URL" -o "$WORK/atlas.json"
install -d -m 0755 "$BASE" "$WEB"
install -d -o www-data -g www-data -m 0700 "$PRIVATE"
rsync -a --exclude='.git/' --exclude='.github/' --exclude='.research/' --exclude='scripts/' --exclude='README.md' "$WORK/repo/" "$WEB/"
chown -R root:www-data "$WEB"
find "$WEB" -type d -exec chmod 0750 {} +
find "$WEB" -type f -exec chmod 0640 {} +
ATLAS_PRIVATE_DIR="$PRIVATE" php -r '
require $argv[1]."/api/bootstrap.php";
try {
 $data=json_decode(file_get_contents($argv[2]),true,128,JSON_THROW_ON_ERROR);
 if (!is_array($data) || !is_int($data["revision"]??null) || !is_array($data["content"]??null)) throw new RuntimeException("Missing live content/revision");
 validatePayload($data);
 atomicWrite($argv[3]."/atlas.json",$data);
 echo "Imported revision ",$data["revision"],"; research documents: ",count($data["content"]["research"]),"\n";
} catch (Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
' "$WEB" "$WORK/atlas.json" "$PRIVATE"
if [[ -f /root/windows11x-old-config.php ]]; then
  install -o www-data -g www-data -m 0600 /root/windows11x-old-config.php "$PRIVATE/config.php"
  echo 'Existing owner configuration imported.'
else
  echo 'The old host private password hash is not publicly accessible.'
  echo 'Set the owner login/password below (you can reuse the old site password).'
  ATLAS_PRIVATE_DIR="$PRIVATE" php "$WORK/repo/scripts/configure-admin.php"
fi
[[ -s $PRIVATE/config.php ]] || { echo 'Owner configuration was not created. Stopping.'; exit 1; }
php -r '$c=require $argv[1];if(!is_string($c["username"]??null)||empty(password_get_info($c["password_hash"]??"")["algo"]))exit(1);' "$PRIVATE/config.php"
chown -R www-data:www-data "$PRIVATE"
chmod 0700 "$PRIVATE"
chmod 0600 "$PRIVATE"/*
cat > "$WORK/nginx.conf" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN $TARGET_IP;
    root $WEB;
    index index.php index.html;
    client_max_body_size 16m;
    add_header X-Content-Type-Options nosniff always;
    location ^~ /.well-known/acme-challenge/ { default_type text/plain; try_files \$uri =404; }
    location / { try_files \$uri \$uri/ =404; }
    location ~ \.php\$ {
        try_files \$uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
        fastcgi_param PHP_VALUE "post_max_size=16M\nupload_max_filesize=16M\nmemory_limit=256M";
        fastcgi_pass unix:$SOCKET;
    }
    location ~ /\.(?!well-known/) { deny all; }
    location ~ ^/(scripts|windows11x-private)(/|\$) { deny all; }
}
NGINX
install -m 0644 "$WORK/nginx.conf" /etc/nginx/sites-available/windows11x
ln -s /etc/nginx/sites-available/windows11x /etc/nginx/sites-enabled/windows11x
if ! nginx -t; then
  rm /etc/nginx/sites-enabled/windows11x
  echo 'Nginx config rejected; existing Nginx was not reloaded.'; exit 1
fi
systemctl reload nginx
if command -v ufw >/dev/null && ufw status | grep -q '^Status: active'; then ufw allow 'Nginx Full'; fi
curl -fsS -H "Host: $DOMAIN" http://127.0.0.1/api/index.php?action=version
curl -fsS -H "Host: $DOMAIN" http://127.0.0.1/.well-known/oauth-authorization-server/ | python3 -c 'import json,sys;assert json.load(sys.stdin)["issuer"]=="https://dandomi.tech";print("\nOAuth discovery OK")'
# Preserve scripts/checkpoint outside the public web root for the DNS cutover.
install -m 0700 "$WORK/repo/scripts/vps/finish.sh" /root/windows11x-finish.sh
printf '\nVPS prepared. Public preview: http://%s/\n' "$TARGET_IP"
printf 'Change DNS A record for %s to %s. Remove/update any stale AAAA record.\n' "$DOMAIN" "$TARGET_IP"
echo 'Then run: bash /root/windows11x-finish.sh'
echo 'The old host and its private files have not been modified.'
echo 'This copy includes live content/state; older backup files and existing OAuth sessions remain on the old host.'
