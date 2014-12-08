<?php

namespace Fabiang\Sasl\Behat;

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use PHPUnit_Framework_Assert as Assert;
use Fabiang\Sasl\Sasl;

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
    protected $stream;
    protected $authenticationObject;

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
        $this->port     = (int) $port;
        $this->domain   = $domain;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @AfterScenario
     */
    public function closeConnection()
    {
        if ($this->stream) {
            fclose($this->stream);
        }
    }

    /**
     * @Given Connection to xmpp server
     */
    public function connectionToXmppServer()
    {
        $errno  = null;
        $errstr = null;

        $this->stream = stream_socket_client("tcp://{$this->hostname}:{$this->port}", $errno, $errstr, 5);
        if (!$this->stream) {
            throw new \Exception("Coudn't connection to host {$this->hostname}");
        }

        fwrite(
            $this->stream, '<?xml version="1.0" encoding="UTF-8"?><stream:stream to="' . $this->domain
            . '" xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client" version="1.0">'
        );
    }

    /**
     * @Given xmpp server supports authentication method :authenticationMethod
     */
    public function xmppServerSupportsAuthenticationMethod($authenticationMethod)
    {
        $data = $this->readStreamUntil('</features>');
        Assert::assertContains("<mechanism>$authenticationMethod</mechanism>", $data);
    }

    /**
     * @When authenticate with method :authenticationMethod
     */
    public function authenticateWithMethod($authenticationMethod)
    {
        $authenticationFactory = new Sasl;

        $this->authenticationObject = $authenticationFactory->factory($authenticationMethod);
        $authenticationData         = $this->authenticationObject->getResponse($this->username, $this->password);
        fwrite(
            $this->stream, '<auth xmlns="urn:ietf:params:xml:ns:xmpp-sasl" mechanism="'
            . $authenticationMethod . '">' . base64_encode($authenticationData) . '</auth>'
        );
    }

    /**
     * @Then should be authenticated with server
     */
    public function shouldBeAuthenticatedWithServer()
    {
        $data = fread($this->stream, 4096);
        Assert::assertSame("<success xmlns='urn:ietf:params:xml:ns:xmpp-sasl'/>", $data);
    }

    private function readStreamUntil($until, $timeout = 5)
    {
        $readStart = time();
        $data = '';
        do {
            $data .= fread($this->stream, 4096);

            if (time() >= $readStart + $timeout) {
                throw new \Exception('Timeout when trying to receive buffer');
            }

        } while (strpos($data, $until));

        return $data;
    }
}
