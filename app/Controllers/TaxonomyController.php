<?php

/**
 * KP Memos Taxonomy Controller
 *
 * Manage nested categories and tags.
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
use KPM\Core\Request;
use KPM\Core\Response;
use KPM\Core\Taxonomy;

// if the class does not exist already
if (! class_exists('\KPM\Controllers\TaxonomyController')) {

    /**
     * TaxonomyController
     *
     * Category and tag management.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class TaxonomyController extends Controller
    {
        /**
         * Show the categories
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function categories(): string
        {
            $uid = (int) $this->user()->id;
            return $this->render('taxonomy/categories.php', [
                'title' => 'Categories',
                'categories' => Taxonomy::categories($uid),
            ]);
        }

        /**
         * Create a category
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function createCategory(): void
        {
            $this->saveCategory(0);
        }

        /**
         * Update a category
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The category id
         * @return void
         */
        public function updateCategory(string $id): void
        {
            $this->saveCategory((int) $id);
        }

        /**
         * Delete a category; its children move up a level
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The category id
         * @return void
         */
        public function deleteCategory(string $id): void
        {
            $status = (int) Db::value('category_delete', [(int) $this->user()->id, (int) $id]);
            Flash::add($status > 0 ? 'success' : 'error', $status > 0 ? 'Category deleted.' : 'Category not found.');
            Response::redirect('/categories');
        }

        /**
         * Show the tags
         *
         * @since 8.5
         * @access public
         *
         * @return string
         */
        public function tags(): string
        {
            return $this->render('taxonomy/tags.php', [
                'title' => 'Tags',
                'tags' => Taxonomy::tags((int) $this->user()->id),
            ]);
        }

        /**
         * Create a tag
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public function createTag(): void
        {
            $this->saveTag(0);
        }

        /**
         * Rename a tag
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The tag id
         * @return void
         */
        public function updateTag(string $id): void
        {
            $this->saveTag((int) $id);
        }

        /**
         * Delete a tag
         *
         * @since 8.5
         * @access public
         *
         * @param  string $id The tag id
         * @return void
         */
        public function deleteTag(string $id): void
        {
            $status = (int) Db::value('tag_delete', [(int) $this->user()->id, (int) $id]);
            Flash::add($status > 0 ? 'success' : 'error', $status > 0 ? 'Tag deleted.' : 'Tag not found.');
            Response::redirect('/tags');
        }

        /**
         * Create or update a category from the posted form
         *
         * @since 8.5
         * @access private
         *
         * @param  int $id The category id, or 0 to create
         * @return never
         */
        private function saveCategory(int $id): never
        {

            // validate
            $name = Taxonomy::cleanName(Request::post('name'), 100);
            if ($name === '') {
                Flash::add('error', 'Category names cannot be empty.');
                Response::redirect('/categories');
            }

            // save; the procedure enforces ownership, sibling uniqueness, and no cycles
            $status = (int) Db::value('category_save', [
                (int) $this->user()->id,
                $id,
                max(0, (int) Request::post('parent_id')),
                $name,
            ]);
            match (true) {
                $status > 0 => Flash::add('success', $id > 0 ? 'Category saved.' : 'Category created.'),
                $status === -1 => Flash::add('error', 'A category with that name already exists there.'),
                $status === -2 => Flash::add('error', 'That parent category is not valid.'),
                default => Flash::add('error', 'Category not found.'),
            };
            Response::redirect('/categories');
        }

        /**
         * Create or rename a tag from the posted form
         *
         * @since 8.5
         * @access private
         *
         * @param  int $id The tag id, or 0 to create
         * @return never
         */
        private function saveTag(int $id): never
        {

            // validate
            $name = Taxonomy::cleanName(Request::post('name'), 64);
            if ($name === '') {
                Flash::add('error', 'Tag names cannot be empty.');
                Response::redirect('/tags');
            }

            // save
            $status = (int) Db::value('tag_save', [(int) $this->user()->id, $id, $name]);
            match (true) {
                $status > 0 => Flash::add('success', $id > 0 ? 'Tag renamed.' : 'Tag created.'),
                $status === -1 => Flash::add('error', 'That tag already exists.'),
                default => Flash::add('error', 'Tag not found.'),
            };
            Response::redirect('/tags');
        }
    }
}
