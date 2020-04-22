<?php

namespace gftp\drivers;

use gftp\converter\FtpFileListConverter;
use gftp\converter\FtpUnixFileListConverter;
use gftp\converter\FtpWindowsFileListConverter;
use gftp\converter\SimpleFileListConverter;
use gftp\FtpException;
use gftp\FtpUtils;
use Yii;
use yii\base\BaseObject;
use function set_error_handler;

/**
 * Basic FTP connection driver.
 *
 * @property string $host
 * @property int $port
 * @property int $user
 * @property-write string|null $password
 * @property FtpFileListConverter $fileListConverter
 * @property bool $passive
 * @property int $timeout
 */
class FtpDriver extends BaseObject implements RemoteDriver {
	/**
	 * @var mixed FTP handle.
	 */
	protected $_handle;
	/**
	 * @var string FTP hostname.
	 */
	private $_host = 'localhost';
	/**
	 * @var int FTP port.
	 */
	private $_port = 21;
	/**
	 * @var string FTP username.
	 */
	private $_user = 'anonymous';
	/**
	 * @var string|null FTP password.
	 */
	private $_pass = '';
	/**
	 * @var FtpFileListConverter Converts string array in FtpFile array.
	 */
	private $_fileListConverter;
	/**
	 * @var integer Connection timeout in seconds.
	 */
	private $_timeout = 30;
	/**
	 * @var bool Connect in passive mode
	 */
	private $_passive = true;
	/**
	 * @var mixed Used for passing data to error handling function.
	 */
	private $_param = '';

	/**
	 * @inheritDoc
	 */
	public function init() {
		parent::init();

		self::registerErrorHandler();
		FtpUtils::registerTranslation();
	}

	/**
	 * Changing FTP host name or IP
	 *
	 * @param string $host New hostname
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setHost(string $host): void {
		// Close connection before changing host.
		if ($this->_host !== $host) {
			$this->close();
			$this->_host = $host;
		}
	}

	/**
	 * @return string The current FTP host.
	 */
	public function getHost(): string {
		return $this->_host;
	}

	/**
	 * Changing FTP port.
	 *
	 * @param integer $port New hostname
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setPort(int $port): void {
		// Close connection before changing port.
		if ($this->_port !== $port) {
			$this->close();
			$this->_port = $port;
		}
	}

	/**
	 * @return integer The current FTP port.
	 */
	public function getPort(): int {
		return $this->_port;
	}

	/**
	 * Changing FTP connecting username.
	 *
	 * @param string $user New username
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setUser(string $user): void {
		// Close connection before changing username.
		if ($this->_user !== $user) {
			$this->close();
			$this->_user = $user;
		}
	}

	/**
	 * @return string The FTP connecting username.
	 */
	public function getUser(): string {
		return $this->_user;
	}

	/**
	 * Changing FTP password.
	 *
	 * @param string|null $pass New password
	 *
	 * @throws FtpException If closing current connection failed
	 */
	public function setPass(?string $pass): void {
		// Close connection before changing password.
		if ($this->_pass !== $pass) {
			$this->close();
			$this->_pass = $pass;
		}
	}

	/**
	 * Changing FTP passive mode.
	 *
	 * @param bool $passive Set passive mode
	 *
	 * @throws FtpException if passive mode could not be set.
	 */
	public function setPassive(bool $passive): void {
		// Close connection before changing password.
		if ($this->_passive !== $passive) {
			$this->_passive = $passive;
			if (isset($this->_handle) && $this->_handle != null) {
				$this->pasv($this->_passive);
			}
		}
	}

	/**
	 * @return bool FTP passive mode.
	 */
	public function getPassive(): bool {
		return $this->_passive;
	}

	/**
	 * Changing connection timeout in seconds.
	 *
	 * @param integer $timeout Set passive mode
	 */
	public function setTimeout(int $timeout): void {
		$this->_timeout = $timeout;
	}

	/**
	 * @return integer FTP connection timeout.
	 */
	public function getTimeout(): int {
		return $this->_timeout;
	}

	/**
	 * Returns the file list converter used to convert full file list (string array) in FtpFile array.
	 *
	 * @return FtpFileListConverter The current file list converter
	 *
	 * @see Ftp::ls
	 */
	public function getFileListConverter(): FtpFileListConverter {
		return $this->_fileListConverter;
	}

	/**
	 * Change the current file list converter.
	 *
	 * @param FtpFileListConverter $fileListConverter the new file list converter.
	 */
	public function setFileListConverter(FtpFileListConverter $fileListConverter): void {
		$this->_fileListConverter = $fileListConverter;
	}

	/**
	 * @inheritDoc
	 */
	public function connect(): void {
		if (isset($this->_handle) && $this->_handle != null) {
			$this->close();
		}
		$this->_handle = ftp_connect($this->_host, $this->_port, $this->_timeout);
		if ($this->_handle === false) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not connect to FTP server "{host}" on port "{port}"',
							[
									'host' => $this->_host,
									'port' => $this->_port
							])
			);
		} else {
			if (strtolower($this->systype()) == 'unix') {
				$this->setFileListConverter(new FtpUnixFileListConverter());
			} else {
				$this->setFileListConverter(new FtpWindowsFileListConverter());
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function login(): void {
		$this->connectIfNeeded(false);
		if (ftp_login($this->_handle, $this->_user, $this->_pass) === false) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not login to FTP server "{host}" on port "{port}" with user "{user}"',
							[
									'host' => $this->_host,
									'port' => $this->_port,
									'user' => $this->_user
							])
			);
		} else {
			if ($this->_passive) {
				try {
					$this->pasv($this->_passive);
				} catch (FtpException $e) {

				}
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function close(): void {
		if (isset($this->_handle) && $this->_handle != null) {
			if (!ftp_close($this->_handle)) {
				throw new FtpException(
						Yii::t('gftp',
								'Could not close connection to FTP server "{host}" on port "{port}"',
								[
										'host' => $this->_host,
										'port' => $this->_port
								])
				);
			} else {
				$this->_handle = false;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function ls(string $dir = '.', bool $full = false, bool $recursive = false): array {
		$this->connectIfNeeded();
		$this->_param = $dir;
		$fileListConverter = $this->_fileListConverter;

		if (!$full) {
			$opts = $recursive
					? "-R "
					: "";
			$res = ftp_nlist($this->_handle, $opts . $dir);
			$fileListConverter = new SimpleFileListConverter();
		} else {
			$res = ftp_rawlist($this->_handle, $dir, $recursive);
		}

		if ($res === false) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not read folder "{folder}" on server "{host}"',
							[
									'host' => $this->_host,
									'folder' => $dir
							])
			);
		}

		return $fileListConverter->parse($res);
	}

	/**
	 * @inheritDoc
	 */
	public function pwd(): string {
		$this->connectIfNeeded();

		$dir = ftp_pwd($this->_handle);
		if ($dir === false) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not get current folder on server "{host}"',
							[
									'host' => $this->_host
							])
			);
		}

		return $dir;
	}

	/**
	 * @inheritDoc
	 */
	public function chdir(string $dir): string {
		$this->connectIfNeeded();
		$this->_param = $dir;

		if (!ftp_chdir($this->_handle, $dir)) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not go to "{folder}" on server "{host}"',
							[
									'host' => $this->_host,
									'folder' => $dir
							])
			);
		}

		try {
			$path = $this->pwd();
		} catch (FtpException $ex) {
			$path = $dir;
		}
		return $path;
	}

	/**
	 * @inheritDoc
	 */
	public function mkdir(string $dir): void {
		$this->connectIfNeeded();
		$this->_param = $dir;

		if (!ftp_mkdir($this->_handle, $dir)) {
			throw new FtpException(
					Yii::t('gftp',
							'An error occured while creating folder "{folder}" on server "{host}"',
							[
									'host' => $this->_host,
									'folder' => $dir
							])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function rmdir(string $dir): void {
		$this->connectIfNeeded();
		$this->_param = $dir;

		if (!ftp_rmdir($this->_handle, $dir)) {
			throw new FtpException(
					Yii::t('gftp',
							'An error occured while removing folder "{folder}" on server "{host}"',
							[
									'host' => $this->_host,
									'folder' => $dir
							])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function chmod(string $mode, string $file): void {
		$this->connectIfNeeded();
		if (substr($mode, 0, 1) != '0') {
			$mode = (int)(octdec(str_pad($mode, 4, '0', STR_PAD_LEFT)));
		}

		$this->_param = ['mode' => $mode, 'file' => $file];

		if (!ftp_chmod($this->_handle, $mode, $file)) {
			throw new FtpException(
					Yii::t('gftp',
							'Could change mode (to "{mode}") of file "{file}" on server "{host}"',
							[
									'host' => $this->_host,
									'file' => $file,
									'{mode}' => $mode
							])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function fileExists(string $filename): bool {
		$this->connectIfNeeded();
		$mdtm = ftp_mdtm($this->_handle, $filename);
		// ftp_mdtm() does not work with directories.
		if ($mdtm >= 0) {
			return true;
		} else {
			// https://www.php.net/manual/zh/function.ftp-chdir.php
			$origin = ftp_pwd($this->_handle);
			if (@ftp_chdir($this->_handle, $filename)) {
				ftp_chdir($this->_handle, $origin);
				return true;
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $path): void {
		$this->connectIfNeeded();
		$this->_param = $path;

		if (!ftp_delete($this->_handle, $path)) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not delete file "{file}" on server "{host}"',
							[
									'host' => $this->_host,
									'file' => $path
							])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function get(
			string $remote_file,
			$local_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): void {
		$this->connectIfNeeded();
		$resourceMode = is_resource($local_file);

		if (!isset($local_file) || $local_file === null || !is_string($local_file) || trim($local_file) === '') {
			$local_file = getcwd() . DIRECTORY_SEPARATOR . basename($remote_file);
		}
		$this->_param = [
				'remote_file' => $remote_file,
				'local_file' => $local_file,
				'asynchronous' => $asynchronous];

		if ($asynchronous !== true) {
			/** @noinspection PhpParamsInspection */
			$received = $resourceMode
					? ftp_fget($this->_handle, $local_file, $remote_file, $mode)
					: ftp_get($this->_handle, $local_file, $remote_file, $mode);
			if (!$received) {
				throw new FtpException(
						Yii::t('gftp',
								'Could not synchronously get file "{remote_file}" from server "{host}"',
								[
										'host' => $this->_host,
										'remote_file' => $remote_file
								])
				);
			}
		} else {
			/** @noinspection PhpParamsInspection */
			$ret = $resourceMode
					? ftp_nb_fget($this->_handle, $local_file, $remote_file, $mode)
					: ftp_nb_get($this->_handle, $local_file, $remote_file, $mode);
			$asyncFn = $asyncFn
					? $asyncFn
					: function () {
					};

			while ($ret == FTP_MOREDATA) {
				$asyncFn();

				// continue downloading
				$ret = ftp_nb_continue($this->_handle);
			}
			if ($ret !== FTP_FINISHED) {
				throw new FtpException(
						Yii::t('gftp',
								'Could not asynchronously get file "{remote_file}" from server "{host}"',
								[
										'host' => $this->_host,
										'remote_file' => $remote_file
								])
				);
			}
			$asyncFn();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function put(
			$local_file,
			?string $remote_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): void {
		$this->connectIfNeeded();
		$resourceMode = is_resource($local_file);
		$hasRemoteFile = isset($remote_file) && $remote_file !== null && is_string($remote_file)
				&& trim($remote_file) !== "";

		if ($resourceMode && !$hasRemoteFile) {
			throw new FtpException(Yii::t('gftp', 'You must specify remote filename if source is resource'));
		} else {
			if (!$hasRemoteFile) {
				$remote_file = basename($local_file);
			}
		}
		$this->_param = [
				'remote_file' => $remote_file,
				'local_file' => $local_file,
				'asynchronous' => $asynchronous];

		if ($asynchronous !== true) {
			$sent = $resourceMode
					? ftp_fput($this->_handle, $remote_file, $local_file, $mode)
					: ftp_put($this->_handle, $remote_file, $local_file, $mode);
			if (!$sent) {
				throw new FtpException(
						Yii::t('gftp',
								'Could not put file "{local_file}" on "{remote_file}" on server "{host}"',
								[
										'host' => $this->_host,
										'remote_file' => $remote_file,
										'local_file' => $local_file
								])
				);
			}
		} else {
			$ret = $resourceMode
					? ftp_nb_fput($this->_handle, $remote_file, $local_file, $mode)
					: ftp_nb_put($this->_handle, $remote_file, $local_file, $mode);
			$asyncFn = $asyncFn
					? $asyncFn
					: function () {
					};

			while ($ret == FTP_MOREDATA) {
				$asyncFn();
				$ret = ftp_nb_continue($this->_handle);
			}

			if ($ret !== FTP_FINISHED) {
				throw new FtpException(
						Yii::t('gftp',
								'Could not put file "{local_file}" on "{remote_file}" on server "{host}"',
								[
										'host' => $this->_host,
										'remote_file' => $remote_file,
										'local_file' => $local_file
								])
				);
			}
			$asyncFn();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function rename(string $oldName, string $newName): void {
		$this->connectIfNeeded();
		$this->_param = ['oldname' => $oldName, 'newname' => $newName];

		if (!ftp_rename($this->_handle, $oldName, $newName)) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not rename file "{oldname}" to "{newname}" on server "{host}"',
							[
									'host' => $this->_host,
									'oldname' => $oldName,
									'newname' => $newName
							])
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function mdtm(string $path): int {
		$this->connectIfNeeded();
		$this->_param = $path;

		$res = ftp_mdtm($this->_handle, $path);
		if ($res < 0) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not get modification time of file "{file}" on server "{host}"',
							[
									'host' => $this->_host,
									'file' => $path
							])
			);
		}

		return $res;
	}

	/**
	 * @inheritDoc
	 */
	public function size(string $path): int {
		$this->connectIfNeeded();
		$this->_param = $path;

		$res = ftp_size($this->_handle, $path);
		if ($res < 0) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not get size of file "{file}" on server "{host}"',
							[
									'host' => $this->_host,
									'file' => $path
							])
			);
		}

		return $res;
	}

	/**
	 * Returns the remote system type.
	 *
	 * @return string The remote system type
	 *
	 * @throws FtpException If remote connection failed
	 */
	public function systype(): string {
		$this->connectIfNeeded();
		$res = @ftp_systype($this->_handle);
		return $res == null || $res == false
				? 'UNIX'
				: $res;
	}

	/**
	 * Turns on or off passive mode.
	 *
	 * @param bool $passive If <strong>TRUE</strong>, the passive mode is turned on, else it's turned off.
	 *
	 * @throws FtpException If remote connection failed
	 */
	public function pasv(bool $passive): void {
		$this->connectIfNeeded();
		$this->_param = $passive;

		if (!ftp_pasv($this->_handle, $passive === true)) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not {set} passive mode on server "{host}": {message}',
							[
									'host' => $this->_host,
									'set' => $passive
											? "set"
											: "unset"
							])
			);
		}
	}

	/**
	 * Execute any command on FTP server.
	 *
	 * @param string $command FTP command.
	 * @param bool $raw Do not parse command to determine if it is a <i>SITE</i> or <i>SITE EXEC</i> command.
	 *
	 * @returns bool|string[] Depending on command : SITE and SITE EXEC command will returns <strong>TRUE</strong>;
	 *         other command will returns an array. If <strong>$raw</strong> is set to <strong>TRUE</strong>, it always
	 *         return an array.
	 *
	 * @return bool|string[]
	 * @throws FtpException If command execution fails.
	 *
	 * @see Ftp::exec Used to execute a <i>SITE EXEC</i> command
	 * @see Ftp::site Used to execute a <i>SITE</i> command
	 * @see Ftp::raw  Used to execute any other command (or if $raw is set to <strong>TRUE</strong>)
	 */
	public function execute(string $command, bool $raw = false) {
		$this->connectIfNeeded();
		$this->_param = $command;

		if (!$raw && substr($command, 0, 10) == 'SITE EXEC ') {
			$this->exec(substr($command, 10));
			return true;
		} else {
			if (!$raw && substr($command, 0, 5) == 'SITE ') {
				$this->site(substr($command, 5));
				return true;
			} else {
				return $this->raw($command);
			}
		}
	}

	/**
	 * Sends a SITE EXEC command request to the FTP server.
	 *
	 * @param string $command FTP command (does not include <i>SITE EXEC</i> words).
	 *
	 * @throws FtpException If command execution fails.
	 */
	public function exec(string $command): void {
		$this->connectIfNeeded();
		$this->_param = 'SITE EXEC ' . $command;

		if (!ftp_exec($this->_handle, $command)) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not execute command "{command}" on "{host}"',
							[
									'host' => $this->_host,
									'{command}' => $this->_param
							])
			);
		}
	}

	/**
	 * Sends a SITE command request to the FTP server.
	 *
	 * @param string $command FTP command (does not include <strong>SITE</strong> word).
	 *
	 * @throws FtpException If command execution fails.
	 */
	public function site(string $command): void {
		$this->connectIfNeeded();
		$this->_param = 'SITE ' . $command;

		if (!ftp_site($this->_handle, $command)) {
			throw new FtpException(
					Yii::t('gftp',
							'Could not execute command "{command}" on "{host}"',
							[
									'host' => $this->_host,
									'{command}' => $this->_param
							])
			);
		}
	}

	/**
	 * Sends an arbitrary command to the FTP server.
	 *
	 * @param string $command FTP command to execute.
	 *
	 * @return string[] The server's response as an array of strings. No parsing is performed on the response string
	 *         and not determine if the command succeeded.
	 *
	 * @throws FtpException If command execution fails.
	 */
	public function raw(string $command): array {
		$this->connectIfNeeded();
		$this->_param = $command;

		return ftp_raw($this->_handle, $command);
	}

	/**
	 * Connects and log in to FTP server if not already login.
	 * Call to {link GFTp::connect} and {@link GTP::login} is not mandatory.
	 * Must be called in each method, before executing FTP command.
	 *
	 * @param bool $login Flag indicating if login will be done.
	 *
	 * @throws FtpException if connection of login onto FTP server failed.
	 * @see GFTp::login
	 *
	 * @see GFTp::connect
	 */
	protected function connectIfNeeded(bool $login = true): void {
		if (!isset($this->_handle) || $this->_handle == null) {
			$this->connect();

			if ($login && $this->_user != null && $this->_user != "") {
				$this->login();
			}
		}
	}

	/**
	 * Handles FTP error (ftp_** functions sometimes use PHP error instead of methofr return).
	 * It throws FtpException when ftp_** error is found.
	 *
	 * @param string $function FTP function name
	 * @param string $message Error message
	 *
	 * @return FtpException if PHP error on ftp_*** method is found, null otherwise.
	 */
	private function createException(string $function, string $message): FtpException {
		if ($function == 'ftp_connect()' || $function == 'ftp_ssl_connect()') {
			$this->_handle = false;
			return new FtpException(
					Yii::t('gftp',
							'Could not connect to FTP server "{host}" on port "{port}": {message}',
							[
									'host' => $this->_host,
									'port' => $this->_port,
									'message' => $message
							])
			);
		} else if ($function == 'ftp_close()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not close connection to FTP server "{host}" on port "{port}": {message}',
							[
									'host' => $this->_host,
									'port' => $this->_port,
									'message' => $message
							])
			);
		} else if ($function == 'ftp_nlist()' || $function == 'ftp_rawlist()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not read folder "{folder}" on server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'folder' => $this->_param
							])
			);
		} else if ($function == 'ftp_mkdir()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not create folder "{folder}" on "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'folder' => $this->_param
							])
			);
		} else if ($function == 'ftp_rmdir()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not remove folder "{folder}" on "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'folder' => $this->_param
							])
			);
		} else if ($function == 'ftp_cdup()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not move to parent directory on "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'folder' => $this->_param
							])
			);
		} else if ($function == 'ftp_chdir()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not move to folder "{folder}" on "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'folder' => $this->_param
							])
			);
		} else if ($function == 'ftp_pwd()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not get current folder on server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message
							])
			);
		} else if ($function == 'ftp_chmod()') {
			return new FtpException(
					Yii::t('gftp',
							'Could change mode (to "{mode}") of file "{file}" on server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'file' => $this->_param['file'],
									'mode' => $this->_param['mode']
							])
			);
		} else if ($function == 'ftp_put()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not put file "{local_file}" on "{remote_file}" on server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'remote_file' => $this->_param['remote_file'],
									'local_file' => $this->_param['local_file']
							])
			);
		} else if ($function == 'ftp_get()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not synchronously get file "{remote_file}" from server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'remote_file' => $this->_param['remote_file']
							])
			);
		} else if ($function == 'ftp_size()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not get size of file "{file}" on server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'file' => $this->_param
							])
			);
		} else if ($function == 'ftp_nb_get()'
				|| $function == 'ftp_nb_continue()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not asynchronously get file "{remote_file}" from server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'remote_file' => $this->_param['remote_file']
							])
			);
		} else if ($function == 'ftp_rename()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not rename file "{oldname}" to "{newname}" on server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'oldname' => $this->_param['oldname'],
									'newname' => $this->_param['newname']
							])
			);
		} else if ($function == 'ftp_delete()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not delete file "{file}" on server "{host}" : {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'file' => $this->_param
							])
			);
		} else if ($function == 'ftp_pasv()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not {set} passive mode on server "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'set' => $this->_param
											? "set"
											: "unset"
							])
			);
		} else if ($function == 'ftp_mdtm()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not get modification time of file "{file}" on server "{host}"',
							[
									'host' => $this->_host,
									'message' => $message,
									'file' => $this->_param
							])
			);
		} else if ($function == 'ftp_exec()' || $function == 'ftp_raw()' || $function == 'ftp_site()') {
			return new FtpException(
					Yii::t('gftp',
							'Could not execute command "{command}" on "{host}": {message}',
							[
									'host' => $this->_host,
									'message' => $message,
									'command' => $this->_param
							])
			);
		}

		return null;
	}

	private static $errorHandlerRegistered = false;

	private static function registerErrorHandler() {
		if (!self::$errorHandlerRegistered && YII_ENABLE_ERROR_HANDLER) {
			set_error_handler(
					function ($code, $message, $file, $line, $context) {
						if (isset($context['this']) && $context['this'] instanceof FtpDriver) {
							/** @var FtpDriver $driver */
							$driver = $context['this'];
							// disable error capturing to avoid recursive errors
							restore_error_handler();
							restore_exception_handler();
							if (isset($message)) {
								// FTP error message are formed : ftp_***(): <message>
								$messages = explode(':', $message, 2);
								$func = explode(' ', $messages[0], 2);
								$ex = $driver->createException($func[0], $messages[1]);
								if ($ex != null) {
									throw $ex;
								}
							}
						}

						if (isset (Yii::$app) && isset(Yii::$app->errorHandler)) {
							Yii::$app->errorHandler->handleError($code, $message, $file, $line);
						}
					},
					error_reporting());
			self::$errorHandlerRegistered = true;
		}
	}
}
