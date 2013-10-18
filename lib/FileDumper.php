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
 * Wrapper of fopen/gzopn file streams
 * put strings to a file
 * if gz-lib enabled, use gz
 *
 * @package Piwik_DBCleaner
 */
class FileDumper
{
    
    /**
     * Filename (basename) of the file
     *
     * @var string
     */
    protected $filename = null;
    /**
     * workspace dir (FQ-path)
     *
     * @var string
     */
    protected $workspace = null;
    /**
     * Filepointer
     *
     * @var resource
     */
    protected $fp = null;

    /**
     * Constructor set workspace and filename
     *
     * @param string $workspace            
     * @param string $filename            
     */
    public function __construct($workspace, $filename = null)
    {
        $this->filename = $filename;
        $this->workspace = $workspace;
    }

    /**
     * open the file-stream to append
     *
     * @throws \RuntimeException - if fopen fails or filename not set
     * @return resource
     */
    protected function open()
    {
        if ($this->filename == null) {
            throw new \RuntimeException('filename not set');
        }
        if ($this->fp !== null) {
            return $this->fp;
        }
        
        $realFilename = $this->workspace . DIRECTORY_SEPARATOR . $this->filename .
                 ($this->hasZip() ? '.gz' : '');
        $this->fp = $this->hasZip() ? gzopen($realFilename, 'a9') : fopen($realFilename, 'a');
        if (!$this->fp) {
            throw new \RuntimeException('Cannot open Dumpfile: ' . $realFilename);
        }
        return $this->fp;
    }

    /**
     * Put a line to the filestream
     *
     * @param string $str            
     * @throws \RuntimeException - if write fails
     * @return FileDumper
     */
    public function put($str)
    {
        $ret = $this->hasZip() ? gzwrite($this->open(), $str . "\n") : fputs($this->open(), 
                $str . "\n");
        if (!$ret) {
            throw new \RuntimeException('cannot put data to the file');
        }
        return $this;
    }

    /**
     * Close an open filepointer
     *
     * @return FileDumper
     */
    public function close()
    {
        if (!$this->fp) {
            return $this;
        }
        $this->hasZip() ? gzclose($this->fp) : fclose($this->fp);
        $this->fp = null;
        return $this;
    }

    /**
     * List all files in workspace
     *
     * @param string $search            
     * @return array - list of files in Workspace ($filename -> array(additional infos, like mime, ctime...))
     */
    public function glob($search = '*')
    {
        $result = array();
        $files = glob($this->workspace . '/' . $search);
        foreach ($files as $filename) {
            
            if (!is_file($filename)) {
                continue;
            }
            
            $mtype = strstr('.gz', $filename) ? 'application/gzip' : 'application/octet-stream';
            if (function_exists('finfo_open')) {
                $finfo = new \finfo(FILEINFO_MIME);
                $mtype = $finfo->file($filename);
            }
            $result[basename($filename)] = array('realpath' => realpath($filename), 
                    'name' => basename($filename), 
                    'size' => filesize($filename), 
                    'created' => date('Y-m-d H:i:s', filectime($filename)), 
                    'mime' => $mtype, 
                    'mtime' => filemtime($filename), 
                    'ctime' => filectime($filename));
        }
        return $result;
    }

    /**
     * check if it has zip-extension
     *
     * @return boolean
     */
    protected function hasZip()
    {
        return function_exists('gzopen');
    }
}