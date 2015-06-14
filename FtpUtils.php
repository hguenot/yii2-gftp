<?php

namespace gftp;

use \Yii;

/**
 * Utility class for FTP component.
 * 
 * @author Herve Guenot
 * @link http://www.guenot.info
 * @copyright Copyright &copy; 2015 Herve Guenot
 * @license GNU LESSER GPL 3
 * @version 1.0
 */
class FtpUtils {

	/** @var FtpUtils Global instance of FtpUtils. */
	private static $_instance = null;
	
	private static $translationRegistered = false;

	/**
	 * Returns unique instance of FtpUtils.
	 *
	 * @return FtpUtils Unique instance of FtpUtils
	 */
	public static function getInstance() {
		if (self::$_instance == null) {
			self::$_instance = new FtpUtils();
		}

		return self::$_instance;
	}

	/**
	 * Return if a {@link FtpFile} is a directory (based on user rights) or not.
	 *
	 * @param FtpFile $data File to test
	 *
	 * @return boolean <strong>TRUE</strong> if file is a directory, <strong>FALSE</strong> otherwise.
	 */
	public static function isDir($data) {
		return substr($data->rights, 0, 1) == "d";
	}

	/**
	 * Build filename for FtpWidget.
	 *
	 * @param FtpFile $file Current {@link FtpFile}
	 * @param FtpWidget $context Current displayable widget
	 *
	 * @return string Displayed filename
	 */
	public static function displayFilename (FtpFile $file, FtpWidget $context) {
		if ($context->allowNavigation && self::isDir($file)) {
			$dir = $context->baseFolder."/".$file->filename;
			if ($context->baseFolder == "/") {
				$dir = "/".$file->filename;
			}
			if ($file->filename == '..') {
				$dir = str_replace('\\', '/', dirname($context->baseFolder));
			}
			$arr = array_merge([""], $_GET, [$context->navKey => $dir]);
			return \yii\helpers\Html::a($file->filename, $arr);
		} else {
			return $file->filename;
		}
	}

	/**
	 * Initialize global instance.
	 */
	public static function initialize() {
		self::getInstance()->_init();
	}

	/**
	 * Initialize new object (register error handler if global variable <code>YII_ENABLE_ERROR_HANDLER</code> is set to true.
	 */
	private function _init() {
		if(YII_ENABLE_ERROR_HANDLER)
			set_error_handler([$this,'handleError'],error_reporting());
	}

	/**
	 * PHP error handler method used to catch all FTP exception.
	 *
	 * @param integer $code Level of the error raised
	 * @param string $message Error message
	 * @param string $file Filename that the error was raised in
	 * @param integer $line Line number the error was raised at
	 * @param array $context array that points to the active symbol table at the point the error occurred.
	 */
	public function handleError($code,$message,$file,$line,$context)
	{
		if (isset($context['this']) && $context['this'] instanceof FtpComponent) {
			// disable error capturing to avoid recursive errors
			restore_error_handler();
			restore_exception_handler();
			if (isset($message)) {
				// FTP error message are formed : ftp_***(): <message>
				$messages = explode(':', $message, 2);
				$func = explode(' ', $messages[0], 2);
				$ex = $context['this']->createException($func[0], $messages[1]);
				if ($ex != null) throw $ex;
			}
		}

		\Yii::$app->errorHandler->handleError($code,$message,$file,$line);
	}
	
	public static function registerTranslation(){
		if (!self::$translationRegistered) {
			if (isset(Yii::$app) && null !== Yii::$app->getI18n()) {
				$translations = isset(Yii::$app->getI18n()->translations) ? Yii::$app->getI18n()->translations : [];
				if (!isset($translations['gftp']) && !isset($translations['gftp*'])) {
					$translations['gftp'] = [
						'class' => 'yii\i18n\PhpMessageSource',
						'sourceLanguage' => 'en',
						'basePath' => __DIR__ . '/messages',
					];
				}
				Yii::$app->getI18n()->translations = $translations;
			}
			self::$translationRegistered = true;
		}
	}
}

