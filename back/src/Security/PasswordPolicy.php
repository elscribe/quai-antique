<?php

namespace App\Security;

final class PasswordPolicy
{
    public static function isValid(string $password): bool
    {
        return strlen($password) >= 8 && strlen($password) <= 128
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9\s]/', $password) === 1;
    }
}
