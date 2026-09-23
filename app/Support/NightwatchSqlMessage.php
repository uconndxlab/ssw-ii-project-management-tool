<?php

namespace App\Support;

final class NightwatchSqlMessage
{
    public static function trim(string $message): string
    {
        if (! str_starts_with($message, 'SQLSTATE')) {
            return $message;
        }

        $parts = preg_split('/\s+DETAIL:|\s+\(Connection:/', $message);

        if (! is_array($parts)) {
            return $message;
        }

        return $parts[0];
    }
}
