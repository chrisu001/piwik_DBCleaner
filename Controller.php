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

use Piwik\Piwik;
use Piwik\Common;
use Piwik\View;
use Piwik\Plugins\SitesManager\API as SitesManagerAPI;
use Piwik\Option;
use Piwik\Filesystem;

require_once __DIR__ . '/lib/ProcessorFactory.php';

/**
 * Controller for Database Cleaner Plugin
 *
 * @package Piwik_DBCleaner
 */
class Controller extends \Piwik\Plugin\ControllerAdmin
{
    /**
     * default path to store the backupfiles
     *
     * @var string
     */
    const WORKSPACE = "tmp/DBCleaner/backup";
    
    /**
     * Prefix for backupfilenames
     * @var string
     */
    const BACKUP_PREFIX = 'PiwikArchive_';
    
    
    /**
     * Realpath to the backupfiles
     *
     * @var string
     */
    protected $workspace = null;
    
    /**
     * Nonce to prevent corrupt proccesses to be run
     *
     * @var string
     */
    protected $nonce = 'unset';

    /**
     * Index Page
     * Display the Jquery Tab
     */
    public function index()
    {
        $view = new View('@DBCleaner/index');
        $this->setBasicVariablesView($view);
        $view->until = false;
        $view->jsscript = 'script.js';
        echo $view->render();
    }

    /**
     * Tab-Page to
     * delete and backup all log and config entries of a website from database
     */
    public function history()
    {
        $view = new View('@DBCleaner/history');
        $this->setBasicVariablesView($view);
        
        $deleteId = Common::getRequestVar('deleteId', false, 'integer');
        $siteId = Common::getRequestVar('idSite', false, 'integer');
        $idSitesAvailable = SitesManagerAPI::getInstance()->getSitesWithAdminAccess();
        
        $view->idSiteSelected = $siteId;
        $view->idSitesAvailable = $idSitesAvailable;
        $view->siteName = \Piwik\Site::getNameFor($siteId);
        
        $view->dump = false;
        
        if (count($idSitesAvailable) <= 1) {
            // last website could not be deleted, otherwise Piwik will not work
            $view->error = Piwik::translate('DBCleaner_Historyerrorlastsite');
            $deleteId = 0;
        }
        if ($deleteId == 1) {
            // idsite 1 could not be deleted, otherwise Piwik would have trouble
            $view->error = Piwik::translate('DBCleaner_Historyerroridone');
            $deleteId = 0;
        }
        
        if (!$deleteId) {
            print $view->render();
            return;
        }
        
        /*
         * Selection posted, so delete website with $deleteId
         */
        $view->idSiteSelected = $deleteId;
        $view->siteName = \Piwik\Site::getNameFor($deleteId);
        $view->dump = true;
        ProcessorFactory::newWebsite($deleteId, $this->nonce, 
                $this->getWorkspaceDir() . sprintf('%s%d_%s', self::BACKUP_PREFIX, $deleteId, 
                        urlencode($view->siteName)));
        print $view->render();
    }

    /**
     * Tab-Page to
     * cleanup historical log-data
     */
    public function cleanup()
    {
        $view = new View('@DBCleaner/cleanup');
        $this->setBasicVariablesView($view);
        
        $until = Common::getRequestVar('until', false, 'string');
        $view->defaultdate = date('Y-m-d', time() - 32 * 86400); // default -32 days
        $view->dump = false;
        
        if ($until) {
            $until = strtotime($until);
            if (!$until) {
                $view->error = Piwik::translate('DBCleaner_errortime');
            } else {
                // all ok, run a dump;
                $view->dump = true;
                ProcessorFactory::newCleanup($until, $this->nonce, 
                        $this->getWorkspaceDir() . self::BACKUP_PREFIX .'until_' .
                                 date('Ymd_His', intVal($until)));
            }
        }
        print $view->render();
    }

    /**
     * Tab-Page to
     * execute optimize tables
     */
    public function optimize()
    {
        $optimize = Common::getRequestVar('optimize', 0, 'integer');
        if (!$optimize) {
            return $this->advanced();
        }
        // optimize Tables
        $view = new View('@DBCleaner/advanced');
        $this->setBasicVariablesView($view);
        $view->optimize = true;
        ProcessorFactory::newOptimize($this->nonce);
        print $view->render();
    }

    /**
     * Tab-Page Advance to
     * - configure backupdirecory
     * - optimize tables (routes to Controller:optimize())
     */
    public function advanced()
    {
        $view = new View('@DBCleaner/advanced');
        $this->setBasicVariablesView($view);
        
        $ws = Option::get('DBCleaner_workspace');
        $path = Common::getRequestVar('path', false, 'string');
        
        if (empty($ws)) {
            $ws = self::WORKSPACE;
        }
        $view->dumpdir = $ws;
        $view->optimize = false;
        
        if (!$path) {
            print $view->render();
            return;
        }
        // ajax call to update path:
        $error = '';
        $success = false;
        $realpathWorkspace = $path;
        if (substr($realpathWorkspace, 0, 1) != '/') {
            $realpathWorkspace = PIWIK_USER_PATH . '/' . $realpathWorkspace;
        }
        if (!is_dir($realpathWorkspace)) {
            try {
                Filesystem::mkdir($realpathWorkspace, true);
            } catch (\Exception $e) {
            }
        }
        if (!is_dir($realpathWorkspace) || !is_writable($realpathWorkspace)) {
            // not able to get writable workspace directory
            $error = Piwik::translate('DBCleaner_Confignodir');
        } else {
            Option::set('DBCleaner_workspace', $path);
            $success = true;
        }
        
        print 
                json_encode(
                        array('success' => $success, 
                                'error' => $error, 
                                'path' => $path, 
                                'realpath' => $realpathWorkspace));
    }

    /**
     * Get the commited realpath of the configured backupdirectory
     *
     * @return string
     */
    protected function getWorkspaceDir()
    {
        $this->workspace = Option::get('DBCleaner_workspace');
        if (empty($this->workspace)) {
            $this->workspace = self::WORKSPACE;
        }
        // Path is relative ?
        if (substr($this->workspace, 0, 1) != '/') {
            $this->workspace = PIWIK_USER_PATH . '/' . $this->workspace;
        }
        if (!is_dir($this->workspace)) {
            try {
                Filesystem::mkdir($this->workspace, true);
            } catch (\Exception $e) {
            }
        }
        if (!is_dir($this->workspace) || !is_writable($this->workspace)) {
            // Fallback to the default directory and throw execption, if not available
            $this->workspace = PIWIK_USER_PATH . '/' . self::WORKSPACE;
            Filesystem::mkdir($this->workspace, true);
        }
        $this->workspace = realpath($this->workspace);
        return $this->workspace . '/';
    }

    /**
     * Tab-Page
     * - render a list of avaialable backupfiles
     * - download a backupfile
     */
    public function filelist()
    {
        $view = new View('@DBCleaner/filelist');
        $this->setBasicVariablesView($view);
        
        ProcessorFactory::reset();
        
        $filename = Common::getRequestVar('filename', false, 'string');
        
        $fd = new FileDumper($this->getWorkspaceDir());
        $view->filelist = $files = $fd->glob(self::BACKUP_PREFIX.'*');
        
        if (!$filename || !isset($files[$filename])) {
            print $view->render();
            return;
        }
        
        /*
         * download file
         */
        @set_time_limit(300);
        
        $fileinfo = $files[$filename];
        $useragent = empty($_SERVER["HTTP_USER_AGENT"]) ? null : $_SERVER["HTTP_USER_AGENT"];
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 0);
        
        header('Content-Description: File Transfer');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');
        header("Content-type: application/force-download");
        header('Content-Type: application/octet-stream');
        header("Content-Length: " . $fileinfo['size']);
        
        if (strstr($useragent, "MSIE") != false) {
            header(
                    "Content-Disposition: attachment; filename=" . urlencode($fileinfo['name']) .
                             '; modification-date="' . date('r', $fileinfo['mtime']) . '";');
        } else {
            header(
                    "Content-Disposition: attachment; filename=\"" . basename($fileinfo['name']) .
                             '"; modification-date="' . date('r', $fileinfo['mtime']) . '";');
        }
        
        // If it's a large file, readfile might not be able to do it in one go, so:
        $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
        
        $handle = fopen($fileinfo['realpath'], 'rb');
        while (!feof($handle)) {
            echo fread($handle, $chunksize);
            flush();
        }
        fclose($handle);
        exit();
    }

    /**
     * json-ajax method to execute the next process step and return the current status of it
     */
    public function progressBar()
    {
        $nonce = Common::getRequestVar('nonce', 'invalid', 'string');
        
        $proc = ProcessorFactory::runable();
        $error = '';
        
        try {
            $proc->run($nonce);
        } catch (InfoResources_Exception $e) {
            // ignore the low memory/exectime, and retry again with a next call
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        
        print 
                json_encode(
                        array('stats' => $proc->getExtendedStatus(), 
                                'progress' => $proc->progressBar(), 
                                'ready' => $proc->isFinished(), 
                                'count' => $proc->getCount(), 
                                'done' => $proc->getDone(), 
                                'error' => $error));
        if ($proc->isFinished()) {
            // job finished
            $proc->resetCache();
        }
    }

    /**
     * Extend Basicvars with selfaction FQ-link
     * grant superuser access only
     *
     * @see \Piwik\Controller\Admin::setBasicVariablesView()
     * @param \Piwik\View $view            
     */
    protected function setBasicVariablesView($view)
    {
        Piwik::checkUserIsSuperUser();
        $idSite = Common::getRequestVar('idSite', 1, 'integer');
        $period = Common::getRequestVar('period', 'day', 'string');
        $date = Common::getRequestVar('date', 'today', 'string');
        $view->selfaction = sprintf('?module=DBCleaner&idSite=%d&period=%s&date=%s&action=', 
                urlencode($idSite), urlencode($period), urlencode($date));
        $view->onlineavailable = is_dir(dirname(__FILE__) . '/../PluginMarketplace');
        $view->cachebuster = uniqid('cb');
        $view->error = null;
        $view->nonce = $this->nonce = uniqid('sfr');
        return parent::setBasicVariablesView($view);
    }
}
