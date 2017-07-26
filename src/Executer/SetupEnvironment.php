<?php
/* Copyright (c) 2017 Daniel Weise <daniel.weise@concepts-and-training.de>, Extended GPL, see LICENSE */

namespace CaT\Ilse\Executer;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use CaT\Ilse\App;

/**
 * Run the ILIAS installation process in interactive mode
 */
class SetupEnvironment extends BaseExecuter
{
	/**
	 * @var bool
	 */
	protected $interactive;

	/**
	 * Constructor of the class SetupEnvironment
	 * 
	 * @param bool 		$interactive
	 */
	public function __construct($interactive)
	{
		assert('is_bool($interactive)');
		parent::__construct();

		$this->interactive = $interactive;
	}

	/**
	 * Start the setup for the environment
	 */
	public function run()
	{
		$this->crateDataDir();
		$this->checkDataDirPermissions();
		$this->checkDataDirEmpty();
		$this->createLogDir();
		$this->createLogFile();
		$this->checkPHPVersion();
		$this->checkPDO();
		$this->checkDBConnection();
		$this->validPhpForIliasBranch();
		$this->cloneILIAS();
	}

	/**
	 * Create data directory
	 */
	protected function crateDataDir()
	{
		$check = $this->checker->dataDirectoryExists($this->data_path);
		if($this->interactive && !$check)
		{
			echo "Data directory does not exist. Create the directory (yes|no)? ";
			$line = getUserInput();
			if(strtolower($line) != "yes") {
				echo "Aborted by user.";
				exit(1);
			}

			echo "Creating data directory...";
			mkdir($this->data_path, 0777, true);
			echo "\t\t\t\t\t\t\t\t\t\t\tDone!\n";
		}
		else if(!$this->interactive && !$check)
		{
			echo "Creating data directory...";
			mkdir($this->data_path, 0777, true);
			echo "\t\t\t\t\t\t\t\t\t\t\tDone!\n";
		}
	}

	/**
	 * Check data directory permissions
	 */
	protected function checkDataDirPermissions()
	{
		$check = $this->checker->dataDirectoryPermissions($this->data_path);
		if($this->interactive && !$check)
		{
			echo "Not enough permissions on data directory. Set permissions (yes|no)? ";
			$line = getUserInput();
			if(strtolower($line) != "yes") {
				echo "Aborted by user.";
				exit(1);
			}

			echo "Setting permission to required...";
			chmod($this->data_path, 0777);
			echo "\t\t\t\t\t\tDone!\n";
		}
		else if(!$this->interactive && !$check)
		{
			echo "Setting permission to required...";
			chmod($this->data_path, 0777);
			echo "\t\t\t\t\t\tDone!\n";
		}
	}

	/**
	 * Check whether the data directory is empty
	 */
	protected function checkDataDirEmpty()
	{
		$check = $this->checker->dataDirectoryEmpty($this->data_path, $this->client_id, $this->web_dir);
		if($this->interactive && !$check)
		{
			echo "Data directory is not empty. Clean the directory (yes|no)? ";
			$line = getUserInput();
			if(strtolower($line) != "yes") {
				echo "Aborted by user.";
				exit(1);
			}

			echo "Cleaning the directory ".$this->data_path."/".$this->client_id."...";
			clearDirectory($this->data_path."/".$this->client_id);
			echo "\t\t\t\t\t\tDone!\n";
		}
	}

	/**
	 * Create log directory
	 */
	protected function createLogDir()
	{
		$check = $this->checker->logDirectoryExists($this->general_config->log()->path());
		if($this->interactive && !$check)
		{
			echo "Log directory does not exist. Create the directory (yes|no)? ";
			$line = getUserInput();
			if(strtolower($line) != "yes") {
				echo "Aborted by user.";
				exit(1);
			}

			echo "Creating log directory...";
			mkdir($this->gc->log()->path(), 0777, true);
			echo "\t\t\t\t\t\t\t\t\t\t\tDone!\n";
		}
		else if(!$this->interactive && !$check)
		{
			echo "Creating log directory...";
			mkdir($this->gc->log()->path(), 0777, true);
			echo "\t\t\t\t\t\t\t\t\t\t\tDone!\n";
		}
	}

	/**
	 * Create log file
	 */
	protected function createLogFile()
	{
		if(!$this->checker->logFileExists($this->gc->log()->path(), $this->gc->log()->fileName()))
		{
			touch($this->gc->log()->path()."/".$this->gc->log()->fileName());
			chmod($this->gc->log()->path()."/".$this->gc->log()->fileName(), 0777);
		}
	}

	/**
	 * Check for valid php version
	 */
	protected function checkPHPVersion()
	{
		if(!$this->checker->validPHPVersion(phpversion(), "5.4"))
		{
			echo "Your PHP Version is too old. Please update to 5.4 or higher.\n";
			exit(1);
		}
	}

	/**
	 * Check for installed PDO
	 */
	protected function checkPDO()
	{
		if(!$this->checker->pdoExist())
		{
			echo "PDO is not installed.\n";
			exit(1);
		}
	}

	/**
	 * Check db connection
	 */
	protected function checkDBConnection()
	{
		if(!$this->checker->databaseConnectable($this->gc->database()->host(), $this->gc->database()->user(), $this->gc->database()->password()))
		{
			echo "It's not possible to connect a MySQL database.\n";
			echo "Please ensure you have a MySQL database installed or started.\n";
			exit(1);
		}
	}

	/**
	 * Check for valid php version for ILIAS branch
	 */
	protected function validPhpForIliasBranch()
	{
		if(!$this->checker->phpVersionILIASBranchCompatible(phpversion(), $this->git_branch_name))
		{
			echo "Your PHP Version (".phpversion().") is not compatible to the selected branch (".$this->git_branch_name.").";
			exit(1);
		}
	}

	/**
	 * Clone ILIAS
	 */
	protected function cloneILIAS()
	{
		try {
			echo "Clone repository from ".$this->git_url;
			echo " (This could take a few minutes)...";
			$git->cloneGitTo($this->git_url, $this->git_branch_name, $this->absolute_path);
			echo "\t\t\tDone!\n";
		} catch(\RuntimeException $e) {
			echo $e->getMessage();
			exit(1);
		}
	}

	/**
	 * Get user input from cli
	 */
	protected function getUserInput()
	{
		$handle = fopen ("php://stdin","r");
		$line = fgets($handle);
		return trim($line);
	}

	/**
	 * Remove all stuff in the named dir recursiv
	 *
	 * @param string 		$dir
	 */
	protected function clearDirectory($dir)
	{
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? clearDirectory("$dir/$file") : unlink("$dir/$file");
		}

		rmdir($dir);
	}
}