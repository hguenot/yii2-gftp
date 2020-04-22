<?php

namespace gftp\converter;

use gftp\FtpFile;

/**
 * Generic class used to parse file listing result.
 *
 * @author Herve Guenot
 * @link http://www.guenot.info
 * @copyright Copyright &copy; 2015 Herve Guenot
 * @license GNU LESSER GPL 3
 * @version 1.0
 */
interface FtpFileListConverter {
	/**
	 * Parse string array (output for ftp_rawlist) an convert each element in FtpFile.
	 *
	 * @param mixed[] $fullList Array of raw values returns by the remote driver.
	 *
	 * @return FtpFile[] Converted file list.
	 */
	public function parse(array $fullList): array;
}


