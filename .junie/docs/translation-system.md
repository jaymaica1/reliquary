# Translation System

The project includes a comprehensive translation system with the following components:

- **Translation Files**: YAML files in the `translations/` directory, organized by domain and locale (e.g., `relic.en.yaml`, `relic.pt_BR.yaml`)
- **Saint Translations**: Saint-specific data (names and phrases) stored in the database and imported via YAML files in the `data/` directory. See [Saint Translation Guide](translation-saints.md) for details.
- **TranslationAnalyzerService**: A service that scans Twig templates to identify untranslated strings
- **AdminTranslationController**: A controller that provides an admin interface for managing translations

To use the translation system:

1. Access the admin interface at `/admin/translations/scan` to scan for untranslated strings
2. Use the suggested translation keys to add translations to the appropriate YAML files
3. Use the `|trans` filter in Twig templates to translate strings:
   ```twig
   {{ 'relic.title'|trans({}, 'relic') }}
   ```