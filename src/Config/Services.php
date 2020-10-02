<?php namespace CI4Xpander_API\Config;

use CI4Xpander_API\Libraries\RouteCollection;

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

    public static function JWK(bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('JWK');
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

    public static function tokenHeaderCheckerManager($check = [], bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('tokenHeaderCheckerManager', $check);
        }

        return new \Jose\Component\Checker\HeaderCheckerManager(
            array_merge(
                [
                    'alg' => new \Jose\Component\Checker\AlgorithmChecker(
                        [
                            config('Api')->alg,
                        ]
                    ),
                ],
                $check
            ),
            [
                new \Jose\Component\Signature\JWSTokenSupport(),
            ]
        );
    }

    public static function tokenClaimCheckerManager($check = [], bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('tokenClaimCheckerManager', $check);
        }

        return new \Jose\Component\Checker\ClaimCheckerManager([
            'iat' => new \Jose\Component\Checker\IssuedAtChecker(),
            'nbf' => new \Jose\Component\Checker\NotBeforeChecker(),
            'exp' => new \Jose\Component\Checker\ExpirationTimeChecker(),
        ]);
    }

    public static function JWSBuilder($data = [], bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('JWSBuilder', $data);
        }

        $time = time();

        $jws = \Jose\Easy\Build::jws();
        
        $jws->alg(config('Api')->alg);
        $jws->iat($time);
        $jws->nbf($time);
        $jws->exp($data['exp'] ?? ($time + 3600));
        unset($data['exp']);

        if (isset($data['sub'])) {
            $jws->iss($data['sub']);
            unset($data['sub']);
        }

        if (isset($data['iss'])) {
            $jws->iss($data['iss']);
            unset($data['iss']);
        }

        if (isset($data['aud'])) {
            $jws->aud($data['aud']);
            unset($data['aud']);
        }

        foreach ($data as $name => $value) {
            $jws->claim($name, $value);
        }

        return $jws->sign(\Config\Services::JWK());
    }

    public static function JWSLoader($token = '', $check = [], bool $shared = true)
    {
        if ($shared) {
            return static::getSharedInstance('JWSLoader', $token, $check);
        }

        $jwt = \Jose\Easy\Load::jws($token);

        $jwt = $jwt->alg(config('Api')->alg);
        $jwt = $jwt->iat();
        $jwt = $jwt->nbf();
        $jwt = $jwt->exp();

        if (isset($check['sub'])) {
            $jwt = $jwt->iss($check['sub']);
            unset($check['sub']);
        }

        if (isset($check['iss'])) {
            $jwt = $jwt->iss($check['iss']);
            unset($check['iss']);
        }

        if (isset($check['aud'])) {
            $jwt = $jwt->aud($check['aud']);
            unset($check['aud']);
        }

        foreach ($check as $name => $value) {
            $jwt = $jwt->claim($name, $value);
        }

        $jwt = $jwt->key(\Config\Services::JWK());

        return $jwt->run();
    }

    public static function routes(bool $getShared = true)
	{
		if ($getShared)
		{
			return static::getSharedInstance('routes');
		}

		return new RouteCollection(static::locator(), config('Modules'));
	}
}
