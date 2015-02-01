<?php

namespace Antarctica\LaravelTokenAuth\Service\TokenUser;

use Antarctica\LaravelTokenAuth\Repository\User\UserRepositoryInterface;
use Antarctica\LaravelTokenAuth\Service\Token\TokenServiceInterface;
use Carbon;
use Illuminate\Auth\AuthManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Antarctica\LaravelTokenAuth\Exception\Token\UnknownSubjectTokenException;
use Antarctica\LaravelTokenBlacklist\Repository\TokenBlacklistRepositoryInterface;

class TokenUserService implements TokenUserServiceInterface {

    /**
     * @var TokenServiceInterface
     */
    protected $Token;
    /**
     * @var UserRepositoryInterface
     */
    protected $User;
    /**
     * @var TokenBlacklistRepositoryInterface
     */
    private $Blacklist;
    /**
     * @var AuthManager
     */
    private $Auth;

    /**
     * @param TokenServiceInterface $Token
     * @param UserRepositoryInterface $User
     * @param TokenBlacklistRepositoryInterface $Blacklist
     * @param AuthManager $Auth
     */
    function __construct(TokenServiceInterface $Token, UserRepositoryInterface $User, TokenBlacklistRepositoryInterface $Blacklist, AuthManager $Auth)
    {
        $this->Token = $Token;
        $this->User = $User;
        $this->Blacklist = $Blacklist;
        $this->Auth = $Auth;
    }

    /**
     * @return TokenServiceInterface
     */
    public function getTokenInterface()
    {
        return $this->Token;
    }

    /**
     * @return UserRepositoryInterface
     */
    public function getUserInterface()
    {
        return $this->User;
    }

    /**
     * @param string $token
     * @return string
     */
    public function getUserIdentifier($token)
    {
        return $this->Token->getSubject($token);
    }

    /**
     * @param string $token
     * @return array
     * @throws UnknownSubjectTokenException
     */
    public function getUserEntity($token)
    {
        $tokenSubject = $this->getUserIdentifier($token);

        try
        {
            $tokenUser = $this->User->find($tokenSubject);
        }
        catch(ModelNotFoundException $exception)
        {
            throw new UnknownSubjectTokenException();
        }

        return $tokenUser;
    }

    /**
     *
     * TODO: This should be refactored, if a user is not already authenticated this function should hand off to a
     * TODO: authenticate method that returns an authenticated user. This function can then always give a token to an
     * TODO: authenticated user (i.e. remove the 'else' condition)
     *
     * @param array $credentials
     * @return string
     */
    public function issue(array $credentials = [])
    {
        // If a user has already been authenticated at this point assign them a token,
        // otherwise authenticate the user with their credentials.

        if ($this->Auth->check())
        {
            return $this->Token->issueUsingUser($this->Auth->user());
        }

        // TODO: Check credentials have actually been provided, if not raise exception (missing credentials)
        // Not good enough to check for specific properties since some users may use 'username' whereas others
        // use 'email' or some other field. Therefore need to make these credentials configurable then check for them
        // alternatively a parameter could provide these details (meaning no configuration).
        //
        // Thinking at a wider scale it may make more sense not to do auth at all (in the sense that this will
        // handled somewhere else and a user object can be passed in, this allows for ghosting and a reduction in
        // the responsibility this package assumes (which is good).

        return $this->Token->issueUsingCredentials($credentials);
    }

    /**
     * @param string $token
     * @return mixed
     */
    public function revoke($token)
    {
        return $this->Blacklist->create(['token' => $token]);
    }

    /**
     * @param $token
     * @return bool
     */
    public function validate($token)
    {
        // Check token user exists (and by extension that the token is valid)
        $this->getUserEntity($token);

        // Check token isn't blacklisted
        $this->Blacklist->check($token);

        return true;
    }
}
