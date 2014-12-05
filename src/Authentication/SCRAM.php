<?php

// +-----------------------------------------------------------------------+
// | Copyright (c) 2011 Jehan                                              |
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
// | Author: Jehan <jehan.marmottard@gmail.com                             |
// +-----------------------------------------------------------------------+
//
// $Id$

/**
 * Implementation of SCRAM-* SASL mechanisms.
 * SCRAM mechanisms have 3 main steps (initial response, response to the server challenge, then server signature
 * verification) which keep state-awareness. Therefore a single class instanciation must be done and reused for the whole
 * authentication process.
 *
 * @author  Jehan <jehan.marmottard@gmail.com>
 * @access  public
 * @version 1.0
 * @package Auth_SASL2
 */

namespace Fabiang\Sasl\Authentication;

use Fabiang\Sasl\Authentication\AbstractAuthentication;
use Fabiang\Sasl\Exception\InvalidArgumentException;

class SCRAM extends AbstractAuthentication implements AuthenticationInterface
{

    private $hash;
    private $hmac;
    private $gs2_header;
    private $cnonce;
    private $first_message_bare;
    private $saltedPassword;
    private $authMessage;

    /**
     * Construct a SCRAM-H client where 'H' is a cryptographic hash function.
     *
     * @param string $hash The name cryptographic hash function 'H' as registered by IANA in the "Hash Function Textual
     * Names" registry.
     * @link http://www.iana.org/assignments/hash-function-text-names/hash-function-text-names.xml "Hash Function Textual
     * Names"
     * format of core PHP hash function.
     * @throws InvalidArgumentException
     */
    public function __construct($hash)
    {
        // Though I could be strict, I will actually also accept the naming used in the PHP core hash framework.
        // For instance "sha1" is accepted, while the registered hash name should be "SHA-1".
        $hash   = strtolower($hash);
        $hashes = array(
            'md2'     => 'md2',
            'md5'     => 'md5',
            'sha-1'   => 'sha1',
            'sha1'    => 'sha1',
            'sha-224' => 'sha224',
            'sha224'  => 'sha224',
            'sha-256' => 'sha256',
            'sha256'  => 'sha256',
            'sha-384' => 'sha384',
            'sha384'  => 'sha384',
            'sha-512' => 'sha512',
            'sha512'  => 'sha512'
        );

        if (function_exists('hash_hmac') && isset($hashes[$hash])) {
            $hashAlgo = $hashes[$hash];
            $this->hash = create_function('$data', 'return hash("' . $hashAlgo . '", $data, true);');
            $this->hmac = create_function('$key,$str,$raw', 'return hash_hmac("' . $hashAlgo . '", $str, $key, $raw);');
        } elseif ($hash == 'md5') {
            $this->hash = create_function('$data', 'return md5($data, true);');
            $this->hmac = array($this, 'hmacMd5');
        } elseif (in_array($hash, array('sha1', 'sha-1'))) {
            $this->hash = create_function('$data', 'return sha1($data, true);');
            $this->hmac = array($this, 'hmacSha1');
        } else {
            throw new InvalidArgumentException('Invalid SASL mechanism type');
        }
    }

    /**
     * Provides the (main) client response for SCRAM-H.
     *
     * @param  string $authcid   Authentication id (username)
     * @param  string $pass      Password
     * @param  string $challenge The challenge sent by the server.
     * If the challenge is null or an empty string, the result will be the "initial response".
     * @param  string $authzid   Authorization id (username to proxy as)
     * @return string|false      The response (binary, NOT base64 encoded)
     */
    public function getResponse($authcid, $pass, $challenge = null, $authzid = null)
    {
        $authcid = $this->formatName($authcid);
        if (empty($authcid)) {
            return false;
        }
        if (!empty($authzid)) {
            $authzid = $this->formatName($authzid);
        }

        if (empty($challenge)) {
            return $this->generateInitialResponse($authcid, $authzid);
        } else {
            return $this->generateResponse($challenge, $pass);
        }
    }

    /**
     * Prepare a name for inclusion in a SCRAM response.
     *
     * @param string $username a name to be prepared.
     * @return string the reformated name.
     */
    private function formatName($username)
    {
        // TODO: prepare through the SASLprep profile of the stringprep algorithm.
        // See RFC-4013.

        $username = str_replace('=', '=3D', $username);
        $username = str_replace(',', '=2C', $username);
        return $username;
    }

    /**
     * Generate the initial response which can be either sent directly in the first message or as a response to an empty
     * server challenge.
     *
     * @param string $authcid Prepared authentication identity.
     * @param string $authzid Prepared authorization identity.
     * @return string The SCRAM response to send.
     */
    private function generateInitialResponse($authcid, $authzid)
    {
        $gs2_cbind_flag   = 'n,'; // TODO: support channel binding.
        $this->gs2_header = $gs2_cbind_flag . (!empty($authzid) ? 'a=' . $authzid : '') . ',';

        // I must generate a client nonce and "save" it for later comparison on second response.
        $this->cnonce  = $this->generateCnonce();

        // XXX: in the future, when mandatory and/or optional extensions are defined in any updated RFC,
        // this message can be updated.
        $this->first_message_bare = 'n=' . $authcid . ',r=' . $this->cnonce;
        return $this->gs2_header . $this->first_message_bare;
    }

    /**
     * Parses and verifies a non-empty SCRAM challenge.
     *
     * @param  string $challenge The SCRAM challenge
     * @return string|false      The response to send; false in case of wrong challenge or if an initial response has not
     * been generated first.
     */
    private function generateResponse($challenge, $password)
    {
        $matches = array();
        // XXX: as I don't support mandatory extension, I would fail on them.
        // And I simply ignore any optional extension.
        $server_message_regexp = "#^r=([\x21-\x2B\x2D-\x7E]+),s=((?:[A-Za-z0-9/+]{4})*(?:[A-Za-z0-9]{3}=|[A-Xa-z0-9]{2}==)?),i=([0-9]*)(,[A-Za-z]=[^,])*$#";
        if (!isset($this->cnonce, $this->gs2_header) || !preg_match($server_message_regexp, $challenge, $matches)) {
            return false;
        }
        $nonce = $matches[1];
        $salt  = base64_decode($matches[2]);
        if (!$salt) {
            // Invalid Base64.
            return false;
        }
        $i = intval($matches[3]);

        $cnonce = substr($nonce, 0, strlen($this->cnonce));
        if ($cnonce !== $this->cnonce) {
            // Invalid challenge! Are we under attack?
            return false;
        }

        $channel_binding = 'c=' . base64_encode($this->gs2_header); // TODO: support channel binding.
        $final_message   = $channel_binding . ',r=' . $nonce; // XXX: no extension.
        // TODO: $password = $this->normalize($password); // SASLprep profile of stringprep.
        $saltedPassword       = $this->hi($password, $salt, $i);
        $this->saltedPassword = $saltedPassword;
        $clientKey            = call_user_func($this->hmac, $saltedPassword, "Client Key", true);
        $storedKey            = call_user_func($this->hash, $clientKey, true);
        $authMessage          = $this->first_message_bare . ',' . $challenge . ',' . $final_message;
        $this->authMessage    = $authMessage;
        $clientSignature      = call_user_func($this->hmac, $storedKey, $authMessage, true);
        $clientProof          = $clientKey ^ $clientSignature;
        $proof                = ',p=' . base64_encode($clientProof);

        return $final_message . $proof;
    }

    /**
     * Hi() call, which is essentially PBKDF2 (RFC-2898) with HMAC-H() as the pseudorandom function.
     *
     * @param string $str  The string to hash.
     * @param string $salt The salt value.
     * @param int $i The   iteration count.
     */
    private function hi($str, $salt, $i)
    {
        $int1   = "\0\0\0\1";
        $ui     = call_user_func($this->hmac, $str, $salt . $int1, true);
        $result = $ui;
        for ($k = 1; $k < $i; $k++) {
            $ui     = call_user_func($this->hmac, $str, $ui, true);
            $result = $result ^ $ui;
        }
        return $result;
    }

    /**
     * SCRAM has also a server verification step. On a successful outcome, it will send additional data which must
     * absolutely be checked against this function. If this fails, the entity which we are communicating with is probably
     * not the server as it has not access to your ServerKey.
     *
     * @param string $data The additional data sent along a successful outcome.
     * @return bool Whether the server has been authenticated.
     * If false, the client must close the connection and consider to be under a MITM attack.
     */
    public function processOutcome($data)
    {
        $verifier_regexp = '#^v=((?:[A-Za-z0-9/+]{4})*(?:[A-Za-z0-9]{3}=|[A-Xa-z0-9]{2}==)?)$#';
        if (!isset($this->saltedPassword, $this->authMessage) || !preg_match($verifier_regexp, $data, $matches)) {
            // This cannot be an outcome, you never sent the challenge's response.
            return false;
        }

        $verifier                 = $matches[1];
        $proposed_serverSignature = base64_decode($verifier);
        $serverKey                = call_user_func($this->hmac, $this->saltedPassword, "Server Key", true);
        $serverSignature          = call_user_func($this->hmac, $serverKey, $this->authMessage, true);
        return ($proposed_serverSignature === $serverSignature);
    }

    /**
     * @return string
     */
    public function getCnonce()
    {
        return $this->cnonce;
    }

    public function getSaltedPassword()
    {
        return $this->saltedPassword;
    }

    public function getAuthMessage()
    {
        return $this->authMessage;
    }
}
