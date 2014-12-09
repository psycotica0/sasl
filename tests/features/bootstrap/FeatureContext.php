<?php

/**
 * Sasl library.
 *
 * Copyright (c) 2002-2003 Richard Heyes,
 *               2014 Fabian Grutschus
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * o Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.|
 * o The names of the authors may not be used to endorse or promote
 *   products derived from this software without specific prior written
 *   permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Fabian Grutschus <f.grutschus@lubyte.de>
 */

namespace Fabiang\Sasl\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use PHPUnit_Framework_Assert as Assert;
use Fabiang\Sasl\Sasl;

/**
 * Defines application features from the specific context.
 *
 * @author Fabian Grutschus <f.grutschus@lubyte.de>
 */
class FeatureContext implements Context, SnippetAcceptingContext
{

    protected $hostname;
    protected $port;
    protected $domain;
    protected $username;
    protected $password;
    protected $stream;

    /**
     * @var \Fabiang\Sasl\Authentication\AuthenticationInterface
     */
    protected $authenticationObject;

    /**
     * @var Sasl
     */
    protected $authenticationFactory;

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

        $this->authenticationFactory = new Sasl;
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

        Assert::assertNotFalse($this->stream, "Coudn't connection to host {$this->hostname}");

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
        $data = $this->readStreamUntil('</stream:features>');
        Assert::assertContains("<mechanism>$authenticationMethod</mechanism>", $data);
    }

    /**
     * @When authenticate with method PLAIN
     */
    public function authenticateWithMethodPlain()
    {
        $authenticationObject = $this->authenticationFactory->factory('PLAIN');
        $authenticationData   = $authenticationObject->getResponse($this->username, $this->password);
        fwrite(
            $this->stream, '<auth xmlns="urn:ietf:params:xml:ns:xmpp-sasl" mechanism="PLAIN">'
            . base64_encode($authenticationData) . '</auth>'
        );
    }

    /**
     * @When authenticate with method DIGEST-MD5
     */
    public function authenticateWithMethodDigestMd5()
    {
        fwrite($this->stream, "<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='DIGEST-MD5'/>");
    }

    /**
     * @When authenticate with method SCRAM-SHA-1
     */
    public function authenticateWithMethodScramSha1()
    {
        $this->authenticationObject = $this->authenticationFactory->factory('scram-sha-1');

        $authData = base64_encode($this->authenticationObject->getResponse($this->username, $this->password));
        fwrite(
            $this->stream,
            "<auth xmlns='urn:ietf:params:xml:ns:xmpp-sasl' mechanism='SCRAM-SHA-1'>$authData</auth>"
        );
    }

    /**
     * @When responde to challenge received
     */
    public function respondeToChallengeReceived()
    {
        $data = $this->readStreamUntil('</challenge>');
        Assert::assertRegExp("#<challenge xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>[^<]+</challenge>#", $data);

        $authenticationObject = $this->authenticationFactory->factory('DIGEST-MD5');

        $challenge = substr($data, 52, -12);

        $response = $authenticationObject->getResponse(
            $this->username,
            $this->password,
            base64_decode($challenge),
            $this->domain,
            'xmpp'
        );

        fwrite(
            $this->stream,
            "<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>" . base64_encode($response) . "</response>"
        );
    }

    /**
     * @When responde to rspauth challenge
     */
    public function respondeToRspauthChallenge()
    {
        $data = $this->readStreamUntil('</challenge>');

        $challenge = base64_decode(substr($data, 52, -12));

        Assert::assertRegExp('/^rspauth=.+$/', $challenge);

        fwrite($this->stream, "<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'/>");
    }

    /**
     * @When responde to challenge for SCRAM-SHA-1
     */
    public function respondeToChallengeForScramSha1()
    {
        $data = $this->readStreamUntil('</challenge>');
        Assert::assertRegExp("#<challenge xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>[^<]+</challenge>#", $data);

        $challenge = base64_decode(substr($data, 52, -12));

        $authData = $this->authenticationObject->getResponse(
            $this->username,
            $this->password,
            $challenge
        );

        fwrite(
            $this->stream,
            "<response xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>" . base64_encode($authData) . "</response>"
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

    /**
     * @Then should be authenticated with server with verification
     */
    public function shouldBeAuthenticatedWithServerWithVerification()
    {
        $data = fread($this->stream, 4096);
        Assert::assertRegExp("#^<success xmlns='urn:ietf:params:xml:ns:xmpp-sasl'>[^<]+</success>$#", $data);

        $verfication = base64_decode(substr($data, 50, -10));

        Assert::assertTrue($this->authenticationObject->processOutcome($verfication));
    }

    /**
     * Read stream until string is found.
     *
     * @param string  $until
     * @param integer $timeout
     * @return string
     * @throws \Exception
     */
    private function readStreamUntil($until, $timeout = 5)
    {
        $readStart = time();
        $data = '';
        do {
            if (time() >= $readStart + $timeout) {
                throw new \Exception('Timeout when trying to receive buffer');
            }

            $data .= fread($this->stream, 4096);
        } while (false === strpos($data, $until));

        return $data;
    }
}
