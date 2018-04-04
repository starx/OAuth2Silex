<?php

namespace OAuth2ServerExamples\Controllers\OAuth2;


use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth2ServerExamples\Controllers\AbstractController;
use OAuth2ServerExamples\Entities\UserEntity;
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

class AuthorisationController extends AbstractController
{
    public function referenceAuthorizationRequestAction(Request $request)
    {
        $app = $this->getApp();

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
    }

    public function authorizationRequestAction(Request $request)
    {
        $app = $this->getApp();

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

            /** @var Session $session */
            $session = $app['session'];
            $session->set('client_id', $psr7Request->getAttribute('client_id'));
            $session->set('client_details', $psr7Request->getAttribute('client_details'));
            $session->set('redirect_uri', $psr7Request->getAttribute('redirect_uri'));
            $session->set('response_type', $psr7Request->getAttribute('response_type'));
            $session->set('scopes', $psr7Request->getAttribute('scopes'));

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

        /*
         * Factory class to convert PSR7 complaint response to Symfony response
         */
        $httpFoundationFactory = new HttpFoundationFactory();

        // Return the Symfony response
        return $httpFoundationFactory->createResponse($psr7Response);
    }

    private function getSignInForm($formData) {
        $app = $this->getApp();
        /** @var FormFactory $formFactory */
        $formFactory = $app['form.factory'];

        /*
         * Build form
         */
        $form = $formFactory->createBuilder(FormType::class, $formData)
            ->add('username')
            ->add('password', PasswordType::class)
            ->add('submit', SubmitType::class, [
                'label' => 'Sign in',
            ])
            ->getForm();

        return $form;
    }

    public function signInAction(Request $request) {
        $app = $this->getApp();
        /** @var Session $session */
        $session = $app['session'];

        // Retrieve the auth params from the user's session
        $params['client_id'] = $session->get('client_id');
        $params['client_details'] = $session->get('client_details');
        $params['redirect_uri'] = $session->get('redirect_uri');
        $params['response_type'] = $session->get('response_type');
        $params['scopes'] = $session->get('scopes');

        // Check that the auth params are all present
        foreach ($params as $key=>$value) {
            if ($value === null) {
                // Throw an error because an auth param is missing - don't
                //  continue any further
            }
        }

        // Process the sign-in form submission
        $formBuilder = $this->formFactory()->createBuilder(SignInFormType::class, []);
        $form = $formBuilder->getForm();
        try {
            if (
                $request->getMethod() == Request::METHOD_POST &&
                $form->isValid()
            ) {
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

                // Verify the user's username and password
                $grantType = 'authorization_code';

                $clientRepository = new ClientRepository();
                $clientEntity = $clientRepository->getClientEntity($params['client_id'], $grantType);

                $userRepository = new UserRepository();
                $userEntity = $userRepository
                    ->getUserEntityByUserCredentials($u, $p, $grantType, $clientEntity);

                // If the entity was found
                if ($userEntity instanceof UserEntity) {
                    // Set the user's ID to a session
                    $session->set('user_id', $userEntity->getIdentifier());
                }
            }
        } catch(\Exception $e) {
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
        else {
            return $this->twig()->render("OAuth2/sign_in.twig", [
                'form' => $form->createView()
            ]);
        }
    }


    public function accessTokenAction() {
        $app = $this->getApp();

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
    }
}