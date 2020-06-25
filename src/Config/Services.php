<?php namespace CI4Xpander_API\Config;

class Services extends \CodeIgniter\Config\Services
{
    public static function isVerifiedToken(\CodeIgniter\HTTP\IncomingRequest $request)
    {
        $authorizationHeader = $request->getHeader('Authorization');
        if (!is_null($authorizationHeader)) {
            list($type, $value) = explode(' ', $authorizationHeader->getValue());

            if ($type == 'Bearer') {

            }
        }

        return false;
    }

    public static function tokenBuilder(bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('tokenBuilder');
        }

        return new \Jose\Component\Signature\JWSBuilder(\Config\Services::tokenAlgorithmManager());
    }

    public static function tokenAlgorithmManager(bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('tokenAlgorithmManager');
        }

        return new \Jose\Component\Core\AlgorithmManager([
            new \Jose\Component\Signature\Algorithm\HS256()
        ]);
    }

    public static function jwk(bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('jwk');
        }

        return new \Jose\Component\Core\JWK((array) config('Api'));
    }

    public static function tokenSerializer(bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('tokenSerializer');
        }

        return new \Jose\Component\Signature\Serializer\CompactSerializer();
    }

    public static function tokenVerifier(bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('tokenVerifier');
        }

        return new \Jose\Component\Signature\JWSVerifier(
            \Config\Services::tokenAlgorithmManager()
        );
    }
}
