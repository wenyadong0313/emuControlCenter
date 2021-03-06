<?php
/*
 * Created on 03.10.2006
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
 
 class Os {
	
	private $os_env = array();
	
	public function __construct() {}
 	
	/*
	* get_os
	* ermittelt das betriebsystem, auf dem das
	* programm ausgefï¿½hrt wird.
	* @return string
	*/
	public function getOperatingSystemInfos() {
		
		$this->os_env['OS'] = PHP_OS;
		$this->os_env['TMP'] = (isset($_SERVER['TMP']) && $_SERVER['TMP']) ? $_SERVER['TMP'] : '';
		
		if ('WIN' == strtoupper(substr($this->os_env['OS'],0,3))) {
			$this->os_env['PLATFORM'] = 'WIN';
			$this->os_env['FONT'] = 'Arial';
		}
		else {
			$this->os_env['PLATFORM'] = 'UNKNOWN';
			$this->os_env['FONT'] = 'Helvetica';
		}
		return $this->os_env;
	}
	
	// Opens the selected media in the assigned player
	public function executeFileWithProgramm(
		$exeFileSource,
		$param=false,
		$romFileSource = "",
		$fileNameEscape = false,
		$fileName8dot3 = false,
		$filenameOnly = false,
		$noExtension = false,
		$enableEccScript = false,
		$executeInEmuFolder = false,
		$systemIdent = false,
		$useCueFile = false
	) {
		
		if ($theEmuCommand = $this->getEmuCommand($exeFileSource, $param, $romFileSource, $fileNameEscape, $fileName8dot3, $filenameOnly, $noExtension, $enableEccScript, $executeInEmuFolder, $systemIdent, $useCueFile)){
			$emuCommand = $theEmuCommand['command'];
			$chdirDestination = $theEmuCommand['chdir'];
		}
		else return false;
		
		$this->executeCommand($emuCommand, $chdirDestination);
		
		return true;		
	}
	
	public function executeCommand($command, $cwdPath = false, $returnCwdPath = false, $useExec = false){
		
		# windows start tool
		$commandWinStart  = 'start /B'; //Maybe use COM object?
		$commandWinStart .= ($this->os_env['OS'] == 'WINNT') ? ' "player"' : ''; # win98 needs "player". Otherwise, the file isn't started
		$executeCommand = $commandWinStart.' '.$command; # Compile start command
		$cwdBackup = getcwd(); # create an backup of the current cwd	
		if($cwdPath) chdir($cwdPath); # change dir to the programs directory

		if($useExec) exec($executeCommand); # execute this command
		else pclose(popen($executeCommand, "r")); # execute command
		
//		print "<pre>".LF;
//		print_r($executeCommand).LF;
//		print "</pre>".LF;
		
		if($returnCwdPath) return $cwdBackup; # return path
		else chdir($cwdBackup); # change dir back to cwdBackup!
	}
	
	public function getEmuCommand(
		$exeFileSource,
		$param = false,
		$romFileSource = "",
		$fileNameEscape = false,
		$fileName8dot3 = false,
		$filenameOnly = false,
		$noExtension = false,
		$enableEccScript = false,
		$executeInEmuFolder = false,
		$systemIdent = false,
		$useCueFile = false
	) {

		// if filenameOnly set, only use the basename (name.rom) without path!
		if ($filenameOnly) {
			$chdirDestination = dirname(realpath($romFileSource));
			$romFile = ($romFileSource) ? basename($romFileSource) : ''; 
			$exeFile = realpath($exeFileSource);
		}
		else {
			$chdirDestination = dirname(realpath($exeFileSource));
			$romFile = ($romFileSource) ? realpath($romFileSource) : ''; 
			#$exeFile = escapeshellcmd(basename($exeFileSource));
			$exeFile = basename($exeFileSource);
		}
		
		if (!$chdirDestination) return false;
		#if (!$romFile) return false;
		if (!$exeFile) return false;
		
		$eccScriptExeFile = '';
		if ($enableEccScript) {
			
			$fileNameEscape = true; # if ecc script is enabled, path has to be escaped
			$eccLoc = FACTORY::get('manager/Validator')->getEccCoreKey('eccHelpLocations');
			$scriptExtension = $eccLoc['ECC_SCRIPT_EXTENSION'];
			
			// if rom objet available
			if($eccScriptFile = realpath('../ecc-script/'.$systemIdent.'/'.FACTORY::get('manager/FileIO')->get_plain_filename($exeFileSource).$scriptExtension)){
				$exeFile = $eccScriptFile;
				if ($eccScriptExeFile = realpath(ECC_DIR.'/'.$eccLoc['ECC_EXE_SCRIPT'])){
					$eccScriptExeFile = '"'.$eccScriptExeFile.'"';
				}
			}	
			elseif ($eccScriptFile = realpath($exeFileSource.$scriptExtension)){
				$exeFile = $eccScriptFile;
				if ($eccScriptExeFile = realpath(ECC_DIR.'/'.$eccLoc['ECC_EXE_SCRIPT'])){
					$eccScriptExeFile = '"'.$eccScriptExeFile.'"';
				}
			}
		}
		
		// start romfile with removed fileextension e.g. "aof.rom" will be "aof"
		if ($noExtension) {
			$extraDir = (dirname($romFile) != '.') ? dirname($romFile).DIRECTORY_SEPARATOR : '';
			$romFile = $extraDir.FACTORY::get('manager/FileIO')->get_plain_filename($romFile); 
		}

		// use .cue files?
		if($useCueFile && $romFile && !$noExtension){
		
			$path = (dirname($romFile) && dirname($romFile) != '.') ? dirname($romFile) : '';	
		
			//OLD CODE, buggy if there are more dots in the filename, then only the partial name would be given!
			//$romFileName = basename($romFile);
			//if (false !== strrpos($romFileName, ".")) {
			//	$split = explode(".", $romFileName);
			//	$romFileName = array_shift($split);
			//}

			//NEW CODE, added 2013-11-17 (knowing that '.cue' is always 4 chars, just cut 4 chars of the right side of the string!)
			$romFileName = substr(basename($romFile), 0, -4);
								
			if($path) $path = realpath($path).DIRECTORY_SEPARATOR;
			// Look for .CUE file and use if exists!, added 2012-11-18 (so this will also work if some roms don't have cue files!)
			if(!file_exists($path.$romFileName.'.cue')) { //CUE file NOT found
				$romFile = basename($romFile);
				$error = 'FILE-NOT_FOUND-CUE';
			}
			else { //CUE file found
				$romFile = $path.$romFileName.'.cue';
			}
		}
		
		// 8.3 filepath & filename?
		if ($fileName8dot3 && !$filenameOnly) {
			if ($this->os_env['PLATFORM']=='WIN') $romFile = $this->getEightDotThreePath($romFile); 
		}
		
		// escape rompath?
		if (!$fileNameEscape) {
			if ($this->os_env['PLATFORM']=='WIN') $romFile = str_replace("&", "^&", $romFile);
		}
		else{
			$romFile = '"'.$romFile.'"';
			#$romFile = escapeshellarg($romFile);
		}
		
		// eccScript doesn't support commandline-params at the beginning!
		if($enableEccScript){
			$paramPre = '';
			$paramPost = '';
			$romFile = '';
		}
		else{
			$paramPre = '';
			$paramPost = '';
			if (FALSE !== $startPos = strpos($param, '%ROM%')){
				$paramPre = ltrim(substr($param, 0, $startPos));
				$paramPost = rtrim(substr($param, $startPos+5));
			}
			else{
				// if other parameters are set
				$paramPre = $param;
				$paramPost = '';
				$romFile = '';
			}
		}
		
//		if ($paramPre) $paramPre = ' '.$paramPre;
//		if ($paramPost) $paramPost = ' '.$paramPost;
		
		# This is used eg for WinKawaks!
		# change to emu dir -> execute basename of emu with an given
		# filename without extension
		if($executeInEmuFolder){
			$chdirDestination = dirname(realpath($exeFileSource));
			$exeFile = basename($exeFileSource);
		}

		// win98 needs "player". Otherwise, the file isnt started
		$start_ident = ($this->os_env['OS'] == 'WINNT') ? '"player"' : "";
		
		$emuCommand = trim($eccScriptExeFile.' "'.$exeFile.'" '.$paramPre.$romFile.$paramPost);
		
		$ret = array('command' => $emuCommand, 'chdir' => $chdirDestination);
		
//		print __FUNCTION__.'<pre>';
//		print_r($ret);
//		print '</pre>';
		
		return $ret;
		
	}
	
	public function openChooseFolderDialog($path=false, $title=false, $multiSelection = false, $shorcutFolder = false) {

		return $this->openGtk2ChooseFsDialog($path, $title, false, Gtk::FILE_CHOOSER_ACTION_SELECT_FOLDER, $multiSelection, $shorcutFolder);
	}
	
	/**
	 * Function opens a standard windows Choose path dialog
	 * Using the pecl extension win32std
	 */
	private function openWin32ChooseFolderDialog($path=false, $title=false) {
		if (!$path) $path = '%WINDIR%';
		if (!$title) $title = '';
		while (gtk::events_pending()) gtk::main_iteration();
		$result= win_browse_folder($path, $title);
		return ($result) ? $result : false;
	}

	public function openChooseFileDialog($path=false, $title=false, $filter=array(), $defaultFilename=false, $multiSelection = false, $shorcutFolder = false) {
		return $this->openGtk2ChooseFsDialog($path, $title, $filter, Gtk::FILE_CHOOSER_ACTION_OPEN, $multiSelection, $shorcutFolder);
	}
	
	/**
	 * Function opens a standard windows Choose file dialog
	 * Using the pecl extension win32std
	 */
	private function openWin32ChooseFileDialog($path=false, $filter=array(), $defaultFilename=false) {
		if (!$path) $path = '%WINDIR%';
		if (!$defaultFilename) $defaultFilename = '';
	 	$result= win_browse_file(true, realpath($path), $defaultFilename, null, $filter);
	 	return ($result) ? $result : false;
	}
	
	/*
	*
	*/
	public function openGtk2ChooseFsDialog($path=false, $title=false, $extension_limit=false, $type=Gtk::FILE_CHOOSER_ACTION_SELECT_FOLDER, $multiSelection = false, $shorcutFolder = false) {
		
		$title = ($title) ? $title : I18N::get('popup', 'sys_filechooser_miss_title');
		$dialog = new GtkFileChooserDialog(
			$title,
			NULL,
			$type,
			array(
				Gtk::STOCK_CANCEL,
				Gtk::RESPONSE_CANCEL,
				Gtk::STOCK_OK,
				Gtk::RESPONSE_OK
			)
		);
		if ($multiSelection) $dialog->set_select_multiple(true);
		
		$dialog->set_keep_above(true);
		$dialog->set_position(Gtk::WIN_POS_CENTER);
		$dialog->set_size_request(640, 480);
		
		$label = new GtkLabel();
		$label->set_markup('<b>'.$title.'</b>');
		$dialog->set_extra_widget($label);
		
		# if no path is given, try to get the last selected path
		if(!trim($path)) $path = FACTORY::get('manager/IniFile')->getHistoryKey('path_selected_last');
		
		# if the given path isnt available, try to get the dir before this path
		if (!realpath($path)) $path = (dirname($path)) ? dirname($path) : false;
		
		# if after all no path is selected, select the root path of ecc installation
		if(!trim($path)) $path = realpath('/');
	
		if ($path) $dialog->set_filename($path);
		
//		$dialog->set_preview_widget(new GtkEntry());
//		$dialog->connect_simple('update-preview', array($this, 'test'), $dialog);
//		$dialog->set_preview_widget_active();
		
		if ($shorcutFolder && count($shorcutFolder)){
			foreach ($shorcutFolder as $shorcutFolderDirname){
				if (!is_dir(realpath($shorcutFolderDirname))) $shorcutFolder = dirname(realpath($shorcutFolderDirname));
				try {
					$dialog->add_shortcut_folder($shorcutFolderDirname);	
				}
				catch (Exception $e){}
				
			}
		}
				
		if (is_array($extension_limit) && count($extension_limit)) {
			foreach ($extension_limit as $filter_name => $filter_value) {
				$filter = new GtkFileFilter();
				$filter->set_name($filter_name);
				$filter->add_pattern($filter_value);
				$dialog->add_filter($filter);
			}
			#$filter2 = new GtkFileFilter();
		}
		
		$response = $dialog->run();
		if ($response === Gtk::RESPONSE_OK) {
			
			if ($multiSelection) $path = $dialog->get_filenames();
			else $path = $dialog->get_filename();
			
			$dialog->destroy();
			
			# store the last selected path
			if(isset($path[0])) FACTORY::get('manager/IniFile')->storeHistoryKey('path_selected_last', realpath($path[0]));
			
			return $path;
		}
		$dialog->destroy();
		
		return false;
	}
	
	/*
	*
	*/
	public function launch_file($filename) {
		win_shell_execute($filename);
		return true;
	}
	
	public function executeProgramDirect($applicationPath, $action=false, $arguments=false, $directory=false) {
		win_shell_execute($applicationPath, $action, $arguments, $directory);
		return true;		
	}
	
	/**
	 * Function uses com-api to create 8.3 Winpaths
	 * @return string string in 8.3 style
	 */
	public function getEightDotThreePath($filePath) {
		if (!file_exists($filePath)) return $filePath;
		$exFSO = new COM("Scripting.FileSystemObject");
		$exFile = $exFSO->GetFile($filePath);
		$filePath = $exFile->ShortPath;
		unset($exFSO);
		return $filePath;
	}
	
	
	/**
	 * Functions create relative paths from ecc-basepath, if possible
	 * Used by eccSetRelativeDir & eccSetRelativeFile
	 *
	 * @param unknown_type $path
	 * @return unknown
	 */
	public function eccSetPathRelative($path, $fromBasepath = true) {
		
		$path = preg_replace('/\/+/', DIRECTORY_SEPARATOR, $path);
		$path = preg_replace('/\\\+/', DIRECTORY_SEPARATOR, $path);
		
		if(!file_exists($path)) return false;
		
		if (strpos($path, ECC_DIR) === 0) {
			$offset = ($fromBasepath) ? ECC_DIR_OFFSET : '';
			$path = str_replace(ECC_DIR, $offset, $path);
		};

		return $path;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $dir
	 * @return unknown
	 */
	public function eccSetRelativeDir($dir) {
		$dir = $this->eccSetPathRelative($dir);
		#if ($dir && substr($dir, -1) !== DIRECTORY_SEPARATOR) $dir = $dir.DIRECTORY_SEPARATOR;
		
		if($dir){
			$lastChar = substr($dir, -1);
			if($lastChar != '/' && $lastChar != '\\') $dir .= DIRECTORY_SEPARATOR;
		}
		
		return $dir;
	}
	
	public function eccSetRelativeFile($file) {
		return $this->eccSetPathRelative($file);
	}
 }
 
?>
