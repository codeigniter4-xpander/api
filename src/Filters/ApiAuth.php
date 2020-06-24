<?php namespace CI4Xpander_API\Filters;

class ApiAuth extends \CI4Xpander\Filters\Auth
{
    use \CodeIgniter\API\ResponseTrait;

    public $reponse;

    public function __construct()
    {
        $this->response = \Config\Services::response();
    }

    public function before(\CodeIgniter\HTTP\RequestInterface $request, $params = null)
    {
        if (!\Config\Services::isVerifiedToken($request)) {
            return $this->failUnauthorized();
        }
    }
}