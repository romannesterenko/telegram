<?php

/**
 * Exception module.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2020 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */
namespace danog\MadelineProto;

/**
 * Basic exception.
 */
class Exception extends \Exception
{
    use TL\PrettyException;
    /**
     *
     */
    public function __toString()
    {
        return $this->file === 'MadelineProto' ? $this->message : '\\danog\\MadelineProto\\Exception' . ($this->message !== '' ? ': ' : '') . $this->message . ' in ' . $this->file . ':' . $this->line . PHP_EOL . \danog\MadelineProto\Magic::$revision . PHP_EOL . 'TL Trace:' . PHP_EOL . $this->getTLTrace();
    }
    /**
     *
     */
    public function __construct($message = null, $code = 0, self $previous = null, $file = null, $line = null)
    {
        $this->prettifyTL();
        if ($file !== null) {
            $this->file = $file;
        }
        if ($line !== null) {
            $this->line = $line;
        }
        parent::__construct($message, $code, $previous);
        if (\strpos($message, 'socket_accept') === false && !\in_array(\basename($this->file), ['PKCS8.php', 'PSS.php'])) {
            \danog\MadelineProto\Logger::log($message . ' in ' . \basename($this->file) . ':' . $this->line, \danog\MadelineProto\Logger::FATAL_ERROR);
        }
    }
    /**
     * Complain about missing extensions.
     *
     * @param string $extensionName Extension name
     *
     * @return self
     */
    public static function extension(string $extensionName) : self
    {
        $additional = 'Try running sudo apt-get install php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-' . $extensionName . '.';
        if ($extensionName === 'libtgvoip') {
            $additional = 'Follow the instructions @ https://voip.madelineproto.xyz to install it.';
        } elseif ($extensionName === 'prime') {
            $additional = 'Follow the instructions @ https://prime.madelineproto.xyz to install it.';
        }
        $message = 'MadelineProto requires the ' . $extensionName . ' extension to run. ' . $additional;
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            echo $message . '<br>';
        }
        $file = 'MadelineProto';
        $line = 1;
        return new self($message, 0, null, $file, $line);
    }
    /**
     * ExceptionErrorHandler.
     *
     * Error handler
     *
     * @return false
     */
    public static function exceptionErrorHandler($errno = 0, $errstr = null, $errfile = null, $errline = null) : bool
    {
        $errfileReplaced = \preg_replace('/phabel-transpiler\\d+\\./', '', $errfile ?? '');
        // If error is suppressed with @, don't throw an exception
        if (\Phabel\Target\Php80\Polyfill::error_reporting() === 0 || \strpos($errstr, 'headers already sent') || \strpos($errstr, 'Creation of dynamic property') !== false || $errfileReplaced && (\strpos($errfileReplaced, DIRECTORY_SEPARATOR . 'amphp' . DIRECTORY_SEPARATOR) !== false || \strpos($errfileReplaced, DIRECTORY_SEPARATOR . 'league' . DIRECTORY_SEPARATOR) !== false || \strpos($errfileReplaced, DIRECTORY_SEPARATOR . 'phpseclib' . DIRECTORY_SEPARATOR) !== false)) {
            return false;
        }
        throw new self($errstr, $errno, null, $errfile, $errline);
    }
    /**
     * ExceptionErrorHandler.
     *
     * Error handler
     *
     * @return void
     */
    public static function exceptionHandler($exception) : void
    {
        Logger::log($exception, Logger::FATAL_ERROR);
        Magic::shutdown(1);
    }
}