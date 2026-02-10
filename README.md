# OpenBoard website

Source code of the openboard.org website.

## Run locally

1. Install PHP ≥ 8.2 and Composer.  
2. Install dependencies: `composer install`  
3. Start the dev server (pick one):  
   - With Symfony CLI: `symfony serve --no-tls --port=8000`  
   - Native PHP server: `php -S 127.0.0.1:8000 -t public`  
4. Open http://127.0.0.1:8000

## Add / update translations

1. Create or update translation files in `translations/` named with the locale, e.g. `messages.es.yaml` (and optionally `changelog.es.yaml`).  
2. Ensure the navigation label key exists: `nav.languages.<locale>` inside the new `messages.<locale>.yaml`.  
3. Run the site locally to verify strings and the language switcher.  
4. Submit a PR including the new/updated YAML files.
