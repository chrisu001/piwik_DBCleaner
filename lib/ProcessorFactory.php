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
 * @category Piwik_Plugins
 * @package Piwik_DBCleaner
 */
namespace Piwik\Plugins\DBCleaner;

require_once dirname(__FILE__) . '/ProcessorCleanup.php';
require_once dirname(__FILE__) . '/ProcessorOptimize.php';
require_once dirname(__FILE__) . '/ProcessorWebsite.php';

/**
 * create the available process
 * - dump a webiste
 * - dump old log_* entries
 * - optimize tables
 *
 * @package Piwik_DBCleaner
 * @author Christian Suenkel <info@suenkel.de>
 */
class ProcessorFactory
{

    /**
     * Dump all data of a webiste (and delete it)
     *
     * @param integer $idSite            
     * @param string $nonce
     *            - uniqid of the process
     * @param string $filename
     *            - realpath to the dump
     * @return ProcessorWebsite
     */
    public static function newWebsite($idSite, $nonce, $filename)
    {
        $proc = new ProcessorWebsite();
        return $proc->resetCache()
            ->setNonce($nonce)
            ->setFilename($filename)
            ->setConfig('siteid', $idSite);
    }

    /**
     * Dump and delete process to archive log_* tables
     *
     * @param integer $until
     *            - timestamp - all log_* data will be dumped and deleted until this timeframe
     * @param string $nonce
     *            - unique ID of the process
     * @param sring $filename
     *            - filename of the dumpfile
     * @return ProcessorCleanup
     */
    public static function newCleanup($until, $nonce, $filename)
    {
        $proc = new ProcessorCleanup();
        return $proc->resetCache()
            ->setNonce($nonce)
            ->setFilename($filename)
            ->setConfig('until', $until);
    }

    /**
     * Optimize Tables process
     * optimize log-* and archive_* tables
     *
     * @param string $nonce
     *            - unique ID of the process
     * @return ProcessorOptimize
     */
    public static function newOptimize($nonce)
    {
        $proc = new ProcessorOptimize();
        return $proc->resetCache()
            ->setNonce($nonce);
    }

    /**
     * Auto load an existant Process via cache
     *
     * @throws RuntimeException - if unknown processtype
     * @return ProcessorAbstract
     */
    public static function runable()
    {
        $cache = new \Piwik\CacheFile('DBCleaner', 3600);
        $procConfig = $cache->get('processor');
        $procname = isset($procConfig['processname']) ? $procConfig['processname'] : 'invalid';
        switch ($procname) {
            case 'website' :
                return new ProcessorWebsite();
            case 'cleanup' :
                return new ProcessorCleanup();
            case 'optimize' :
                return new ProcessorOptimize();
            default :
                throw new \RuntimeException(
                        sprintf('invalid process name: %s<pre>%s</pre>', $procname, 
                                print_r($procConfig, true)));
        }
    }

    /**
     * delete all cached data of processes
     *
     * @return boolean
     */
    public static function reset()
    {
        $cache = new \Piwik\CacheFile('DBCleaner', 3600);
        $cache->deleteAll();
        return true;
    }
}

/**
 * Dummy Class to be used for cache resetting
 *
 * @author chris
 *        
 */
class ProcessorReset extends ProcessorAbstract
{}
