<?php

namespace App\Support\Api;

class MeteringBodyRedactor
{
    /**
     * @param  list<string>  $redactionKeys
     */
    public function redactAndTruncate(?string $body, array $redactionKeys, int $maxBytes): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $body = json_encode($this->redactArray($decoded, $redactionKeys), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($body === false) {
                $body = '';
            }
        } else {
            $body = $this->redactPlainText($body, $redactionKeys);
        }

        if (strlen($body) <= $maxBytes) {
            return $body;
        }

        return mb_substr($body, 0, $maxBytes);
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $redactionKeys
     * @return array<mixed>
     */
    private function redactArray(array $data, array $redactionKeys): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->shouldRedactKey($key, $redactionKeys)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redactArray($value, $redactionKeys);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    /**
     * @param  list<string>  $redactionKeys
     */
    private function redactPlainText(string $body, array $redactionKeys): string
    {
        foreach ($redactionKeys as $key) {
            $pattern = '/("'.preg_quote($key, '/').'"\s*:\s*")([^"]*)(")/i';
            $body = (string) preg_replace($pattern, '$1[REDACTED]$3', $body);
        }

        return $body;
    }

    /**
     * @param  list<string>  $redactionKeys
     */
    private function shouldRedactKey(string $key, array $redactionKeys): bool
    {
        $normalisedKey = strtolower($key);

        foreach ($redactionKeys as $redactionKey) {
            $normalisedRedactionKey = strtolower($redactionKey);

            if ($normalisedKey === $normalisedRedactionKey || str_contains($normalisedKey, $normalisedRedactionKey)) {
                return true;
            }
        }

        return false;
    }
}
