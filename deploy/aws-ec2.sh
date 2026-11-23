#!/usr/bin/env bash
# =============================================================================
#  Funeraria Cristo Rey - instalación en AWS EC2 (Ubuntu Server 24.04)
#
#  En el servidor:
#    git clone https://github.com/BastianGonzalez22/funeraria-cristo-rey.git
#    sudo bash funeraria-cristo-rey/deploy/aws-ec2.sh        # 1.a vez: instala todo
#    cd funeraria-cristo-rey && git pull && sudo bash deploy/aws-ec2.sh   # actualizar
#    sudo bash funeraria-cristo-rey/deploy/aws-ec2.sh ip     # si cambió la IP
# =============================================================================
set -euo pipefail

APP_DIR=/var/www/funeraria
PROYECTO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"   # carpeta del git clone
DB_NAME=funeraria_cristo_rey
DB_USER=funeraria
CRED_FILE=/root/funeraria-db.txt
export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1

paso() { echo; echo "==> $*"; }
artisan() { sudo -u www-data php "$APP_DIR/artisan" "$@"; }

[ "$(id -u)" -eq 0 ] || { echo "Ejecute con sudo: sudo bash $0"; exit 1; }

ip_publica() {
    local token ip
    token=$(curl -s -m 3 -X PUT http://169.254.169.254/latest/api/token -H "X-aws-ec2-metadata-token-ttl-seconds: 60" || true)
    ip=$(curl -s -m 3 -H "X-aws-ec2-metadata-token: $token" http://169.254.169.254/latest/meta-data/public-ipv4 || true)
    [ -n "$ip" ] || ip=$(curl -s -m 5 https://checkip.amazonaws.com || true)
    echo "$ip" | tr -d '[:space:]'
}

poner_env() { # poner_env CLAVE valor
    if grep -q "^$1=" "$APP_DIR/.env"; then
        sed -i "s|^$1=.*|$1=$2|" "$APP_DIR/.env"
    else
        echo "$1=$2" >> "$APP_DIR/.env"
    fi
}

cachear() {
    artisan config:clear >/dev/null
    artisan cache:clear >/dev/null || true
    artisan config:cache
    artisan route:cache
    artisan view:cache
}

# --- Solo actualizar la IP ---
if [ "${1:-}" = "ip" ]; then
    IP=$(ip_publica)
    [ -n "$IP" ] || { echo "No se pudo obtener la IP pública."; exit 1; }
    poner_env APP_URL "http://$IP"
    cachear
    systemctl reload apache2
    echo "Listo: http://$IP"
    exit 0
fi

[ -f "$PROYECTO/artisan" ] || { echo "Ejecute este script desde el repositorio clonado."; exit 1; }

# --- 1. Swap (Composer necesita memoria en instancias pequeñas) ---
if ! swapon --show | grep -q .; then
    paso "Creando 1 GB de swap"
    fallocate -l 1G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null && swapon /swapfile
    grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# --- 2. Paquetes ---
paso "Instalando Apache, PHP 8.3, MariaDB y Composer (tarda unos minutos)"
apt-get update -y
apt-get install -y apache2 mariadb-server unzip rsync curl \
    php8.3 libapache2-mod-php8.3 php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl composer

cat > /etc/php/8.3/apache2/conf.d/99-funeraria.ini <<'INI'
upload_max_filesize = 8M
post_max_size = 48M
memory_limit = 256M
max_execution_time = 60
date.timezone = America/Santiago
expose_php = Off
INI

# --- 3. Copiar el código (sin pisar .env, fotos ni comprobantes del servidor) ---
PRIMERA_VEZ=0
[ -f "$APP_DIR/.env" ] || PRIMERA_VEZ=1

paso "Copiando el código a $APP_DIR"
mkdir -p "$APP_DIR"
rsync -a --delete \
    --exclude '.env' --exclude 'vendor/' --exclude '.git/' \
    --exclude 'storage/app/' --exclude 'storage/logs/' --exclude 'storage/framework/' \
    --exclude 'public/storage' \
    "$PROYECTO"/ "$APP_DIR"/

mkdir -p "$APP_DIR"/storage/{app/public,app/private,logs} \
         "$APP_DIR"/storage/framework/{cache/data,sessions,views} \
         "$APP_DIR"/bootstrap/cache

# --- 4. Base de datos y .env (solo la primera vez) ---
if [ "$PRIMERA_VEZ" -eq 1 ]; then
    paso "Creando la base de datos $DB_NAME"
    systemctl enable --now mariadb
    DB_PASS=$(openssl rand -hex 16)
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
    printf 'DB_DATABASE=%s\nDB_USERNAME=%s\nDB_PASSWORD=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASS" > "$CRED_FILE"
    chmod 600 "$CRED_FILE"

    paso "Creando el .env de producción"
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    poner_env APP_ENV production
    poner_env APP_DEBUG false
    poner_env LOG_LEVEL warning
    poner_env DB_HOST 127.0.0.1
    poner_env DB_DATABASE "$DB_NAME"
    poner_env DB_USERNAME "$DB_USER"
    poner_env DB_PASSWORD "$DB_PASS"
    IP=$(ip_publica)
    poner_env APP_URL "http://${IP:-localhost}"
fi

# --- 5. Dependencias y permisos ---
paso "Instalando dependencias con Composer"
cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

chown -R www-data:www-data "$APP_DIR"
chmod 640 "$APP_DIR/.env"
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 775 {} +

# --- 6. Laravel ---
if [ "$PRIMERA_VEZ" -eq 1 ]; then
    paso "Generando APP_KEY, tablas y datos de prueba"
    artisan key:generate --force
    artisan migrate --seed --force
else
    paso "Aplicando migraciones nuevas (sin borrar datos)"
    artisan migrate --force
fi
[ -L "$APP_DIR/public/storage" ] || artisan storage:link
cachear

# --- 7. Apache ---
paso "Configurando Apache"
cat > /etc/apache2/sites-available/funeraria.conf <<APACHE
<VirtualHost *:80>
    ServerName funeraria
    DocumentRoot $APP_DIR/public

    <Directory $APP_DIR/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/funeraria-error.log
    CustomLog \${APACHE_LOG_DIR}/funeraria-access.log combined
</VirtualHost>
APACHE
a2enmod rewrite headers >/dev/null
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite funeraria >/dev/null
sed -i 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf
apache2ctl configtest
systemctl enable apache2 >/dev/null
systemctl restart apache2

URL=$(grep '^APP_URL=' "$APP_DIR/.env" | cut -d= -f2-)
echo
echo "=============================================================="
echo "  Listo. Abra en el navegador: $URL"
echo "  Usuario: admin@cristorey.cl / CristoRey2026"
echo "  Clave de la base de datos guardada en $CRED_FILE"
echo "=============================================================="