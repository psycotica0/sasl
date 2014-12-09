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
 * @author Richard Heyes <richard@php.net>
 */

namespace Fabiang\Sasl\Authentication;

/**
 * Common functionality to SASL mechanisms
 *
 * @author Richard Heyes <richard@php.net>
 */
abstract class AbstractAuthentication
{

    /**
     * Function which implements HMAC MD5 digest
     *
     * @param  string $key  The secret key
     * @param  string $data The data to hash
     * @param  bool $raw_output Whether the digest is returned in binary or hexadecimal format.
     *
     * @return string       The HMAC-MD5 digest
     */
    protected function hmacMd5($key, $data, $raw_output = false)
    {
        if (strlen($key) > 64) {
            $key = pack('H32', md5($key));
        }

        if (strlen($key) < 64) {
            $key = str_pad($key, 64, chr(0));
        }

        $k_ipad = substr($key, 0, 64) ^ str_repeat(chr(0x36), 64);
        $k_opad = substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64);

        $inner  = pack('H32', md5($k_ipad . $data));
        $digest = md5($k_opad . $inner, $raw_output);

        return $digest;
    }

    /**
     * Creates the client nonce for the response
     *
     * @return string The cnonce value
     */
    protected function generateCnonce()
    {
        foreach (array('/dev/urandom', '/dev/random') as $file) {
            if (is_readable($file)) {
                return base64_encode(file_get_contents($file, false, null, -1, 32));
            }
        }

        $str = '';
        for ($i = 0; $i < 32; $i++) {
            $str .= chr(mt_rand(0, 255));
        }

        return base64_encode($str);
    }
}
