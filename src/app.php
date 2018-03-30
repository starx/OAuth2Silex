<?php

use Silex\Provider\ServiceControllerServiceProvider;

use OAuth2ServerExamples\Repositories\UserRepository;
use OAuth2ServerExamples\Repositories\ClientRepository;
use OAuth2ServerExamples\Repositories\ScopeRepository;
use OAuth2ServerExamples\Repositories\AccessTokenRepository;
use OAuth2ServerExamples\Repositories\AuthCodeRepository;
use OAuth2ServerExamples\Repositories\RefreshTokenRepository;
use OAuth2ServerExamples\Entities\UserEntity;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Middleware\ResourceServerMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Zend\Diactoros\Stream;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;


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
        'file://' . __DIR__ . '/../public.key'  // the authorization server's public key
    );

    return $server;
};

$apiAuthApp = $app['controllers_factory'];

$apiAuthApp->get('/authorize', function (Request $request) use ($app) {
    /* @var AuthorizationServer $server */
    $server = $app['authorization.server'];
    /*
     * Factory class to convert Symfony Request to PSR7 complaint Request
     */
    $diactorosFactory = new DiactorosFactory();

    // Create the request using GLOBALS and convert
    $psr7Request = $diactorosFactory->createRequest($request);

    // Response
    $psr7Response = $diactorosFactory->createResponse(new Response());

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
    $httpFoundationFactory = new HttpFoundationFactory();

    // Return the Symfony response
    return $httpFoundationFactory->createResponse($psr7Response);
});

$apiAuthApp->post('/access_token', function() use ($app) {

    /* @var AuthorizationServer $server */
    $server = $app['authorization.server'];

    /*
     * Factory class to convert Symfony Request to PSR7 complaint Request
     */
    $diactorosFactory = new DiactorosFactory();

    // Create the request using GLOBALS and convert
    $request = Request::createFromGlobals();
    $psr7Request = $diactorosFactory->createRequest($request);

    // Response
    $psr7Response = $diactorosFactory->createResponse(new Response());
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

        $psr7Response = $psr7Response->withStatus(500)->withBody($body);
    }

    /*
     * Factory class to convert PSR7 complaint response to Symfony response
     */
    $httpFoundationFactory = new HttpFoundationFactory();

    // Return the Symfony response
    return $httpFoundationFactory->createResponse($psr7Response);
});

$app->mount('/auth', $apiAuthApp);

$apiApp = $app['controllers_factory'];

// Add the resource server middleware which will intercept and validate requests
$apiApp->before(function (\Symfony\Component\HttpFoundation\Request $request, \Silex\Application $app) {
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
$apiApp->get('/users', function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
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
});

$apiApp->get('/user', function (\Symfony\Component\HttpFoundation\Request $request) use ($app) {
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

    // Response
    return new \Symfony\Component\HttpFoundation\JsonResponse($params);
});

$app->mount('/api', $apiApp);

/*
 * An example redirect URI to be redirected from the authorize code
 */
$app->get('/redirect_uri', function (Request $request) use ($app) {
    return new Response(<<<HTML
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

return $app;
