# Woo Nalda Sync - Language Files

This directory contains translation files for the Woo Nalda Sync plugin.

## Generating POT File

Run the following command from the plugin root directory:

```bash
wp i18n make-pot . languages/woo-nalda-sync.pot --domain=woo-nalda-sync
```

## Creating Translations

1. Copy `woo-nalda-sync.pot` to `woo-nalda-sync-{locale}.po`
2. Translate the strings using a PO editor (like Poedit)
3. Generate the MO file

Example for French:
- `woo-nalda-sync-fr_FR.po`
- `woo-nalda-sync-fr_FR.mo`
