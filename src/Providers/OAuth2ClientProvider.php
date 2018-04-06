<?php

namespace OAuth2ServerExamples\Providers;

use OAuth2ServerExamples\Controllers\OAuth2Client\OAuth2ClientController;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;

class OAuth2ClientProvider implements ControllerProviderInterface, ServiceProviderInterface
{
    public function connect(Application $app)
    {
        /** @var ControllerCollection $controllersFactory */
        $controllersFactory = $app['controllers_factory'];

        $controllersFactory
            ->get("/redirect_uri", 'controller.oauth2.client:demoRedirectUriAction')
            ->bind('oauth2.client.redirect_uri');


        return $controllersFactory;
    }

    public function register(Container $app)
    {
        $app['controller.oauth2.client'] = function() use ($app){
            $controller = new OAuth2ClientController($app);

            return $controller;
        };

    }

    public function boot(Application $app)
    {
    }
}