# Paket CloudPanel Single Domain

Paket ini menjalankan frontend React dan backend Laravel pada satu domain.

```text
https://bsiflash.com/       -> React dari public/index.html
https://bsiflash.com/api    -> Laravel API
```

## Upload

1. Extract isi ZIP ke folder aplikasi CloudPanel.
2. Set document root PHP Site ke folder `public/`.
3. Pastikan `public/index.php` dan `public/index.html` berada langsung di folder tersebut.
4. Jangan membuat folder tambahan seperti `public/dist/`.

## `.env` Backend

Buat file `.env` di root aplikasi server. Jangan memakai `.env` lokal dari komputer.

```dotenv
APP_NAME="BSI Flash 2026"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://bsiflash.com
FRONTEND_URL=https://bsiflash.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_cloudpanel
DB_USERNAME=user_database_cloudpanel
DB_PASSWORD=password_database_cloudpanel

FILESYSTEM_DISK=public
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

## Database Manual

Database boleh di-import manual menggunakan file SQL Anda. Setelah import selesai, jalankan:

```bash
composer install --no-dev --optimize-autoloader
php artisan storage:link
php artisan config:clear
php artisan config:cache
```

Jika database dibuat dari migration, gunakan:

```bash
php artisan migrate --force
```

Jangan menjalankan `migrate:fresh` pada database yang sudah berisi data. Seeder hanya perlu dijalankan jika memang ingin membuat data awal/demo:

```bash
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --no-interaction
```

## Verifikasi

```text
https://bsiflash.com/
https://bsiflash.com/lomba
https://bsiflash.com/login
https://bsiflash.com/api/editions
https://bsiflash.com/api/competitions?status=Buka
```

Frontend sudah dibuild dengan API relatif `/api`, sehingga tidak membutuhkan `npm run dev` atau file `.env` frontend di server.
