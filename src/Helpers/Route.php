<?php namespace CI4Xpander_API\Helpers;

class Route
{
    public static function create(\CodeIgniter\Router\RouteCollection $routes, $namespace = '', $url = null)
    {
        $routes->get(isset($url) ? $url : '/', "{$namespace}::index");
        $routes->get(isset($url) ? "{$url}/(:num)" : '(:num)', "{$namespace}::show/$1");
        $routes->post(isset($url) ? $url : '/', "{$namespace}::create");
        $routes->put(isset($url) ? "{$url}/(:num)" : '(:num)', "{$namespace}::update/$1");
        $routes->delete(isset($url) ? "{$url}/(:num)" : '(:num)', "{$namespace}::delete/$1");
    }
}