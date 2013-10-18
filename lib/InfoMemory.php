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
 * try to get information about the available memory and memory usage
 *
 * @package Piwik_DBCleaner
 */
class InfoMemory
{
    const MAX_MEM = 134217728;
    /**
     * memory available
     *
     * @var integer
     */
    protected $maxMemAvail = 134217728; // assume 128M default
    
    /**
     * Singleton
     *
     * @var InfoMemory
     */
    protected static $instance = null;

    /**
     * get/create Instance
     *
     * @return InfoMemory
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
     * try to find out the memory limit
     *
     * @return InfoMemory
     */
    public function updateEnvironment()
    {
        $maxMem = @ini_get('memory_limit');
        if (!$maxMem || $maxMem <= 0) {
            $maxMem = self::MAX_MEM;
        }
        $unit = substr($maxMem, -1, 1);
        switch ($unit) {
            case 'M' :
            case 'm' :
                $maxMem = intval($maxMem) * 1024 * 1024;
                break;
            case 'k' :
            case 'K' :
                $maxMem = intval($maxMem) * 1024;
                break;
            case 'G' :
            case 'g' :
                $maxMem = intval($maxMem) * 1024 * 1024 * 1024;
                break;
            default :
                $maxMem = intval($maxMem);
        }
        $maxMem = max(self::MAX_MEM / 4, $maxMem);
        $this->maxMemAvail = $maxMem;
        return $this;
    }

    /**
     * try to "estimate" the remaining memory
     *
     * @return number
     */
    public function getAvailable()
    {
        return $this->maxMemAvail - $this->getUsage();
    }

    /**
     * get memory Limit
     *
     * @return number
     */
    public function getLimit()
    {
        return $this->maxMemAvail;
    }

    /**
     * Get current memusage
     *
     * @return number
     */
    public function getUsage()
    {
        if (!function_exists('memory_get_usage')) {
            return 1;
        }
        return memory_get_usage();
    }

    /**
     * try to increase Memory to the max
     *
     * @return bool - was memory setting sucessfull
     */
    public function setMax()
    {
        $oldValue = @ini_set('memory_limit', -1);
        $this->updateEnvironment();
        return is_string($oldValue);
    }

    /**
     * Throws an exception, if memory_limit might exceed
     *
     * @throws InfoMemory_Exception
     */
    public function throwExceptionIfExceeds($buffer = 0.05)
    {
        if ($this->getAvailable() < $this->getLimit() * $buffer) {
            throw new InfoMemory_Exception('low Memory');
        }
    }
}

/**
 * Exception to be thrown, if memmory limit might exceed
 */
class InfoMemory_Exception extends \RuntimeException
{}