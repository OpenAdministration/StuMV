<?php

namespace App\Support;

use League\OAuth2\Client\OptionProvider\PostAuthOptionProvider;
use League\OAuth2\Client\Provider\AbstractProvider;

/**
 * HTTP Basic client authentication for the token endpoint. league ships one
 * of these, but it base64-encodes the raw id and secret, where RFC 6749 2.3.1
 * requires each to be form-urlencoded first. Providers that follow the letter
 * of the spec - Authelia documents this as a common client bug - reject the
 * raw form for any secret containing a character that needs encoding.
 */
class FormEncodedBasicAuthOptionProvider extends PostAuthOptionProvider
{
    /**
     * @param  string  $method
     * @return array<string, mixed>
     */
    #[\Override]
    public function getAccessTokenOptions($method, array $params)
    {
        // urlencode(), not rawurlencode(): the spec names the
        // application/x-www-form-urlencoded algorithm, which writes a space
        // as "+" rather than "%20".
        $credentials = sprintf(
            '%s:%s',
            urlencode((string) ($params['client_id'] ?? '')),
            urlencode((string) ($params['client_secret'] ?? ''))
        );

        // Sending them in the body as well is what strict providers reject.
        unset($params['client_id'], $params['client_secret']);

        $options = parent::getAccessTokenOptions(AbstractProvider::METHOD_POST, $params);
        $options['headers']['Authorization'] = 'Basic '.base64_encode($credentials);

        return $options;
    }
}
