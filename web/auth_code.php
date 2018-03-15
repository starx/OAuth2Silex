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
use League\OAuth2\Server\Grant\AuthCodeGrant;
use OAuth2ServerExamples\Entities\UserEntity;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use OAuth2ServerExamples\Repositories\AuthCodeRepository;
use OAuth2ServerExamples\Repositories\ClientRepository;
use OAuth2ServerExamples\Repositories\RefreshTokenRepository;
use OAuth2ServerExamples\Repositories\ScopeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
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
        new \DateInterval('PT1H')
    );

    return $server;

};

$app->get('/authorize', function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    /* @var \League\OAuth2\Server\AuthorizationServer $server */
    $server = $app['authorization.server'];
    /*
     * Factory class to convert Symfony Request to PSR7 complaint Request
     */
    $diactorosFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory();

    // Create the request using GLOBALS and convert
    $psr7Request = $diactorosFactory->createRequest($request);

    // Response
    $psr7Response = $diactorosFactory->createResponse(new \Symfony\Component\HttpFoundation\Response());

    try {
        // Validate the HTTP request and return an AuthorizationRequest object.
        // The auth request object can be serialized into a user's session
        $authRequest = $server->validateAuthorizationRequest($psr7Request);

        // Once the user has logged in set the user on the AuthorizationRequest
        $authRequest->setUser(new UserEntity());

        // Once the user has approved or denied the client update the status
        // (true = approved, false = denied)
        $authRequest->setAuthorizationApproved(true);

        // Return the HTTP redirect response
        $psr7Response = $server->completeAuthorizationRequest($authRequest, $psr7Response);
    } catch (OAuthServerException $exception) {
        $psr7Response = $exception->generateHttpResponse($psr7Response);
    } catch (\Exception $exception) {
        $body = new Stream('php://temp', 'r+');
        $body->write($exception->getMessage());

        $psr7Response = $psr7Response->withStatus(500)->withBody($body);
    }

    /*
     * Factory class to convert PSR7 complaint response to Symfony response
     */
    $httpFoundationFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory();

    // Return the Symfony response
    return $httpFoundationFactory->createResponse($psr7Response);
});

$app->get('/redirect_uri', function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    return new \Symfony\Component\HttpFoundation\Response(<<<HTML
<html>
    <body>
    <table width="400px">
        <tr>
            <td>Code: </td>
            <td>{$request->get('code')}</td>
        </tr>
        <tr>
            <td>State: </td>
            <td>{$request->get('state')}</td>
        </tr>
    </table>
    </body>
</html>
HTML
);
});

$app->post('/access_token', function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
    /* @var \League\OAuth2\Server\AuthorizationServer $server */
    $server = $app['authorization.server'];

    /*
     * Factory class to convert Symfony Request to PSR7 complaint Request
     */
    $diactorosFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory();

    // Create the request using GLOBALS and convert
    $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $request = $diactorosFactory->createRequest($request);

    // Response
    $response = $diactorosFactory->createResponse(new \Symfony\Component\HttpFoundation\Response());

    try {
        $response = $server->respondToAccessTokenRequest($request, $response);
    } catch (OAuthServerException $exception) {
        $response = $exception->generateHttpResponse($response);
    } catch (\Exception $exception) {
        $body = new Stream('php://temp', 'r+');
        $body->write($exception->getMessage());

        $response = $response->withStatus(500)->withBody($body);
    }

    /*
     * Factory class to convert PSR7 complaint response to Symfony response
     */
    $httpFoundationFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory();

    // Return the Symfony response
    return $httpFoundationFactory->createResponse($response);
});

$app->run();
