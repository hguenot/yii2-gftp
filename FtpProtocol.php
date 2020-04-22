<?php

namespace gftp;

/**
 * Description of FtpProtocol
 *
 * @property-read string $protocol Protocol name.
 * @property-read string $driver Driver class name.
 * @property-read int $port Default port number.
 *
 * @author Herve Guenot
 * @link http://www.guenot.info
 * @copyright Copyright &copy; 2015 Herve Guenot
 * @license GNU LESSER GPL 3
 * @version 1.0
 *
 * @internal
 */
class FtpProtocol {
	/** @var FtpProtocol[] All known protocols. */
	private static $drivers = [];

	/**
	 * @param string $protocol Protocol name
	 * @param string $driver Driver class name
	 * @param int $port Default port
	 */
	public static function registerDriver(string $protocol, string $driver, int $port) {
		$key = strtolower($protocol);
		self::$drivers[$key] = new FtpProtocol($protocol, $driver, $port);
	}

	/**
	 * @return FtpProtocol[] All known protocols.
	 */
	public static function values(): array {
		return array_merge([], self::$drivers);
	}

	/**
	 * @param string $protocol Expected protocol name.
	 *
	 * @return FtpProtocol|null Found protocol or `null` if not exists
	 */
	public static function valueOf(string $protocol): ?FtpProtocol {
		$key = strtolower($protocol);
		return array_key_exists($key, self::$drivers) && isset(self::$drivers[$key])
				? self::$drivers[$key]
				: null;
	}

	/**
	 * @param string $protocol Protocol name
	 * @param string $driver Driver class name
	 * @param int $port Default port
	 */
	private function __construct(string $protocol, string $driver, int $port) {
		$this->_protocol = $protocol;
		$this->_driver = $driver;
		$this->_port = $port;
	}

	/** @var string Protocol name */
	private $_protocol;

	/** @var string Driver class name */
	private $_driver;

	/** @var int Default port */
	private $_port;

	/**
	 * @return string Protocol name.
	 */
	public function getProtocol(): string {
		return $this->_protocol;
	}

	/**
	 * @return string Driver class name.
	 */
	public function getDriver(): string {
		return $this->_driver;
	}

	/**
	 * @return int Default port
	 */
	public function getPort(): int {
		return $this->_port;
	}

	public function __get($name) {
		if ($name == 'protocol') {
			return $this->getProtocol();
		}
		if ($name == 'driver') {
			return $this->getDriver();
		}
		if ($name == 'port') {
			return $this->getPort();
		}
	}
}
