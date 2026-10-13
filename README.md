# MindMantra Live API

High-performance, minimal PHP 8.2+ REST API backend for MindMantra.

## Quick Start

- Start local development server:
  ```bash
  composer dev
  # or: php spark serve --port 8080
  ```
- View all active API routes:
  ```bash
  composer routes
  ```
- Optimize framework caches:
  ```bash
  composer optimize
  ```
- Clear system caches:
  ```bash
  composer cache:clear
  ```

## Performance & Lean Architecture

- **Zero Debug Overhead**: Removed Debug Toolbar, Hot Reloader listeners, and Kint dumps to eliminate per-request memory bloat.
- **No Body Scanning**: Removed `PerformanceMetrics` filter that scanned entire JSON payloads for template tags.
- **No Module Auto-Discovery**: Disabled recursive filesystem package scans on every request in `Config/Modules.php`.
- **Fast Routing**: Disabled controller PHP reflection attributes in `Config/Routing.php`.
- **Native MySQL Numbers**: Enabled `numberNative => true` in `Config/Database.php` so numeric IDs/counts return as native ints/floats.
- **Cached Static OpenAPI**: Direct cached stream delivery for `openapi.json` without redundant decode/re-encode cycles.
- **Robust JSON 404 Handler**: Converted 404 override to controller method, eliminating fatal string conversion crashes.

## API Endpoints

- **System & Health**:
  - `GET /` — API service information
  - `GET /health` or `GET /api/v1/health` — Health check
  - `GET /ping` or `GET /api/v1/ping` — Lightweight latency check
  - `GET /api/v1/openapi.json` — OpenAPI 3.0 specification
- **Authentication**:
  - `POST /api/v1/auth/signup` — User registration
  - `POST /api/v1/auth/login` — Session login & token generation
  - `POST /api/v1/auth/logout` — Terminate session & clear cookies
- **Courses & MCQs**:
  - `GET /api/v1/courses` — List courses with pagination & search
  - `GET /api/v1/courses/{id-or-slug}` — Fetch single course details
  - `GET /api/v1/courses/{id-or-slug}/mcqs` — List paginated questions for course
