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

## Supporting-page design

About, the insurance directory, claims, Cape Coral, collector cars, media, careers, document submission, privacy and all existing thank-you URLs use the shared supporting-page design. HTML sitemap links are generated from published database records. Insurance cards and agency team profiles also query published records at runtime.

The migration and idempotent `SupportPageSeeder` install named editable `page_sections`. Original sections are retained with component `legacy_archive` and are hidden from public rendering. Admin ? Pages & posts edits each design section; Admin ? Forms & site copy ? Supporting pages edits shared labels and form copy. Keep the dynamic directory, team and request-form markers in their sections.

Native careers, documents and collector-car enquiries appear in the existing admin inbox. Document uploads and optional PDF resumes are saved under `storage/app/private/enquiry-files`, with metadata in `contact_files`. Only authenticated administrators may download them. File types and limits are checked on the server. Saving a request does not send emails or submit information to an insurer.

## SEO preservation

`seo_profiles` stores metadata for the 297 distinct destinations reached by the original 417 audited URLs. Runtime output prefers saved Yoast fields, then saved Rank Math fields, then the originally rendered WordPress values. The source audit, raw saved fields and legacy plugin scores remain preserved separately from the active metadata. The user-approved Renters title/description correction is recorded in `database/import/seo-approved-overrides.json` and in that profile's source history.

Admin ? SEO & metadata manages titles, descriptions, canonicals, robots, language, social tags, JSON-LD and hreflang. Page-editor links open the matching SEO profile. Visible headings and page titles remain separate from imported SEO overrides. New pages fall back to their database title/description and a self canonical. Paginated archives receive separate canonicals. Native /auto-quote/ and /quote/ aliases use the original quote-page canonicals; new bundle queries retain distinct canonicals. The importer uses insert-or-ignore and never replaces an existing admin edit.

The first audit was of the restored local WordPress copy, where SEO plugins were inactive. Missing source descriptions remain unset; no JSON-LD was invented when the audit had none. Saved social images are served at their original `/wp-content/uploads/` paths through a restricted public-image compatibility route.

Local/staging pages remain noindex while `SEARCH_ENGINE_INDEXING=false`. At launch, configure the correct HTTPS `APP_URL`, enable indexing on the server, and refresh configuration caches. `robots.txt` is generated dynamically; remove the old physical public/robots.txt when updating an older deployment. Matching metadata does not establish unchanged rankings or traffic, especially after changes to content and headings.

For a repeatable, read-only crawl of the original inventory: `php scripts/audit-seo.php --output=/absolute/path/report.json --indexing=on`. That indexing override affects only the audit process, not the running server configuration.
