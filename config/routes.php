<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

Router::plugin(
    'SubsGuru/Payline',
    ['path' => '/payline'],
    function (RouteBuilder $routes) {
        $routes->extensions(['json', 'xml']);
        $routes->fallbacks(DashedRoute::class);
    }
);
