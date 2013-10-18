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

require_once dirname(__FILE__) . '/InfoMemory.php';
require_once dirname(__FILE__) . '/InfoTime.php';

/**
 * Baseclass for the transformation of the database to a "dumpfile"
 *
 * @package Piwik_DBCleaner*
 *         
 */
abstract class DbAbstract
{
    
    /**
     * holds the configuration values to be used to access to the database
     *
     * @var array
     */
    protected $config = array();

    /**
     * Constructor, set up the configuration
     *
     * @param array $config
     *            configuration array
     *            (
     *            'limit' => 500, - the size of chunks that should be selected from the database
     *            'skipOtimizeTables' => false - run "OPTIMIZE TABLE" at the end
     *            )
     */
    public function __construct($config = array())
    {
        foreach ($config as $key => $value) {
            $this->setConfig($key, $value);
        }
    }

    /**
     * write the preamble of the dumpfile
     *
     * @param FileDumper $fp            
     * @return DbAbstract
     */
    public function preamble(FileDumper $fp)
    {
        throw\RuntimeException(__METHOD__ . ' not implemented yet');
        return $this;
    }

    /**
     * write the appendix to the dumpfile
     *
     * @param FileDumper $fp            
     * @return DbAbstract
     */
    public function appendix(FileDumper $fp)
    {
        throw\RuntimeException(__METHOD__ . ' not implemented yet');
        return $this;
    }

    /**
     * dump a rowset of a table to the dumpfile
     *
     * @param FileDumper $fp            
     * @param string $tableName            
     * @param array $rowset            
     * @return DbAbstract
     */
    public function dump(FileDumper $fp, $tableName, &$rowset)
    {
        throw\RuntimeException(__METHOD__ . ' not implemented yet');
        return $this;
    }

    /**
     * get the number of visits, which will be dumped to the file
     *
     * @return number
     */
    public function count()
    {
        throw\RuntimeException(__METHOD__ . ' not implemented yet');
        return 0;
    }

    /**
     * execute the dump-process
     *
     * @param FileDumper $fp            
     * @return number
     */
    public function execute(FileDumper $fp)
    {
        throw\RuntimeException(__METHOD__ . ' not implemented yet');
        return 0;
    }

    /**
     * get a configuration value
     *
     * @param string $key            
     * @param mixed $default            
     * @return mixed
     */
    final public function getConfig($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * set a configuration value
     *
     * @param string $key            
     * @param mixed $value            
     * @return DbAbstract
     */
    final public function setConfig($key, $value)
    {
        $this->config[$key] = $value;
        return $this;
    }

    /**
     * check Resources
     *
     * @param float $buffer
     *            - percent of resources which should be reserved (default 0.05 == 5%)
     * @throws InfoResources_Exception - if extimated resource not sufficent
     * @return DbAbstract
     */
    public function throwExceptionIfLackOfResources($buffer = 0.05)
    {
        try {
            InfoTime::getInstance()->throwExceptionIfExceeds($buffer);
            InfoMemory::getInstance()->throwExceptionIfExceeds($buffer);
        } catch (\Exception $e) {
            throw new InfoResources_Exception('lack of resources', 0, $e);
        }
        return $this;
    }
}

/**
 * to be thrown, if memory or execution time exceeds
 *
 * @author chris
 *        
 */
class InfoResources_Exception extends \RuntimeException
{}