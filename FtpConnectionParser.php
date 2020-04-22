<?php

namespace gftp;

use Yii;
use yii\helpers\VarDumper;
use function GuzzleHttp\Psr7\parse_query;

/**
 * Parser for FTP connection string
 *
 * @author Herve Guenot
 * @link http://www.guenot.info
 * @copyright Copyright &copy; 2015 Herve Guenot
 * @license GNU LESSER GPL 3
 * @version 1.0
 *
 * @internal
 */
class FtpConnectionParser {
	/**
	 * Parses a connection string and returns an array containing connection information as describe below.
	 * Connection string is a valid URL (<scheme>://[<user>[:<pass>]@]<host>[:<port>][?<query string>] -
	 * parsable using `parse_url` and `parse_query` functions)
	 *
	 * array['class']    string Driver class name (default to 'FTP' protocol).
	 * array['host']     string Host name.
	 * array['port']     int Port number (using default protocol if not set).
	 * array['user']     string Username.
	 * array['pass']     string Password.
	 *
	 * all query string parameters will be added to the result (specified key below are not overridden)
	 *
	 * @param string $str
	 * @return array
	 * @throws FtpException
	 * @see parse_url()
	 * @see parse_query()
	 */
	public function parse(string $str): array {
		FtpUtils::registerTranslation();
		$protocol = null;

		$url = parse_url($str);

		if ($url === false) {
			throw new FtpException("URL is not valid " . $str);
		}

		$scheme = array_key_exists('scheme', $url)
				? strtolower($url['scheme'])
				: 'ftp';
		foreach (FtpProtocol::values() as $current) {
			if (strtolower($current->getProtocol()) === $scheme) {
				$protocol = $current;
				break;
			}
		}

		$res = [];

		if (array_key_exists('query', $url)) {
			$options = [];
			parse_str($url['query'], $options);

			if ($options) {
				$res = array_merge($res, $options);
			}
		}

		$res = array_merge($res,
				[
						'class' => $protocol->driver,
						'host' => array_key_exists('scheme', $url)
								? $url['host']
								: 'localhost',
						'port' => array_key_exists('port', $url)
								? $url['port']
								: $protocol->port,
						'user' => array_key_exists('user', $url)
								? $url['user']
								: 'anonymous',
						'pass' => array_key_exists('pass', $url)
								? $url['pass']
								: ''
				]);

		if (!isset($res['host'])) {
			throw new FtpException("No host found");
		}

		if (isset($res['port']) && !preg_match('/^[0-9]+/', $res['port'])) {
			throw new FtpException("Port is not a number");
		}

		Yii::debug(VarDumper::dumpAsString($res), 'gftp');

		return $res;
	}
}
