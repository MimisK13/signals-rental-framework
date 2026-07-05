<?php

namespace App\Services\Notifications;

/**
 * SMS segment calculator for GSM-7 encoding (P28 template editor).
 */
class SmsSegmentCounter
{
    /**
     * @return array{characters: int, segments: int, per_segment: int}
     */
    public function analyse(string $text): array
    {
        $length = mb_strlen($text);
        $perSegment = 160;
        $concatenatedPerSegment = 153;

        if ($length === 0) {
            return [
                'characters' => 0,
                'segments' => 0,
                'per_segment' => $perSegment,
            ];
        }

        if ($length <= $perSegment) {
            return [
                'characters' => $length,
                'segments' => 1,
                'per_segment' => $perSegment,
            ];
        }

        $segments = (int) ceil($length / $concatenatedPerSegment);

        return [
            'characters' => $length,
            'segments' => $segments,
            'per_segment' => $concatenatedPerSegment,
        ];
    }
}
