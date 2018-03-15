<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Middleware\AuthorizationServerMiddleware;
use League\OAuth2\Server\Middleware\ResourceServerMiddleware;
use League\OAuth2\Server\ResourceServer;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use OAuth2ServerExamples\Repositories\AuthCodeRepository;
use OAuth2ServerExamples\Repositories\ClientRepository;
use OAuth2ServerExamples\Repositories\RefreshTokenRepository;
use OAuth2ServerExamples\Repositories\ScopeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use Zend\Diactoros\Stream;

include __DIR__ . '/../vendor/autoload.php';

$app = new \Silex\Application(['debug' => true]);

$app['authorization.server'] = function() use ($app) {
    // Init our repositories
    $clientRepository = new ClientRepository(); // instance of ClientRepositoryInterface
    $scopeRepository = new ScopeRepository(); // instance of ScopeRepositoryInterface
    $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface
    $authCodeRepository = new AuthCodeRepository();
    $refreshTokenRepository = new RefreshTokenRepository();

    // Path to public and private keys
    $privateKey = 'file://' . __DIR__ . '/../private.key';

    // Setup the authorization server
    $server = new AuthorizationServer(
        $clientRepository,
        $accessTokenRepository,
        $scopeRepository,
        $privateKey,
        'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen'
    );

    // Enable the authentication code grant on the server with a token TTL of 1 hour
    $server->enableGrantType(
        new AuthCodeGrant(
            $authCodeRepository,
            $refreshTokenRepository,
            new \DateInterval('PT10M')
        ),
        new \DateInterval('PT1H') // access tokens will expire after 1 hour
    );

    // Enable the refresh token grant on the server with a token TTL of 1 month
    $server->enableGrantType(
        new RefreshTokenGrant($refreshTokenRepository),
        new \DateInterval('P1M')
    );

    return $server;

};

$app['resource.server'] = function() use ($app) {
    $publicKeyPath = 'file://' . __DIR__ . '/../public.key';

    $server = new ResourceServer(
        new AccessTokenRepository(),
        $publicKeyPath
    );

    return $server;
};

// Access token issuer
$app->post('/access_token', function (\Symfony\Component\HttpFoundation\Request $request) {
})->before(function(\Symfony\Component\HttpFoundation\Request $request, \Silex\Application $app) {
    /*
     * Factory class to convert Symfony Request to PSR7 complaint Request
     */
    $diactorosFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory();
    $psr7Request = $diactorosFactory->createRequest($request);

    // Response
    $psr7Response = $diactorosFactory->createResponse(new \Symfony\Component\HttpFoundation\Response());

    $middleware = new AuthorizationServerMiddleware($app['authorization.server']);
    $middlewareResponse = $middleware->__invoke(
        $psr7Request,
        $psr7Response,
        /*
         * This is a callback function for the middleware to call after it
         * finishes
         */
        function (ServerRequestInterface $psr7Request, ResponseInterface $psr7Response) use($request) {
            // Just normally return the response
            return $psr7Response;
        }
    );

    /*
     * If the middleware has responded with anything other than null,
     * convert that into a Symfony response to respond
     */
    if(!is_null($middlewareResponse)) {
        /*
         * Factory class to convert PSR7 complaint response to Symfony response
         */
        $httpFoundationFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory();

        // Return the Symfony response
        return $httpFoundationFactory->createResponse($middlewareResponse);
    }
});


$app->get(
    '/api/user',
    function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
        /*
        * Factory class to convert Symfony Request to PSR7 complaint Request
        */
        $diactorosFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory();
        /** @var ServerRequestInterface $request */
        $psr7Request = $diactorosFactory->createRequest($request);

        $params = [];

        if (in_array('basic', $psr7Request->getAttribute('oauth_scopes', []))) {
            $params = [
                'id' => 1,
                'name' => 'Alex',
                'city' => 'London',
            ];
        }

        if (in_array('email', $psr7Request->getAttribute('oauth_scopes', []))) {
            $params['email'] = 'alex@example.com';
        }

        $body = new Stream('php://temp', 'r+');
        $body->write(json_encode($params));

        // Return a response with the stream body
        return new \Symfony\Component\HttpFoundation\Response($body);
})
->before(function(\Symfony\Component\HttpFoundation\Request $request, \Silex\Application $app) {
    $middleWare = new ResourceServerMiddleware(
        $app['resource.server']
    );

    /*
     * Factory class to convert Symfony Request to PSR7 complaint Request
     */
    $diactorosFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory();
    // Convert to PSR 7 request
    $psr7Request = $diactorosFactory->createRequest($request);

    // Response
    $psr7Response = $diactorosFactory->createResponse(new \Symfony\Component\HttpFoundation\Response());

    /*
     * Invoke the middleware manually to let it do what it wants to do
     */
    $middlewareResponse = $middleWare->__invoke(
        $psr7Request,
        $psr7Response,

        /*
         * This is a callback function for the middleware to call after it
         * finishes
         */
        function (ServerRequestInterface $psr7Request, ResponseInterface $psr7Response) use($request) {

            /**
             * The OAuth2 middleware modifies the request and responds
             * with some additional parameters. Since we are trying to
             * invoke the PSR 7 Middleware through the Symfony middleware,
             * the modifications will not have been made on the actual
             * request object, so we should add the additional parameters
             * manually.
             *
             * The following attributes are those modified by the
             * BearerTokenValidator
             *
             */
            foreach([
                        'oauth_access_token_id',
                        'oauth_client_id',
                        'oauth_user_id',
                        'oauth_scopes'
                    ] as $oauthVariable) {
                if(!empty($psr7Request->getAttribute($oauthVariable))) {
                    $request->attributes->set($oauthVariable, $psr7Request->getAttribute($oauthVariable));
                }
            }

            // To not short circuit the controller, you shouldn't return response
            // https://silex.symfony.com/doc/2.0/middlewares.html#short-circuiting-the-controller
            return null;
        }
    );

    /*
     * If the middleware has responded with anything other than null,
     * convert that into a Symfony response to respond
     */
    if(!is_null($middlewareResponse)) {
        /*
         * Factory class to convert PSR7 complaint response to Symfony response
         */
        $httpFoundationFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory();

        // Return the Symfony response
        return $httpFoundationFactory->createResponse($middlewareResponse);
    }
});

//// Secured API
//$app->group('/api', function () {
//
//})->add(new ResourceServerMiddleware($app['resource.server']));

$app->run();
