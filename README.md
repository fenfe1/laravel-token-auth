# Laravel Token Auth

Enables use of API tokens as a form of stateless authentication within Laravel.

This package is designed not to rely on any particular Token or Token Blacklist implementation, however by default the [tymondesigns/jwt-auth](https://github.com/tymondesigns/jwt-auth) package is used to provide a JWT based Token implementation and the `antarctica/laravel-token-blacklist` package is used to provided a default Token Blacklist implementation.

It is possible to provide your own implementations for managing tokens and/or blacklisting them. See the _custom implementations_ section for details.

## Installing

Require this package in your `composer.json` file:

```json
{
    "require": {
        "antarctica/laravel-token-auth": "0.1.*"
    }
}
```

Run `composer update`.

Register the service provider in the `providers` array of your `app/config/app.php` file:

```php
'providers' => array(
	Antarctica\LaravelTokenAuth\LaravelTokenAuthServiceProvider,
)
```

This package uses a Repository through which users can be retrieved. There is NO default implementation for this repository included in this package. You MUST therefore provide an implementation that implements the provided interface through this package's config file.

To publish the config file run:

```shell
php artisan config:publish antarctica/laravel-token-auth
```
    
Then edit the `user_repository` key.

### Filters

To support both standard session based and token based authentication this package provides an `auth.combined` filter.

It it also recommended to use a [once basic](http://laravel.com/docs/4.2/security#http-basic-authentication) filter for 
authenticating users via Basic authentication in order to request a token (i.e. at the start of the authentication flow).

To enable these filters add the following to your `app/filters.php` file:

```php
/*
|--------------------------------------------------------------------------
| Custom Authentication Filters
|--------------------------------------------------------------------------
|
| The "combined" filter is a custom filter which allows session and token
| based authentication to be combined. This means a user can be authenticated
| using either an active session (i.e. being logged in) or by providing a
| token (i.e. using the Authorization header). The "once" filter is a
| stateless version of the "basic" filter suitable for use in APIs.
|
*/

Route::filter('auth.combined', 'Antarctica\LaravelTokenAuth\Filter\AuthFilter');

Route::filter('auth.once', function()
{
	
	return Auth::onceBasic();
});
```

Note: The `Auth::onceBasic()` method will check against a `email` field in your user model by default. If you use a 
different field (i.e. `username`) add this as a argument.

E.g.

```php
	return Auth::onceBasic('username');
```

## Usage

### Issuing tokens

In a controller (i.e. `tokensController`) add a method (i.e. `store`) to issue a token.

It is recommended to protect this method with Basic authentication (SSL is therefore a requirement) using the `auth.basic`
filter. This ensures a valid user exists, which can then be passed to this package to issue a token.

In the controller constructor inject the TokenUserService from this package and protect the store method used to issue 
new tokens with the `auth.basic` filter.

```php
<?php

use Antarctica\LaravelTokenAuth\Service\TokenUser\TokenUserServiceInterface;

class TokensController extends BaseController {

    function __construct(TokenUserServiceInterface $TokenUser)
    {
        // The store method is the only method that accepts a users real credentials,
        // and therefore uses a different authentication filter to perform a check of
        // the users actual credentials (as opposed to a token or a session).
        $this->beforeFilter('auth.once', array('only' => array('store')));

        $this->TokenUser = $TokenUser;
    }
    
    /**
     * Issue a new token if auth successful
     * POST /tokens
     * @return \Illuminate\Http\JsonResponse
     */
    public function store()
    {
        $token = $this->TokenUser->issue();

        $tokenExpiry = $this->TokenUser->getTokenInterface()->getExpiry($token);

        $data = [
            'token' => $token
        ];

        $notices = [
            [
                'type' => 'token_generated',
                'details' => [
                    'expiry' => [
                        'expires' => $tokenExpiry,
                        'message' => 'This token will expire at: ' . Carbon::createFromTimeStamp($tokenExpiry)->toDateTimeString() . ', at which point you will need to request a new token.'
                    ]
                ]
            ]
        ];

        return Response::json(['notices' => $notices, 'data' => $data]);
    }
}
```

Note: Currently this approach only allows the actual auth user to be used (i.e. you cannot provide a user object to 
perform user ghosting. This will be addressed in future versions of the package.

Alternatively you can use this package to authenticate a user's credentials and issue a token. This is not recommended
as users will need to use a non-standard way to provide their credentials and you will have to collect them and pass to
this package. Internally this package performs exactly the same *auth once* check that the recommended method uses.

Note: From version `1.0.0` onwards this package will no longer provide the ability to pass a users credentials. It is 
therefore recommended not to rely on this ability now.

### Protecting routes (i.e. require a valid token to access)

Use the `auth.combined` filter on any route you wish to protect. If a valid token (or authentication session) cannot be
found suitable error will be returned (i.e. expired token, no token, etc.)

E.g. In `routes.php`

```php
Route::get('/secret', array('before' => 'auth.combined', function()
{
    	return Response::json(['message' => 'Yay you get to know the secret']);
}));
```

E.g. In a controller:

```php
function __construct()
    {
        $this->beforeFilter('auth.combined');
    }
```

## Contributing

This project welcomes contributions, see `CONTRIBUTING` for our general policy.

## Developing

To aid development and keep your local computer clean, a VM (managed by Vagrant) is used to create an isolated environment with all necessary tools/libraries available.

### Requirements

* Mac OS X
* Ansible `brew install ansible`
* [VMware Fusion](http://vmware.com/fusion)
* [Vagrant](http://vagrantup.com) `brew cask install vmware-fusion vagrant`
* [Host manager](https://github.com/smdahlen/vagrant-hostmanager) and [Vagrant VMware](http://www.vagrantup.com/vmware) plugins `vagrant plugin install vagrant-hostmanager && vagrant plugin install vagrant-vmware-fusion`
* You have a private key `id_rsa` and public key `id_rsa.pub` in `~/.ssh/`
* You have an entry like [1] in your `~/.ssh/config`

[1] SSH config entry

```shell
Host bslweb-*
    ForwardAgent yes
    User app
    IdentityFile ~/.ssh/id_rsa
    Port 22
```

### Provisioning development VM

VMs are managed using Vagrant and configured by Ansible.

```shell
$ git clone ssh://git@stash.ceh.ac.uk:7999/basweb/laravel-token-auth.git
$ cp ~/.ssh/id_rsa.pub laravel-token-auth/provisioning/public_keys/
$ cd laravel-token-auth
$ ./armadillo_standin.sh

$ vagrant up

$ ssh bslweb-laravel-token-auth-dev-node1
$ cd /app

$ composer install

$ logout
```

### Committing changes

The [Git flow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow) workflow is used to manage development of this package.

Discrete changes should be made within *feature* branches, created from and merged back into *develop* (where small one-line changes may be made directly).

When ready to release a set of features/changes create a *release* branch from *develop*, update documentation as required and merge into *master* with a tagged, [semantic version](http://semver.org/) (e.g. `v1.2.3`).

After releases the *master* branch should be merged with *develop* to restart the process. High impact bugs can be addressed in *hotfix* branches, created from and merged into *master* directly (and then into *develop*).

### Issue tracking

Issues, bugs, improvements, questions, suggestions and other tasks related to this package are managed through the BAS Web & Applications Team Jira project ([BASWEB](https://jira.ceh.ac.uk/browse/BASWEB)).

### Clean up

To remove the development VM:

```shell
vagrant halt
vagrant destroy
```

The `laravel-token-auth` directory can then be safely deleted as normal.

## License

Copyright 2015 NERC BAS. Licensed under the MIT license, see `LICENSE` for details.
