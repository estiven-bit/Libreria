<?php

class AdminMiddleware
{
    public static function requireAdmin(array $user): void
    {
        if (($user['role'] ?? '') !== 'ADMINISTRADOR') {
            Response::json(['error' => 'Forbidden'], 403);
        }
    }
}
