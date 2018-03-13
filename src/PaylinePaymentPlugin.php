<?php
namespace SubsGuru\Payline;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use SubsGuru\Core\Payments\PaymentGatewayRepository;

class PaylinePaymentPlugin extends BasePlugin
{
    public function middleware($middleware)
    {
        // Add middleware here.
        return $middleware;
    }

    public function console($commands)
    {
        // Add console commands here.
        return $commands;
    }

    public function bootstrap(PluginApplicationInterface $app)
    {
        parent::bootstrap($app);

        PaymentGatewayRepository::add('SubsGuru\\Payline\\Payments\\Gateway\\PaylinePaymentGateway');
    }

    public function routes($routes)
    {
        parent::routes($routes);

        $routes->plugin(
            'SubsGuru/Payline',
            ['path' => '/payline'],
            function (RouteBuilder $routes) {
                $routes->setExtensions(['json', 'xml']);
                $routes->fallbacks(DashedRoute::class);
            }
        );
    }
}