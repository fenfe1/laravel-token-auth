<?php

namespace Antarctica\LaravelTokenAuth\Service\Token;

interface TokenServiceInterface {

    /**
     * @return string
     */
    public function get();

    /**
     * @param string $token
     * @return array
     */
    public function decode($token);

    /**
     * @param string $token
     * @return string
     */
    public function getSubject($token);

    /**
     * @param string $token
     * @return string
     */
    public function getExpiry($token);

    /**
     * @param array $credentials
     * @return string
     */
    public function issueUsingCredentials(array $credentials);

    /**
     * @param $user
     * @return string
     */
    public function issueUsingUser($user);
}
