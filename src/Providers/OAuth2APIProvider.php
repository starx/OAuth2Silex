<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
namespace OAuth2ServerExamples\Providers;

use OAuth2ServerExamples\Middlewares\OAuth2Middleware;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;

class OAuth2APIProvider implements ControllerProviderInterface, ServiceProviderInterface
{
    public function connect(Application $app)
    {
        /** @var ControllerCollection $controllersFactory */
        return $controllersFactory;
    }

    public function register(Container $app)
    {
        $app['middleware.oauth2.api'] = function() use ($app) {
            $middleware = new OAuth2Middleware();

            return $middleware;
        };

    }

    public function boot(Application $app)
    {
    }

}