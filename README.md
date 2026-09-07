# CNIC OCR

Laravel app for scanning a Pakistani CNIC (front required, back optional), extracting fields with OCR, letting an operator correct them, checking duplicates, and saving a person record **only after confirmation**.

Images stay on a private disk. Nothing is stored as a person until you click **Review and save**.

## What you will run

| Piece | Role | Default local setup |
| --- | --- | --- |
| Laravel (PHP) | Web app, auth, parser, storage | Runs on your PC with `php artisan serve` |
| MySQL 8 | App data (`cnic_ocr`) and tests (`cnic_ocr_testing`) | Docker on port **3307**, or a local MySQL install |
| PaddleOCR 3.x | Real OCR (English + Arabic/Urdu) | Docker on port **8868**, or Python on the host |
| Node.js 20.19+ | Vite + Tailwind CSS build | `npm run build` or `npm run dev` |

Tesseract and Google Cloud Vision are optional fallbacks. Automated tests always use the **fake** OCR provider.

---

## Install these first

### 1. Git

- [Git](https://git-scm.com/downloads)

```bash
git clone https://github.com/its-Hamza-Baig/cnic-ocr.git
cd cnic-ocr
```

### 2. PHP 8.3+ and Composer

Required PHP extensions: `bcmath`, `ctype`, `curl`, `fileinfo`, `gd`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`.

**Linux (Debian/Ubuntu / WSL):**

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-mysql php8.3-gd php8.3-bcmath php8.3-zip unzip
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**macOS (Homebrew):**

```bash
brew install php@8.3 composer
```

**Windows:**

- Install [PHP 8.3](https://windows.php.net/download/) (enable `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `curl`, `openssl` in `php.ini`)
- Install [Composer](https://getcomposer.org/download/)

Check:

```bash
php -v    # 8.3 or newer
composer -V
```

### 3. Node.js 20.19 or newer

Vite 8 needs Node **20.19+**. Node 18 cannot build the frontend.

- [Node.js LTS](https://nodejs.org/) (20.19+ or 22)
- Or [nvm](https://github.com/nvm-sh/nvm) / [nvm-windows](https://github.com/coreybutler/nvm-windows)

```bash
node -v   # v20.19.0 or higher
npm -v
```

### 4. Docker (recommended) **or** local MySQL + Python

Pick one path below.

**With Docker (easiest):**

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows / macOS)
- Or Docker Engine + Compose plugin (Linux)

```bash
docker -v
docker compose version
```

On Windows, enable WSL 2 backend. From WSL, use the same `docker` command if Docker Desktop WSL integration is on.

**Without Docker:**

- MySQL 8.x ([Windows](https://dev.mysql.com/downloads/installer/), [macOS](https://dev.mysql.com/doc/refman/8.4/en/macos-installation.html), `sudo apt install mysql-server` on Linux)
- For real OCR: Python 3.11+ and pip (see [PaddleOCR without Docker](#paddleocr-without-docker))
- Optional: `tesseract-ocr` with English + Urdu language packs

---

## One-time app setup (always)

From the project root, on every machine:

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
```

`.env` is local-only. Do not commit it.

Then start **MySQL** and **OCR** using either Docker or the no-Docker sections, then finish with [migrate, seed, and run](#migrate-seed-and-run).

---

## Option A — Local PC **with Docker** (recommended)

This starts MySQL 8.4 on **3307** and PaddleOCR 3.x on **8868**. Laravel still runs on the host (`php artisan serve`).

`.env` already matches this:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=cnic_ocr
DB_USERNAME=cnic
DB_PASSWORD=cnic

OCR_PROVIDER=paddleocr
PADDLEOCR_URL=http://127.0.0.1:8868
OCR_TIMEOUT_SECONDS=180
```

Start services:

```bash
docker compose up -d
```

First PaddleOCR build can take **10–20 minutes** (large Python image). First start also downloads models and can take several minutes. Wait until it is healthy:

```bash
docker compose ps
curl http://127.0.0.1:8868/health
```

You want `cnic-ocr-mysql` running and `cnic-ocr-paddleocr` **healthy**. Health JSON looks like:

```json
{"ok": true, "engines": ["ar", "en"]}
```

Useful Docker commands:

```bash
docker compose logs -f paddleocr
docker compose restart paddleocr
docker compose down          # stop containers (keeps database volume)
docker compose down -v       # stop and DELETE MySQL + OCR model cache
```

MySQL defaults inside Compose:

| Item | Value |
| --- | --- |
| Host port | `3307` (container `3306`) |
| Database | `cnic_ocr` |
| Test database | `cnic_ocr_testing` (created automatically) |
| User / password | `cnic` / `cnic` |
| Root password | `cnic_root_local` |

Continue at [Migrate, seed, and run](#migrate-seed-and-run).

---

## Option B — Local PC **without Docker**

### MySQL 8

Install MySQL, start it, then create the databases and user:

```sql
CREATE DATABASE cnic_ocr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE cnic_ocr_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cnic'@'localhost' IDENTIFIED BY 'cnic';
GRANT ALL PRIVILEGES ON cnic_ocr.* TO 'cnic'@'localhost';
GRANT ALL PRIVILEGES ON cnic_ocr_testing.* TO 'cnic'@'localhost';
FLUSH PRIVILEGES;
```

Point `.env` at your install (typical host install uses port **3306**):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cnic_ocr
DB_USERNAME=cnic
DB_PASSWORD=cnic
```

If your MySQL user or password is different, change `.env` to match. `phpunit.xml` still uses port **3307** and user `cnic` unless you edit it for tests.

### PaddleOCR without Docker

Use this when you want real OCR but no Docker.

1. Install Python 3.11+ and pip.
2. From the project root:

```bash
python3 -m venv .venv
source .venv/bin/activate          # Windows: .venv\Scripts\activate
pip install paddlepaddle==3.2.0 -i https://www.paddlepaddle.org.cn/packages/stable/cpu/
pip install -r docker/paddleocr/requirements.txt
```

3. Run the same HTTP server the app calls:

```bash
export FLAGS_use_mkldnn=0 OMP_NUM_THREADS=1 MKL_NUM_THREADS=1
export PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK=True
python docker/paddleocr/server.py
```

Windows PowerShell:

```powershell
$env:FLAGS_use_mkldnn="0"
$env:OMP_NUM_THREADS="1"
$env:MKL_NUM_THREADS="1"
$env:PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK="True"
python docker/paddleocr/server.py
```

Leave that terminal open. It listens on `http://127.0.0.1:8868`. First start downloads models.

Keep in `.env`:

```env
OCR_PROVIDER=paddleocr
PADDLEOCR_URL=http://127.0.0.1:8868
OCR_TIMEOUT_SECONDS=180
PADDLEOCR_LANGS=en,ar
```

`en` is PP-OCRv5 English. `ar` uses `arabic_PP-OCRv5_mobile_rec` (covers Urdu as well).

### OCR without PaddleOCR (no Docker, no Python)

You can still run the Laravel UI.

**Fake provider (no real reading of the card):**

```env
OCR_PROVIDER=fake
```

Uploads return fixture text (`ALI RAZA` / `35202-1234567-1`). Fine for UI work and required for automated tests.

**Tesseract on the host:**

Install Tesseract with English and Urdu:

```bash
# Debian/Ubuntu/WSL
sudo apt install -y tesseract-ocr tesseract-ocr-eng tesseract-ocr-urd
```

```bash
# macOS
brew install tesseract tesseract-lang
```

Windows: [UB Mannheim Tesseract installer](https://github.com/UB-Mannheim/tesseract/wiki) and add it to `PATH`.

```env
OCR_PROVIDER=tesseract
TESSERACT_BINARY=tesseract
TESSERACT_LANG=eng
TESSERACT_BACK_LANG=urd
TESSERACT_SCRIPTS=eng,urd
```

If the `tesseract` binary is missing, the app tries `docker run` with image `cnic-ocr-tesseract` (build with `docker compose --profile ocr build tesseract`).

**Google Cloud Vision:**

```env
OCR_PROVIDER=google_vision
GOOGLE_VISION_API_KEY=your-key
```

Continue at [Migrate, seed, and run](#migrate-seed-and-run).

---

## Migrate, seed, and run

After MySQL is reachable:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link   # optional; CNIC images are not public
```

Build CSS/JS and start the web server:

```bash
npm run build
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

For live frontend rebuilds while you edit Blade/CSS:

```bash
npm run dev
```

Keep `php artisan serve` in another terminal.

### Sign in

Seeded operator (from `.env`, local only):

| Field | Default |
| --- | --- |
| Email | `admin@example.com` |
| Password | `password` |

Change `ADMIN_EMAIL` / `ADMIN_PASSWORD` and run `php artisan db:seed` again before any shared machine.

### Use the app

1. Log in → **Scan CNIC**.
2. Upload **front** JPEG/PNG (required). **Back** is optional but strongly recommended (address + MRZ).
3. **Extract details** — PaddleOCR must be up if `OCR_PROVIDER=paddleocr`.
4. Check every field. Confirm, then **Review and save**.
5. List / view / edit / delete records from **Persons**.

Max upload size is `OCR_MAX_UPLOAD_KB` (default 5120 KB). Only JPEG and PNG.

---

## Daily start (after the first setup)

**With Docker:**

```bash
docker compose up -d
php artisan serve
```

Wait for `curl http://127.0.0.1:8868/health` if you use PaddleOCR.

**Without Docker:**

1. Start MySQL.
2. Start `python docker/paddleocr/server.py` (if using PaddleOCR).
3. `php artisan serve`.

Optional scheduler (prunes old temp scans hourly):

```bash
php artisan schedule:work
```

Manual prune:

```bash
php artisan cnic:prune-expired
```

---

## Environment variables

Copy from `.env.example`. Important keys:

| Variable | Purpose |
| --- | --- |
| `APP_KEY` | Laravel encryption key (`php artisan key:generate`) |
| `APP_URL` | Default `http://localhost:8000` |
| `DB_*` | MySQL host, port, database, user, password |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Seeded login |
| `OCR_PROVIDER` | `paddleocr`, `tesseract`, `google_vision`, or `fake` |
| `OCR_TIMEOUT_SECONDS` | HTTP/process timeout (use `180` for PaddleOCR) |
| `OCR_RATE_LIMIT_PER_MINUTE` | Scan endpoint throttle (default 10) |
| `OCR_MAX_UPLOAD_KB` | Max image size (default 5120) |
| `OCR_STORE_RAW_TEXT` | If `true`, encrypts raw OCR text for a short time |
| `OCR_RAW_TEXT_RETENTION_HOURS` | How long debug OCR text is kept |
| `PADDLEOCR_URL` | PaddleOCR HTTP base URL |
| `PADDLEOCR_LANGS` | Passes to run, default `en,ar` |
| `TESSERACT_*` | Binary, languages, Docker image name |
| `GOOGLE_VISION_API_KEY` | Vision API key |
| `CNIC_DISK` | Private disk name (`cnic`) |
| `CNIC_TEMP_IMAGE_RETENTION_HOURS` | Unsaved scan images (default 24h) |
| `CNIC_PERSON_IMAGE_RETENTION_DAYS` | Empty = keep saved images |
| `CNIC_SENSITIVE_RATE_LIMIT_PER_MINUTE` | Duplicate-check and save throttle |
| `FORWARD_DB_PORT` | Host port for Compose MySQL (default 3307) |
| `FORWARD_PADDLEOCR_PORT` | Host port for Compose OCR (default 8868) |

---

## OCR behaviour

`OcrServiceInterface` is the app API. Drivers live in `app/Services/Ocr/Providers`.

| Provider | When to use |
| --- | --- |
| `paddleocr` | Default. Best accuracy for Digital CNIC English + Urdu/Arabic |
| `tesseract` | Offline fallback; weaker on watermarks and Nasta’liq |
| `google_vision` | Cloud OCR if you have an API key |
| `fake` | Tests and UI without reading the image |

The parser is not language-specific: it keeps English or Urdu as printed. The machine-readable zone (MRZ) on the **back** is used as a fallback for CNIC number and name. Father’s name is only on the front. Always verify fields before save.

Raw OCR text is **not** stored unless `OCR_STORE_RAW_TEXT=true` (encrypted, then pruned).

---

## Database tables

```bash
php artisan migrate
```

- `persons` — unique CNIC, soft deletes, image **paths** only
- `ocr_scans` — job metadata (no raw text by default)
- `audit_logs` — field-name-only history

---

## Storage

Private disk: `storage/app/private/cnic` (`serve` is off). No public image URLs.

Authenticated routes:

- `GET /persons/create/preview/{side}` — pending scan
- `GET /persons/{person}/image/{side}` — saved record

Filenames are random. Temp files live under `tmp/`.

---

## Tests

Needs MySQL database `cnic_ocr_testing` (Compose creates it; without Docker, create it yourself). `phpunit.xml` forces `OCR_PROVIDER=fake` and `DB_PORT=3307` unless you change it.

```bash
php artisan test
```

---

## Troubleshooting

**`PaddleOCR is not reachable`**

The container (or Python server) is down, still loading models, or Laravel cannot open `PADDLEOCR_URL`.

```bash
docker compose ps
docker compose logs paddleocr --tail 50
curl http://127.0.0.1:8868/health
```

`docker compose up -d paddleocr` only starts the process. Wait until **healthy**, then scan again.

**Health is OK but extract fails**

Inference crashed (often CPU MKL-DNN). Current Docker image disables MKL-DNN and uses PP-OCRv5 mobile models. Rebuild if you are on an old image:

```bash
docker compose up -d --build paddleocr
```

**Wrong or empty name / father / address**

OCR is probabilistic. Digital Card watermarks and Urdu Nasta’liq still fail sometimes. Copy from the photo before save. Upload a sharp, well-lit **front and back**.

**`npm run build` fails**

Node is too old. Use 20.19+ (`node -v`).

**`SQLSTATE[HY000] [2002] Connection refused`**

MySQL is not running, or `.env` port is wrong (Compose = **3307**, typical local install = **3306**).

**`could not find driver` / `pdo_mysql`**

Install `php-mysql` / enable `pdo_mysql` in `php.ini`.

**GD errors on upload**

Install `php-gd`. The app uses GD to inspect and crop images.

**WSL + Docker Desktop**

Run commands from the WSL project directory. Ensure Docker Desktop WSL integration is enabled for that distro so `127.0.0.1:3307` and `:8868` work from PHP.

---

## Privacy

- All management routes require login and `PersonPolicy`
- Logs redact CNIC numbers and sensitive values
- CSRF on HTML forms
- Uploads checked by MIME and image contents, not extension only
- Duplicate CNIC blocked in the app and by a unique database index

Do not commit `.env`, CNIC photos, or real identity data.
