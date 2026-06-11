<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Auth\UserIdentity;
use Glueful\Extensions\I18n\Contracts\LocaleResolverInterface;
use Glueful\Extensions\I18n\Support\LocaleContext;
use Symfony\Component\HttpFoundation\Request;

final class LocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private ApplicationContext $context,
        private LocaleManager $locales,
    ) {
    }

    public function resolveLocale(mixed $context = null): string
    {
        if (is_string($context) && $context !== '') {
            return $this->allow($context) ? $context : $this->locales->default();
        }

        if ($context instanceof LocaleContext) {
            return $this->fromLocaleContext($context);
        }

        if ($context instanceof Request) {
            return $this->fromRequest($context);
        }

        return $this->locales->default();
    }

    private function fromLocaleContext(LocaleContext $context): string
    {
        $preferred = $context->claims['preferred_locale'] ?? null;
        $candidates = [
            $context->explicitLocale,
            $context->request !== null ? $this->fromRequest($context->request, false) : null,
            is_scalar($preferred) ? (string) $preferred : null,
            $context->tenantLocale,
            $context->appLocale,
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && $locale !== '' && $this->allow($locale)) {
                return $locale;
            }
        }

        return $this->locales->default();
    }

    private function fromRequest(Request $request, bool $fallback = true): string
    {
        if ((bool) \config($this->context, 'i18n.request_override', true)) {
            $query = $request->query->get('locale') ?? $request->headers->get('X-Locale');
            if (is_scalar($query) && (string) $query !== '' && $this->allow((string) $query)) {
                return (string) $query;
            }
        }

        $user = $request->attributes->get('auth.user');
        if ($user instanceof UserIdentity) {
            $claims = $user->claims();
            $preferred = $claims['preferred_locale'] ?? null;
            if (is_scalar($preferred) && $this->allow((string) $preferred)) {
                return (string) $preferred;
            }
        }

        $tenantLocale = $request->attributes->get('tenant.locale');
        if (is_scalar($tenantLocale) && $this->allow((string) $tenantLocale)) {
            return (string) $tenantLocale;
        }

        return $fallback ? $this->locales->default() : '';
    }

    private function allow(string $locale): bool
    {
        foreach ($this->locales->enabled() as $row) {
            if ((string) $row['code'] === $locale) {
                return true;
            }
        }

        $configured = (array) \config($this->context, 'i18n.enabled_locales', ['en']);

        return in_array($locale, array_map('strval', $configured), true);
    }
}
