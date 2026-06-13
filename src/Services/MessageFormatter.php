<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

final class MessageFormatter
{
    /**
     * Matches ICU argument syntax for the block types we route to ICU:
     * "{name, plural, ...}", "{name, select, ...}", "{name, selectordinal, ...}".
     */
    private const ICU_ARGUMENT_PATTERN =
        '/\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*,\s*(?:plural|selectordinal|select)\s*,/';

    /** @param array<string, scalar|null> $parameters */
    public function format(string $message, array $parameters, string $locale): string
    {
        if (class_exists(\MessageFormatter::class) && $this->usesIcuSyntax($message)) {
            try {
                $formatter = new \MessageFormatter($locale, $message);
                $formatted = $formatter->format($parameters);
                if (is_string($formatted)) {
                    return $formatted;
                }
            } catch (\Throwable) {
                // Stored translations are editor-controlled data; malformed ICU
                // patterns should degrade to simple substitution, not 500.
            }
        }

        $message = $this->simplePlural($message, $parameters);
        foreach ($parameters as $key => $value) {
            $message = str_replace('{' . $key . '}', (string) $value, $message);
        }

        return $message;
    }

    private function usesIcuSyntax(string $message): bool
    {
        return preg_match(self::ICU_ARGUMENT_PATTERN, $message) === 1;
    }

    /**
     * No-intl fallback: handles only the two-branch "one {...} other {...}"
     * plural form (with "#" count substitution); select/selectordinal and
     * locale-specific plural categories require ext-intl.
     *
     * @param array<string, scalar|null> $parameters
     */
    private function simplePlural(string $message, array $parameters): string
    {
        return (string) preg_replace_callback(
            '/\{(\w+),\s*plural,\s*one\s*\{([^{}]*)}\s*other\s*\{([^{}]*)}}/',
            static function (array $m) use ($parameters): string {
                $count = (int) ($parameters[$m[1]] ?? 0);
                $choice = $count === 1 ? $m[2] : $m[3];

                return str_replace('#', (string) $count, $choice);
            },
            $message
        );
    }
}
