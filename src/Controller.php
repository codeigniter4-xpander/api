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

    protected function _doCRUD(?callable $function)
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

                        /** @var \CI4Xpander\Entity */
                        $entity = $this->CRUD['entity'] ?? (!is_null($model) ? $model->getEntity() : null);

                        return $function($query, $model, $entity, $table);
                    }
                }
            }
        }

        return null;
    }

    public function index()
    {
        $crud = $this->_doCRUD(function ($query = null, ?\CI4Xpander\Model $model, ?string $entity, ?string $table) {
            $jsonParams = $this->request->getJSON();
            if (!is_null($jsonParams)) {
                if (isset($jsonParams->query)) {
                    $query = \CI4Xpander\Helpers\Database\Query\Builder::fromJSON($query, $jsonParams->query);
                }
            }

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

            // return $this->respond($builder->getCompiledSelect());

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

            $data = [];
            if (!is_null($entity)) {
                $data = $builder->get()->getCustomResultObject($entity);
            } else {
                $data = $builder->get()->getResult();
            }

            return $this->respond([
                'status' => true,
                'data' => $data,
                'total_rows' => $totalRecords,
                'total_filtered_rows' => $filteredRecords,
                'pagination' => [
                    'limit' => $limit,
                    'current_page' => $page,
                    'total_page' => ceil($filteredRecords / $limit)
                ],
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

        return $this->_doCRUD(function ($query = null, ?\CI4Xpander\Model $model, ?string $entity, ?string $table) use ($id) {
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
        return $this->_doCRUD(function ($query = null, ?\CI4Xpander\Model $model, ?string $entity, ?string $table) {
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

            return $this->_actionTransaction(function (?BaseConnection $builder) use ($table, $model, $params) {
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

        return $this->_doCRUD(function ($query = null, ?\CI4Xpander\Model $model, ?string $entity, ?string $table) use ($id) {
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

        return $this->_doCRUD(function ($query = null, ?\CI4Xpander\Model $model, ?string $entity, ?string $table) use ($id) {
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

            return $this->_actionTransaction(function (?BaseConnection $builder) use ($table, $model, $params, $item) {
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

    protected function _actionTransaction(?callable $function = null)
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

    public function data()
    {
        $table = null;

        /** @var \CI4Xpander\Model */
        $model = $this->CRUD['model'] ?? null;

        if (!is_null($model)) {
            if (!is_a($model, \CI4Xpander\Model::class)) {
                $model = $model::create();
            }
            $table = $model->getTable();
        }

        $columns = $this->CRUD['index']['columns'] ?? [];

        $query = null;
        if (isset($this->CRUD['index']['query'])) {
            $query = $this->CRUD['index']['query'];

            if (is_callable($query)) {
                $query = $query(\Config\Database::connect(), $model);
            }
        } else {
            if (!is_null($model)) {
                $query = $model->builder();
            }
        }

        $draw = $this->request->getGet('draw');
        $columnsGet = $this->request->getGet('columns');
        $order = $this->request->getGet('order');
        $start = $this->request->getGet('start');
        $length = $this->request->getGet('length');
        $search = preg_replace('/\s+/', '%', $this->request->getGet('search')) ?? '';

        /** @var \CodeIgniter\Database\BaseBuilder */
        $data = \Config\Database::connect()->table('ci4x_dashboard_data_temporary_table');

        /** @var \CodeIgniter\Database\BaseBuilder */
        $recordsFiltered = \Config\Database::connect()->table('ci4x_dashboard_data_temporary_table');

        if (!is_string($query)) {
            $compiledQuery = $query->getCompiledSelect();
        } else {
            $compiledQuery = $query;
        }

        $data->from("({$compiledQuery}) ci4x_dashboard_data_temporary_table", true);
        $recordsFiltered->from("({$compiledQuery}) ci4x_dashboard_data_temporary_table", true);

        $recordsTotal = \Config\Database::connect()->table('ci4x_dashboard_data_temporary_table')->from("({$compiledQuery}) ci4x_dashboard_data_temporary_table", true);

        if (isset($search)) {
            if (isset($search['value'])) {
                if (!empty($search['value'])) {
                    if (isset($columnsGet)) {
                        if (is_array($columnsGet)) {
                            $data->groupStart();
                            $recordsFiltered->groupStart();
                            $i = 0;
                            foreach ($columnsGet as $column) {
                                if ($column['searchable'] == 'true') {
                                    $c = $columns[$column['data']];

                                    $searchValue = trim(preg_replace('/\s+/', '%', $search['value']));

                                    if (is_array($c)) {
                                        if (isset($c['value'])) {
                                            if (is_array($c['value'])) {
                                                if ($i == 0) {
                                                    $data->groupStart();
                                                    $recordsFiltered->groupStart();
                                                } else {
                                                    $data->orGroupStart();
                                                    $recordsFiltered->orGroupStart();
                                                }
                                                $j = 0;
                                                foreach ($c['value'] as $cKey => $cValue) {
                                                    if (is_numeric($cKey)) {
                                                        $fToS = $cValue;
                                                    } else {
                                                        $fToS = $cKey;
                                                    }

                                                    if ($j == 0) {
                                                        $data->like("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                        $recordsFiltered->like("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                    } else {
                                                        $data->orLike("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                        $recordsFiltered->orLike("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                    }

                                                    $j++;
                                                }
                                                $data->groupEnd();
                                                $recordsFiltered->groupEnd();
                                            } else {
                                                if ($i == 0) {
                                                    $data->like("{$c['value']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                    $recordsFiltered->like("{$c['value']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                } else {
                                                    $data->orLike("{$c['value']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                    $recordsFiltered->orLike("{$c['value']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                }
                                            }
                                        } else {
                                            if ($i == 0) {
                                                $data->like("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                $recordsFiltered->like("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                            } else {
                                                $data->orLike("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                $recordsFiltered->orLike("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                            }
                                        }
                                    } else {
                                        if ($i == 0) {
                                            $data->like("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                            $recordsFiltered->like("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                        } else {
                                            $data->orLike("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                            $recordsFiltered->orLike("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                        }
                                    }

                                    $i++;
                                }
                            }

                            $data->groupEnd();
                            $recordsFiltered->groupEnd();
                        }
                    }
                }
            }
        }

        if (isset($columnsGet)) {
            if (is_array($columnsGet)) {
                foreach ($columnsGet as $column) {
                    if ($column['searchable'] == 'true') {
                        if (!empty($column['search']['value'])) {
                            $c = $columns[$column['data']];

                            $searchValue = trim(preg_replace('/\s+/', '%', $column['search']['value']));

                            if (is_array($c)) {
                                if (isset($c['value'])) {
                                    if (is_array($c['value'])) {
                                        $data->groupStart();
                                        $recordsFiltered->groupStart();
                                        $i = 0;
                                        foreach ($c['value'] as $cKey => $cValue) {
                                            if (is_numeric($cKey)) {
                                                $fToS = $cValue;
                                            } else {
                                                $fToS = $cKey;
                                            }

                                            if ($i == 0) {
                                                $data->like("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                $recordsFiltered->like("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                            } else {
                                                $data->orLike("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                                $recordsFiltered->orLike("{$fToS}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                            }

                                            $i++;
                                        }
                                        $data->groupEnd();
                                        $recordsFiltered->groupEnd();
                                    } elseif (is_callable($c['value'])) {
                                        $data->like("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                        $recordsFiltered->like("{$column['data']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                    } else {
                                        $data->like("{$c['value']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                        $recordsFiltered->like("{$c['value']}::TEXT", \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                    }
                                } else {
                                    $data->like($column['data'] . '::TEXT', \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                    $recordsFiltered->like($column['data'] . '::TEXT', \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                }
                            } else {
                                $data->like($column['data'] . '::TEXT', \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                                $recordsFiltered->like($column['data'] . '::TEXT', \Config\Database::connect()->escape("%{$searchValue}%"), 'none', false, true);
                            }
                        }
                    }
                }
            }
        }

        if (isset($order)) {
            if (is_array($order)) {
                foreach ($order as $columnOrder) {
                    if (isset($columnsGet)) {
                        if ($columnsGet[intval($columnOrder['column'])]['orderable'] == 'true') {
                            $colKey = $columnsGet[intval($columnOrder['column'])]['data'];
                            if (is_array($columns[$colKey])) {
                                if (isset($columns[$colKey]['value'])) {
                                    if (is_array($columns[$colKey]['value'])) {
                                        foreach ($columns[$colKey]['value'] as $field => $name) {
                                            $data->orderBy(is_string($field) ? $field : $name, $columnOrder['dir']);
                                        }
                                    } elseif (is_string($columns[$colKey]['value'])) {
                                        $data->orderBy($columns[$colKey]['value'], $columnOrder['dir']);
                                    }
                                } else {
                                    $data->orderBy($colKey, $columnOrder['dir']);
                                }
                            } else {
                                $data->orderBy($columnsGet[intval($columnOrder['column'])]['data'], $columnOrder['dir']);
                            }
                        }
                    }
                }
            }
        }

        if (isset($start)) {
            $data->offset(intval($start));
        }

        if (isset($length)) {
            $data->limit(intval($length));
        }

        return $this->response->setJSON([
            'draw' => isset($draw) ? intval($draw) : 0,
            'recordsTotal' => $recordsTotal->countAllResults(),
            'recordsFiltered' => $recordsFiltered->countAllResults(),
            'data' => $data->get()->getResult()
        ]);
    }
}
