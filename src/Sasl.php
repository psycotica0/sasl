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

namespace Fabiang\Sasl;

use Fabiang\Sasl\Exception\InvalidArgumentException;

/**
 * Client implementation of various SASL mechanisms
 *
 * @author Richard Heyes <richard@php.net>
 */
class Sasl
{

    /**
     * Known authentication mechanisms classes.
     *
     * @var array
     */
    protected $mechanisms = array(
        'anonymous' => 'Fabiang\\Sasl\Authentication\\Anonymous',
        'login'     => 'Fabiang\\Sasl\Authentication\\Login',
        'plain'     => 'Fabiang\\Sasl\Authentication\\Plain',
        'external'  => 'Fabiang\\Sasl\Authentication\\External',
        'crammd5'   => 'Fabiang\\Sasl\Authentication\\CramMD5',
        'digestmd5' => 'Fabiang\\Sasl\Authentication\\DigestMD5',
    );

    /**
     * Factory class. Returns an object of the request
     * type.
     *
     * @param string $type One of: Anonymous
     *                             Plain
     *                             CramMD5
     *                             DigestMD5
     *                             SCRAM-* (any mechanism of the SCRAM family)
     *                     Types are not case sensitive
     */
    public function factory($type)
    {
        $className = null;
        $parameter = null;
        $matches   = array();

        $formatedType = strtolower(str_replace('-', '', $type));

        if (isset($this->mechanisms[$formatedType])) {
            $className = $this->mechanisms[$formatedType];
        } elseif (preg_match('/^scram(?<algo>.{1,9})$/i', $formatedType, $matches)) {
            $className = 'Fabiang\\Sasl\Authentication\\SCRAM';
            $parameter = $matches['algo'];
        }

        if (null === $className) {
            throw new InvalidArgumentException("Invalid SASL mechanism type '$type'");
        }

        $object = new $className($parameter);
        return $object;
    }
}
