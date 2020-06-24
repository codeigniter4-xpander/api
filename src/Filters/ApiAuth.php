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
        \Config\Services::validateJWS($request);

        if (is_array($params)) {
            if (in_array('outside', $params)) {

            } elseif (in_array('inside', $params)) {
    
            } else {
                return $this->failUnauthorized();
            }
        } else {
            d($request);
            // return $this->failUnauthorized();
        }
    }
}