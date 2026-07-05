<?php

namespace App\Support;

class JsonSyntaxHighlight
{
    public static function highlight(string $json): string
    {
        if ($json === '') {
            return '';
        }

        $pattern = '/("(\\\\u[a-fA-F0-9]{4}|\\\\[^u]|[^\\\\"])*")(\s*:)?|\b(true|false)\b|-?\d+(?:\.\d*)?(?:[eE][+-]?\d+)?|\bnull\b/S';

        $result = '';
        $offset = 0;

        while (preg_match($pattern, $json, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $match[0][1];
            $matched = $match[0][0];

            $result .= htmlspecialchars(substr($json, $offset, $start - $offset), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if (isset($match[3]) && $match[3][0] !== '') {
                $result .= '<span class="s-json-key">'.htmlspecialchars($match[1][0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>:';
            } elseif (isset($match[1]) && str_starts_with($match[1][0], '"')) {
                $result .= '<span class="s-json-string">'.htmlspecialchars($match[1][0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>';
            } elseif ($matched === 'true' || $matched === 'false') {
                $result .= '<span class="s-json-bool">'.$matched.'</span>';
            } elseif ($matched === 'null') {
                $result .= '<span class="s-json-null">null</span>';
            } else {
                $result .= '<span class="s-json-number">'.htmlspecialchars($matched, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>';
            }

            $offset = $start + strlen($matched);
        }

        $result .= htmlspecialchars(substr($json, $offset), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return $result;
    }

    public static function isJson(string $value): bool
    {
        if ($value === '' || $value === '(empty body)') {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
