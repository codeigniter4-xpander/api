<?php namespace CI4Xpander_API\Filters;

class ApiAuth extends \CI4Xpander\Filters\Auth
{
    use \CodeIgniter\API\ResponseTrait;

    public $reponse;
    public $authorization;
    public $token;
    public $check = [];
    public $jwt;
    public $authorizationHeaderName = 'authorization';

    public function __construct()
    {
        $this->response = \Config\Services::response();
    }

    protected function getAuthorization(\CodeIgniter\HTTP\IncomingRequest $request) {
        return $request->getHeader($this->authorizationHeaderName);
    }

    public function before(\CodeIgniter\HTTP\RequestInterface $request, $params = null)
    {
        $this->authorization = $this->getAuthorization($request);

        if (!is_null($this->authorization)) {
            if (\Stringy\StaticStringy::startsWith($this->authorization->getValue(), 'Bearer ')) {
                $this->token = \Stringy\StaticStringy::substr($this->authorization->getValue(), 7);

                $this->jwt = \Config\Services::JWSLoader($this->token, $this->check);
            } else {
                \Config\Services::modelTracker()->setCreatedBy(0);
                \Config\Services::modelTracker()->setUpdatedBy(0);
                \Config\Services::modelTracker()->setDeletedBy(0);
                return $this->failUnauthorized();
            }
        } else {
            \Config\Services::modelTracker()->setCreatedBy(0);
            \Config\Services::modelTracker()->setUpdatedBy(0);
            \Config\Services::modelTracker()->setDeletedBy(0);
            return $this->failUnauthorized();
        }
    }
}
