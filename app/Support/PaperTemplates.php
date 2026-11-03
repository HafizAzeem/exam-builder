<?php

namespace App\Support;

final class PaperTemplates
{
    public const IDS = [1, 'board', 'federal', 'academy', 'urdu', 'entry'];

    public static function normalize(mixed $value): int|string
    {
        return in_array($value, self::IDS, true) ? $value : 1;
    }
}
