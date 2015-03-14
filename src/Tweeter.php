<?php
namespace Auraphp\Bin;

use TwitterOAuth\Auth\SingleUserAuth;
use TwitterOAuth\Serializer\ArraySerializer;

class Tweeter
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function postStatusesUpdate($status)
    {
        $this->newAuth()->post('statuses/update', array(
            'status' => $status,
        ));
    }

    protected function newAuth()
    {
        $credentials = array(
            'consumer_key' => $this->config->twitter_consumer_key,
            'consumer_secret' => $this->config->twitter_consumer_secret,
            'oauth_token' => $this->config->twitter_access_token,
            'oauth_token_secret' => $this->config->twitter_access_token_secret,
        );

        return new SingleUserAuth($credentials, new ArraySerializer());
    }
}
