#!/usr/bin/env bash
#
# Pasang Aksara Font Preview Service sebagai layanan systemd.
#
# Menggantikan cara lama "jalankan python3 app.py manual di terminal",
# yang berarti layanan mati begitu terminal ditutup atau server reboot —
# dan typing tool ikut mati tanpa ada yang tahu.
#
# Pakai:
#   sudo ./deploy/install.sh /path/ke/wp-content/uploads/aksara-private
#
# Opsional lewat variabel lingkungan:
#   PORT=5055 WORKERS=4 RUN_USER=www-data sudo -E ./deploy/install.sh <dir>
#
set -euo pipefail

FONT_STORAGE_DIR="${1:-}"
PORT="${PORT:-5055}"
RUN_USER="${RUN_USER:-www-data}"
VENV_DIR="${VENV_DIR:-}"

SERVICE_NAME="aksara-font-preview"
SERVICE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

die() { echo "Error: $*" >&2; exit 1; }

# Jumlah worker default = jumlah core, minimal 2. Alasannya ada di README
# bagian "Load test konkurensi": ini beban CPU-bound, jadi yang menentukan
# adalah jumlah PROSES, bukan thread. Dihitung di awal karena ikut dipakai
# di pesan bantuan saat pengecekan di bawah gagal.
if [ -z "${WORKERS:-}" ]; then
  CORES="$(nproc 2>/dev/null || echo 2)"
  WORKERS=$(( CORES < 2 ? 2 : CORES ))
fi

[ "$(id -u)" -eq 0 ] || die "jalankan dengan sudo (butuh akses tulis ke /etc/systemd/system)."
[ -n "$FONT_STORAGE_DIR" ] || die "sebutkan folder privat WordPress sebagai argumen pertama.
Contoh: sudo ./deploy/install.sh /var/www/situs/wp-content/uploads/aksara-private"
[ -d "$FONT_STORAGE_DIR" ] || die "folder tidak ditemukan: $FONT_STORAGE_DIR
Folder ini dibuat otomatis saat plugin Aksara Marketplace diaktifkan — aktifkan dulu pluginnya."
id "$RUN_USER" >/dev/null 2>&1 || die "user '$RUN_USER' tidak ada. Set RUN_USER ke user yang menjalankan web server Anda."

# Versi Python dicek SEBELUM virtualenv dibuat. fonttools 4.63 dan
# gunicorn 26 sama-sama menetapkan Requires-Python >=3.10; tanpa cek ini
# pip baru gagal belakangan dengan pesan "no matching distribution found"
# yang menyesatkan (terbaca seperti masalah jaringan, padahal versi Python).
command -v python3 >/dev/null 2>&1 || die "python3 tidak ditemukan di server ini."
PY_VERSION="$(python3 -c 'import sys; print("%d.%d.%d" % sys.version_info[:3])')"
python3 -c 'import sys; raise SystemExit(0 if sys.version_info >= (3, 10) else 1)' \
  || die "butuh Python 3.10 atau lebih baru, terdeteksi $PY_VERSION.
Pasang versi yang lebih baru lalu tunjuk lewat virtualenv sendiri, misalnya:
  python3.12 -m venv $SERVICE_DIR/.venv && VENV_DIR=$SERVICE_DIR/.venv sudo -E ./deploy/install.sh <dir>"

# Mengecek keberadaan binary systemctl saja TIDAK cukup: di container
# (Docker dkk.) binary-nya sering ada padahal systemd tidak berjalan
# sebagai PID 1, sehingga daemon-reload baru gagal belakangan setelah
# skrip terlanjur membuat virtualenv. /run/systemd/system hanya ada
# kalau systemd benar-benar mem-boot sistem ini.
[ -d /run/systemd/system ] || die "systemd tidak berjalan di sistem ini (umum terjadi di dalam container).
Jalankan layanan lewat mekanisme lain, misalnya sebagai proses terpisah di docker-compose:
  gunicorn --workers $WORKERS --bind 127.0.0.1:$PORT app:app"

echo "==> Menyiapkan dependency Python"
if [ -z "$VENV_DIR" ]; then
  VENV_DIR="$SERVICE_DIR/.venv"
fi

if [ ! -d "$VENV_DIR" ]; then
  python3 -m venv "$VENV_DIR" || die "gagal membuat virtualenv. Pasang paket python3-venv terlebih dahulu."
fi
"$VENV_DIR/bin/pip" install --quiet --upgrade pip
"$VENV_DIR/bin/pip" install --quiet -r "$SERVICE_DIR/requirements.txt"

GUNICORN_BIN="$VENV_DIR/bin/gunicorn"
[ -x "$GUNICORN_BIN" ] || die "gunicorn tidak terpasang di $VENV_DIR (cek requirements.txt)."

RUN_GROUP="$(id -gn "$RUN_USER")"

echo "==> Memasang unit systemd"
sed \
  -e "s|__SERVICE_DIR__|$SERVICE_DIR|g" \
  -e "s|__FONT_STORAGE_DIR__|$FONT_STORAGE_DIR|g" \
  -e "s|__GUNICORN_BIN__|$GUNICORN_BIN|g" \
  -e "s|__RUN_USER__|$RUN_USER|g" \
  -e "s|__RUN_GROUP__|$RUN_GROUP|g" \
  -e "s|__WORKERS__|$WORKERS|g" \
  -e "s|__PORT__|$PORT|g" \
  "$SERVICE_DIR/deploy/$SERVICE_NAME.service" \
  > "/etc/systemd/system/$SERVICE_NAME.service"

systemctl daemon-reload
systemctl enable --now "$SERVICE_NAME"

echo "==> Memverifikasi layanan merespons"
sleep 2
if curl -fsS "http://127.0.0.1:$PORT/health" >/dev/null 2>&1; then
  echo
  echo "Selesai. Layanan aktif di 127.0.0.1:$PORT dengan $WORKERS worker."
  echo
  echo "  Status : sudo systemctl status $SERVICE_NAME"
  echo "  Log    : sudo journalctl -u $SERVICE_NAME -f"
  echo "  Restart: sudo systemctl restart $SERVICE_NAME"
  echo
  echo "Cek juga halaman WooCommerce > Status Layanan Aksara di wp-admin —"
  echo "seharusnya sekarang menunjukkan layanan pratinjau font 'Aktif'."
else
  echo
  echo "Layanan terpasang tapi belum merespons di http://127.0.0.1:$PORT/health" >&2
  echo "Periksa log: sudo journalctl -u $SERVICE_NAME -n 50" >&2
  exit 1
fi
