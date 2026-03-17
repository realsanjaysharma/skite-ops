<?php

/**
 * AuthService
 *
 * Purpose:
 * Handles user authentication logic using UserRepository.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - No SQL is allowed in this service
 * - Authentication uses active, non-deleted users only
 * - Password hashes must never be returned to callers
 * - Authentication failures must use the same error message
 */

require_once __DIR__ . '/../repositories/UserRepository.php';

class AuthService
{
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    /**
     * Authenticate user with normalized email and password verification.
     */
    public function login($email, $password)
    {
        if (!is_string($email)) {
            throw new InvalidArgumentException('Email must be a string.');
        }

        if (!is_string($password)) {
            throw new InvalidArgumentException('Password must be a string.');
        }

        if (trim($email) === '') {
            throw new InvalidArgumentException('Email is required.');
        }

        if (trim($password) === '') {
            throw new InvalidArgumentException('Password is required.');
        }

        $normalizedEmail = strtolower(trim($email));

        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email format is invalid.');
        }

        $user = $this->userRepository->getUserByEmail($normalizedEmail);

        if (!$user) {
            throw new RuntimeException('Invalid email or password');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid email or password');
        }

        unset($user['password_hash']);

        return $user;
    }
}
