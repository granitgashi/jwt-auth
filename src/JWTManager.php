<?php

namespace Tymon\JWTAuth;

use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Contracts\Providers\JWT;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;

class JWTManager
{
    /**
     * @var \Tymon\JWTAuth\Contracts\Providers\JWT
     */
    protected $provider;

    /**
     * @var \Tymon\JWTAuth\Blacklist
     */
    protected $blacklist;

    /**
     * @var \Tymon\JWTAuth\PayloadFactory
     */
    protected $payloadFactory;

    /**
     * @var boolean
     */
    protected $blacklistEnabled = true;

    /**
     * @var boolean
     */
    protected $refreshFlow = false;

    /**
     *  @param \Tymon\JWTAuth\Contracts\Providers\JWT  $provider
     *  @param \Tymon\JWTAuth\Blacklist                $blacklist
     *  @param \Tymon\JWTAuth\PayloadFactory           $payloadFactory
     */
    public function __construct(JWT $provider, Blacklist $blacklist, PayloadFactory $payloadFactory)
    {
        $this->provider = $provider;
        $this->blacklist = $blacklist;
        $this->payloadFactory = $payloadFactory;
    }

    /**
     * Encode a Payload and return the Token
     *
     * @param  \Tymon\JWTAuth\Payload  $payload
     *
     * @return \Tymon\JWTAuth\Token
     */
    public function encode(Payload $payload)
    {
        $token = $this->provider->encode($payload->get());

        return new Token($token);
    }

    /**
     * Decode a Token and return the Payload
     *
     * @param  \Tymon\JWTAuth\Token $token
     *
     * @throws TokenBlacklistedException
     *
     * @return \Tymon\JWTAuth\Payload
     */
    public function decode(Token $token)
    {
        $payloadArray = $this->provider->decode($token->get());

        $payload = $this->payloadFactory->setRefreshFlow($this->refreshFlow)->make($payloadArray);

        if ($this->blacklistEnabled && $this->blacklist->has($payload)) {
            throw new TokenBlacklistedException('The token has been blacklisted');
        }

        return $payload;
    }

    /**
     * Refresh a Token and return a new Token
     *
     * @param  \Tymon\JWTAuth\Token  $token
     * @param  array                 $customClaims
     *
     * @return \Tymon\JWTAuth\Token
     */
    public function refresh(Token $token, array $customClaims = [])
    {
        $payload = $this->setRefreshFlow()->decode($token);

        if ($this->blacklistEnabled) {
            // invalidate old token
            $this->blacklist->add($payload);
        }

        // return the new token
        return $this->encode(
            $this->payloadFactory->make(
                array_merge($customClaims, ['sub' => $payload['sub'], 'iat' => $payload['iat']])
            )
        );
    }

    /**
     * Invalidate a Token by adding it to the blacklist
     *
     * @param  Token  $token
     *
     * @throws JWTException
     *
     * @return boolean
     */
    public function invalidate(Token $token)
    {
        if (! $this->blacklistEnabled) {
            throw new JWTException('You must have the blacklist enabled to invalidate a token.');
        }

        return $this->blacklist->add($this->decode($token));
    }

    /**
     * Get the PayloadFactory instance
     *
     * @return \Tymon\JWTAuth\PayloadFactory
     */
    public function getPayloadFactory()
    {
        return $this->payloadFactory;
    }

    /**
     * Get the JWTProvider instance
     *
     * @return \Tymon\JWTAuth\Contracts\Providers\JWT
     */
    public function getJWTProvider()
    {
        return $this->provider;
    }

    /**
     * Get the Blacklist instance
     *
     * @return \Tymon\JWTAuth\Blacklist
     */
    public function getBlacklist()
    {
        return $this->blacklist;
    }

    /**
     * Set whether the blacklist is enabled
     *
     * @param bool  $enabled
     *
     * @return \Tymon\JWTAuth\JWTManager
     */
    public function setBlacklistEnabled($enabled)
    {
        $this->blacklistEnabled = $enabled;

        return $this;
    }

    /**
     * Set the refresh flow
     *
     * @param boolean $refreshFlow
     *
     * @return JWTManager
     */
    public function setRefreshFlow($refreshFlow = true)
    {
        $this->refreshFlow = $refreshFlow;

        return $this;
    }
}
