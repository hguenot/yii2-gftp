<?php

namespace gftp\converter;

use gftp\FtpFile;
use yii\base\Component;

/**
 * Description of SftpFileListConverter
 */
class SimpleFileListConverter extends Component implements FtpFileListConverter {

	/**
	 * @inheritDoc
	 */
	public function parse(array $files): array {
		
		$ftpFiles = [];
		
		foreach ($files as $file) {
			$ftpFiles[] = new FtpFile([
				'filename' => $file
			]);
		}
		
		return $ftpFiles;
	}
	
}
