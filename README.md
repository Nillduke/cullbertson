# Culbertson Agency Laravel website

Database-driven Culbertson website and private CMS, built with Laravel 13 and PHP 8.3+.

- Shared homepage/header/footer and insurance, article, team and contact layouts.
- Native categories, tags, author archives, XML/HTML sitemaps and legacy redirects.
- Step-by-step quote forms, contact enquiries, feedback, and a searchable private submission inbox.
- Admin content editing with locally hosted TinyMCE 8.10.0 (GPL edition; third-party notices retained).

## Local development

Install Composer dependencies, copy `.env.example` to `.env`, configure a SQLite database, then run:

```sh
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=ContentSeeder
php artisan cms:create-admin
php artisan serve --port=8083
```

The initial content importer runs only against an empty content database. Runtime content comes from the database. CSS, JavaScript, images, videos and TinyMCE are served directly from `public/`; this design does not require a Vite build.

## Deployment

See [DEPLOYMENT.txt](DEPLOYMENT.txt) for cPanel/manual upload, the private/public folder layout, production configuration, database transfer and first administrator setup. `scripts/package-cpanel.py` prepares the split-folder staging files; its `--zip` option packages an already prepared staging directory after production dependencies have been installed.

Secrets, local databases, customer submissions, runtime caches and Composer `vendor/` are excluded from Git. Public media assets and self-hosted TinyMCE files are tracked. A Git checkout alone does not include current database edits; transfer the prepared content-only snapshot for a first deployment. Never replace a live server database during code updates.

Run tests with `php artisan test`.

## Quote progress and partial leads

All quote services begin with email, then phone and contact permission. Selecting Continue (or an option that advances the form) saves a validated snapshot in `quote_requests`. Typing alone does not save. The first email save creates an incomplete lead; subsequent steps and final submission update that same record. Existing completed submissions remain completed.

In Admin ? Messages & requests, select Partial leads to see incomplete requests, saved answers, progress and the last saved question. Follow-up status remains independent of form completion. Forms & site copy ? quote-progress-copy controls the disclosure and contact-step labels.

Resume is restricted to the original Laravel session and a per-form opaque identifier stored in sessionStorage; contact data is not stored in browser storage. Reloading resumes at the last saved question. Closing the browser tab or session expiry may start a new lead. Saving does not send email/SMS or submit to an insurer.

For existing deployments, upload the updated code/assets and run `php artisan migrate --force`. Never replace the live database with the packaged first-install snapshot.
