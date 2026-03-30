<?php

class JsonBodyMiddleware
{
    public static function parse(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}
