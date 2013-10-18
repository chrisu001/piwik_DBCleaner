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

require_once dirname(__FILE__) . '/FileDumper.php';
require_once dirname(__FILE__) . '/ProcessorAbstract.php';

/**
 * Process the cleanup of log_* and associated tables
 * - mode website dump and delete all infos about a single siteId (log_*, archive_* and config-tables)
 * - mode until dump and delete all log_* entries until this time
 *
 * @package Piwik_DBCleaner
 */
class ProcessorCleanup extends ProcessorAbstract
{
    /**
     * Processname
     *
     * @var string
     */
    protected $myprocessname = 'cleanup';
    
    /**
     * relapath of the filename to store the dump
     *
     * @var string
     */
    protected $filename = null;
    
    /**
     * If low memory, then swith to a "dump only 1 visit per step"
     *
     * @var int
     */
    protected $lowMemory = 0;
    /**
     *
     * @var FileDumper
     */
    protected $filedumper;
    
    /**
     * Databasedriver
     * 
     * @var DbAbstract
     */
    protected $db;

    /**
     * Setup the DatabaseHandler and the FileHandler
     *
     * @see \Piwik\Plugins\DBCleaner\ProcessorAbstract::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        if ($this->filename == null) {
            throw new \RuntimeException('configuration error no Filename was set');
        }
        $this->filedumper = new FileDumper(dirname($this->filename), basename($this->filename));
        $this->db = DbFactory::Instance(
                array('limit' => 10, 
                        'idsite' => $this->getConfig('siteid', 0), 
                        'until' => $this->getConfig('until', 0)));
    }

    protected function tearDown()
    {
        $this->filedumper->close();
        return parent::tearDown();
    }

    /**
     * Write the preamble to the File and
     * count the steps to be processed
     * (count the visits to be dumped)
     *
     * @return ProcessorCleanup
     */
    protected function preprocess()
    {
        // write preamble and count visits(Ids) to be dumped
        $this->db->preamble($this->filedumper);
        $this->setCached('stepsToProcess', $this->db->count());
        return parent::preprocess();
    }

    /**
     * Loop
     *
     * loop through the visits in chunks and dump them to the filesystem
     *
     * @throws \RuntimeException - if lack of resources
     * @return ProcessorCleanup
     */
    protected function loop()
    {
        /*
         * dump data in chunks, until maxexectime will be reached or no more memory is available
         */
        $rowsAffected = 1;
        while ($rowsAffected > 0) {
            $currentLimit = $this->calcLimit();
            $lowMemory = $this->getCached('lowMemory');
            try {
                $rowsAffected = $this->db->setConfig('limit', $currentLimit)
                    ->execute($this->filedumper);
            } catch (InfoMemory_Exception $e) {
                // there was a low memory situation, and DbAbstract was not able to store the chunk of visits
                $this->filedumper->close();
                if ($lowMemory > 0) {
                    // there was already a low memory warning, and limit was reduced to the minimum, so there is no way to execute the dump!
                    throw new \RuntimeException(
                            'Low Memory: unable to dump the tables, increase memory_limit in php.ini', 
                            null, $e);
                }
                // retry next 30 steps with limit = 1 aka dump only single visits each processstep
                return $this->setCached('lowMemory', min($currentLimit, 30));
            } catch (InfoResources_Exception $e) {
                // not able to dump the whole chunk, retry again
                return $this->snapshotClose();
            }
            $this->stepsProcessed += $rowsAffected;
            if ($lowMemory > 0) {
                $this->setCached('lowMemory', $lowMemory - $rowsAffected);
            }
            $this->saveCache();
        }
        
        // add appendix
        if ($rowsAffected == 0 && $this->stepsProcessed < $this->stepsToProcess) {
            $this->stepsProcessed = $this->stepsToProcess;
        }
        return $this->snapshotClose();
    }

    /**
     * dump the appendix to the mysql-dump-file
     * 
     * @see \Piwik\Plugins\DBCleaner\ProcessorAbstract::postprocess()
     */
    protected function postprocess()
    {
        $this->db->appendix($this->filedumper);
        return parent::postprocess();
    }

    /**
     * Calculate the estimated chunksize (limit) of visits to be processed
     * to fit max_memory_limit and max_executiontime
     *
     * @return number
     */
    protected function calcLimit()
    {
        // Limit Chunks to be fetched from database by Time
        $remainTime = InfoTime::getInstance()->getAvailable();
        
        $StepsPerSec = ($this->stepsProcessed <= 0) ? 20 : (time() - $this->startTS) /
                 $this->stepsProcessed;
        $StepsPerSec = max($StepsPerSec, 10);
        
        $limit = ($remainTime <= 0) ? 1 : $remainTime / $StepsPerSec;
        
        $this->setCached('currentLimitByTime', $limit);
        
        // Limit Chunks to be fetched from database by Memory assumed 10 Kb per visit
        $memPerStep = 1024 * 10;
        $mem = InfoMemory::getInstance()->getAvailable();
        $limit = min($limit, $mem / $memPerStep);
        $this->setCached('currentLimitByMem', $mem / $memPerStep);
        
        // first run, reduce limit to have a first good quality for the estimations
        if ($this->stepsProcessed < 20) {
            $limit = min(20, $limit);
        }
        // fence 5 <= limit <= 800;
        $limit = max(5, min(800, intVal($limit)));
        
        // if lowMemory, limit = 1
        $lowMemory = $this->getCached('lowMemory');
        if ($lowMemory > 0) {
            $limit = 1;
        }
        $this->setCached('currentLimit', $limit);
        return $limit;
    }

    /**
     * close the current session
     * close FileDumper and save the cached variables
     *
     * @return ProcessorCleanup
     */
    protected function snapshotClose()
    {
        $this->filedumper->close();
        return $this->saveCache();
    }

    /**
     * Set Filename to dump to
     *
     * @param string $filename            
     * @return Processor
     */
    public function setFilename($filename)
    {
        return $this->setCached('filename', $filename . '.sql')
            ->saveCache();
    }
}