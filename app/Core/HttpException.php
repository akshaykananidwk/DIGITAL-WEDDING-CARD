<?php

declare(strict_types=1);

namespace App\Core;

/** Exception that maps directly to an HTTP status code. */
class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode = 500,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message);
    }

    public static function badRequest(string $message = ''): self
    {
        return new self(422, $message);
    }

    public static function tooManyRequests(string $message = ''): self
    {
        return new self(429, $message);
    }

    private static function defaultMessage(int $code): string
    {
        return match ($code) {
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'You are not allowed to do that.',
            404 => 'Not found.',
            405 => 'Method not allowed.',
            419 => 'Your session expired. Please try again.',
            422 => 'The submitted data is invalid.',
            429 => 'Too many requests. Please slow down.',
            503 => 'The service is temporarily unavailable.',
            default => 'Something went wrong.',
        };
    }
}
