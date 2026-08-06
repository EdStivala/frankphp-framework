<?php
namespace Frank\Services;

/**
 * ServiceResult
 * 
 * Standardized response object for all service layer operations.
 * Provides consistent structure for success/failure handling across services.
 */
class ServiceResult
{
    public bool $success;
    public string $message;
    public array $data;
    public ?string $errorType;

    public function __construct(
        bool $success,
        string $message = '',
        array $data = [],
        ?string $errorType = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->data = $data;
        $this->errorType = $errorType;
    }

    /**
     * Create a successful result
     */
    public static function success(string $message = '', array $data = []): self
    {
        return new self(true, $message, $data, null);
    }

    /**
     * Create a failed result
     */
    public static function failure(
        string $message,
        ?string $errorType = null,
        array $data = []
    ): self {
        return new self(false, $message, $data, $errorType);
    }

    /**
     * Check if this is a validation error
     */
    public function isValidationError(): bool
    {
        return $this->errorType === 'validation';
    }

    /**
     * Check if this is a rate limit error
     */
    public function isRateLimitError(): bool
    {
        return $this->errorType === 'rate_limit';
    }

    /**
     * Check if this is a system error
     */
    public function isSystemError(): bool
    {
        return $this->errorType === 'system';
    }

    /**
     * Get data value by key
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}
