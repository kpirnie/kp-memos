<?php

/**
 * KP Memos CLI Helpers
 *
 * Prompting and output helpers for the bin/ scripts.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Cli')) {

    /**
     * Cli
     *
     * Terminal io and sql file splitting.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Cli
    {
        /**
         * Write a line
         *
         * @since 8.5
         * @access public
         *
         * @param  string $text The text
         * @return void
         */
        public static function line(string $text = ''): void
        {
            fwrite(STDOUT, $text . PHP_EOL);
        }

        /**
         * Write an error and exit
         *
         * @since 8.5
         * @access public
         *
         * @param  string $text The error
         * @return never
         */
        public static function fail(string $text): never
        {
            fwrite(STDERR, 'Error: ' . $text . PHP_EOL);
            exit(1);
        }

        /**
         * Prompt for a value
         *
         * @since 8.5
         * @access public
         *
         * @param  string $prompt  The prompt
         * @param  string $default The default when left blank
         * @return string
         */
        public static function ask(string $prompt, string $default = ''): string
        {
            fwrite(STDOUT, $prompt . ($default !== '' ? " [{$default}]" : '') . ': ');
            $value = trim(self::read());
            return $value === '' ? $default : $value;
        }

        /**
         * Read a line from stdin, refusing to guess when there is no input at all
         *
         * @since 8.5
         * @access private
         *
         * @return string
         */
        private static function read(): string
        {
            $line = fgets(STDIN);
            if ($line === false) {
                fwrite(STDOUT, PHP_EOL);
                self::fail('No input. Run this interactively, e.g. podman exec -it <container> php bin/<script>.php');
            }
            return $line;
        }

        /**
         * Prompt for a secret without echoing it
         *
         * @since 8.5
         * @access public
         *
         * @param  string $prompt The prompt
         * @return string
         */
        public static function secret(string $prompt): string
        {

            // turn off echo when we have a terminal and are allowed to shell out to stty
            $tty = function_exists('posix_isatty') ? posix_isatty(STDIN) : stream_isatty(STDIN);
            $hide = $tty && function_exists('shell_exec');
            if ($hide) {
                shell_exec('stty -echo');
            } elseif ($tty) {
                fwrite(STDOUT, '(shell_exec is disabled, so this input will be visible)' . PHP_EOL);
            }

            // read it
            fwrite(STDOUT, $prompt . ': ');
            $value = rtrim(self::read(), "\r\n");

            // restore echo
            if ($hide) {
                shell_exec('stty echo');
                fwrite(STDOUT, PHP_EOL);
            }
            return $value;
        }

        /**
         * Split an sql file into statements, honoring DELIMITER directives
         *
         * @since 8.5
         * @access public
         *
         * @param  string $sql The file contents
         * @return list<string>
         */
        public static function splitSql(string $sql): array
        {

            // setup
            $statements = [];
            $delimiter = ';';
            $buffer = '';

            // walk the lines
            foreach (preg_split('/\R/', $sql) ?: [] as $line) {
                // delimiter directives change the terminator
                if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m)) {
                    $delimiter = $m[1];
                    continue;
                }

                // accumulate
                $buffer .= $line . "\n";

                // a statement ends when the line ends with the delimiter
                if (str_ends_with(rtrim($line), $delimiter)) {
                    $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
                    $buffer = '';

                    // skip comment-only chunks
                    $code = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));
                    if ($code !== '') {
                        $statements[] = $statement;
                    }
                }
            }

            // anything left over without a terminator
            $code = trim((string) preg_replace('/^\s*--.*$/m', '', $buffer));
            if ($code !== '') {
                $statements[] = trim($buffer);
            }
            return $statements;
        }
    }
}
