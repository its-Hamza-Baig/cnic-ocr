# CNIC Management System — Phase 1 MVP

Secure Laravel application for uploading a Pakistani CNIC image, extracting details with OCR, reviewing them in an editable form, checking duplicates, and saving only after explicit confirmation.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20.19+ (Vite 8 / Tailwind 4)
- Docker (for local MySQL 8 and the PaddleOCR 3.x service)
- Optional: Tesseract, or a Google Cloud Vision API key

## Local setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
docker compose up -d
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Sign in with the seeded operator from `.env`:

- `ADMIN_EMAIL` (default `admin@example.com`)
- `ADMIN_PASSWORD` (default `password` — local development only)

Change these before any shared or production deployment.

## Environment variables

| Variable | Purpose |
| --- | --- |
| `APP_KEY` | Laravel encryption key |
| `DB_*` | MySQL connection |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Seeded operator (never commit real credentials) |
| `OCR_PROVIDER` | `fake`, `paddleocr`, `tesseract`, or `google_vision` |
| `OCR_TIMEOUT_SECONDS` | OCR HTTP/process timeout (180 for PaddleOCR) |
| `OCR_RATE_LIMIT_PER_MINUTE` | OCR endpoint throttle |
| `OCR_MAX_UPLOAD_KB` | Max image size (default 5120) |
| `OCR_STORE_RAW_TEXT` | Opt-in encrypted OCR debug storage (`false` by default) |
| `OCR_RAW_TEXT_RETENTION_HOURS` | Auto-delete debug OCR text |
| `TESSERACT_BINARY`, `TESSERACT_LANG` | Optional Tesseract fallback |
| `PADDLEOCR_URL` | PaddleOCR 3.x HTTP service (`http://127.0.0.1:8868`) |
| `PADDLEOCR_LANGS` | Recognition passes (`en,ar` — Arabic model also covers Urdu) |
| `GOOGLE_VISION_API_KEY` | Google Cloud Vision API key |
| `GOOGLE_VISION_ENDPOINT` | Vision API endpoint |
| `CNIC_DISK` | Private filesystem disk name (`cnic`) |
| `CNIC_TEMP_IMAGE_RETENTION_HOURS` | Temporary scan image lifetime |
| `CNIC_PERSON_IMAGE_RETENTION_DAYS` | Optional saved-image retention; empty keeps images |
| `CNIC_SENSITIVE_RATE_LIMIT_PER_MINUTE` | Duplicate-check and save throttle |

Keep API keys and passwords in `.env` only. Do not commit `.env`.

## Migrations

```bash
php artisan migrate
```

Tables:

- `persons` — CNIC records (unique `cnic`, soft deletes, no image binaries)
- `ocr_scans` — OCR job metadata (no raw text unless debug is enabled)
- `audit_logs` — field-name-only change history

## Storage

CNIC images are stored on the private `cnic` disk:

`storage/app/private/cnic`

They are not publicly URL-addressable. Authenticated operators load images through:

- `GET /persons/create/preview/{side}` for a pending scan
- `GET /persons/{person}/image/{side}` for a saved record

Temporary uploads live under `tmp/` and are pruned by:

```bash
php artisan cnic:prune-expired
```

The command is scheduled hourly. Run the scheduler with `php artisan schedule:work` in development.

## OCR configuration

`OcrServiceInterface` is the application-facing OCR API. Vendor code lives in `app/Services/Ocr/Providers`.

- `fake` — fixture text for automated tests only (`phpunit.xml` forces this)
- `paddleocr` — PaddleOCR 3.x (PP-OCRv5) via the local Docker service
- `tesseract` — optional Tesseract fallback
- `google_vision` — Google Cloud Vision `DOCUMENT_TEXT_DETECTION`

This project defaults to `OCR_PROVIDER=paddleocr`. Start the OCR service with MySQL:

```bash
docker compose up -d
```

The first PaddleOCR start downloads models and can take several minutes. Laravel posts each CNIC image to `PADDLEOCR_URL` (`http://127.0.0.1:8868`). English uses PP-OCRv5; Urdu/Arabic uses `arabic_PP-OCRv5_mobile_rec`. The parser still keeps whichever script is printed, and the MRZ is a fallback for CNIC number and name.

Tesseract remains available with `OCR_PROVIDER=tesseract` if you need it.

Automated tests always bind the fake provider.

Raw OCR text is not stored. If you temporarily enable `OCR_STORE_RAW_TEXT=true`, the payload is encrypted and deleted after `OCR_RAW_TEXT_RETENTION_HOURS`.

## Tests

```bash
php artisan test
```

Tests use MySQL database `cnic_ocr_testing` (created by `docker compose`). PHP’s SQLite driver is not required.

## Privacy notes

- Management routes require authentication and `PersonPolicy` authorization
- Logs redact CNIC numbers and sensitive field values
- CSRF protection is enabled on all HTML forms
- Upload validation checks MIME type and image contents, not only the file extension
- Stored filenames are randomized
- Duplicate prevention is server-side, with a database unique constraint as the final guard
