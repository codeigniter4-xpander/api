<?php namespace CI4Xpander_API\Helpers;

class Route
{
    public static function create(\CI4Xpander\Core\RouteCollection $routes, $config = [])
    {
        $namespace = $config['namespace'] ?? '';
        $url = $config['url'] ?? '';

        $options = [];
        if (isset($config['version'])) {
            $options['version'] = $config['version'];
        }

        $routes->get($url, "{$namespace}::index", $options);
        $routes->get(empty($url) ? '(:num)' : $url . '/(:num)', "{$namespace}::item/$1", $options);
        $routes->post($url, "{$namespace}::create", $options);
        $routes->put(empty($url) ? '(:num)' : $url . '/(:num)', "{$namespace}::update/$1", $options);
        $routes->delete(empty($url) ? '(:num)' : $url . '/(:num)', "{$namespace}::delete/$1", $options);
    }
}
