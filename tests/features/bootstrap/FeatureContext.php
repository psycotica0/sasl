<?php

namespace Fabiang\Sasl\Behat;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext
{

    protected $hostname;
    protected $port;
    protected $domain;
    protected $username;
    protected $password;

    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     *
     * @param string  $hostname Hostname for connection
     * @param integer $port
     * @param string  $domain
     * @param string  $username Domain name of server (important for connecting)
     * @param string  $password
     */
    public function __construct($hostname, $port, $domain, $username, $password)
    {
        $this->hostname = $hostname;
        $this->port     = $port;
        $this->domain   = $domain;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @Given Connection to xmpp server
     */
    public function connectionToXmppServer()
    {
        throw new PendingException();
    }

    /**
     * @Given xmpp server supports authentication method :authenticationMethod
     */
    public function xmppServerSupportsAuthenticationMethod($authenticationMethod)
    {
        throw new PendingException();
    }

    /**
     * @When authenticate with method :authenticationMethod
     */
    public function authenticateWithMethod($authenticationMethod)
    {
        throw new PendingException();
    }

    /**
     * @Then should be authenticated with server
     */
    public function shouldBeAuthenticatedWithServer()
    {
        throw new PendingException();
    }
}
