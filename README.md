# AI Voice Recording Summarizer

A zero-database, stateless **Core PHP 8.2+** web application for transcribing audio and video recordings, generating structured multi-section executive summaries, and exporting downloadable PDF reports. Built with Bootstrap 5, Font Awesome, cURL, Google Gemini Multimodal / Files APIs, and Agnes AI provider configuration support.

## Key Features

- **Stateless Zero-Database Architecture:** Uses PHP sessions for CSRF tokens, temporary upload tracking, and authorized PDF downloads without requiring a relational database.
- **Audio & Video Support:** Accepts common audio (`MP3`, `WAV`, `M4A`, `AAC`, `FLAC`, `OGG`, `WMA`) and video (`MP4`, `WMV`) recordings up to `250MB`.
- **Inline & Resumable Files API Handling:** Sends smaller recordings inline via base64 and automatically uses the Gemini resumable Files API for larger media files, cleaning up remote and temporary files after processing.
- **Multi-Language Controls:** Supports selecting audio language (`Auto Detect`, `Chinese`, `English`, `Malay`) and summary language (`Chinese`, `English`, `Malay`).
- **7-Section Executive Summary & Transcript:** Produces structured summaries (Overall Summary, Main Topics Discussed, Important Points, Key Metrics/References/Information, Decisions & Conclusions, Action Items & Next Steps, and High-Level Synthesis) alongside a full transcript and one-click copy/PDF export.
- **Runtime Settings & Connection Testing:** Allows configuring and testing **Google Gemini** and **Agnes AI** endpoints, models (including custom model names), and API keys directly from the UI and saving them to `.env`.

## Project Structure

```text
Yong_ai-voice-summarizer/
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── main.js
├── config/
│   └── config.php
├── includes/
│   ├── GeminiClient.php
│   ├── PdfReport.php
│   └── Security.php
├── storage/
│   ├── logs/
│   │   └── .htaccess
│   ├── temp/
│   └── uploads/
│       └── .htaccess
├── .env
├── .env.example
├── .htaccess
├── api_settings.php
├── download_pdf.php
├── index.php
├── process.php
└── upload.php
```

## Requirements

- **PHP 8.2+** with `curl`, `mbstring`, `json`, and `fileinfo` extensions enabled
- **Web Server:** Apache (with `mod_php` / `.htaccess` support) or PHP built-in development server (`php -S`)
- **API Keys:** A Google Gemini API key (and optionally an Agnes AI API key)

## Installation & Configuration

1. **Clone or copy the project** into your web root or project directory.

2. **Create your `.env` file** from `.env.example`:

   ```bash
   cp .env.example .env
   ```

3. **Configure `.env` values:**

   ```env
   APP_URL=http://localhost:8080
   GEMINI_API_KEY=your_gemini_api_key_here
   GEMINI_MODEL=gemini-3.8-flash
   GEMINI_ENDPOINT=https://generativelanguage.googleapis.com/v1
   AGNES_API_KEY=your_agnes_api_key_here
   AGNES_MODEL=agnes-3.0-flash
   AGNES_ENDPOINT=https://apihub.agnes-ai.com/v1
   ```

   - Keep `APP_URL` aligned with the URL and port you use to access the app in your browser.
   - Never commit `.env` containing real API keys to version control.

4. **Ensure `storage/` directories are writable** by the web server or PHP process (`storage/logs`, `storage/temp`, `storage/uploads`).

## Running Locally

### Option A: PHP Built-in Server

```bash
php -S localhost:8080
```

Then open `http://localhost:8080` in your browser.

> **Note:** When using `php -S`, `.htaccess` `php_value` directives are not automatically applied. Adjust `upload_max_filesize`, `post_max_size`, `max_execution_time`, and `memory_limit` in your active `php.ini` if processing large recordings.

### Option B: Apache (e.g., XAMPP / WAMP / Linux Apache)

Place the project inside your Apache document root and ensure `mod_php` and `mod_curl` are enabled, then visit your local URL (such as `http://localhost/Yong_ai-voice-summarizer`).

## Usage Workflow

1. Click **Settings** in the top navigation bar to configure or update your Gemini / Agnes AI endpoint, model, and API key.
2. Click **Test Gemini Connection** (or **Test Agnes AI Connection**) to verify connectivity, then click **Save Settings to Server (.env)**.
3. Drag and drop or browse for an audio or video recording on the main card.
4. Select **Audio Language** and **Summary Language**, then click **Transcribe & Generate Summary**.
5. Review the 7-section summary, expand the full transcript if needed, copy text to your clipboard, or click **Download PDF**.

## Troubleshooting

- **`Unexpected token '<' ... is not valid JSON` in Settings or Console:** Usually means PHP emitted a warning, notice, or fatal error before outputting JSON, or a broken asset URL returned HTML. Inspect the **Network** tab response in browser DevTools and verify `extension=curl` is enabled in `php.ini`.
- **Model unavailable error from Gemini:** Update `GEMINI_MODEL` in **Settings** (or `.env`) to an active multimodal model supported by your account/endpoint, test the connection, and save settings.
- **Upload size or timeout errors on large files:** Check `upload_max_filesize`, `post_max_size`, `max_execution_time`, and `memory_limit` in both `.htaccess` (for Apache) and `php.ini`.