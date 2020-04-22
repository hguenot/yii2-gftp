<?php

namespace gftp\drivers;

use gftp\FtpException;
use gftp\FtpFile;

/**
 * Base interface for remote communication on FTP like severs.
 *
 * @author herve
 */
interface RemoteDriver {
	const ASCII = FTP_ASCII;
	const BINARY = FTP_BINARY;

	/**
	 * Connect to FTP server.
	 *
	 * @throws FtpException If connection failed.
	 */
	public function connect(): void;

	/**
	 * Close FTP connection.
	 *
	 * @throws FtpException Raised when error occurred when closing FTP connection.
	 */
	public function close(): void;

	/**
	 * Log into the FTP server. If connection is not opened, it will be opened before login.
	 *
	 * @throws FtpException If connection failed.
	 */
	public function login(): void;

	/**
	 * Returns list of files in the given directory.
	 *
	 * @param string $dir The directory to be listed.
	 *                    This parameter can also include arguments, eg. $ftp->ls("-la /your/dir");
	 *                    Note that this parameter isn't escaped so there may be some issues with filenames containing spaces and other characters.
	 * @param bool $full List full dir description.
	 * @param bool $recursive List folder recursively
	 *
	 * @return FtpFile[] Array containing list of files.
	 *
	 * @throws FtpException If remote connection failed.
	 */
	public function ls(string $dir = '.', bool $full = false, bool $recursive = false): array;

	/**
	 * Create a new folder on FTP server.
	 *
	 * @param string $dir Folder to create on server (relative or absolute path).
	 *
	 * @throws FtpException If folder creation failed.
	 */
	public function mkdir(string $dir): void;

	/**
	 * Removes a folder on FTP server.
	 *
	 * @param string $dir Folder to delete from server (relative or absolute path).
	 *
	 * @throws FtpException If folder deletion failed.
	 */
	public function rmdir(string $dir): void;

	/**
	 * Changes current folder.
	 *
	 * @param string $dir Folder to move on (relative or absolute path).
	 *
	 * @return string Current folder on FTP server.
	 *
	 * @throws FtpException If changing folder failed.
	 */
	public function chdir(string $dir): string;

	/**
	 * Download a file from FTP server.
	 *
	 * @param string $remote_file The remote file path.
	 * @param resource|string $local_file The local file path. If set to <strong>null</strong>, file will be downloaded inside current folder using remote file base name).
	 * @param int $mode The transfer mode. Must be either <strong>ASCII</strong> or <strong>BINARY</strong>.
	 * @param bool $asynchronous Flag indicating if file transfer should block php application or not.
	 * @param callable $asyncFn Async callback function called during download process
	 *
	 * @throws FtpException If an error occurred during file transfer.
	 */
	public function get(
			string $remote_file,
			$local_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): void;

	/**
	 * Upload a file to the FTP server.
	 *
	 * @param string|resource $local_file The local file path.
	 * @param string $remote_file The remote file path. If set to <strong>null</strong>, file will be downloaded inside
	 *                            current folder using local file base name).
	 * @param int $mode The transfer mode. Must be either <strong>ASCII</strong> or <strong>BINARY</strong>.
	 * @param bool $asynchronous Flag indicating if file transfer should block php application or not.
	 * @param callable $asyncFn Async callback function called during upload process
	 *
	 * @throws FtpException If an error occurred during file transfer.
	 */
	public function put(
			$local_file,
			?string $remote_file = null,
			int $mode = FTP_ASCII,
			bool $asynchronous = false,
			callable $asyncFn = null): void;

	/**
	 * Test existence of file/folder on remote server.
	 *
	 * @param string $filename File or folder path to test existence.
	 *
	 * @return bool `true` if file exists, `false` otherwise.
	 *
	 * @throws FtpException If remote connection failed
	 */
	public function fileExists(string $filename): bool;

	/**
	 * Deletes specified files from FTP server.
	 *
	 * @param string $path The file to delete.
	 *
	 * @throws FtpException If file could not be deleted.
	 */
	public function delete(string $path): void;

	/**
	 * Retrieves the file size in bytes.
	 *
	 * @param string $path The file to delete.
	 *
	 * @return int File size.
	 *
	 * @throws FtpException If an error occurred while retrieving file size.
	 */
	public function size(string $path): int;

	/**
	 * Renames a file or a directory on the FTP server.
	 *
	 * @param string $oldName The old file/directory name.
	 * @param string $newName The new name.
	 *
	 * @throws FtpException If an error occurred while renaming file or folder.
	 */
	public function rename(string $oldName, string $newName): void;

	/**
	 * Returns the current directory name.
	 *
	 * @return string The current directory name.
	 *
	 * @throws FtpException If an error occurred while getting current folder name.
	 */
	public function pwd(): string;

	/**
	 * Set permissions on a file via FTP.
	 *
	 * @param string $file The remote file.
	 * @param string $mode The new permissions, given as an <strong>octal</strong> value.
	 *
	 * @throws FtpException If couldn't set file permission.
	 */
	public function chmod(string $mode, string $file): void;

	/**
	 * Gets the last modified time for a remote file.
	 *
	 * @param string $path The file from which to extract the last modification time.
	 *
	 * @return int The last modified time as a Unix timestamp on success.
	 *
	 * @throws FtpException If could not retrieve the last modification time of a file.
	 */
	public function mdtm(string $path): int;
}
