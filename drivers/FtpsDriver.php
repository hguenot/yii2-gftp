<?php

namespace gftp\drivers;

use gftp\converter\FtpUnixFileListConverter;
use gftp\converter\FtpWindowsFileListConverter;
use gftp\FtpException;
use Yii;

/**
 * FTP over SSL connection driver.
 */
class FtpsDriver extends FtpDriver {
	/**
	 * @inheritDoc
	 */
	public function connect(): void {
		if (isset($this->_handle) && $this->_handle != null) {
			$this->close();
		}
		$this->_handle = ftp_ssl_connect($this->host, $this->port, $this->timeout);
		if ($this->_handle === false) {
			$this->_handle = false;
			throw new FtpException(
					Yii::t('gftp',
							'Could not connect to FTP server "{host}" on port "{port}" using SSL',
							[
									'host' => $this->host,
									'port' => $this->port
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
}
