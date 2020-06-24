<?php namespace CI4Xpander_API\Config;

class Services extends \CodeIgniter\Config\Services
{
    public static function validateJWS(\CodeIgniter\HTTP\IncomingRequest $request)
    {
        $authorizationHeader = $request->getHeader('Authorization');
        if (!is_null($authorizationHeader)) {
            list($type, $value) = explode(' ', $authorizationHeader->getValue());

            if ($type == 'Bearer') {

            }
        }

        return false;
    }

    public static function jwk(bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('jwk');
        }

        return \Jose\Component\KeyManagement\JWKFactory::createFromSecret(env('api.secret_key', 'ci4xpander'), [
            'alg' => 'HS256',
            'use' => 'sig'
        ]);
    }
}