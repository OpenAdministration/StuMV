<?php

namespace App\Livewire\Concerns;

/**
 * Shared by NewIdentityProvider/EditIdentityProvider: lets admins enter extra
 * authorize-URL query parameters (e.g. Keycloak's kc_idp_hint) as plain
 * "key=value" lines instead of raw JSON.
 */
trait ParsesExtraAuthorizeParams
{
    private function parseExtraAuthorizeParams(string $input): array
    {
        $params = [];

        foreach (preg_split("/\r\n|\n|\r/", $input) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $params[trim($key)] = trim($value);
        }

        return $params;
    }

    private function formatExtraAuthorizeParams(?array $params): string
    {
        if (empty($params)) {
            return '';
        }

        $lines = [];
        foreach ($params as $key => $value) {
            $lines[] = "{$key}={$value}";
        }

        return implode("\n", $lines);
    }

    private function validateExtraAuthorizeParamsLine(string $attribute, mixed $value, \Closure $fail): void
    {
        foreach (preg_split("/\r\n|\n|\r/", (string) $value) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! str_contains($line, '=') || str_starts_with($line, '=')) {
                $fail(__('identity_providers.extra_authorize_params_invalid_line', ['line' => $line]));

                return;
            }
        }
    }
}
