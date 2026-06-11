<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Http;

use Glueful\Validation\ValidationException;

/**
 * Validates HTTP payloads for the i18n management API.
 *
 * Every method either returns a whitelisted, normalized payload or throws the
 * framework's ValidationException (rendered as HTTP 422). Unknown fields are
 * stripped so raw request bodies can never reach the SQL layer.
 */
final class I18nPayloadValidator
{
    private const LOCALE_CODE_PATTERN = '/\A[a-z]{2,3}(-[a-z0-9]{2,8}){0,2}\z/i';
    private const LOCALE_CODE_MAX_LENGTH = 16;
    private const DIRECTIONS = ['ltr', 'rtl'];

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function validateLocaleCreate(array $payload): array
    {
        $errors = [];
        $validated = [];

        $code = $this->localeCode($payload['code'] ?? null, 'code', $errors, required: true);
        if ($code !== null) {
            $validated['code'] = $code;
        }

        $name = $this->requiredString($payload['name'] ?? null, 'name', 255, $errors);
        if ($name !== null) {
            $validated['name'] = $name;
        }

        $validated += $this->optionalLocaleFields($payload, $errors);

        $this->throwIfErrors($errors);

        return $validated;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function validateLocaleUpdate(array $payload): array
    {
        $errors = [];
        $validated = [];

        if (array_key_exists('code', $payload)) {
            $errors['code'][] = 'code cannot be changed.';
        }

        if (array_key_exists('name', $payload)) {
            $name = $this->requiredString($payload['name'], 'name', 255, $errors);
            if ($name !== null) {
                $validated['name'] = $name;
            }
        }

        $validated += $this->optionalLocaleFields($payload, $errors);

        if ($errors === [] && $validated === []) {
            $errors['payload'][] = 'At least one updatable field is required.';
        }

        $this->throwIfErrors($errors);

        return $validated;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{domain:string,locale:string,key:string,value:string}
     */
    public function validateTranslationUpsert(array $payload): array
    {
        $errors = [];

        $key = $this->requiredString($payload['key'] ?? null, 'key', 255, $errors);
        $value = $this->requiredString($payload['value'] ?? null, 'value', null, $errors);
        $domain = array_key_exists('domain', $payload)
            ? $this->requiredString($payload['domain'], 'domain', 120, $errors)
            : 'messages';
        $locale = array_key_exists('locale', $payload)
            ? $this->localeCode($payload['locale'], 'locale', $errors, required: true)
            : 'en';

        $this->throwIfErrors($errors);

        return [
            'domain' => (string) $domain,
            'locale' => (string) $locale,
            'key' => (string) $key,
            'value' => (string) $value,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{value:string}
     */
    public function validateTranslationValue(array $payload): array
    {
        $errors = [];
        $value = $this->requiredString($payload['value'] ?? null, 'value', null, $errors);

        $this->throwIfErrors($errors);

        return ['value' => (string) $value];
    }

    /** @param array<string,mixed> $payload */
    public function validateImport(array $payload): string
    {
        $errors = [];
        $path = $this->requiredString($payload['path'] ?? null, 'path', null, $errors);

        $this->throwIfErrors($errors);

        return (string) $path;
    }

    /**
     * Validates the optional locale fields shared by create and update.
     *
     * @param array<string,mixed> $payload
     * @param array<string,list<string>> $errors
     * @return array<string,mixed>
     */
    private function optionalLocaleFields(array $payload, array &$errors): array
    {
        $validated = [];

        if (array_key_exists('native_name', $payload)) {
            $validated['native_name'] = $this->nullableString($payload['native_name'], 'native_name', 255, $errors);
        }

        foreach (['enabled', 'is_default'] as $flag) {
            if (array_key_exists($flag, $payload)) {
                $bool = $this->boolean($payload[$flag], $flag, $errors);
                if ($bool !== null) {
                    $validated[$flag] = $bool;
                }
            }
        }

        if (array_key_exists('fallback_locale', $payload)) {
            $validated['fallback_locale'] = $this->localeCode(
                $payload['fallback_locale'],
                'fallback_locale',
                $errors,
                required: false
            );
        }

        if (array_key_exists('direction', $payload)) {
            $direction = is_scalar($payload['direction']) ? strtolower((string) $payload['direction']) : null;
            if ($direction === null || !in_array($direction, self::DIRECTIONS, true)) {
                $errors['direction'][] = 'direction must be one of: ltr, rtl.';
            } else {
                $validated['direction'] = $direction;
            }
        }

        if (array_key_exists('region', $payload)) {
            $validated['region'] = $this->nullableString($payload['region'], 'region', 16, $errors);
        }

        // Drop keys whose value failed validation (recorded in $errors).
        return array_filter(
            $validated,
            static fn(string $field): bool => !isset($errors[$field]),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Validates a locale code; returns null when invalid (error recorded) or
     * when an optional code is explicitly cleared (null/empty).
     *
     * @param array<string,list<string>> $errors
     */
    private function localeCode(mixed $value, string $field, array &$errors, bool $required): ?string
    {
        if ($value === null || $value === '') {
            if ($required) {
                $errors[$field][] = sprintf('%s is required.', $field);
            }

            return null;
        }

        if (
            !is_scalar($value)
            || strlen((string) $value) > self::LOCALE_CODE_MAX_LENGTH
            || preg_match(self::LOCALE_CODE_PATTERN, (string) $value) !== 1
        ) {
            $errors[$field][] = sprintf('%s must be a valid locale code (e.g. en, fr-CA).', $field);

            return null;
        }

        return (string) $value;
    }

    /** @param array<string,list<string>> $errors */
    private function requiredString(mixed $value, string $field, ?int $maxLength, array &$errors): ?string
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            $errors[$field][] = sprintf('%s must be a non-empty string.', $field);

            return null;
        }

        $string = (string) $value;
        if ($maxLength !== null && strlen($string) > $maxLength) {
            $errors[$field][] = sprintf('%s must be %d characters or fewer.', $field, $maxLength);

            return null;
        }

        return $string;
    }

    /** @param array<string,list<string>> $errors */
    private function nullableString(mixed $value, string $field, int $maxLength, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->requiredString($value, $field, $maxLength, $errors);
    }

    /** @param array<string,list<string>> $errors */
    private function boolean(mixed $value, string $field, array &$errors): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $bool = is_scalar($value)
            ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        if ($bool === null) {
            $errors[$field][] = sprintf('%s must be a boolean.', $field);
        }

        return $bool;
    }

    /** @param array<string,list<string>> $errors */
    private function throwIfErrors(array $errors): void
    {
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
