<?php

declare(strict_types=1);

namespace Frank\Services;

use Frank\Core\Clock;
use Frank\Services\Email\EmailAddress;

/**
 * GoogleSignInService (v3.0.0)
 *
 * Framework-owned "Continue with Google" sign-in, written without outside
 * libraries (Google's OpenID Connect server flow, see
 * https://developers.google.com/identity/openid-connect/openid-connect).
 *
 * Step 1, authorizationRequest(): build the address that sends the person to
 * Google's sign-in page, with a random state (anti-forgery) and nonce (replay
 * protection) that the controller keeps in the session and checks when Google
 * sends the person back to the return address (/login/google/callback).
 * Step 2, exchangeCode(): swap the code Google returns for the person's
 * confirmed email address.
 *
 * Keys come from $config['oauth']['google'] (GOOGLE_CLIENT_ID /
 * GOOGLE_CLIENT_SECRET in app/.env). Both are optional; the controller only
 * uses this service when both are set.
 */
class GoogleSignInService
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT         = 'https://oauth2.googleapis.com/token';
    private const ISSUERS                = ['https://accounts.google.com', 'accounts.google.com'];

    /**
     * @param string $clientId      GOOGLE_CLIENT_ID
     * @param string $clientSecret  GOOGLE_CLIENT_SECRET (sent when the code is exchanged)
     * @param string $redirectUri   Absolute return address, e.g. http://localhost:8888/login/google/callback.
     *                              Must match the address registered with Google exactly.
     */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri
    ) {}

    /**
     * A new sign-in request: the Google address to send the person to, plus
     * the state and nonce the caller must keep (in the session) to check the
     * return.
     *
     * @return array{url: string, state: string, nonce: string}
     */
    public function authorizationRequest(): array
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $query = http_build_query([
            'client_id'     => $this->clientId,
            'response_type' => 'code',
            'scope'         => 'openid email',
            'redirect_uri'  => $this->redirectUri,
            'state'         => $state,
            'nonce'         => $nonce,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url'   => self::AUTHORIZATION_ENDPOINT . '?' . $query,
            'state' => $state,
            'nonce' => $nonce,
        ];
    }

    /**
     * Step 2: swap the one-time code Google sent back for the person's email
     * address. The ID token comes straight from Google's token endpoint over
     * https, so Google says its signature need not be checked; issuer,
     * audience, expiry and nonce are still checked here.
     *
     * Success data: ['email' => normalised address]. Fails with errorType
     * 'system' (no message; the caller shows its own) when anything is wrong,
     * and with 'validation' and a message — the owner's rule — when Google has
     * not confirmed the address.
     */
    public function exchangeCode(string $code, string $expectedNonce): ServiceResult
    {
        $invalid = ServiceResult::failure('', 'system');

        if ($code === '' || $expectedNonce === '') {
            return $invalid;
        }

        $tokens = $this->postToTokenEndpoint([
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        $claims = $this->decodeIdToken((string) ($tokens['id_token'] ?? ''));
        if ($claims === null) {
            return $invalid;
        }

        $issuerOk   = in_array($claims['iss'] ?? '', self::ISSUERS, true);
        $audienceOk = ($claims['aud'] ?? '') === $this->clientId;
        $notExpired = (int) ($claims['exp'] ?? 0) > Clock::nowUtc()->getTimestamp();
        $nonceOk    = hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''));

        if (!$issuerOk || !$audienceOk || !$notExpired || !$nonceOk) {
            return $invalid;
        }

        $email = EmailAddress::normalise((string) ($claims['email'] ?? ''));
        if (($claims['email_verified'] ?? false) !== true || !EmailAddress::isValid($email)) {
            return ServiceResult::failure('Google did not confirm this email address.', 'validation');
        }

        return ServiceResult::success('', ['email' => $email]);
    }

    /** POST to Google's token endpoint; the decoded JSON reply, or [] on any failure. */
    private function postToTokenEndpoint(array $fields): array
    {
        $curl = curl_init(self::TOKEN_ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body   = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($body === false || $status !== 200) {
            error_log('[GoogleSignInService] Token exchange failed: HTTP ' . $status . ' ' . curl_error($curl));
            return [];
        }

        $decoded = json_decode((string) $body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** The claims (middle part) of an ID token, or null if it is not a readable token. */
    private function decodeIdToken(string $idToken): ?array
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return null;
        }

        $json   = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $claims = $json === false ? null : json_decode($json, true);
        return is_array($claims) ? $claims : null;
    }
}
