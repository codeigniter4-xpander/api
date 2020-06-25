<?php

namespace CI4Xpander_API;

class Controller extends \CodeIgniter\RESTful\ResourceController
{
    use \CI4Xpander\ClassInitializerTrait, \CI4Xpander\PropertyInitializerTrait;

    protected $format = 'json';

    protected $search;
    protected $searchFields;
    protected $page = 1;
    protected $limit = 10;
    protected $minLimit = 10;
    protected $maxLimit = 100;
    protected $offset = 0;

    protected $useCustomIndexQuery = false;
    protected $customIndexQuery;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->_initReflectionClass();
        $this->_initDocBlock();
        $this->_initProperty();
        $this->_init();
    }

    protected function _init()
    {
        $this->search = preg_replace('/\s+/', '%', $this->request->getGet('search')) ?? '';

        $this->page = $this->request->getGet('page') ?? 1;
        if (is_numeric($this->page)) {
            $this->page = intval($this->page);
            if ($this->page < 1) {
                $this->page = 1;
            }
        } else {
            $this->page = 1;
        }

        $this->limit = $this->request->getGet('limit') ?? 10;
        if (is_numeric($this->limit)) {
            $this->limit = intval($this->limit);
            if ($this->limit < $this->minLimit) {
                $this->limit = $this->minLimit;
            } elseif ($this->limit > $this->maxLimit) {
                $this->limit = $this->maxLimit;
            }
        } else {
            $this->limit = 10;
        }

        $this->offset = $this->page * $this->limit - $this->limit;
    }

    public function index()
    {
        if ($this->useCustomIndexQuery) {
            /** @var \CodeIgniter\Database\BaseBuilder */
            $modelBuilder = $this->model->builder();

            $total = $modelBuilder->countAll(false);

            if (!empty($this->search)) {
                $searchField = $this->searchField;
                if (is_null($searchField)) {
                    $searchField = $this->model->allowedFields;
                } else {
                    if (count($searchField) == 0) {
                        $searchField = $this->model->allowedFields;
                    }
                }

                $i = 0;
                foreach ($searchField as $field) {
                    if ($i == 0) {
                        $modelBuilder->like($field, $this->search, 'both', null, true);
                    } else {
                        $modelBuilder->orLike($field, $this->search, 'both', null, true);
                    }
                    $i++;
                }
            }

            $totalFiltered = $modelBuilder->countAllResults(false);

            $modelBuilder->limit($this->limit, $this->offset);

            $data = $modelBuilder->get()->getResult();

            return $this->respond([
                'status' => true,
                'data' => $data,
                'total_rows' => $total,
                'total_filtered_rows' => $totalFiltered,
                'pagination' => [
                    'limit' => $this->limit,
                    'current_page' => $this->page,
                    'total_page' => ceil($totalFiltered / $this->limit)
                ]
            ]);
        } else {
            $builder = \Config\Database::connect();
            $builder->query($this->customIndexQuery, [
                'limit' => $this->limit,
                'offset' => $this->offset,
                'search' => $this->search
            ]);
        }
    }

    public function show($id = null)
    {
    }

    public function create()
    {
    }

    public function update($id = null)
    {
    }

    public function delete($id = null)
    {
    }
}
