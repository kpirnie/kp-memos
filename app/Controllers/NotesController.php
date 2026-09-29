<?php

/**
 * KP Memos Notes Controller
 *
 * Create, view, edit, and delete notes, and upload their attachments.
 * Every lookup is scoped to the signed-in user by the stored procedures.
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
use KPM\Core\Flash;
use KPM\Core\Html;
use KPM\Core\NoteQuery;
use KPM\Core\Request;
use KPM\Core\Response;
use KPM\Core\Storage;
use KPM\Core\Taxonomy;
use KPM\Core\View;
use KPT\Paginator;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\NotesController')) {

    /**
     * NotesController
     *
     * Note editing and viewing.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class NotesController extends Controller
    {
        /** @var int the most tags a note may carry */
        private const MAX_TAGS = 50;

        /** @var int unpinned notes per page */
        private const PER_PAGE = 24;

        /** @var int the most pinned notes shown */
        private const MAX_PINNED = 200;

        /**
         * List, search, and filter notes
         *
         * JSON callers get the rendered result fragments so the page can
         * refresh in place without a template duplicated in javascript.
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function index(): string
        {

            // setup
            $uid = (int) $this->user()->id;
            $filters = NoteQuery::fromRequest($uid);
            $categories = Taxonomy::categories($uid);
            $categoryMap = Taxonomy::categoryMap($uid);
            $tagMap = Taxonomy::tagMap($uid);

            // pinned notes are never paged, so they can be reordered as a whole
            $pinned = Db::rows('notes_list', NoteQuery::params($uid, $filters, true, 0, self::MAX_PINNED));

            // everything else, a page at a time
            $offset = ($filters->page - 1) * self::PER_PAGE;
            $rows = Db::rows('notes_list', NoteQuery::params($uid, $filters, false, $offset, self::PER_PAGE));
            $total = $rows !== [] ? (int) $rows[0]->total : 0;
            $pager = Paginator::fromTotal(
                $rows,
                $total,
                self::PER_PAGE,
                $filters->page,
                static fn (int $page): string => '/notes?'
                    . NoteQuery::queryString($filters, ['page' => $page > 1 ? $page : null])
            );

            // everything the result fragments need
            $data = [
                'filters' => $filters,
                'pinned' => $pinned,
                'pager' => $pager,
                'categoryMap' => $categoryMap,
                'tagMap' => $tagMap,
                'sortable' => ! NoteQuery::isFiltered($filters),
                'count' => $total + count($pinned),
            ];

            // json refresh
            if (Request::wantsJson()) {
                Response::json([
                    'success' => true,
                    'html' => View::render('notes/results.php', $data, null),
                    'query' => NoteQuery::queryString($filters),
                ]);
            }

            // full page
            return $this->render('notes/index.php', array_merge($data, [
                'title' => 'Notes',
                'categories' => $categories,
                'tags' => Taxonomy::tags($uid),
                'scripts' => ['assets/js/notes.js'],
            ]));
        }

        /**
         * Pin or unpin a note
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return void
         */
        public function pin(string $id): void
        {
            $body = Request::json();
            $pinned = ! empty($body['pinned']) ? 1 : 0;
            $status = (int) Db::value('note_pin_set', [(int) $this->user()->id, (int) $id, $pinned]);
            if ($status < 1) {
                Response::abort(404);
            }
            Response::json(['success' => true, 'pinned' => (bool) $pinned]);
        }

        /**
         * Save the order of the pinned notes
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function pinOrder(): void
        {
            $ids = Request::json()['ids'] ?? [];
            $ids = is_array($ids) ? array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0) : [];
            $ids = array_slice(array_values(array_unique($ids)), 0, self::MAX_PINNED);
            $count = (int) Db::value('notes_pin_order', [(int) $this->user()->id, implode(',', $ids)]);
            Response::json(['success' => true, 'updated' => $count]);
        }

        /**
         * Show the editor for a new note
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function create(): string
        {
            return $this->editor(null);
        }

        /**
         * Show a note
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return string
         */
        public function show(string $id): string
        {

            // load it with everything the view needs
            $uid = (int) $this->user()->id;
            $note = $this->find($uid, (int) $id);
            $categories = Taxonomy::categoryMap($uid);
            $tags = Taxonomy::tagMap($uid);

            return $this->render('notes/show.php', [
                'title' => $note->title,
                'note' => $note,
                'noteCategories' => array_values(array_filter(array_map(
                    static fn (int $cid): ?object => $categories[$cid] ?? null,
                    Taxonomy::ids($note->category_ids)
                ))),
                'noteTags' => array_values(array_filter(array_map(
                    static fn (int $tid): ?object => $tags[$tid] ?? null,
                    Taxonomy::ids($note->tag_ids)
                ))),
                'attachments' => Db::rows('attachments_list', [$uid, (int) $note->id]),
                'scripts' => ['assets/js/note.js'],
            ]);
        }

        /**
         * Show the editor for an existing note
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return string
         */
        public function edit(string $id): string
        {
            return $this->editor($this->find((int) $this->user()->id, (int) $id));
        }

        /**
         * Save a new note
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function store(): string
        {
            return $this->save(null);
        }

        /**
         * Save an existing note
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return string
         */
        public function update(string $id): string
        {
            return $this->save($this->find((int) $this->user()->id, (int) $id));
        }

        /**
         * Delete a note and its attachment files
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return void
         */
        public function destroy(string $id): void
        {

            // make sure it's ours, then delete; the procedure hands back the files to remove
            $uid = (int) $this->user()->id;
            $note = $this->find($uid, (int) $id);
            foreach (Db::rows('note_delete', [$uid, (int) $note->id]) as $file) {
                Storage::delete((string) $file->stored_name);
            }

            // done
            if (Request::wantsJson()) {
                Response::json(['success' => true]);
            }
            Flash::add('success', 'Note deleted.');
            Response::redirect('/notes');
        }

        /**
         * Upload attachments to a note
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The note id
         * @return void
         */
        public function upload(string $id): void
        {
            $uid = (int) $this->user()->id;
            $note = $this->find($uid, (int) $id);
            [$saved, $errors] = $this->storeUploads($uid, (int) $note->id);
            Response::json(
                ['success' => $errors === [], 'files' => $saved, 'errors' => $errors],
                $errors === [] ? 200 : 207
            );
        }

        /**
         * Load a note owned by the user or 404
         *
         * @since 8.5
         * @access private
         *
         * @param  int $uid The user id
         * @param  int $id  The note id
         * @return object
         */
        private function find(int $uid, int $id): object
        {
            $note = $id > 0 ? Db::row('note_get', [$uid, $id]) : null;
            if ($note === null) {
                Response::abort(404);
            }
            return $note;
        }

        /**
         * Render the editor
         *
         * @since 8.5
         * @access private
         *
         * @param  object|null $note The note, or null for a new one
         * @return string
         */
        private function editor(?object $note): string
        {

            // hold the taxonomy
            $uid = (int) $this->user()->id;
            $tags = Taxonomy::tagMap($uid);

            return $this->render('notes/edit.php', [
                'title' => $note !== null ? 'Edit: ' . $note->title : 'New note',
                'note' => $note,
                'categories' => Taxonomy::categories($uid),
                'selectedCategories' => $note !== null ? Taxonomy::ids($note->category_ids) : [],
                'noteTags' => $note !== null ? array_values(array_filter(array_map(
                    static fn (int $tid): ?string => isset($tags[$tid]) ? (string) $tags[$tid]->name : null,
                    Taxonomy::ids($note->tag_ids)
                ))) : [],
                'allTags' => array_map(static fn (object $t): string => (string) $t->name, Taxonomy::tags($uid)),
                'attachments' => $note !== null ? Db::rows('attachments_list', [$uid, (int) $note->id]) : [],
                'maxUpload' => Storage::maxUploadBytes(),
                'maxFiles' => Storage::maxFileCount(),
                'styles' => ['assets/vendor/jodit/jodit.min.css'],
                'scripts' => ['assets/vendor/jodit/jodit.min.js', 'assets/js/editor.js'],
            ]);
        }

        /**
         * Validate and save the posted note
         *
         * @since 8.5
         * @access private
         *
         * @param  object|null $note The existing note, or null to create
         * @return string
         */
        private function save(?object $note): string
        {

            // setup
            $uid = (int) $this->user()->id;
            $title = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', Request::post('title')));
            $title = mb_substr($title, 0, 255);
            $html = Html::purify(Request::post('body'));
            $text = Html::text($html);

            // validate
            $error = match (true) {
                $title === '' => 'Every note needs a title.',
                strlen($html) > Html::MAX_BYTES => 'This note is too large. Move big images into attachments.',
                default => null,
            };
            if ($error !== null) {
                if (Request::wantsJson()) {
                    Response::json(['success' => false, 'message' => $error], 422);
                }
                Flash::add('error', $error);
                Response::redirect($note !== null ? '/notes/' . (int) $note->id . '/edit' : '/notes/new');
            }

            // save the content
            $id = $note !== null
                ? (int) Db::value('note_update', [$uid, (int) $note->id, $title, $html, $text])
                : (int) Db::value('note_create', [$uid, $title, $html, $text]);
            if ($id < 1) {
                Response::abort(404);
            }

            // categories: the procedure ignores ids the user doesn't own
            $categories = array_filter(
                array_map('intval', Request::postArray('categories')),
                static fn (int $c): bool => $c > 0
            );
            Db::value('note_categories_set', [$uid, $id, implode(',', array_unique($categories))]);

            // tags: comma separated names, created as needed
            Db::value('note_tags_set', [$uid, $id, implode("\x1F", $this->parseTags(Request::post('tags')))]);

            // plain form posts may carry files too
            [, $errors] = $this->storeUploads($uid, $id);

            // respond
            if (Request::wantsJson()) {
                Response::json(['success' => true, 'id' => $id, 'url' => '/notes/' . $id, 'errors' => $errors]);
            }
            foreach ($errors as $message) {
                Flash::add('error', $message);
            }
            Flash::add('success', 'Note saved.');
            Response::redirect('/notes/' . $id);
        }

        /**
         * Parse the tag field into clean, unique names
         *
         * @since 8.5
         * @access private
         *
         * @param  string $raw Comma separated names
         * @return list<string>
         */
        private function parseTags(string $raw): array
        {
            $tags = [];
            foreach (explode(',', $raw) as $name) {
                $name = Taxonomy::cleanName(ltrim(trim($name), '#'), 64);
                if ($name !== '') {
                    $tags[mb_strtolower($name)] = $name;
                }
            }
            return array_slice(array_values($tags), 0, self::MAX_TAGS);
        }

        /**
         * Store every uploaded file in the "files" field against a note
         *
         * @since 8.5
         * @access private
         *
         * @param  int $uid    The user id
         * @param  int $noteId The note id
         * @return array{0: list<array<string, mixed>>, 1: list<string>} Saved files and error messages
         */
        private function storeUploads(int $uid, int $noteId): array
        {

            // normalize php's upload array
            $files = $_FILES['files'] ?? null;
            if (! is_array($files) || ! isset($files['name'])) {
                return [[], []];
            }
            $count = is_array($files['name']) ? count($files['name']) : 1;
            $pick = static fn (string $key, int $i): mixed => is_array($files[$key])
                ? ($files[$key][$i] ?? null)
                : $files[$key];

            // store each one
            $saved = [];
            $errors = [];
            for ($i = 0; $i < $count; $i++) {
                $error = (int) $pick('error', $i);
                $name = $this->cleanFilename((string) $pick('name', $i));
                if ($error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($error !== UPLOAD_ERR_OK) {
                    $errors[] = $name . ': ' . match ($error) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'too large for the server\'s upload limit.',
                        UPLOAD_ERR_PARTIAL => 'the upload was interrupted.',
                        default => 'the server could not accept this file.',
                    };
                    continue;
                }

                // move it into storage and record it
                $stored = Storage::store((string) $pick('tmp_name', $i));
                if ($stored === null) {
                    $errors[] = $name . ': the file could not be stored.';
                    continue;
                }
                $attachmentId = (int) Db::value('attachment_add', [
                    $uid, $noteId, $name, $stored['stored'], $stored['mime'], $stored['size'], $stored['sha256'],
                ]);
                if ($attachmentId < 1) {
                    Storage::delete($stored['stored']);
                    $errors[] = $name . ': the file could not be attached.';
                    continue;
                }
                $saved[] = [
                    'id' => $attachmentId,
                    'name' => $name,
                    'size' => $stored['size'],
                    'mime' => $stored['mime'],
                ];
            }
            return [$saved, $errors];
        }

        /**
         * Clean an uploaded file's display name
         *
         * @since 8.5
         * @access private
         *
         * @param  string $name The client-supplied name
         * @return string
         */
        private function cleanFilename(string $name): string
        {

            // no paths, no control characters; it's only ever displayed and sent as a download name
            $name = basename(str_replace('\\', '/', $name));
            $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
            $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $name));
            $name = mb_substr($name, -255);
            return $name !== '' && $name !== '.' && $name !== '..' ? $name : 'file';
        }
    }
}
