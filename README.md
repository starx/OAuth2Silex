# OAuth 2 Silex

This repository contains all the examples shown using Slim framework on PHP League's OAuth2 and show how to do them in Silex.

## Setup

It's very simple:

- Checkout the repository
- Setup a web server vhost to point to the `/web` folder of the project. (For the examples, I have used `http://www.oauth2-silex.test` as my host)
- Thats' it

----

Below you will find curl request showing different stuff from the library.

## Grant Types

### Auth Code Grant

#### Authorization code

To receive the authorization code, the following work flow should be followed.

1. Make a request to `http://www.oauth2-silex.test/auth` with all the required parameters.

    e.g `http://www.oauth2-silex.test/auth?response_type=code&client_id=myawesomeapp&client_details%5Bauto_approve%5D=0&scope=basic&state=randomcsrftoken&redirect_uri=http%3A%2F%2Fwww.oauth2-silex.test%2Fredirect_uri`
    
2. If the provider parameters are correct, the system will redirect to a page, where it will ask the user to sign in.

    The test credentials are: Username: `alex`, Password: `whisky`
    
3. If the credentials are correct, the system will redirect to a page, where it will ask the user, if he wants to authorize the client to access the selected scopes.
4. If authorized, the system will now generate the authorization code and redirect to the redirect URL specified.
      
#### Access token

    curl --request POST \
      --url http://www.oauth2-silex.test/auth/access_token \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=authorization_code&client_id=myawesomeapp&client_secret=abc123&code=<Auth Code>&redirect_uri=http%3A%2F%2Fwww.oauth2-silex.test%2Fredirect_uri'

### Client Credentials Grant

#### Access token      

    curl --request POST \
      --url http://www.oauth2-silex.test/auth/access_token \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=client_credentials&client_id=myawesomeapp&client_secret=abc123&scope=basic%20email'
      
### Password Grant

#### Access token

    curl --request POST \
      --url http://www.oauth2-silex.test/auth/access_token \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=password&client_id=myawesomeapp&client_secret=abc123&username=alex&password=whisky&scope=basic%20email'
      
### Refresh Token Grant

_(Note: Replace the place holder "\<Refresh Token\>" with the refresh token)_

    curl --request POST \
      --url 'http://www.oauth2-silex.test/auth/access_token?=' \
      --header 'accept: 1.0' \
      --header 'content-type: application/x-www-form-urlencoded' \
      --data 'grant_type=refresh_token&client_id=myawesomeapp&client_secret=abc123&refresh_token=<Refresh Token>'


## Other Examples

### API

#### User Details

The following lists the users. _(Note: Replace the place holder "\<Access Token\>" with the access token)_

    curl --request GET \n
      --url http://www.oauth2-silex.test/api/user \
      --header 'accept: 1.0' \
      --header 'authorization: Bearer <Access Token>'
      
#### Users Listing

The following call will give the user detail of the verifying user detail. _(Note: Replace the place holder "\<Access Token\>" with the access token)_

    curl --request GET \
      --url http://www.oauth2-silex.test/api/users \
      --header 'accept: 1.0' \
      --header 'authorization: Bearer <Access Token>'
      

Enjoy! 

~[Starx](http://mrnepal.com)
