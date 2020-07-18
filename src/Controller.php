<?php

namespace CI4Xpander_API;

class Controller extends \CodeIgniter\RESTful\ResourceController
{
    use \CI4Xpander\ClassInitializerTrait, \CI4Xpander\PropertyInitializerTrait;

    protected $format = 'json';

    protected $CRUD = [
        'enable' => false,
        'index' => [
            'minLimit' => 10,
            'maxLimit' => 100,
        ]
    ];

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->_initReflectionClass();
        $this->_initDocBlock();
        $this->_initProperty();
        $this->_init();
    }

    protected function setCRUD($CRUD = [])
    {
        $this->CRUD = array_merge_recursive($this->CRUD, $CRUD);
        return $this;
    }

    protected function _doCRUD($function = null)
    {
        if (isset($this->CRUD)) {
            if (isset($this->CRUD['enable'])) {
                if ($this->CRUD['enable']) {
                    if (!is_null($function)) {
                        return $function();
                    }
                }
            }
        }

        return null;
    }

    public function index()
    {
        $crud = $this->_doCRUD(function () {
            $error = isset($this->CRUD['index']) ? (
                isset($this->CRUD['index']['query']) ? false : true
            ) : true;

            if ($error) {
                return $this->failServerError();
            }

            $search = preg_replace('/\s+/', '%', $this->request->getGet('search')) ?? '';

            $page = $this->request->getGet('page') ?? 1;
            if (is_numeric($page)) {
                $page = intval($page);
                if ($page < 1) {
                    $page = 1;
                }
            } else {
                $page = 1;
            }

            $limit = $this->request->getGet('limit') ?? $this->CRUD['index']['minLimit'];
            if (is_numeric($limit)) {
                $limit = intval($limit);
                if ($limit < $this->CRUD['index']['minLimit']) {
                    $limit = $this->CRUD['index']['minLimit'];
                } elseif ($limit > $this->CRUD['index']['maxLimit']) {
                    $limit = $this->CRUD['index']['maxLimit'];
                }
            } else {
                $limit = $this->CRUD['index']['minLimit'];
            }

            $offset = $page * $limit - $limit;

            if (is_string($this->CRUD['index']['query'])) {
                $query = $this->CRUD['index']['query'];
            } else if (is_a($this->CRUD['index']['query'], \CodeIgniter\Database\BaseBuilder::class)) {
                $query = $this->CRUD['index']['query']->getCompiledSelect();
            } elseif (is_callable($this->CRUD['index']['query'])) {
                $query = $this->CRUD['index']['query']();
                if (is_a($query, \CodeIgniter\Database\BaseBuilder::class)) {
                    $query = $query->getCompiledSelect();
                }
            }

            $builder = \Config\Database::connect()
                ->table('ci4x_api_index_temporary_table')
                ->from("({$query}) ci4x_api_index_temporary_table", true);
            $totalRecords = $builder->countAllResults(false);

            if (isset($search)) {
                if (!empty($search)) {
                    $i = 0;
                    foreach ($this->CRUD['index']['searchColumns'] ?? [] as $column) {
                        if ($i == 0) {
                            $builder->like($column, $search, 'both', null, true);
                        } else {
                            $builder->orLike($column, $search, 'both', null, true);
                        }

                        $i++;
                    }
                }
            }

            $filteredRecords = $builder->countAllResults(false);

            $builder->limit($limit, $offset);

            return $this->respond([
                'status' => true,
                'data' => $builder->get()->getResult(),
                'total_rows' => $totalRecords,
                'total_filtered_rows' => $filteredRecords,
                'pagination' => [
                    'limit' => $limit,
                    'current_page' => $page,
                    'total_page' => ceil($filteredRecords / $limit)
                ]
            ]);
        });

        if (!is_null($crud)) {
            return $crud;
        } else {
            return $this->failNotFound();
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
