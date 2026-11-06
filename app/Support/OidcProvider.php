<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use RuntimeException;

/**
 * Generic OpenID Connect provider for Socialite, configured from the issuer's discovery document.
 *
 * Uses the authorization code flow with PKCE and reads the user from the userinfo endpoint, so the
 * ID token's signature doesn't need checking (OpenID Connect Core 3.1.3.7 lets a client that gets
 * its tokens straight from the token endpoint over TLS rely on that connection instead).
 */
class OidcProvider extends AbstractProvider
{
    /**
     * @var array<int, string>
     */
    protected $scopes = ['openid', 'profile', 'email'];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    protected string $issuer = '';

    /**
     * Set the issuer whose discovery document configures this provider.
     */
    public function setIssuer(string $issuer): static
    {
        $this->issuer = rtrim($issuer, '/');

        return $this;
    }

    protected function getAuthUrl(mixed $state): string
    {
        return $this->buildAuthUrlFromBase($this->discovery()['authorization_endpoint'], $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->discovery()['token_endpoint'];
    }

    /**
     * Exchange the authorization code for tokens, authenticating the way the issuer supports.
     *
     * @return array<string, mixed>
     */
    public function getAccessTokenResponse(mixed $code): array
    {
        $fields = $this->getTokenFields($code);
        $request = Http::asForm()->acceptJson();

        if ($this->usesClientSecretBasic()) {

            unset($fields['client_secret']);

            // RFC 6749 2.3.1: both parts are form-encoded before they're joined
            $request = $request->withHeaders([
                'Authorization' => 'Basic '.base64_encode(urlencode((string) $this->clientId).':'.urlencode((string) $this->clientSecret)),
            ]);
        }

        return $request->post($this->getTokenUrl(), $fields)->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken(mixed $token): array
    {
        return Http::withToken((string) $token)
            ->acceptJson()
            ->get($this->discovery()['userinfo_endpoint'])
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'],
            'nickname' => $user['preferred_username'] ?? null,
            'name' => $user['name'] ?? $user['preferred_username'] ?? $user['email'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }

    /**
     * The spec's default when the issuer doesn't list its methods is client_secret_basic, so only
     * fall back to sending the secret in the body when the issuer says it can't take basic auth.
     */
    protected function usesClientSecretBasic(): bool
    {
        $methods = $this->discovery()['token_endpoint_auth_methods_supported'] ?? ['client_secret_basic'];

        return in_array('client_secret_basic', $methods) || ! in_array('client_secret_post', $methods);
    }

    /**
     * Fetch (and cache) the issuer's discovery document.
     *
     * @return array{issuer: string, authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string, token_endpoint_auth_methods_supported?: array<int, string>}
     */
    protected function discovery(): array
    {
        if ($this->issuer === '') {
            throw new RuntimeException('OIDC_ISSUER is not set.');
        }

        return Cache::remember('oidc-discovery:'.sha1($this->issuer), now()->addHour(), function (): array {

            $document = Http::acceptJson()
                ->get($this->issuer.'/.well-known/openid-configuration')
                ->throw()
                ->json();

            if (rtrim((string) Arr::get($document, 'issuer'), '/') !== $this->issuer) {
                throw new RuntimeException("The OIDC discovery document's issuer doesn't match OIDC_ISSUER ({$this->issuer}).");
            }

            foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $endpoint) {
                if (empty($document[$endpoint])) {
                    throw new RuntimeException("The OIDC discovery document has no {$endpoint}.");
                }
            }

            return $document;
        });
    }
}
