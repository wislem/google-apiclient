<?php

namespace PulkitJalan\Google;

use PulkitJalan\Google\Exceptions\UnknownServiceException;

class Client
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var \Google_Client
     */
    protected $client;

    /**
     * @param array $config
     * @param string $userEmail
     */
    public function __construct(array $config, $userEmail='')
    {
        $this->config = $config;

        // create an instance of the google client for OAuth2
        $this->client = new \Google_Client();

        // set application name
        $this->client->setApplicationName(array_get($config, 'application_name', ''));

        // set oauth2 configs
        $this->client->setClientId(array_get($config, 'client_id', ''));
        $this->client->setClientSecret(array_get($config, 'client_secret', ''));
        $this->client->setRedirectUri(array_get($config, 'redirect_uri', ''));
        $this->client->setScopes(array_get($config, 'scopes', []));
        $this->client->setAccessType(array_get($config, 'access_type', 'online'));
        $this->client->setApprovalPrompt(array_get($config, 'approval_prompt', 'auto'));

        // set developer key
        $this->client->setDeveloperKey(array_get($config, 'developer_key', ''));

        // auth for service account
        if (array_get($config, 'service.enable', false)) {
            $this->auth($userEmail);
        }
    }

    /**
     * Getter for the google client.
     *
     * @return \Google_Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Getter for the google service.
     *
     * @param string $service
     *
     * @throws \Exception
     *
     * @return \Google_Service
     */
    public function make($service)
    {
        $service = 'Google_Service_'.ucfirst($service);

        if (class_exists($service)) {
            $class = new \ReflectionClass($service);

            return $class->newInstance($this->client);
        }

        throw new UnknownServiceException($service);
    }

    /**
     * Setup correct auth method based on type.
     *
     * @param $userEmail
     * @return void
     */
    protected function auth($userEmail='')
    {
        // see (and use) if user has set Credentials
        if ($this->useAssertCredentials($userEmail)) {
            return;
        }

        // check (and use) if running on app engine
        if ($this->useAppEngine()) {
            return;
        }

        // fallback to compute engine
        $auth = new \Google_Auth_ComputeEngine($this->client);
        $this->client->setAuth($auth);
    }

    /**
     * Determine and use credentials if user has set them.
     * @param $userEmail
     * @return boolean used or not
     */
    protected function useAssertCredentials($userEmail='')
    {
        $account = array_get($this->config, 'service.account', '');
        if (!empty($account)) {
            $cert = new \Google_Auth_AssertionCredentials(
                array_get($this->config, 'service.account', ''),
                array_get($this->config, 'service.scopes', []),
                file_get_contents(array_get($this->config, 'service.key', ''))
            );
            if($userEmail){
                $cert->sub=$userEmail;
            }
            $this->client->setAssertionCredentials($cert);

            return true;
        }

        return false;
    }

    /**
     * Determine and use app engine credentials
     * if running on app engine.
     *
     * @return boolean used or not
     */
    protected function useAppEngine()
    {
        // if running on app engine
        if ($this->client->isAppEngine()) {
            $auth = new \Google_Auth_AppIdentity($this->client);
            $this->client->setAuth($auth);

            return true;
        }

        return false;
    }

    /**
     * Magic call method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->client, $method)) {
            return call_user_func_array([$this->client, $method], $parameters);
        }

        throw new \BadMethodCallException(sprintf('Method [%s] does not exist.', $method));
    }
}