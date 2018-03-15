<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */

use League\OAuth2\Server\ResourceServer;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use League\OAuth2\Server\Middleware\ResourceServerMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

include __DIR__ . '/../vendor/autoload.php';

$app = new \Silex\Application(['debug' => true]);
$app['resource.server'] = function() use ($app) {
    // Add the resource server middleware which will intercept and validate requests
    $server = new ResourceServer(
        new AccessTokenRepository(),            // instance of AccessTokenRepositoryInterface
        'file://' . __DIR__ . '/../public.key'  // the authorization server's public key
    );

    return $server;
};


// Add the resource server middleware which will intercept and validate requests
$app->before(function (\Symfony\Component\HttpFoundation\Request $request, \Silex\Application $app) {
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



// An example endpoint secured with OAuth 2.0
$app->get(
    '/users',
    function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
        /*
         * Factory class to convert Symfony Request to PSR7 complaint Request
         */
        $diactorosFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory();
        /** @var ServerRequestInterface $request */
        $psr7Request = $diactorosFactory->createRequest($request);

        $users = [
            [
                'id'    => 123,
                'name'  => 'Alex',
                'email' => 'alex@thephpleague.com',
            ],
            [
                'id'    => 124,
                'name'  => 'Frank',
                'email' => 'frank@thephpleague.com',
            ],
            [
                'id'    => 125,
                'name'  => 'Phil',
                'email' => 'phil@thephpleague.com',
            ],
        ];

        // If the access token doesn't have the `basic` scope hide users' names
        if (in_array('basic', $psr7Request->getAttribute('oauth_scopes', [])) === false) {
            for ($i = 0; $i < count($users); $i++) {
                unset($users[$i]['name']);
            }
        }

        // If the access token doesn't have the `email` scope hide users' email addresses
        if (in_array('email', $psr7Request->getAttribute('oauth_scopes', [])) === false) {
            for ($i = 0; $i < count($users); $i++) {
                unset($users[$i]['email']);
            }
        }

        // Response
        return new \Symfony\Component\HttpFoundation\JsonResponse($users);
    }
);

$app->run();
