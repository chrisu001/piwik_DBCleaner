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

require_once __DIR__ . '/DbFactory.php';
require_once __DIR__ . '/InfoMemory.php';
require_once __DIR__ . '/InfoTime.php';

/**
 * BaseClass to process a longrunning process
 * setup()->preprocess()->loop()->postprocess()->teardown()
 *
 * invoking run() will go through the steps (pre/post will run only once)
 *
 * @package Piwik_DBCleaner
 */
class ProcessorAbstract
{
    /**
     * processname
     *
     * @var string
     */
    protected $myprocessname = 'unset';
    
    /**
     * \Piwik\CacheFile
     *
     * @var \Piwik\CacheFile
     */
    protected $cache;
    /**
     * Cached Properties
     *
     * @var array
     */
    protected $cached = array();
    
    /**
     * Various (cached) status information
     *
     * @var mixed
     */
    protected $preprocessed = false;
    protected $postprocessed = false;
    protected $stepsToProcess = 0;
    protected $stepsProcessed = 0;
    protected $startTS = 0;
    protected $nonce = null;
    
    /**
     * process name eg.
     * website or history
     *
     * @var string
     */
    protected $processname = null;
    /**
     * configuration for the process
     *
     * @var mixed
     */
    protected $runtimeConfig = null;
    
    /**
     * Local profile infos to estimate the runtime
     *
     * @var int
     */
    protected $maxExecTime = 0;
    protected $estimatedExectime = 0;
    protected $loadTS = 0;

    /**
     * Constructor
     * load the cached data
     */
    public function __construct()
    {
        $this->cache = new \Piwik\CacheFile('DBCleaner', 3600);
        $this->reloadCache();
    }

    /**
     * Run the current cache-configured process
     *
     * @param string $nonce
     *            - uniq ID of the process (initiated by the factory) to avoid concurrent processes
     * @throws \RuntimeException - if another process is active
     * @return Processor
     */
    public function run($nonce)
    {
        if ($this->processname !== $this->myprocessname) {
            throw new \RuntimeException(
                    'cached process name is not equal to the currently invoced process: ' .
                             $this->processname);
        }
        if ($this->nonce != $nonce) {
            throw new \RuntimeException('chached process uniqId is not valid: ' . $this->nonce);
        }
        $this->setUp();
        
        if ($this->postprocessed) {
            // ready
            return $this;
        }
        if (!$this->preprocessed) {
            $this->preprocess();
        }
        if (!$this->isLoopFinished()) {
            $this->loop();
            $this->saveCache();
        }
        
        if (!$this->postprocessed && $this->isLoopFinished()) {
            $this->postprocess();
        }
        $this->tearDown();
        return $this;
    }

    /**
     * setup environment, so the precessor can take of
     *
     * @return \Piwik\Plugins\DBCleaner\ProcessorAbstract
     */
    protected function setUp()
    {
        InfoMemory::getInstance()->setMax();
        InfoTime::getInstance()->setMax();
        return $this;
    }

    /**
     * cleanup
     *
     * @return \Piwik\Plugins\DBCleaner\ProcessorAbstract
     */
    protected function tearDown()
    {
        return $this->saveCache();
    }

    /**
     * before running the loop of the process, this function is called once in the lifecycle
     *
     * @return \Piwik\Plugins\DBCleaner\ProcessorAbstract
     */
    protected function preprocess()
    {
        return $this->setCached('startTS', time())
            ->setCached('preprocessed', true)
            ->saveCache();
    }

    /**
     * loop function will be called until
     * stepsToProcess > stepsProcessed
     *
     * @return \Piwik\Plugins\DBCleaner\ProcessorAbstract
     */
    protected function loop()
    {
        return $this;
    }

    /**
     * after finishing the loop of the process, this function is called once in the lifecycle
     *
     * @return \Piwik\Plugins\DBCleaner\ProcessorAbstract
     */
    protected function postprocess()
    {
        return $this->setCached('postprocessed', true)
            ->setCached('endTS', time());
    }
    /*
     * Status information methods
     */
    /**
     * Get the current progress in %
     *
     * @return number
     */
    public function progressBar()
    {
        return $this->stepsToProcess == 0 ? 100 : intVal(
                100 * $this->stepsProcessed / $this->stepsToProcess);
    }

    /**
     * check, if the current process was finished
     *
     * @return boolean - true, if the current dump was finished
     */
    public function isFinished()
    {
        return ($this->postprocessed);
    }

    protected function isLoopFinished()
    {
        return ($this->getCount() <= $this->getDone());
    }

    /**
     * Get the # of steps which will be processed
     *
     * @return number
     */
    public function getCount()
    {
        return $this->stepsToProcess;
    }

    /**
     * get the # of steps they were performed
     *
     * @return number
     */
    public function getDone()
    {
        return $this->stepsProcessed;
    }
    
    /*
     * Cache methods
     */
    public function setNonce($nonce)
    {
        return $this->setCached('nonce', $nonce)
            ->saveCache();
    }

    /**
     * Set RuntimeConfiguration
     * and save to the chache
     *
     * @param string $key            
     * @param mixed $value            
     * @return ProcessorAbstract
     */
    public function setConfig($key, $value)
    {
        $this->runtimeConfig[$key] = $value;
        return $this->setCached('runtimeConfig', $this->runtimeConfig)
            ->saveCache();
    }

    /**
     * return current runtimeconfiguration settings
     *
     * @param mixed $key            
     * @param mixed $default            
     * @return mixed
     */
    public function getConfig($key, $default = null)
    {
        return isset($this->runtimeConfig[$key]) ? $this->runtimeConfig[$key] : $default;
        ;
    }

    /**
     * Save the currently marked cached class-attributes
     *
     * @return ProcessorAbstract
     */
    protected function saveCache()
    {
        foreach (array_keys($this->cached) as $key) {
            $this->cached[$key] = $this->$key;
        }
        $this->cache->set('processor', $this->cached);
        return $this;
    }

    /**
     * set a cached attribute
     * caution: does not save the cache
     *
     * @param string $varname            
     * @param mixed $value            
     * @return ProcessorAbstract
     */
    protected function setCached($varname, $value)
    {
        $this->$varname = $value;
        $this->cached[$varname] = $value;
        return $this;
    }

    protected function getCached($varname)
    {
        if (!isset($this->cached[$varname])) {
            return null;
        }
        return $this->cached[$varname];
    }

    public function resetCache()
    {
        $this->cached = null;
        $this->cache->deleteAll();
        return $this->reloadCache();
    }

    protected function reloadCache()
    {
        $this->cached = $this->cache->get('processor');
        if (empty($this->cached)) {
            // setup default
            $this->cached = array('postprecessed' => false, 
                    'preprocessed' => false, 
                    'stepsToProcess' => 0, 
                    'stepsProcessed' => 0, 
                    'startTS' => 0, 
                    'processname' => $this->myprocessname, 
                    'runtimeConfig' => array());
        }
        foreach ($this->cached as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    public function getExtendedStatus()
    {
        $mem = InfoMemory::getInstance();
        $ts = InfoTime::getInstance();
        return array_merge(
                array('memoryLimit' => $mem->getLimit(), 
                        'memUsage' => $mem->getUsage(), 
                        'memAvail' => $mem->getAvailable(), 
                        'timeLimit' => $ts->getLimit(), 
                        'timeLimitD' => $ts->getExecTimeLimit(), 
                        'timeUsage' => $ts->getUsage(), 
                        'timeAvail' => $ts->getAvailable(), 
                        'duration' => sprintf('%02d:%02d', (time() - $this->startTS) / 60, 
                                (time() - $this->startTS) % 60)), $this->cached);
    }
}