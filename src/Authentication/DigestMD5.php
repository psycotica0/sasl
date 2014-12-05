<?php

// +-----------------------------------------------------------------------+
// | Copyright (c) 2002-2003 Richard Heyes                                 |
// | All rights reserved.                                                  |
// |                                                                       |
// | Redistribution and use in source and binary forms, with or without    |
// | modification, are permitted provided that the following conditions    |
// | are met:                                                              |
// |                                                                       |
// | o Redistributions of source code must retain the above copyright      |
// |   notice, this list of conditions and the following disclaimer.       |
// | o Redistributions in binary form must reproduce the above copyright   |
// |   notice, this list of conditions and the following disclaimer in the |
// |   documentation and/or other materials provided with the distribution.|
// | o The names of the authors may not be used to endorse or promote      |
// |   products derived from this software without specific prior written  |
// |   permission.                                                         |
// |                                                                       |
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   |
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     |
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR |
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  |
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, |
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      |
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, |
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY |
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   |
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE |
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  |
// |                                                                       |
// +-----------------------------------------------------------------------+
// | Author: Richard Heyes <richard@php.net>                               |
// +-----------------------------------------------------------------------+
//
// $Id$

/**
 * Implmentation of DIGEST-MD5 SASL mechanism
 *
 * @author  Richard Heyes <richard@php.net>
 * @access  public
 * @version 1.0
 * @package Auth_SASL
 */

namespace Fabiang\Sasl\Authentication;

use Fabiang\Sasl\Exception\InvalidArgumentException;

class DigestMD5 extends AbstractAuthentication implements AuthenticationInterface
{

    /**
     * Provides the (main) client response for DIGEST-MD5
     * requires a few extra parameters than the other
     * mechanisms, which are unavoidable.
     *
     * @param  string $authcid   Authentication id (username)
     * @param  string $pass      Password
     * @param  string $challenge The digest challenge sent by the server
     * @param  string $hostname  The hostname of the machine you're connecting to
     * @param  string $service   The servicename (eg. imap, pop, acap etc)
     * @param  string $authzid   Authorization id (username to proxy as)
     * @return string            The digest response (NOT base64 encoded)
     * @access public
     */
    function getResponse($authcid, $pass, $challenge, $hostname, $service, $authzid = '')
    {
        $parsedChallenge = $this->parseChallenge($challenge);
        $authzidString = '';
        if ($authzid != '') {
            $authzidString = ',authzid="' . $authzid . '"';
        }

        if (!empty($parsedChallenge)) {
            $cnonce         = $this->generateCnonce();
            $digestUri      = sprintf('%s/%s', $service, $hostname);
            $responseValue = $this->getResponseValue(
                $authcid,
                $pass,
                $parsedChallenge['realm'],
                $parsedChallenge['nonce'],
                $cnonce,
                $digestUri,
                $authzid
            );

            if ($parsedChallenge['realm']) {
                $realm = $parsedChallenge['realm'];

                return sprintf(
                    'username="%s",realm="%s"%s,nonce="%s",cnonce="%s",nc=00000001,qop=auth,digest-uri="%s",'
                    . 'response=%s,maxbuf=%d',
                    $authcid,
                    $realm,
                    $authzidString,
                    $parsedChallenge['nonce'],
                    $cnonce,
                    $digestUri,
                    $responseValue,
                    $parsedChallenge['maxbuf']
                );
            } else {
                return sprintf(
                    'username="%s"%s,nonce="%s",cnonce="%s",nc=00000001,qop=auth,digest-uri="%s",response=%s,maxbuf=%d',
                    $authcid,
                    $authzidString,
                    $parsedChallenge['nonce'],
                    $cnonce,
                    $digestUri,
                    $responseValue,
                    $parsedChallenge['maxbuf']
                );
            }
        }

        throw new InvalidArgumentException('Invalid digest challenge');
    }

    /**
     * Parses and verifies the digest challenge*
     *
     * @param  string $challenge The digest challenge
     * @return array             The parsed challenge as an assoc
     *                           array in the form "directive => value".
     * @access private
     */
    private function parseChallenge($challenge)
    {
        $tokens  = array();
        $matches = array();
        while (preg_match('/^(?<key>[a-z-]+)=(?<value>"[^"]+(?<!\\\)"|[^,]+)/i', $challenge, $matches)) {
            $match = $matches[0];
            $key   = $matches['key'];
            $value = $matches['value'];

            // Ignore these as per rfc2831
            if ($key == 'opaque' || $key == 'domain') {
                $challenge = substr($challenge, strlen($match) + 1);
                continue;
            }

            // Allowed multiple "realm" and "auth-param"
            if (!empty($tokens[$key]) && ($key == 'realm' || $key == 'auth-param')) {
                if (is_array($tokens[$key])) {
                    $tokens[$key][] = $this->trim($value);
                } else {
                    $tokens[$key] = array($tokens[$key], $this->trim($value));
                }

            // Any other multiple instance = failure
            } elseif (!empty($tokens[$key])) {
                return array();
            } else {
                $tokens[$key] = $this->trim($value);
            }

            // Remove the just parsed directive from the challenge
            $challenge = substr($challenge, strlen($match) + 1);
        }

        /**
         * Defaults and required directives
         */
        // Realm
        if (empty($tokens['realm'])) {
            $tokens['realm'] = "";
        }

        // Maxbuf
        if (empty($tokens['maxbuf'])) {
            $tokens['maxbuf'] = 65536;
        }

        // Required: nonce, algorithm
        if (empty($tokens['nonce']) || empty($tokens['algorithm'])) {
            return array();
        }

        return $tokens;
    }

    /**
     *
     * @param string $string
     * @return string
     */
    private function trim($string)
    {
        return trim($string, '"');
    }

    /**
     * Creates the response= part of the digest response
     *
     * @param  string $authcid    Authentication id (username)
     * @param  string $pass       Password
     * @param  string $realm      Realm as provided by the server
     * @param  string $nonce      Nonce as provided by the server
     * @param  string $cnonce     Client nonce
     * @param  string $digest_uri The digest-uri= value part of the response
     * @param  string $authzid    Authorization id
     * @return string             The response= part of the digest response
     * @access private
     */
    private function getResponseValue($authcid, $pass, $realm, $nonce, $cnonce, $digest_uri, $authzid = '')
    {
        if ($authzid == '') {
            $A1 = sprintf('%s:%s:%s', pack('H32', md5(sprintf('%s:%s:%s', $authcid, $realm, $pass))), $nonce, $cnonce);
        } else {
            $A1 = sprintf('%s:%s:%s:%s', pack('H32', md5(sprintf('%s:%s:%s', $authcid, $realm, $pass))), $nonce, $cnonce, $authzid);
        }
        $A2 = 'AUTHENTICATE:' . $digest_uri;
        return md5(sprintf('%s:%s:00000001:%s:auth:%s', md5($A1), $nonce, $cnonce, md5($A2)));
    }
}
