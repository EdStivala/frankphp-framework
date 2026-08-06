<?php
namespace Frank\Services\Email;

/**
 * EmailMessage
 * 
 * Value object representing an email message to be sent.
 * Used to pass email data between services and the EmailService.
 */
class EmailMessage
{
	public string $credentialsUserName; 
	public string $credentialsUserSecret;
    public string $to;
    public string $toName;
    public string $subject;
    public string $htmlBody;
    public ?string $textBody;
    public string $from;
    public string $fromName;
    public ?string $replyTo;

    public function __construct(
    	string $credentialsUserName,
    	string $credentialsUserSecret,
        string $to,
        string $subject,
        string $htmlBody,
        string $toName = '',
        ?string $textBody = null,
        string $from = 'noreply@bitfitter.me',
        string $fromName = 'BitFitter',
        ?string $replyTo = 'hello@bitfitter.me'
    ) {
		$this->credentialsUserName = $credentialsUserName;
		$this->credentialsUserSecret = $credentialsUserSecret;
        $this->to = $to;
        $this->toName = $toName;
        $this->subject = $subject;
        $this->htmlBody = $htmlBody;
        $this->textBody = $textBody;
        $this->from = $from;
        $this->fromName = $fromName;
        $this->replyTo = $replyTo;
    }

    /**
     * Validate email message has required fields
     */
    public function isValid(): bool
    {
        return !empty($this->to) 
            && !empty($this->subject) 
            && !empty($this->htmlBody)
            && filter_var($this->to, FILTER_VALIDATE_EMAIL);
    }
}
