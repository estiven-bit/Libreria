<?php

class RateLimiter
{
    public static function check(string $key, int $limit, int $windowSeconds = 60): bool
    {
        $file = sys_get_temp_dir() . '/rate_' . md5($key) . '.json';
        $now = time();

        if (!file_exists($file)) {
            file_put_contents($file, json_encode(['count' => 1, 'start' => $now]));
            return true;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data || ($now - $data['start']) > $windowSeconds) {
            file_put_contents($file, json_encode(['count' => 1, 'start' => $now]));
            return true;
        }

        if ($data['count'] >= $limit) {
            return false;
        }

        $data['count'] += 1;
        file_put_contents($file, json_encode($data));
        return true;
    }
}
