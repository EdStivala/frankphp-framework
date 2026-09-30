<?php

declare(strict_types=1);

namespace Frank\Services;

/**
 * TenantNameGenerator
 *
 * Framework-owned, pure helper (no DB, no Clock). Derives a default tenant
 * name and slug candidate from a signup email address, following the usual
 * B2B SaaS convention:
 *
 *   jane@acme-corp.com  → "Acme Corp"          / acme-corp
 *   jane.doe@gmail.com  → "Jane Doe's Workspace" / jane-does-workspace
 *
 * Business domains name the tenant after the organisation; free-mail
 * domains fall back to a personal workspace. The owner can rename the
 * tenant afterwards in Tenant Settings. Slug uniqueness is NOT handled
 * here — TenantService::create() owns that.
 *
 * Introduced in FrankPHP v2.1.0.
 */
class TenantNameGenerator
{
    /** Consumer mailbox providers — never used as an organisation name. */
    public const FREE_MAIL_DOMAINS = [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'hotmail.co.uk', 'live.com', 'live.co.uk', 'msn.com',
        'yahoo.com', 'yahoo.co.uk', 'ymail.com', 'rocketmail.com',
        'icloud.com', 'me.com', 'mac.com',
        'aol.com',
        'proton.me', 'protonmail.com', 'pm.me',
        'gmx.com', 'gmx.de', 'gmx.net', 'web.de',
        'mail.com', 'zoho.com', 'yandex.com', 'yandex.ru',
        'fastmail.com', 'hey.com', 'tutanota.com', 'tuta.io',
        'btinternet.com', 'sky.com', 'virginmedia.com', 'talktalk.net',
        'qq.com', '163.com', '126.com',
    ];

    /** @var array<string, true> */
    private array $freeMail;

    /**
     * @param string[] $extraFreeMailDomains  App-specific additions
     */
    public function __construct(array $extraFreeMailDomains = [])
    {
        $domains = array_merge(self::FREE_MAIL_DOMAINS, $extraFreeMailDomains);
        $this->freeMail = array_fill_keys(array_map('strtolower', $domains), true);
    }

    /**
     * @return array{name: string, slug: string}
     */
    public function fromEmail(string $email): array
    {
        $email  = strtolower(trim($email));
        $at     = strrpos($email, '@');
        $local  = $at === false ? $email : substr($email, 0, $at);
        $domain = $at === false ? '' : substr($email, $at + 1);

        $org = $this->isFreeMail($domain) ? '' : $this->organisationLabel($domain);

        $name = $org !== ''
            ? $this->humanise($org)
            : $this->personalWorkspaceName($local);

        $slug = self::slugify($name);

        return [
            'name' => $name,
            'slug' => $slug !== '' ? $slug : 'workspace',
        ];
    }

    public function isFreeMail(string $domain): bool
    {
        return isset($this->freeMail[strtolower($domain)]);
    }

    /**
     * Lowercase, ASCII-only, hyphen-separated, max 140 chars (leaves room
     * for a collision suffix within tenants.slug VARCHAR(150)).
     */
    public static function slugify(string $value): string
    {
        $value = str_replace(["'", '’'], '', $value);
        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value;
        } elseif (function_exists('iconv')) {
            $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        }
        $value = strtolower($value);
        $value = str_replace(["'", '’'], '', $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return rtrim(substr($value, 0, 140), '-');
    }

    /**
     * Registrable label of a domain: "mail.acme-corp.co.uk" → "acme-corp".
     * Drops a two-part public suffix (co.uk, com.au, ...) when present.
     */
    private function organisationLabel(string $domain): string
    {
        $labels = array_values(array_filter(explode('.', $domain), 'strlen'));
        $count  = count($labels);

        if ($count < 2) {
            return $labels[0] ?? '';
        }

        $tld    = $labels[$count - 1];
        $second = $labels[$count - 2];
        $twoPartSuffix = $count >= 3 && strlen($tld) === 2
            && in_array($second, ['co', 'com', 'org', 'net', 'ac', 'gov', 'ltd', 'plc', 'edu'], true);

        return $labels[$count - ($twoPartSuffix ? 3 : 2)];
    }

    private function personalWorkspaceName(string $local): string
    {
        $local = explode('+', $local)[0];
        $person = $this->humanise($local);
        return $person !== '' ? "{$person}'s Workspace" : 'My Workspace';
    }

    private function humanise(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', str_replace(['.', '_', '-'], ' ', $value)) ?? '');
        return mb_substr(ucwords($value), 0, 130);
    }
}
