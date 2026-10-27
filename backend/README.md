<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## Local audio transcription

`POST /api/claims/transcribe` accepts an authenticated multipart upload with an `audio` field and returns `{"text":"...","language":"id"}`. Audio is processed locally; it is not sent to an external AI service. Supported uploads are FLAC, MP3, M4A, OGG, WAV, and WebM, up to 20 MB. The endpoint is limited to three requests per minute.

The backend server must have FFmpeg and a `whisper.cpp` `whisper-cli` executable installed, along with a multilingual Whisper model. Set these paths in `backend/.env`:

```dotenv
LOCAL_WHISPER_BINARY='C:\path\to\whisper-cli.exe'
LOCAL_WHISPER_FFMPEG='C:\path\to\ffmpeg.exe'
LOCAL_WHISPER_MODEL='C:\path\to\ggml-base.bin'
LOCAL_WHISPER_TIMEOUT=180
```

Audio uploads are converted to temporary 16 kHz mono WAV files before transcription. Temporary files are removed after processing. The claim form records audio and sends it to this endpoint when recording stops. The client statement is stored separately from the bullet-formatted damage description. Run `php artisan migrate` to add the `client_statement` column. Automatic bullet formatting does not use an AI summarization service; `/api/claims/summarize` remains unavailable until one is configured.

## Paid service requests

Admins can create paid-service requests for product units whose warranty has ended, assign a vendor, record the estimated cost, and track the request through approval and service completion. This workflow is separate from warranty claims and requires an authenticated admin session. Run `php artisan migrate` to create the `paid_service_requests` table.

- `GET /api/paid-service-requests` lists requests.
- `GET /api/paid-service-requests/eligible-units` lists units with an ended warranty and no current or future warranty.
- `POST /api/paid-service-requests` creates a request; the backend verifies the unit's warranty has ended.
- `PATCH /api/paid-service-requests/{id}` updates its vendor, estimated cost, notes, and status (`requested`, `quoted`, `approved`, `in_progress`, `completed`, or `cancelled`).

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
