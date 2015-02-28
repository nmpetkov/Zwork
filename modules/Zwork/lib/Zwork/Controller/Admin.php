<?php
/**
 * Zwork Zikula Module
 *
 * @copyright Nikolay Petkov
 * @license GNU/GPL
 */
class Zwork_Controller_Admin extends Zikula_AbstractController
{
    /**
     * Main administration function
     */
    public function main()
    {
        return $this->displaysysinfo();
    }
    /**
     * Modify module Config
     */
    public function modifyconfig()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        // Get module configuration vars
        $vars = $this->getVars();
        if (!isset($vars['zwork_url_cpanel'])) {
            $vars['zwork_url_cpanel'] = '';
        }
        if (!isset($vars['zwork_url_phpmyadmin'])) {
            $vars['zwork_url_phpmyadmin'] = '';
        }
        if (!isset($vars['zwork_scriptssort'])) {
            $vars['zwork_scriptssort'] = '0';
        }
        if (!isset($vars['zwork_showphpinfo'])) {
            $vars['zwork_showphpinfo'] = '1';
        }

        $this->view->assign('vars', $vars);
        $this->view->assign('scriptsdir_exist', is_dir($vars['zwork_scriptsdir']));
        $this->view->assign('backupsdir_exist', is_dir($vars['zwork_backupsdir']));

        return $this->view->fetch('admin/modifyconfig.tpl');
    }

    /**
     * Update module Config
     */
    public function updateconfig()
    {
        $this->checkCsrfToken();
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $vars = array();
        $vars['zwork_backupsdir'] = FormUtil::getPassedValue('zwork_backupsdir', 'userdata/Zwork/backups');
        $vars['zwork_scriptsdir'] = FormUtil::getPassedValue('zwork_scriptsdir', 'userdata/Zwork/scripts');
        $vars['zwork_scriptssort'] = FormUtil::getPassedValue('zwork_scriptssort', "0");
        $vars['zwork_showphpinfo'] = FormUtil::getPassedValue('zwork_showphpinfo', "0");
        $vars['zwork_url_cpanel'] = FormUtil::getPassedValue('zwork_url_cpanel', '');
        $vars['zwork_url_phpmyadmin'] = FormUtil::getPassedValue('zwork_url_phpmyadmin', '');
        $scriptsdir_createfolder = (bool)FormUtil::getPassedValue('scriptsdir_createfolder', false, 'POST');
        $backupsdir_createfolder = (bool)FormUtil::getPassedValue('backupsdir_createfolder', false, 'POST');

        // set the new variables
        $this->setVars($vars);

        if ($backupsdir_createfolder && !empty($vars['zwork_backupsdir'])) {
            if (is_dir($vars['zwork_backupsdir'])) {
                 LogUtil::registerStatus($this->__f('Directory exists: %s.', $vars['zwork_backupsdir']));
            } else {
                if (FileUtil::mkdirs($vars['zwork_backupsdir'], 0777)) {
                    LogUtil::registerStatus($this->__f('Directory is created: %s.', $vars['zwork_backupsdir']));
                } else {
                    LogUtil::registerError($this->__f('Can not create directory %s.', $vars['zwork_backupsdir']) 
                    .'<br />'. $this->__('Please create it manually, for example with FTP client.'));
                }
            }
        }

        if ($scriptsdir_createfolder && !empty($vars['zwork_scriptsdir'])) {
            if (is_dir($vars['zwork_scriptsdir'])) {
                 LogUtil::registerStatus($this->__f('Directory exists: %s.', $vars['zwork_scriptsdir']));
            } else {
                if (FileUtil::mkdirs($vars['zwork_scriptsdir'], 0777)) {
                    LogUtil::registerStatus($this->__f('Directory is created: %s.', $vars['zwork_scriptsdir']) 
                    .'<br />'. $this->__('Please create it manually, for example with FTP client.'));
                } else {
                    LogUtil::registerError($this->__f('Can not create directory %s.', $vars['zwork_scriptsdir']));
                }
            }
        }

        // clear the cache
        $this->view->clear_cache();
    
        LogUtil::registerStatus($this->__('Done! Updated configuration.'));
        return $this->modifyconfig();
    }

    /**
     * Display scripts to execute
     */
    public function scripts()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        // Get module configuration vars
        $vars = $this->getVars();

        $scripts = array();

        if (empty($vars['zwork_scriptsdir'])) {
            LogUtil::registerError($this->__('Please specify in module settings directory for scripts to execute!'));
        } else {
            if (is_dir($vars['zwork_scriptsdir'])) {
                $files = scandir($vars['zwork_scriptsdir']);
                foreach ($files as $file) {
                    if (!is_dir($vars['zwork_scriptsdir'] . (substr($vars['zwork_scriptsdir'], -1) == '/' ? '' : '/') . $file)) {
                    $scripts[] = $file;
                    }
                }
                if ($vars['zwork_scriptssort']) {
                    natcasesort($scripts);
                }
            } else {
                LogUtil::registerError($this->__('Please visit module settings and create directory for scripts to execute!'));
            }
        }

        $this->view->assign('vars', $vars);
        $this->view->assign('scripts', $scripts);
        $this->view->assign('scriptsdirlockstatus', ModUtil::apiFunc($this->name, 'admin', 'getScriptsDirLockStatus'));

        return $this->view->fetch('admin/scripts.tpl');
    }

    /**
     * Execute selected scripts
     */
    public function executescripts()
    {
        $this->checkCsrfToken();
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $execute = FormUtil::getPassedValue('execute', array());
        $scripts = FormUtil::getPassedValue('scripts', array());
        $newSriptFilename = FormUtil::getPassedValue('newSriptFilename', '');
        
        // create file, if given
        if ($newSriptFilename) {
            $newSriptFilename = pathinfo($newSriptFilename, PATHINFO_FILENAME) . '.php'; // force php extension
            $newSriptFilename = $this->getScriptFullPath($newSriptFilename);
            $filecontent = '<?php' . PHP_EOL;
            $fileIsCreated = ModUtil::apiFunc($this->name, 'admin', 'createFile', array('filename' => $newSriptFilename, 'filecontent' => $filecontent));
            if ($fileIsCreated) {
                LogUtil::registerStatus($this->__f('New file %s is created.', $newSriptFilename));
            }
        }

        // execute selected scripts
        $countexecutions = 0;
        foreach ($execute as $key => $value) {
            if ($value) {
                ModUtil::apiFunc($this->name, 'admin', 'executeScript', array('filename' => $scripts[$key]));
                $countexecutions ++;
            }
        }
  
        if ($countexecutions > 0) {
            LogUtil::registerStatus($this->__('Done! Executed selected scripts.'));
        } else {
            if (!$newSriptFilename) {
                LogUtil::registerStatus($this->__('Please select scripts to execute!'));
            }
        }

        return $this->scripts();
    }

    /**
     * Display Server information
     */
    public function displaysysinfo()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_READ), LogUtil::getErrorMsgPermission());

        // Get module configuration vars
        $vars = $this->getVars();
        $connection = $this->entityManager->getConnection();
        $dbparams = $connection->getParams();
        // $connection->getDatabasePlatform()->getName() - returns mysql
        $dbserverversion = '';
        if ($connection->getDatabasePlatform()->getName() == "mysql") {
            $stmt = $connection->prepare("SHOW VARIABLES LIKE 'version'");
            $stmt->execute();
            $result = $stmt->fetchAll(Doctrine_Core::FETCH_ASSOC);
            if ($result) {
                $dbserverversion = 'MySQL ' . $result[0]['Value'];
            }
        }

        $this->view->assign('vars', $vars);
        $this->view->assign('dbserverversion', $dbserverversion);
        $this->view->assign('dbparams', $dbparams);
        $this->view->assign('phpos', PHP_OS);
        $this->view->assign('phpinfo', ModUtil::apiFunc($this->name, 'admin', 'getphpinfoclean'));
        $this->view->assign('site_root', System::serverGetVar('DOCUMENT_ROOT'));
        $this->view->assign('server_ip', System::serverGetVar('SERVER_ADDR'));
        $this->view->assign('server_port', System::serverGetVar('SERVER_PORT'));
        $this->view->assign('server_software', System::serverGetVar('SERVER_SOFTWARE'));
        $this->view->assign('server_phpversion', phpversion());

        return $this->view->fetch('admin/sysinfo.tpl');
    }

    /**
     * Display phpinfo() (new page)
     */
    public function displaysysinforaw()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_OVERVIEW), LogUtil::getErrorMsgPermission());

        phpinfo();
        exit();
    }

    /**
     * Display browser information
     */
    public function displaybrowserinfo()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_OVERVIEW), LogUtil::getErrorMsgPermission());

        $this->view->assign('user_ip', System::serverGetVar('REMOTE_ADDR'));
        $this->view->assign('user_port', System::serverGetVar('REMOTE_PORT'));
        $this->view->assign('user_agent', System::serverGetVar('HTTP_USER_AGENT'));
        $this->view->assign('user_lang', System::serverGetVar('HTTP_ACCEPT_LANGUAGE'));
        $this->view->assign('user_accept', System::serverGetVar('HTTP_ACCEPT'));
        $this->view->assign('user_cookies', System::serverGetVar('HTTP_COOKIE'));

        return $this->view->fetch('admin/clientinfo.tpl');
    }

    /**
     * Display cookies (new page)
     */
    public function displaycookies()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_OVERVIEW), LogUtil::getErrorMsgPermission());

        echo System::serverGetVar('HTTP_COOKIE');
        exit();
    }

    /**
     * Edit a script (php) file in scripts directory
     */
    public function editfile($args)
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $filename = FormUtil::getPassedValue('filename', isset($args['filename']) ? $args['filename'] : null, 'REQUEST');

        if (empty($filename)) {
            LogUtil::registerArgsError();
            return System::redirect(ModUtil::url($this->name, 'admin', 'scripts'));
        }
        $filenameWithpath = $this->getScriptFullPath($filename);

        if (file_exists($filenameWithpath)) {
            $filecontent = file_get_contents($filenameWithpath);
        } else {
            LogUtil::registerError($this->__f('Error! File does not exist: %s', $filenameWithpath));
            return System::redirect(ModUtil::url($this->name, 'admin', 'scripts'));
        }

        $this->view->assign('filename', $filename);
        $this->view->assign('filecontent', $filecontent);

        return $this->view->fetch('admin/editfile.tpl');
    }

    /**
     * Delete a script file in scripts directory
     */
    public function deletefile($args)
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $filename = FormUtil::getPassedValue('filename', isset($args['filename']) ? $args['filename'] : null, 'REQUEST');

        if (empty($filename)) {
            LogUtil::registerArgsError();
            return System::redirect(ModUtil::url($this->name, 'admin', 'scripts'));
        }
        $filenameWithpath = $this->getScriptFullPath($filename);

        if (file_exists($filenameWithpath)) {
            if (unlink($filenameWithpath)) {
            LogUtil::registerStatus($this->__f('File %s is deleted.', $filenameWithpath));
            } else {
                LogUtil::registerError($this->__f('Error! File %s can not be deleted.', $filenameWithpath));
            }
        } else {
            LogUtil::registerStatus($this->__f('File does not exist: %s', $filenameWithpath));
        }

        return System::redirect(ModUtil::url($this->name, 'admin', 'scripts'));
    }

    /**
     * Save edited script
     */
    public function savescript()
    {
        $this->checkCsrfToken();
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $filename = FormUtil::getPassedValue('filename', '');
        $filecontent = FormUtil::getPassedValue('filecontent', '');
        $edit = FormUtil::getPassedValue('edit', '');
        $execute = FormUtil::getPassedValue('execute', '');

        if (empty($filename)) {
            LogUtil::registerError($this->__('Error! File name is empty.'));
        }
        $filenameWithpath = $this->getScriptFullPath($filename);

        if (file_exists($filenameWithpath)) {
            if (file_put_contents($filenameWithpath, $filecontent) === false) {
                LogUtil::registerError($this->__f('Error! Can not write content to file %s.', $filenameWithpath));
            } else {
                LogUtil::registerStatus($this->__f('Done! File %s is saved.', $filenameWithpath));
            }
        } else {
            LogUtil::registerError($this->__f('Error! File %s does not exist.', $filenameWithpath));
        }

        if ($edit) {
            return System::redirect(ModUtil::url($this->name, 'admin', 'editfile', array('filename' => $filename)));
        } elseif ($execute) {
                ModUtil::apiFunc($this->name, 'admin', 'executeScript', array('filename' => $filename));
        }

        return System::redirect(ModUtil::url($this->name, 'admin', 'scripts'));
    }

    /**
     * Save edited script
     */
    public function lockscriptsdir($args)
    {
        $this->checkCsrfToken();
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_ADMIN), LogUtil::getErrorMsgPermission());

        $lock = FormUtil::getPassedValue('lock', '');
        $unlock = FormUtil::getPassedValue('unlock', '');
        $type = '';
        if ($lock) {
             $type = 'lock';
        } elseif ($unlock) {
             $type = 'unlock';
        }

        ModUtil::apiFunc($this->name, 'admin', 'setScriptsDirLockStatus', array('type' => $type));

        return System::redirect(ModUtil::url($this->name, 'admin', 'scripts'));
    }

    public function getScriptFullPath($filename)
    {
        $scriptsdir = ModUtil::apiFunc($this->name, 'admin', 'getScriptsDir');

        return DataUtil::formatForOS($scriptsdir . $filename);
    }

    /**
     * Backup database
     */
    public function backupdb()
    {
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Zwork::', '::', ACCESS_EDIT), LogUtil::getErrorMsgPermission());

        // Get module configuration vars
        $vars = $this->getVars();

        $backups = array();

        if (empty($vars['zwork_backupsdir'])) {
            LogUtil::registerError($this->__('Please specify in module settings directory for backups to store!'));
        } else {
            if (is_dir($vars['zwork_backupsdir'])) {
                $files = scandir($vars['zwork_backupsdir']);
                foreach ($files as $file) {
                    if (!is_dir($vars['zwork_backupsdir'] . (substr($vars['zwork_backupsdir'], -1) == '/' ? '' : '/') . $file)) {
                    $backups[] = $file;
                    }
                }
                natcasesort($backups);
            } else {
                LogUtil::registerError($this->__('Please visit module settings and create directory for backups to store!'));
            }
        }

        $this->view->assign('vars', $vars);
        $this->view->assign('backups', $backups);

        return $this->view->fetch('admin/backupdb.tpl');
    }
}