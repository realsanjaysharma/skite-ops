<?php

/**
 * BaseController
 *
 * Purpose:
 * Provides protected helper methods shared by all API controllers.
 * Eliminates repeated boilerplate for request parsing, actor extraction, and method enforcement.
 *
 * Architecture Rule:
 * - All controllers extend this class.
 * - This class contains NO business logic and NO SQL.
 * - Each controller still controls its own method flow — nothing is forced abstract.
 *
 * Usage in controllers:
 *   class FooController extends BaseController { ... }
 *
 *   $input       = $this->getInput();
 *   $actor       = $this->getActor();
 *   if (!$this->requireMethod('POST')) return;
 */
class BaseController
{
    /**
     * Decode a JSON body or fall back to $_POST.
     *
     * Handles both explicit 'application/json' Content-Type and bare JSON bodies
     * (common in some Postman/curl workflows without explicit headers).
     *
     * @return array
     */
    protected function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            $raw     = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        // Try parsing raw input anyway — some clients send JSON without the Content-Type header.
        $raw = file_get_contents('php://input');
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $_POST;
    }

    /**
     * Return the authenticated actor's user_id and role_key from the session.
     *
     * These values are already validated by AuthMiddleware before any controller runs.
     *
     * @return array{user_id: int, role_key: string}
     */
    protected function getActor(): array
    {
        return [
            'user_id'  => (int)   ($_SESSION['user_id']  ?? 0),
            'role_key' => (string) ($_SESSION['role_key'] ?? ''),
        ];
    }

    /**
     * Enforce a required HTTP method.
     *
     * Sends a 405 response and returns false if the method does not match.
     * Caller must `return` immediately when this returns false.
     *
     * Example:
     *   if (!$this->requireMethod('POST')) return;
     *
     * @param string $method  Expected HTTP method (case-insensitive).
     * @return bool
     */
    protected function requireMethod(string $method): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
            Response::error('Method not allowed', 405);
            return false;
        }
        return true;
    }
}
