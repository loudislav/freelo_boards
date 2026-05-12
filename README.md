# Freelo Boards

A lightweight PHP dashboard for [Freelo](https://app.freelo.io) that shows two views:

- **Úkoly na tento a příští týden** (`/`) — active tasks due within the current and next week
- **Deska hanby** (`/shame`) — overdue active tasks with a leaderboard ranked by assignee

Both pages support dark mode (follows system preference, with a manual toggle that persists across sessions).

---

## Prerequisites

- PHP 8.1 or newer
- The `curl` PHP extension enabled
- Access to the [Freelo API](https://api.freelo.io/v1) (requires a Freelo account)

---

## Local development

```bash
# 1. Clone the repository
git clone https://github.com/PiechZ/freelo_boards.git
cd freelo_boards

# 2. Create your .env file
cp .env.example .env

# 3. Fill in your credentials (see "Getting your API key" below)
#    Also set FREEL0_SSL_VERIFY=false if you hit SSL errors locally

# 4. Start the built-in PHP server
php -S localhost:8080

# 5. Open http://localhost:8080 in your browser
```

### Getting your API key

1. Log in to [app.freelo.io](https://app.freelo.io)
2. Click your avatar → **Nastavení účtu** (Account settings)
3. Open the **API** tab
4. Copy your API key and paste it into `.env` as `FREEL0_API_KEY`

### Enabling the cURL extension (Windows)

If you see `Call to undefined function curl_init()`:

1. Find your `php.ini` — run `php --ini` and look for "Loaded Configuration File"
2. Open the file and find the line `;extension=curl`
3. Remove the leading semicolon → `extension=curl`
4. Restart the PHP server

---

## Deployment

The app is a single directory of plain PHP files with no build step.

### Any PHP-capable host (shared hosting, VPS, etc.)

```bash
# Upload all files except .env.example and .claude/
# Then create .env on the server with your production values
```

Key points for production:
- Set `FREEL0_SSL_VERIFY=true` (or omit it — true is the default)
- Make sure `.env` is **not** publicly accessible. If using Apache, add to `.htaccess`:
  ```
  <Files ".env">
      Require all denied
  </Files>
  ```
  Nginx equivalent:
  ```
  location ~ /\.env {
      deny all;
  }
  ```
- Point the web root at the project directory so `index.php` is served at `/`
- The `shame/` subdirectory is served automatically — no extra configuration needed

### Docker (optional)

```dockerfile
FROM php:8.3-apache
RUN docker-php-ext-install curl
COPY . /var/www/html/
```

```bash
docker build -t freelo-boards .
docker run -p 8080:80 --env-file .env freelo-boards
```

---

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `FREEL0_EMAIL` | yes | Your Freelo account email |
| `FREEL0_API_KEY` | yes | API key from Freelo account settings |
| `FREEL0_UA` | yes | User-Agent header sent with API requests |
| `FREEL0_SSL_VERIFY` | no | Set to `false` to disable SSL verification (local dev only). Default: `true` |

> **Note:** The variable names use the digit **0** (zero), not the letter O — e.g. `FREEL0_EMAIL`.
