<?php

namespace OAuth2ServerExamples\Providers;


use OAuth2ServerExamples\Controllers\API\UsersController;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;

class APIProvider implements ControllerProviderInterface, ServiceProviderInterface
{
    public function connect(Application $app)
    {
        /** @var ControllerCollection $controllersFactory */
        $controllersFactory = $app['controllers_factory'];
        $controllersFactory->before(array($app['middleware.oauth2.api'], 'invokeBefore'));

        $controllersFactory
            ->get("/users", 'controller.api.users:getUsersAction')
            ->bind('api.users.get_users');

        $controllersFactory
            ->get("/user", 'controller.api.users:getUserAction')
            ->bind('api.users.get_user');

        return $controllersFactory;
    }

    public function register(Container $app)
    {
        $app['controller.api.users'] = function() use ($app){
            $controller = new UsersController($app);

            return $controller;
        };

    }

    public function boot(Application $app)
    {
    }
}