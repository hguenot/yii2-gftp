<?php

namespace gftp;

use Exception;
use gftp\drivers\RemoteDriver;
use Yii;
use yii\base\Component;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Component used to manage FTP connection
 *
 * @author Herve Guenot
 * @link http://www.guenot.info
 * @copyright Copyright &copy; 2012 Herve Guenot
 * @license GNU LESSER GPL 3
 * @version 1.0
 */
class FtpComponent extends Component {
	/**
	 * @var RemoteDriver FTP handle.
	 */
	private $handle;
	/**
	 * @var array Driver options.
	 */
	private $driverOptions = [];
	/**
	 * @var string Connection string
	 */
	private $connectionString = null;

	/**
	 * @throws FtpException If connection string is set but not valid.
	 */
	public function init(): void {
		parent::init();

		FtpUtils::registerTranslation();

		if ($this->connectionString != null) {
			$this->setConnectionString($this->connectionString);
		}
		$this->parseConnectionString();
	}

	/**
	 * Destructor. Try to close FTP connection.
	 */
	public function __destruct() {
		try {
			$this->close();
		} catch (Exception $ex) {
			// silently close...
		}
	}

	/**
	 * Sets a new connection string. If connection is already openned, try to close it before.
	 *
	 * @param string $connectionString FTP connection string (like ftp://[<user>[:<pass>]@]<host>[:<port>])
	 *
	 * @throws FtpException if <i>connectionString</i> is not valid or if could not close an already openned connection.
	 */
	public function setConnectionString(?string $connectionString): void {
		if (!isset($connectionString) || !is_string($connectionString) || trim($connectionString) === "") {
			throw new FtpException(
					Yii::t('gftp',
							'{connectString} is not a valid connection string',
							[
									'connectString' => $connectionString
							])
			);
		}

		$this->close();
		$this->connectionString = $connectionString;
	}

	/**
	 * @throws FtpException if <i>connectionString</i> is not valid
	 */
	private function parseConnectionString(): void {
		if (isset($this->connectionString) && is_string($this->connectionString)
				&& trim($this->connectionString) !== "") {
			try {
				$p = new FtpConnectionParser();
				$parts = $p->parse($this->connectionString);
			} catch (Exception $e) {
				throw new FtpException(
						Yii::t('gftp',
								'{connectString} is not a valid connection string: {message}',
								[
										'connectString' => $this->connectionString,
										'message' => $e->getMessage()
								])
				);
			}

			$this->close();
			$this->driverOptions = array_merge($this->driverOptions, $parts);
		}
	}

	/**
	 * Returns the connection string with or without password.
	 *
	 * @return string Connection string.
	 */
	public function getConnectionString(): string {
		return $this->connectionString;
	}

	/**
	 * Sets the driver options as an array.
	 * It must define a 'class' key representing the driver class name.
	 *
	 * @param array $driverOptions Driver connection options.
	 */
	public function setDriverOptions(array $driverOptions): void {
		$this->driverOptions = $driverOptions;
	}

	/**
	 * @return array The driver options.
	 */
	public function getDriverOptions(): array {
		return $this->driverOptions;
	}

	// *************************************************************************
	// UTILITY METHODS
	// *************************************************************************
	/**
	 * Connects and log in to FTP server if not already login.
	 * Call to {link GFTp::connect} and {@link GTP::login} is not mandatory.
	 * Must be called in each method, before executing FTP command.
	 *
	 * @param bool $login Flag indicating if login will be done.
	 *
	 * @throws FtpException If connection of login onto FTP server failed.
	 * @throws InvalidConfigException If configuration is not valid
	 *
	 * @see GFTp::connect
	 * @see GFTp::login
	 */
	protected function connectIfNeeded(bool $login = true): void {
		if (!isset($this->handle) || $this->handle == null) {
			$this->connect();
			if ($login) {
				$this->login();
			}
		}
	}

	// *************************************************************************
	// REMOTE WRAPPER METHODS
	// *************************************************************************
	/**
	 * Connect to FTP server.
	 *
	 * @throws FtpException If connection failed.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function connect(): void {
		if (isset($this->handle) && $this->handle != null) {
			$this->close();
		}

		$this->parseConnectionString();
		$this->handle = Yii::createObject($this->driverOptions);
		$this->handle->connect();
		$this->onConnectionOpen(new Event(['sender' => $this]));
	}

	/**
	 * Log into the FTP server. If connection is not openned, it will be openned before login.
	 *
	 * @throws FtpException If connection failed.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function login(): void {
		$this->connectIfNeeded(false);
		$this->handle->login();
		$this->onLogin(new Event(['sender' => $this, 'data' => $this->handle->user]));
	}

	/**
	 * Returns list of files in the given directory.
	 *
	 * @param string $dir The directory to be listed.
	 *                                 This parameter can also include arguments, eg. $ftp->ls("-la /your/dir");
	 *                                 Note that this parameter isn't escaped so there may be some issues with filenames containing spaces and other characters.
	 * @param bool $full List full dir description.
	 * @param bool $recursive Recursively list folder content
	 *
	 * @return FtpFile[] Array containing list of files.
	 *
	 * @throws FtpException If an FTP error occurred.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function ls(string $dir = ".", bool $full = false, bool $recursive = false): array {
		$this->connectIfNeeded();
		return $this->handle->ls($dir, $full, $recursive);
	}

	/**
	 * Close FTP connection.
	 *
	 * @throws FtpException Raised when error occurred when closing FTP connection.
	 */
	public function close(): void {
		if (isset($this->handle) && $this->handle != null) {
			$this->handle->close();
			$this->handle = false;
			$this->onConnectionClose(new Event(['sender' => $this]));
		}
	}

	/**
	 * Create a new folder on FTP server.
	 *
	 * @param string $dir Folder to create on server (relative or absolute path).
	 *
	 * @throws FtpException If folder creation failed.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function mkdir(string $dir): void {
		$this->connectIfNeeded();
		$this->handle->mkdir($dir);
		$this->onFolderCreated(new Event(['sender' => $this, 'data' => $dir]));
	}

	/**
	 * Removes a folder on FTP server.
	 *
	 * @param string $dir Folder to delete from server (relative or absolute path).
	 *
	 * @throws FtpException If folder deletion failed.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function rmdir(string $dir): void {
		$this->connectIfNeeded();
		$this->handle->rmdir($dir);
		$this->onFolderDeleted(new Event(['sender' => $this, 'data' => $dir]));
	}

	/**
	 * Changes current folder.
	 *
	 * @param string $dir Folder to move on (relative or absolute path).
	 *
	 * @return string Current folder on FTP server.
	 *
	 * @throws FtpException If folder deletion failed.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function chdir(string $dir): string {
		$this->connectIfNeeded();
		$this->handle->chdir($dir);
		$this->onFolderChanged(new Event(['sender' => $this, 'data' => $dir]));
		try {
			$cwd = $this->pwd();
		} catch (FtpException $ex) {
			$cwd = $dir;
		}
		return $cwd;
	}

	/**
	 * Download a file from FTP server.
	 *
	 * @param string $remote_file The remote file path.
	 * @param string|resource $local_file The local file path. If set to <strong>null</strong>, file will be downloaded inside current folder using remote file base name).
	 * @param int $mode The transfer² mode. Must be either <strong>FTP_ASCII</strong> or <strong>FTP_BINARY</strong>.
	 * @param bool $asynchronous Flag indicating if file transfer should block php application or not.
	 * @param callable $asyncFn Async callback function called during download process
	 *
	 * @return string The full local path (absolute).
	 *
	 * @throws FtpException If an FTP error occurred.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function get(
			string $remote_file,
			$local_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): string {
		$this->connectIfNeeded();
		$local_file = $this->handle->get($remote_file, $local_file, $mode, $asynchronous, $asyncFn);
		$this->onFileDownloaded(new Event(['sender' => $this, 'data' => $local_file]));
		return $local_file;
	}

	/**
	 * Upload a file to the FTP server.
	 *
	 * @param string|resource $local_file The local file path.
	 * @param string $remote_file The remote file path. If set to <strong>null</strong>, file will be downloaded inside current folder using local file base name).
	 * @param int $mode The transfer mode. Must be either <strong>FTP_ASCII</strong> or <strong>FTP_BINARY</strong>.
	 * @param bool $asynchronous Flag indicating if file transfer should block php application or not.
	 * @param callable $asyncFn Async callback function called during download process
	 *
	 * @return string The full local path (absolute).
	 *
	 * @throws FtpException If an error occurred during file transfer.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function put(
			$local_file,
			?string $remote_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): string {
		$this->connectIfNeeded();
		$full_remote_file = $this->handle->put($local_file, $remote_file, $mode, $asynchronous, $asyncFn);
		$this->onFileUploaded(new Event(['sender' => $this, 'data' => $remote_file]));
		return $full_remote_file;
	}

	/**
	 * Test existence of file/folder on remote server.
	 *
	 * @param string $filename File or folder path to test existence.
	 *
	 * @return bool `true` if file exists, `false` otherwise.
	 *
	 * @throws FtpException If an error occurred during file transfer.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function fileExists(string $filename): bool {
		$this->connectIfNeeded();
		return $this->handle->fileExists($filename);
	}

	/**
	 * Deletes specified files from FTP server.
	 *
	 * @param string $path The file to delete.
	 *
	 * @throws FtpException If file could not be deleted.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function delete(string $path): void {
		$this->connectIfNeeded();
		$this->handle->delete($path);
		$this->onFileDeleted(new Event(['sender' => $this, 'data' => $path]));
	}

	/**
	 * Retrieves the file size in bytes.
	 *
	 * @param string $path The file to delete.
	 *
	 * @return int File size.
	 *
	 * @throws FtpException If an error occurred while retrieving file size.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function size(string $path): int {
		$this->connectIfNeeded();
		return $this->handle->size($path);
	}

	/**
	 * Renames a file or a directory on the FTP server.
	 *
	 * @param string $oldname The old file/directory name.
	 * @param string $newname The new name.
	 *
	 * @throws FtpException If an error occurred while renaming file or folder.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function rename(string $oldname, string $newname): void {
		$this->connectIfNeeded();
		$this->handle->rename($oldname, $newname);
		$this->onFileRenamed(
				new Event([
						'sender' => $this,
						'data' => [
								'oldname' => $oldname,
								'newname' => $newname
						]])
		);
	}

	/**
	 * Returns the current directory name.
	 *
	 * @return string The current directory name.
	 *
	 * @throws FtpException If an error occurred while getting current folder name.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function pwd(): string {
		$this->connectIfNeeded();
		return $this->handle->pwd();
	}

	/**
	 * Set permissions on a file via FTP.
	 *
	 * @param string $mode The new permissions, given as an <strong>octal</strong> value.
	 * @param string $file The remote file.
	 *
	 * @throws FtpException If couldn't set file permission.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function chmod($mode, $file): void {
		$this->connectIfNeeded();
		$this->handle->chmod($mode, $file);
		$this->onFileModeChanged(
				new Event([
						'sender' => $this,
						'data' => [
								'mode' => $mode,
								'file' => $file
						]])
		);
	}

	/**
	 * Gets the last modified time for a remote file.
	 *
	 * @param string $path The file from which to extract the last modification time.
	 *
	 * @return string The last modified time as a Unix timestamp on success.
	 *
	 * @throws FtpException If could not retrieve the last modification time of a file.
	 * @throws InvalidConfigException If configuration is not valid.
	 */
	public function mdtm($path): string {
		$this->connectIfNeeded();
		return $this->handle->mdtm($path);
	}

	/* *********************************
	 * EVENTS SECTION
	 */
	/**
	 * Raised when connection to FTP server was opened.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onConnectionOpen($event): void {
		$this->trigger('onConnectionOpen', $event);
	}

	/**
	 * Raised when connection to FTP server was closed.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onConnectionClose($event): void {
		$this->trigger('onConnectionClose', $event);
	}

	/**
	 * Raised when users has logged in on the FTP server.
	 * Username is stored in : <code>$event->params</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onLogin($event): void {
		$this->trigger('onLogin', $event);
	}

	/**
	 * Raised when a folder was created on FTP server.
	 * Folder name is stored in : <code>$event->params</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFolderCreated($event): void {
		$this->trigger('onFolderCreated', $event);
	}

	/**
	 * Raised when a folder was deleted on FTP server.
	 * Folder name is stored in : <code>$event->params</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFolderDeleted($event): void {
		$this->trigger('onFolderDeleted', $event);
	}

	/**
	 * Raised when current FTP server directory has changed.
	 * New current folder is stored in : <code>$event->params</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFolderChanged($event): void {
		$this->trigger('onFolderChanged', $event);
	}

	/**
	 * Raised when a file was downloaded from FTP server.
	 *
	 * Local filename is stored in : <code>$event->params['local_file']</code>.
	 * Remote filename is stored in : <code>$event->params['remote_file']</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFileDownloaded($event): void {
		$this->trigger('onFileDownloaded', $event);
	}

	/**
	 * Raised when a file was uploaded to FTP server.
	 *
	 * Local filename is stored in : <code>$event->params['local_file']</code>.
	 * Remote filename is stored in : <code>$event->params['remote_file']</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFileUploaded($event): void {
		$this->trigger('onFileUploaded', $event);
	}

	/**
	 * Raised when file's permissions was changed on FTP server.
	 *
	 * Remote filename is stored in : <code>$event->params['file']</code>.
	 * New permisseion are stored in octal value in : <code>$event->params['mode']</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFileModeChanged($event): void {
		$this->trigger('onFileModeChanged', $event);
	}

	/**
	 * Raised when a file was deleted on FTP server.
	 * Remote filename is stored in : <code>$event->params</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFileDeleted($event): void {
		$this->trigger('onFileDeleted', $event);
	}

	/**
	 * Raised when a file or folder was renamed on FTP server.
	 * Old filename is stored in : <code>$event->params['oldname']</code>.
	 * New filename is stored in : <code>$event->params['newname']</code>.
	 *
	 * @param $event Event Event parameter.
	 */
	public function onFileRenamed($event): void {
		$this->trigger('onFileRenamed', $event);
	}
}
