<?php

namespace CI4Xpander_API;

use CodeIgniter\Database\BaseConnection;

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
                        $table = null;

                        /** @var \CI4Xpander\Model */
                        $model = $this->CRUD['model'] ?? null;

                        if (!is_null($model)) {
                            if (!is_a($model, \CI4Xpander\Model::class)) {
                                $model = $model::create();
                            }
                            $table = $model->getTable();
                        }

                        $query = null;
                        if (isset($this->CRUD['index']['query'])) {
                            $query = $this->CRUD['index']['query'];
                        } else {
                            if (!is_null($model)) {
                                $query = $model->builder();
                            }
                        }

                        return $function($table, $model, $query);
                    }
                }
            }
        }

        return null;
    }

    public function index()
    {
        $crud = $this->_doCRUD(function (string $table, \CI4Xpander\Model $model, $query) {
            $query = \CI4Xpander\Helpers\Database\Query\Builder::forceQueryToString($query, \Config\Database::connect(), $model);

            if (is_null($query)) {
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
        if (is_null($id)) {
            return $this->failNotFound();
        }

        return $this->_doCRUD(function (string $table, \CI4Xpander\Model $model, $query) use ($id) {
            $query = \CI4Xpander\Helpers\Database\Query\Builder::forceQueryToString($query, \Config\Database::connect(), $model);
            if (is_null($query)) {
                return $this->failServerError();
            }

            $item = \Config\Database::connect()
                ->table('ci4x_api_show_temporary_table')
                ->from("({$query}) ci4x_api_show_temporary_table")
                ->where('id', $id);

            if (is_null($item)) {
                return $this->failNotFound();
            }

            return $this->respond([
                'status' => true,
                'data' => $item
            ]);
        }) ?? $this->failNotFound();
    }

    public function create()
    {
        return $this->_doCRUD(function (string $table, \CI4Xpander\Model $model, $query) {
            if (is_null($model)) {
                if (is_null($table)) {
                    return $this->failServerError();
                }
            }

            $params = $this->request->getJSON();

            if (isset($this->CRUD['create']['validation'])) {
                $validator = \Config\Services::validation();

                if (!$validator->setRules($this->CRUD['create']['validation'])->run((array) $params)) {
                    return $this->failValidationError(json_encode($validator->getErrors()));
                }
            }

            return $this->_actionTransaction(function (BaseConnection $builder) use ($table, $model, $params) {
                if (!is_null($model)) {
                    $id = $model->insert((array) $params);
                    return $this->respondCreated([
                        'status' => true,
                        'data' => [
                            'id' => $id
                        ]
                    ]);
                } elseif (!is_null($table)) {
                    $table = $builder->table($table);
                    $table->insert((array) $params);
                    $id = $builder->insertID();
                    return $this->respondCreated([
                        'status' => true,
                        'data' => [
                            'id' => $id
                        ]
                    ]);
                } else {
                    return null;
                }
            }) ?? $this->failServerError();

        }) ?? $this->failNotFound();
    }

    public function update($id = null)
    {
        if (is_null($id)) {
            return $this->failNotFound();
        }

        return $this->_doCRUD(function (string $table, \CI4Xpander\Model $model, $query) use ($id) {
            if (is_null($model) && is_null($model)) {
                return $this->failServerError();
            }

            $query = \CI4Xpander\Helpers\Database\Query\Builder::forceQueryToString($query, \Config\Database::connect(), $model);
            if (is_null($query)) {
                return $this->failServerError();
            }

            $item = \Config\Database::connect()
                ->table('ci4x_api_update_temporary_table')
                ->from("({$query}) ci4x_api_update_temporary_table")
                ->where('id', $id);

            if (is_null($item)) {
                return $this->failNotFound();
            }

            $params = $this->request->getJSON();

            if (isset($this->CRUD['update']['validation'])) {
                $validator = \Config\Services::validation();

                if (!$validator->setRules($this->CRUD['update']['validation'])->run((array) $params)) {
                    return $this->failValidationError(json_encode($validator->getErrors()));
                }
            }

            return $this->_actionTransaction(function (BaseConnection $builder) use ($table, $model, $params, $item) {
                if (!is_null($model)) {
                    $update = $model->update($item->id, (array) $params);
                    return $this->respondUpdated(array_merge([
                        'status' => $update,
                    ], $update ? [
                        'data' => array_merge((array) $item, (array) $params),
                    ] : []));
                } elseif (!is_null($table)) {

                } else {
                    return null;
                }
            });
        }) ?? $this->failNotFound();
    }

    public function delete($id = null)
    {
        if (is_null($id)) {
            return $this->failNotFound();
        }

        return $this->_doCRUD(function (string $table, \CI4Xpander\Model $model, $query) use ($id) {
            if (is_null($model) && is_null($model)) {
                return $this->failServerError();
            }

            $query = \CI4Xpander\Helpers\Database\Query\Builder::forceQueryToString($query, \Config\Database::connect(), $model);
            if (is_null($query)) {
                return $this->failServerError();
            }

            $item = \Config\Database::connect()
                ->table('ci4x_api_delete_temporary_table')
                ->from("({$query}) ci4x_api_delete_temporary_table")
                ->where('id', $id);

            if (is_null($item)) {
                return $this->failNotFound();
            }

            $params = $this->request->getJSON();

            if (isset($this->CRUD['delete']['validation'])) {
                $validator = \Config\Services::validation();

                if (!$validator->setRules($this->CRUD['delete']['validation'])->run((array) $params)) {
                    return $this->failValidationError(json_encode($validator->getErrors()));
                }
            }

            return $this->_actionTransaction(function (BaseConnection $builder) use ($table, $model, $params, $item) {
                if (!is_null($model)) {
                    $delete = $model->delete($item->id);
                    return $this->respondDeleted(array_merge([
                        'status' => $delete,
                    ], $delete ? [
                        'data' => array_merge((array) $item, (array) $params)
                    ] : []));
                } elseif (!is_null($table)) {

                } else {
                    return null;
                }
            });
        }) ?? $this->failNotFound();
    }

    protected function _actionTransaction(callable $function = null)
    {
        if (!is_null($function)) {
            $databaseConnection = \Config\Database::connect();
            $databaseConnection->transStart();
            $result = $function($databaseConnection);
            $databaseConnection->transComplete();

            if ($databaseConnection->transStatus()) {
                return $result;
            } else {
                return $this->failServerError(json_encode($databaseConnection->error()));
            }
        }

        return null;
    }
}
