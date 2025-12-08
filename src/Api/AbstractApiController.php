<?php

declare(strict_types=1);

namespace Snaply\Api;

use Snaply\Exception\EntityNotFoundException;
use Snaply\Exception\ValidationException;

/**
 * Base API controller with common functionality.
 *
 * Provides JSON response formatting, error handling, session token extraction,
 * and HTTP method validation.
 */
abstract class AbstractApiController
{
    /**
     * Send a JSON success response.
     *
     * @param array<string, mixed> $data Response data
     * @param string|null $message Optional success message
     * @param int $statusCode HTTP status code (default: 200)
     */
    protected function jsonSuccess(array $data, ?string $message = null, int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        $this->sendJsonResponse($response, $statusCode);
    }

    /**
     * Send a JSON error response.
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param array<string, mixed>|null $details Optional error details
     * @param int $statusCode HTTP status code (default: 400)
     */
    protected function jsonError(
        string $code,
        string $message,
        ?array $details = null,
        int $statusCode = 400
    ): void {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        $this->sendJsonResponse($response, $statusCode);
    }

    /**
     * Send a JSON response with appropriate headers.
     *
     * @param array<string, mixed> $data Response data
     * @param int $statusCode HTTP status code
     */
    private function sendJsonResponse(array $data, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Get session token from cookie or header.
     *
     * @return string The session token
     */
    protected function getSessionToken(): string
    {
        // Try cookie first (preferred)
        if (isset($_COOKIE['session_token']) && is_string($_COOKIE['session_token'])) {
            $token = trim($_COOKIE['session_token']);
            if ($token !== '') {
                return $token;
            }
        }

        // Try custom header as fallback
        $headers = getallheaders();
        if ($headers !== false && isset($headers['X-Session-Token'])) {
            $token = trim($headers['X-Session-Token']);
            if ($token !== '') {
                return $token;
            }
        }

        $this->jsonError(
            'MISSING_SESSION_TOKEN',
            'Session token is required',
            null,
            400
        );
        exit; // Never reached due to exit in jsonError, but makes static analysis happy
    }

    /**
     * Get or create session token.
     *
     * If no session token exists, generates a new one and sets the cookie.
     *
     * @return string The session token
     */
    protected function getOrCreateSessionToken(): string
    {
        // Check if token already exists
        if (isset($_COOKIE['session_token']) && is_string($_COOKIE['session_token'])) {
            $token = trim($_COOKIE['session_token']);
            if ($token !== '') {
                return $token;
            }
        }

        // Generate new secure token
        $token = bin2hex(random_bytes(32)); // 64 hex characters

        // Set secure HTTP-only cookie
        $cookieOptions = [
            'expires' => time() + (int) (getenv('SESSION_COOKIE_LIFETIME') ?: 2592000), // 30 days default
            'path' => '/',
            'domain' => getenv('SESSION_COOKIE_DOMAIN') ?: '',
            'secure' => (bool) (getenv('SESSION_COOKIE_SECURE') ?: true),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        setcookie('session_token', $token, $cookieOptions);

        return $token;
    }

    /**
     * Validate that the request method matches the expected method.
     *
     * @param string $expectedMethod Expected HTTP method (GET, POST, etc.)
     */
    protected function validateMethod(string $expectedMethod): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method !== $expectedMethod) {
            $this->jsonError(
                'METHOD_NOT_ALLOWED',
                "This endpoint only supports {$expectedMethod} requests",
                null,
                405
            );
        }
    }

    /**
     * Handle exceptions and send appropriate JSON error responses.
     *
     * @param \Throwable $e The exception to handle
     */
    protected function handleException(\Throwable $e): void
    {
        // ValidationException - 400 Bad Request
        if ($e instanceof ValidationException) {
            $this->jsonError(
                'VALIDATION_ERROR',
                'Validation failed',
                $e->getErrors(),
                400
            );
        }

        // EntityNotFoundException - 404 Not Found
        if ($e instanceof EntityNotFoundException) {
            $this->jsonError(
                'NOT_FOUND',
                $e->getMessage(),
                null,
                404
            );
        }

        // Log unexpected errors (in production, this should use proper logging)
        error_log(sprintf(
            'API Error: %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        // Generic error response for unexpected exceptions
        $this->jsonError(
            'INTERNAL_ERROR',
            'An unexpected error occurred',
            null,
            500
        );
    }

    /**
     * Parse JSON request body.
     *
     * @return array<string, mixed> Parsed JSON data
     */
    protected function getJsonInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') === false) {
            // For POST requests without body, return empty array
            return [];
        }

        $input = file_get_contents('php://input');
        if ($input === false || $input === '') {
            return [];
        }

        $data = json_decode($input, true);

        if (!is_array($data)) {
            $this->jsonError(
                'INVALID_JSON',
                'Request body must be valid JSON',
                null,
                400
            );
        }

        return $data;
    }
}
