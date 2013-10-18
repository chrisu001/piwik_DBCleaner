<?php

/**
  *
 * DBCleaner
 *
 * Copyright (c) 2012-2013, Christian Suenkel <info@suenkel.org>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * * Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in
 *   the documentation and/or other materials provided with the
 *   distribution.
 *
 * * Neither the name of Christian Suenkel nor the names of his
 *   contributors may be used to endorse or promote products derived
 *   from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Christian Suenkel <christian@suenkel.de>
 * @link http://plugin.suenkel.org
 * @copyright 2012-2013 Christian Suenkel <info@suenkel.de>
 * @license http://www.opensource.org/licenses/BSD-3-Clause The BSD 3-Clause License
 * @package Piwik_DBCleaner
 */
namespace Piwik\Plugins\DBCleaner;

/**
 * try to get information about the execution time limit
 * 
 * @package Piwik_DBCleaner
 */
class InfoTime
{
    const MAX_TIME = 60;
    /**
     * exec time limit
     * assume 60 sec as default
     * 
     * @var integer
     */
    protected $execTimeLimit = 60;
    
    /**
     * Singleton
     *
     * @var InfoTime
     */
    protected static $instance = null;
    
    /**
     * first time loading
     *
     * @var timestamp
     */
    protected static $firstTS = null;

    /**
     * get/create Instance
     *
     * @return InfoTime
     */
    public static function getInstance()
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = new self();
        return self::$instance->updateEnvironment();
    }

    /**
     * Construct (set firstTime Timestamp
     * as a "estimated" start point
     */
    public function __construct()
    {
        if (self::$firstTS == null) {
            self::$firstTS = time();
        }
    }

    /**
     * try to find out the exectime limit
     *
     * @return InfoTime
     */
    public function updateEnvironment()
    {
        $max = intVal(@ini_get("max_execution_time"));
        if (!$max || $max == -1) {
            $max = self::MAX_TIME;
        }
        $max = max(self::MAX_TIME / 4, $max);
        $this->execTimeLimit = $max;
        return $this;
    }

    /**
     * try to "estimate" the remaining execution time
     *
     * @return number
     */
    public function getAvailable()
    {
        return $this->getLimit() - time();
    }

    /**
     * get exec time limit
     *
     * @return integer
     */
    public function getLimit()
    {
        // TODO: might use getrusage() instead
        return (self::$firstTS + $this->execTimeLimit);
    }

    /**
     * Return the currently configured exec_time limit
     * 
     * @return integer
     */
    public function getExecTimeLimit()
    {
        return $this->execTimeLimit;
    }

    /**
     * Get current profiling timeusage
     *
     * @return integer
     */
    public function getUsage()
    {
        return time() - self::$firstTS;
    }

    /**
     * try to increase execution time to "endless"
     *
     * @return bool - was setting sucessfull
     */
    public function setMax()
    {
        $oldValue = @ini_set('max_execution_time', -1);
        @set_time_limit(0);
        $this->updateEnvironment();
        return is_string($oldValue);
    }

    /**
     * Throws a exception, if exectime might exceed
     *
     * @throws InfoTime_Exception
     */
    public function throwExceptionIfExceeds($buffer = 0.05)
    {
        if (time() > $this->getLimit() - ($buffer * $this->execTimeLimit)) {
            throw new InfoTime_Exception('timelimit exceeds');
        }
    }
}

/**
 * Exception to be thrown, if execTime limit might exceed
 */
class InfoTime_Exception extends \RuntimeException
{}