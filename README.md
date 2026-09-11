# E-SISMAN 2

Laravel 8 rewrite baseline for E-SISMAN, targeting the agreed legacy-compatible stack.

## Runtime

- PHP 8.1 portable: `..\_tools\php-8.1`
- Laravel Framework 8.83.27
- MariaDB 10.4.6 portable: `..\_tools\mariadb-10.4.6`
- Database port: `3307`
- Frontend: Laravel Mix 6, Tailwind CSS 3, Alpine.js

## Local Commands

Use the scripts in `scripts/` so commands always use the portable PHP/MariaDB versions.

```bat
scripts\db-start.bat
scripts\artisan.bat --version
scripts\artisan.bat migrate:fresh
scripts\composer.bat install
npm run dev
scripts\artisan.bat serve --host=127.0.0.1 --port=8008
```

Stop the portable database:

```bat
scripts\db-stop.bat
```

## Rewrite Rules

This project is not a direct file-for-file downgrade. During the rewrite, repeated UI patterns should become Blade components, repeated queries should become query classes/scopes, and heavy controller logic should be moved into actions/services/view models.

The reusable component/action/service list is expected to grow during implementation whenever new duplication is found.
