<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use OAuth2ServerExamples\Repositories\ClientRepository;
use OAuth2ServerExamples\Repositories\ScopeRepository;
use Zend\Diactoros\Stream;

include __DIR__ . '/../vendor/autoload.php';

$app = new \Silex\Application(['debug' => true]);
$app['authorization.server'] = function() use ($app) {
    // Init our repositories
    $clientRepository = new ClientRepository(); // instance of ClientRepositoryInterface
    $scopeRepository = new ScopeRepository(); // instance of ScopeRepositoryInterface
    $accessTokenRepository = new AccessTokenRepository(); // instance of AccessTokenRepositoryInterface

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

    // Enable the client credentials grant on the server
    $server->enableGrantType(
        new \League\OAuth2\Server\Grant\ClientCredentialsGrant(),
        new \DateInterval('PT1H') // access tokens will expire after 1 hour
    );

    return $server;

};



$app->post('/access_token', function() use ($app) {

    /* @var \League\OAuth2\Server\AuthorizationServer $server */
    $server = $app['authorization.server'];

    /*
     * Factory class to convert Symfony Request to PSR7 complaint Request
     */
    $diactorosFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory();

    // Create the request using GLOBALS and convert
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $psr7Request = $diactorosFactory->createRequest($request);

    // Response
    $psr7Response = $diactorosFactory->createResponse(new \Symfony\Component\HttpFoundation\Response());
    try {

        // Try to respond to the request
        $psr7Response = $server->respondToAccessTokenRequest($psr7Request, $psr7Response);
    } catch (OAuthServerException $exception) {

        // All instances of OAuthServerException can be formatted into a HTTP response
        $psr7Response = $exception->generateHttpResponse($psr7Response);
    } catch (\Exception $exception) {

        // Unknown exception
        $body = new Stream('php://temp', 'r+');
        $body->write($exception->getMessage());

        $psr7Response = $response->withStatus(500)->withBody($body);
    }

    /*
     * Factory class to convert PSR7 complaint response to Symfony response
     */
    $httpFoundationFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory();

    // Return the Symfony response
    return $httpFoundationFactory->createResponse($psr7Response);
});

$app->run();
