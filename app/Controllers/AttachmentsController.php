<?php

/**
 * KP Memos Attachments Controller
 *
 * Download and delete attachments the signed-in user owns.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Controllers;

// we need the core
use KPM\Core\Db;
use KPM\Core\Request;
use KPM\Core\Response;
use KPM\Core\Storage;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\AttachmentsController')) {

    /**
     * AttachmentsController
     *
     * Owner attachment access.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class AttachmentsController extends Controller
    {
        /**
         * Send an attachment
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The attachment id
         * @return void
         */
        public function download(string $id): void
        {
            $file = Db::row('attachment_get', [(int) $this->user()->id, (int) $id]);
            if ($file === null) {
                Response::abort(404);
            }
            Storage::send($file, Request::query('download') === '1');
        }

        /**
         * Delete an attachment
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The attachment id
         * @return void
         */
        public function destroy(string $id): void
        {

            // the procedure only deletes what the user owns and hands back the file name
            $row = Db::row('attachment_delete', [(int) $this->user()->id, (int) $id]);
            if ($row === null || empty($row->stored_name)) {
                Response::abort(404);
            }
            Storage::delete((string) $row->stored_name);
            Response::json(['success' => true]);
        }
    }
}
