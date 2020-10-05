<?php namespace CI4Xpander_API\Helpers;

class Route
{
    public static function create(\CI4Xpander_API\Libraries\RouteCollection $routes, $config = [])
    {
        helper('array');

        $namespace = $config['namespace'] ?? null;
        if (is_null($namespace)) {
            $namespace = '';
        } else {
            $namespace = '\\' . $namespace;
        }

        $method = $config['method'] ?? ['index' => 'index', 'item' => 'item', 'create' => 'create', 'update' => 'update', 'delete' => 'delete'];

        $version = $config['version'] ?? [];
        $version[] = '';

        $url = $config['url'] ?? null;

        $ns = str_replace($routes->getDefaultNamespace() . 'Api\\', '', $routes->getGroupNamespace());

        $tempGroup = $routes->getGroupSet();

        $versionsMap = [];

        foreach ($version as $key => $val) {
            $versionNAMESPACE = '';
            $versionURL = '';
            if ($val != '') {
                $exGroupSet = explode('/', $routes->getGroupSet());
                array_splice($exGroupSet, 1, 0, $val);
                $versionURL = implode('/', $exGroupSet);

                $routes->setGroupSet($versionURL);

                $exVal = explode('.', $val);

                if (count($exVal) == 3) {
                    $versionsMap = array_merge_recursive($versionsMap, [
                        'M' . strval($exVal[0]) => [
                            'm' . strval($exVal[1]) => [
                                'p' . strval($exVal[2]) => $val
                            ]
                        ]
                    ]);

                    $versionNAMESPACE = '\\' . $routes->getDefaultNamespace() . 'Api' . '\\' . 'V_' . str_replace('.', '_', $val) . '\\' . rtrim($ns, '\\');
                } else if (count($exVal) == 2) {
                    $versionsMap = array_merge_recursive($versionsMap, [
                        'M' . strval($exVal[0]) => [
                            'm' . strval($exVal[1]) => []
                        ]
                    ]);

                    $findVersion = dot_array_search("M{$exVal[0]}.m{$exVal[1]}", $versionsMap);
                    $findVersion = array_shift($findVersion);

                    $versionNAMESPACE = '\\' . $routes->getDefaultNamespace() . 'Api' . '\\' . 'V_' . str_replace('.', '_', $findVersion) . '\\' . rtrim($ns, '\\');
                } else if (count($exVal) == 1) {
                    $versionsMap = array_merge_recursive($versionsMap, [
                        'M' . strval($exVal[0]) => []
                    ]);

                    $findVersion = dot_array_search("M{$exVal[0]}", $versionsMap);
                    $findVersion = array_shift($findVersion);
                    $findVersion = array_shift($findVersion);

                    $versionNAMESPACE = '\\' . $routes->getDefaultNamespace() . 'Api' . '\\' . 'V_' . str_replace('.', '_', $findVersion) . '\\' . rtrim($ns, '\\');
                }
            } else {
                $findVersion = $versionsMap[array_keys($versionsMap)[0]];
                $findVersion = $findVersion[array_keys($findVersion)[0]];
                $findVersion = $findVersion[array_keys($findVersion)[0]];

                $versionNAMESPACE = '\\' . $routes->getDefaultNamespace() . 'Api' . '\\' . 'V_' . str_replace('.', '_', $findVersion) . '\\' . rtrim($ns, '\\');
            }

            if (array_key_exists('index', $method)) {
                $routes->get($url ?? $method['index'], (!empty($versionNAMESPACE) ? $versionNAMESPACE : '') . "{$namespace}::{$method['index']}");
            }
    
            if (array_key_exists('item', $method)) {
                $routes->get(($url ?? $method['item']) . '/(:num)', (!empty($versionNAMESPACE) ? $versionNAMESPACE : '') . "{$namespace}::{$method['item']}/$1");
            }
    
            if (array_key_exists('create', $method)) {
                $routes->post($url ?? $method['create'], (!empty($versionNAMESPACE) ? $versionNAMESPACE : '') . "{$namespace}::{$method['create']}");
            }
    
            if (array_key_exists('update', $method)) {
                $routes->put(($url ?? $method['update']) . '/(:num)', (!empty($versionNAMESPACE) ? $versionNAMESPACE : '') . "{$namespace}::{$method['update']}/$1");
            }
    
            if (array_key_exists('delete', $method)) {
                $routes->delete(($url ?? $method['delete']) . '/(:num)', (!empty($versionNAMESPACE) ? $versionNAMESPACE : '') . "{$namespace}::{$method['delete']}/$1");
            }

            $routes->setGroupSet($tempGroup);
        }
    }
}