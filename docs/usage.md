# i18n Usage

`glueful/i18n` binds `translation.manager` for Glueful catalog loading and also
exposes typed extension contracts.

Locale resolution order:

1. Explicit locale passed to the translator.
2. Request query/header override when enabled.
3. `preferred_locale` identity claim.
4. Soft tenant/app locale context.
5. Configured default locale.

Fallback order is requested locale, locale parent/fallback chain, global fallback
locale, then the key itself.

Missing translations return the key. Recording misses is default-off and
rate-limited so production traffic does not write on every request.
