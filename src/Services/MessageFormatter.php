<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

final class MessageFormatter
{
    /** @param array<string, scalar|null> $parameters */
    public function format(string $message, array $parameters, string $locale): string
    {
        if (class_exists(\MessageFormatter::class) && str_contains($message, 'plural')) {
            $formatter = new \MessageFormatter($locale, $message);
            $formatted = $formatter->format($parameters);
            if (is_string($formatted)) {
                return $formatted;
            }
        }

        $message = $this->simplePlural($message, $parameters);
        foreach ($parameters as $key => $value) {
            $message = str_replace('{' . $key . '}', (string) $value, $message);
        }

        return $message;
    }

    /** @param array<string, scalar|null> $parameters */
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
