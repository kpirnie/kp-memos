<?php

/**
 * KP Memos Attachment Storage
 *
 * Stores uploaded files under storage/attachments with random,
 * extension-less names, and streams them back with download-safe headers.
 * The storage directory is denied by nginx; files are only ever served
 * through the application after an ownership or share check.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Storage')) {

    /**
     * Storage
     *
     * Attachment file handling.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Storage
    {
        /** @var list<string> mime types safe to show inline; everything else downloads */
        private const INLINE = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif'];

        /**
         * Get the attachments root
         *
         * @since 8.5
         * @access private
         *
         * @return string
         */
        private static function root(): string
        {
            return KPM_PATH . '/storage/attachments';
        }

        /**
         * Resolve a stored name to its path
         *
         * @since 8.5
         * @access public
         *
         * @param  string $stored The stored name
         * @return string
         * @throws \InvalidArgumentException When the name is malformed
         */
        public static function path(string $stored): string
        {

            // stored names are always 64 lowercase hex characters; nothing else ever reaches the filesystem
            if (! preg_match('/^[a-f0-9]{64}$/', $stored)) {
                throw new \InvalidArgumentException('Invalid stored name');
            }

            // shard two levels deep
            return self::root() . '/' . substr($stored, 0, 2) . '/' . substr($stored, 2, 2) . '/' . $stored;
        }

        /**
         * Move an uploaded file into storage
         *
         * @since 8.5
         * @access public
         *
         * @param  string $tmpPath The php upload temp path
         * @return array{stored: string, size: int, sha256: string, mime: string}|null
         */
        public static function store(string $tmpPath): ?array
        {

            // only genuine uploads
            if (! is_uploaded_file($tmpPath)) {
                return null;
            }

            // pick a random name and make the shard directory
            $stored = bin2hex(random_bytes(32));
            $path = self::path($stored);
            $dir = dirname($path);
            if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
                return null;
            }

            // move it and lock down the permissions
            if (! move_uploaded_file($tmpPath, $path)) {
                return null;
            }
            chmod($path, 0640);

            // describe it
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            return [
                'stored' => $stored,
                'size' => (int) filesize($path),
                'sha256' => (string) hash_file('sha256', $path),
                'mime' => is_string($mime) && $mime !== '' ? mb_substr($mime, 0, 127) : 'application/octet-stream',
            ];
        }

        /**
         * Delete a stored file
         *
         * @since 8.5
         * @access public
         *
         * @param  string $stored The stored name
         * @return void
         */
        public static function delete(string $stored): void
        {
            try {
                $path = self::path($stored);
                if (is_file($path)) {
                    unlink($path);
                }
            } catch (\Throwable $e) {
                error_log('kpm storage delete failed: ' . $e->getMessage());
            }
        }

        /**
         * Stream a stored file to the client
         *
         * @since 8.5
         * @access public
         *
         * @param  object $file     Row with stored_name, original_name, mime_type
         * @param  bool   $download Force a download even for inline-safe types
         * @return never
         */
        public static function send(object $file, bool $download = false): never
        {

            // make sure it's there
            $path = self::path((string) $file->stored_name);
            if (! is_file($path) || ! is_readable($path)) {
                Response::abort(404);
            }

            // decide inline vs download; anything that could script (html, svg, xml...) always downloads
            $mime = (string) $file->mime_type;
            $inline = ! $download && in_array($mime, self::INLINE, true);
            $type = $inline ? $mime : 'application/octet-stream';

            // build a safe content-disposition with an ascii fallback and the utf-8 name
            $name = (string) $file->original_name;
            $ascii = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $name) ?: 'download';
            $disposition = ($inline ? 'inline' : 'attachment')
                . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name);

            // clear any buffered output and send it
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header_remove('Content-Security-Policy');
            header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
            header('Content-Type: ' . $type);
            header('Content-Disposition: ' . $disposition);
            header('Content-Length: ' . (string) filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store, max-age=0');
            header('Cross-Origin-Resource-Policy: same-origin');

            // stream it
            $handle = fopen($path, 'rb');
            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
            }
            exit;
        }

        /**
         * The effective per-file upload limit in bytes, from php's configuration
         *
         * @since 8.5
         * @access public
         *
         * @return int
         */
        public static function maxUploadBytes(): int
        {
            $upload = self::iniBytes((string) ini_get('upload_max_filesize'));
            $post = self::iniBytes((string) ini_get('post_max_size'));
            $limits = array_filter([$upload, $post], static fn(int $v): bool => $v > 0);
            return $limits !== [] ? min($limits) : 0;
        }

        /**
         * The effective per-request post limit in bytes, from php's configuration
         *
         * @since 8.5
         * @access public
         *
         * @return int
         */
        public static function maxPostBytes(): int
        {
            return self::iniBytes((string) ini_get('post_max_size'));
        }

        /**
         * The number of files php accepts per request
         *
         * @since 8.5
         * @access public
         *
         * @return int
         */
        public static function maxFileCount(): int
        {
            return max(1, (int) ini_get('max_file_uploads'));
        }

        /**
         * Convert a php.ini size shorthand to bytes
         *
         * @since 8.5
         * @access private
         *
         * @param  string $value e.g. "64M"
         * @return int
         */
        private static function iniBytes(string $value): int
        {
            $value = trim($value);
            if ($value === '') {
                return 0;
            }
            $number = (int) $value;
            return match (strtolower(substr($value, -1))) {
                'g' => $number * 1024 ** 3,
                'm' => $number * 1024 ** 2,
                'k' => $number * 1024,
                default => $number,
            };
        }
    }
}
