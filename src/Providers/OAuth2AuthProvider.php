<?php
namespace OAuth2ServerExamples\Providers;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\ImplicitGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use OAuth2ServerExamples\Controllers\OAuth2\AuthorisationController;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use OAuth2ServerExamples\Repositories\AuthCodeRepository;
use OAuth2ServerExamples\Repositories\ClientRepository;
use OAuth2ServerExamples\Repositories\RefreshTokenRepository;
use OAuth2ServerExamples\Repositories\ScopeRepository;
use OAuth2ServerExamples\Repositories\UserRepository;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Api\ControllerProviderInterface;
use Silex\Application;
use Silex\ControllerCollection;

class OAuth2AuthProvider implements ControllerProviderInterface, ServiceProviderInterface
{
    public function connect(Application $app)
    {
        /** @var ControllerCollection $controllersFactory */
        $controllersFactory = $app['controllers_factory'];
        $controllersFactory
            ->get(
                '/',
                'controller.oauth2.auth:authorizationRequestAction'
            )
            ->bind('oauth2.auth.request');

        $controllersFactory
            ->get(
                '/sign-in',
                'controller.oauth2.auth:signInAction'
            )
            ->bind('oauth2.auth.sign_in');

        $controllersFactory
            ->post(
                '/sign-in',
                'controller.oauth2.auth:signInAction'
            )
            ->bind('oauth2.auth.sign_in_post');

        $controllersFactory
            ->get(
                '/authorize',
                'controller.oauth2.auth:authorizeAction'
            )
            ->bind('oauth2.auth.authorize');

        $controllersFactory
            ->post(
                '/authorize',
                'controller.oauth2.auth:authorizeAction'
            )
            ->bind('oauth2.auth.authorize_post');

        $controllersFactory
            ->post(
                '/access_token',
                'controller.oauth2.auth:accessTokenAction'
            )
            ->bind('oauth2.auth.access_token');
        return $controllersFactory;
    }

    public function register(Container $app)
    {

        $app['authorization.server'] = function() use ($app) {
            // Init our repositories
            $clientRepository = new ClientRepository(); // instance of ClientRepositoryInterface
            $scopeRepository = new ScopeRepository(); // instance of ScopeRepositoryInterface
            $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface
            $authCodeRepository = new AuthCodeRepository();
            $refreshTokenRepository = new RefreshTokenRepository();

            // Path to public and private keys
            $privateKey = $app['base_path'] . '/private.key';

            // Setup the authorization server
            $server = new AuthorizationServer(
                $clientRepository,
                $accessTokenRepository,
                $scopeRepository,
                $privateKey,
                'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen'
            );

            /*
             * Enable the authentication code grant on the server
             */
            // Enable the authentication code grant on the server with a token TTL of 1 hour
            $server->enableGrantType(
                new AuthCodeGrant(
                    $authCodeRepository,
                    $refreshTokenRepository,
                    new \DateInterval('PT10M')
                ),
                new \DateInterval('PT1H')
            );

            /*
             * Enable the client credentials grant on the server
             */
            $server->enableGrantType(
                new ClientCredentialsGrant(),
                new \DateInterval('PT1H') // access tokens will expire after 1 hour
            );

            /*
             * Enable the password credentials grant on the server
             */
            $grant = new PasswordGrant(
                new UserRepository(),           // instance of UserRepositoryInterface
                new RefreshTokenRepository()    // instance of RefreshTokenRepositoryInterface
            );
            $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // refresh tokens will expire after 1 month

            // Enable the password grant on the server with a token TTL of 1 hour
            $server->enableGrantType(
                $grant,
                new \DateInterval('PT1H') // access tokens will expire after 1 hour
            );

            /*
             * Enable the implicit grant on the server
             */

            // Enable the implicit grant on the server with a token TTL of 1 hour
            $server->enableGrantType(new ImplicitGrant(new \DateInterval('PT1H')));

            /*
             * Enable the refresh token grant on the server
             */
            $grant = new RefreshTokenGrant($refreshTokenRepository);
            $grant->setRefreshTokenTTL(new \DateInterval('P1M')); // The refresh token will expire in 1 month

            $server->enableGrantType(
                $grant,
                new \DateInterval('PT1H') // access tokens will expire after 1 hour
            );

            return $server;

        };

        $app['resource.server'] = function() use ($app) {
            // Add the resource server middleware which will intercept and validate requests
            $server = new ResourceServer(
                new AccessTokenRepository(),            // instance of AccessTokenRepositoryInterface
                $app['base_path'] . '/public.key' // the authorization server's public key
            );

            return $server;
        };

        $app['controller.oauth2.auth'] = function() use ($app) {
            $controller = new AuthorisationController($app);

            return $controller;
        };

    }

    public function boot(Application $app)
    {
    }
}