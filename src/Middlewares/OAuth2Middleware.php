<?php

namespace OAuth2ServerExamples\Middlewares;


use League\OAuth2\Server\Middleware\ResourceServerMiddleware;
use OAuth2ServerExamples\Controllers\AbstractController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Silex\Application;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OAuth2Middleware
{
    public function invokeBefore(Request $request, Application $app) {

        $middleWare = new ResourceServerMiddleware(
            $app['resource.server']
        );

        /*
         * Factory class to convert Symfony Request to PSR7 complaint Request
         */
        $diactorosFactory = new DiactorosFactory();
        // Convert to PSR 7 request
        $psr7Request = $diactorosFactory->createRequest($request);

        // Response
        $psr7Response = $diactorosFactory->createResponse(new Response());

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
            $httpFoundationFactory = new HttpFoundationFactory();

            // Return the Symfony response
            return $httpFoundationFactory->createResponse($middlewareResponse);
        }
    }

}