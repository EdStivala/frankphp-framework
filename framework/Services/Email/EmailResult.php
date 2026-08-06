<?php
namespace Frank\Services\Email;

/**
 * EmailResult
 * 
 * Response object for email operations.
 * Provides details about email sending success/failure.
 */
class EmailResult
{
    public bool $success;
    public string $message;
    public ?string $emailId;
    public ?string $error;

    public function __construct(
        bool $success,
        string $message = '',
        ?string $emailId = null,
        ?string $error = null
    ) {
        $this->success = $success;
        $this->message = $message;
        $this->emailId = $emailId;
        $this->error = $error;
    }

    /**
     * Create a successful email result
     */
    public static function success(string $message = 'Email sent successfully', ?string $emailId = null): self
    {
        return new self(true, $message, $emailId, null);
    }

    /**
     * Create a failed email result
     */
    public static function failure(string $error): self
    {
        return new self(false, 'Email failed to send', null, $error);
    }
}
