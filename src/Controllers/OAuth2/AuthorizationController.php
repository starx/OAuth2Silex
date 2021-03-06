<?php
/**
 * @author      Starx <contact@starx.io>
 * @copyright   Copyright (c) Nabin Nepal
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/starx/OAuth2Silex
 */

namespace OAuth2ServerExamples\Controllers\OAuth2;


use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use OAuth2ServerExamples\Controllers\AbstractController;
use OAuth2ServerExamples\Entities\UserEntity;
use OAuth2ServerExamples\Forms\OAuth2\AuthorizeClientFormType;
use OAuth2ServerExamples\Forms\OAuth2\SignInFormType;
use OAuth2ServerExamples\Repositories\ClientRepository;
use OAuth2ServerExamples\Repositories\UserRepository;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Zend\Diactoros\Stream;

class AuthorizationController extends AbstractController
{
    /**
     * Authorization Code Grant: Workflow
     *
     * Validates if the authorization request is correct
     * Forwards the user to sign in page if requirements met
     *
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function authorizationRequestAction(Request $request)
    {
        $app = $this->getApp();

        /* @var AuthorizationServer $server */
        $server = $app['authorization.server'];

        // Factory class to convert Symfony Request to PSR7 complaint Request
        $diactorosFactory = new DiactorosFactory();

        // Create the request using GLOBALS and convert
        $psr7Request = $diactorosFactory->createRequest($request);

        // Response
        $psr7Response = $diactorosFactory->createResponse(new Response());

        try {
            // Validate the HTTP request and return an AuthorizationRequest object.
            // The auth request object can be serialized into a user's session
            $authRequest = $server->validateAuthorizationRequest($psr7Request);

            /** @var Session $session */
            $session = $app['session'];
            $session->set('client_id', $request->get('client_id'));
            $session->set('client_details', $request->get('client_details'));
            $session->set('redirect_uri', $request->get('redirect_uri'));
            $session->set('response_type', $request->get('response_type'));
            $session->set('scope', $request->get('scope'));
            $session->set('auth_request', $authRequest);

            /** @var UrlGenerator $urlGenerator */
            $urlGenerator = $app['url_generator'];

            return new RedirectResponse($urlGenerator->generate('oauth2.auth.sign_in'));

        } catch (OAuthServerException $exception) {
            $psr7Response = $exception->generateHttpResponse($psr7Response);
        } catch (\Exception $exception) {
            $body = new Stream('php://temp', 'r+');
            $body->write($exception->getMessage());

            $psr7Response = $psr7Response->withStatus(500)->withBody($body);
        }

        // Factory class to convert PSR7 complaint response to Symfony response
        $httpFoundationFactory = new HttpFoundationFactory();

        // Return the Symfony response
        return $httpFoundationFactory->createResponse($psr7Response);
    }

    /**
     * Authorization Code Grant: Workflow
     *
     * Shows an interface where the user can sign in
     * Forwards the user to approval/authorize page for consent
     *
     * @param Request $request
     * @return string|RedirectResponse
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function signInAction(Request $request)
    {
        $app = $this->getApp();
        /** @var Session $session */
        $session = $app['session'];

        // Retrieve the auth params from the user's session
        $params['client_id'] = $session->get('client_id');
        $params['client_details'] = $session->get('client_details');
        $params['redirect_uri'] = $session->get('redirect_uri');
        $params['response_type'] = $session->get('response_type');
        $params['scope'] = $session->get('scope');

        // Validate the params
        try {
            // Check that the auth params are all present
            foreach ($params as $key => $value) {
                if ($value === null) {
                    // Throw an error because an auth param is missing - don't
                    //  continue any further
                    throw new \Exception("{$key} missing.");
                }
            }
        } catch (\Exception $e) {
            $body = new Stream('php://temp', 'r+');
            $body->write($e->getMessage());
            return new Response($body, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Process the sign-in form submission
        $formBuilder = $this->formFactory()->createBuilder(SignInFormType::class, []);
        $formBuilder->setMethod('POST');

        $form = $formBuilder->getForm();

        try {
            if ($request->getMethod() == Request::METHOD_POST) {
                $form->handleRequest($request);
                if ($form->isValid()) {
                    $data = $form->getData();

                    // Get username
                    $u = $data['username'];
                    if ($u === null || trim($u) === '') {
                        throw new \Exception('Please enter your username.');
                    }

                    // Get password
                    $p = $data['password'];
                    if ($p === null || trim($p) === '') {
                        throw new \Exception('Please enter your password.');
                    }

                    /*
                     * Verify the user's username and password
                     */
                    $grantType = 'authorization_code';

                    // Try to get the client entity
                    $clientRepository = new ClientRepository();

                    $clientEntity = $clientRepository->getClientEntity($params['client_id'], $grantType, null, false);

                    if (!$clientEntity instanceof ClientEntityInterface) {
                        throw new \Exception('Client could not be identified');
                    }

                    // Try to get the user entity
                    $userRepository = new UserRepository();
                    $userEntity = $userRepository
                        ->getUserEntityByUserCredentials($u, $p, $grantType, $clientEntity);

                    // If the entity was found
                    if ($userEntity instanceof UserEntity) {
                        // Set the user's ID to a session
                        $session->set('user_id', $userEntity->getIdentifier());

                        // Get the authorization request object from the session
                        /** @var AuthorizationRequest $authRequest */
                        $authRequest = $session->get('auth_request');

                        // Set the user into the AuthRequest
                        $authRequest->setUser($userEntity);

                        // Save the updated AuthorizationRequest on the session
                        $session->set('auth_request', $authRequest);
                    } else {
                        throw new \Exception('User could not be identified');
                    }
                }
            }
        } catch (\Exception $e) {
            $params['error_message'] = $e->getMessage();
        }

        // Get the user's ID from their session
        $params['user_id'] = $session->get('user_id');

        // User is signed in
        if ($params['user_id'] !== null) {
            // Redirect the user to authorise route
            /** @var UrlGenerator $urlGenerator */
            $urlGenerator = $app['url_generator'];
            return new RedirectResponse($urlGenerator->generate('oauth2.auth.authorize'));
        }

        // User is not signed in, show the sign-in form
        return $this->twig()->render("OAuth2/sign_in.twig", [
            'form' => $form->createView(),
            'data' => [
                'params' => $params
            ]
        ]);
    }

    /**
     * Authorization Code Grant: Workflow
     *
     * Shows an interface to receive user consent about the client accessing the details
     * Gives Authorization code if every requirement is full filled
     *
     * @param Request $request
     * @return string|RedirectResponse|Response
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function authorizeAction(Request $request)
    {
        $app = $this->getApp();
        /** @var Session $session */
        $session = $app['session'];

        // Retrieve the auth params from the user's session
        $params['client_id'] = $session->get('client_id');
        $params['client_details'] = $session->get('client_details');
        $params['redirect_uri'] = $session->get('redirect_uri');
        $params['response_type'] = $session->get('response_type');
        $params['scope'] = $session->get('scope');

        // Validate the params
        try {
            // Check that the auth params are all present
            foreach ($params as $key => $value) {
                if ($value === null) {
                    // Throw an error because an auth param is missing - don't
                    //  continue any further
                    throw new \Exception("{$key} missing.");
                }
            }
        } catch (\Exception $e) {
            $body = new Stream('php://temp', 'r+');
            $body->write($e->getMessage());
            return new Response($body, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Get the user's ID and Authorization request from the session
        $params['user_id'] = $session->get('user_id');
        $params['auth_request'] = $session->get('auth_request');

        // User is not signed in so redirect them to the sign-in route
        if ($params['user_id'] == null || $params['auth_request'] == null) {
            // Redirect the user to authorise route
            /** @var UrlGenerator $urlGenerator */
            $urlGenerator = $app['url_generator'];
            return new RedirectResponse($urlGenerator->generate('oauth2.auth.sign_in'));
        }

        // Flag to mark approval of authorization
        $authorizationApproved = false;

        // Check if the client should be automatically approved
        $autoApprove = ($params['client_details']['auto_approve'] === '1') ? true : false;

        // If not set for auto approval, show/process the approval form
        if ($autoApprove !== true) {
            // Process the sign-in form submission
            $formBuilder = $this->formFactory()->createBuilder(AuthorizeClientFormType::class, []);
            $formBuilder->setMethod('POST');
            $form = $formBuilder->getForm();

            // Handle the form's post
            if ($request->getMethod() == Request::METHOD_POST) {
                // Set the authorized flag as false by default
                $clientAuthorized = false;

                $form->handleRequest($request);
                if ($form->isValid()) {
                    $data = $form->getData();

                    // Update the $clientAuthorised with value from the form
                    $clientAuthorized = $data['authorize'];
                }

                $authorizationApproved = $clientAuthorized === true;

            } else {
                // User has not approved the client
                // show the authorize client form

                return $this->twig()->render("OAuth2/authorize_client.twig", [
                    'form' => $form->createView(),
                    'data' => [
                        'params' => $params
                    ]
                ]);
            }
        } else {
            // Since selected for auto approval, change the flag to true
            $authorizationApproved = true;
        }

        /*
         * Final approval stage, process the authorization details and respond
         */
        try {
            // Get the authorization request object from the session
            /** @var AuthorizationRequest $authRequest */
            $authRequest = $session->get('auth_request');

            // Update the AuthorizationRequest with the status
            // (true = approved, false = denied)
            $authRequest->setAuthorizationApproved($authorizationApproved);

            /*
             * Complete the authorization process
             */

            // Get the authorization server
            /** @var AuthorizationServer $server */
            $server = $app['authorization.server'];

            // Factory class to convert Symfony Request to PSR7 complaint Request
            $diactorosFactory = new DiactorosFactory();

            // Get the HTTP redirect response
            $psr7Response = $server->completeAuthorizationRequest(
                $authRequest,
                $diactorosFactory->createResponse(new Response())
            );

            // Factory class to convert PSR7 complaint response to Symfony response
            $httpFoundationFactory = new HttpFoundationFactory();

            // Invalidate the session
            $session->invalidate();

            // Return the Symfony response
            return $httpFoundationFactory->createResponse($psr7Response);

        } catch (\Exception $e) {
            $body = new Stream('php://temp', 'r+');
            $body->write($e->getMessage());
            return new Response($body, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Gives access token based on the grant
     *
     * @return Response
     */
    public function accessTokenAction()
    {
        $app = $this->getApp();

        /* @var AuthorizationServer $server */
        $server = $app['authorization.server'];

        // Factory class to convert Symfony Request to PSR7 complaint Request
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

        // Factory class to convert PSR7 complaint response to Symfony response
        $httpFoundationFactory = new HttpFoundationFactory();

        // Return the Symfony response
        return $httpFoundationFactory->createResponse($psr7Response);
    }
}
