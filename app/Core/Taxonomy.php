<?php

/**
 * KP Memos Taxonomy
 *
 * Loads a user's categories and tags, builds the category tree, and
 * cleans submitted names.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Taxonomy')) {

    /**
     * Taxonomy
     *
     * Category and tag helpers.
     *
     * Tree nodes are the category rows with these properties added:
     * <code>
     * depth    int           0 for top level
     * path     string        "Work › Servers › Keys"
     * children list<object>  child nodes
     * </code>
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Taxonomy
    {
        /** @var array<int, list<object>> per-request category cache keyed by user */
        private static array $categories = [];

        /** @var array<int, list<object>> per-request tag cache keyed by user */
        private static array $tags = [];

        /**
         * Clean a submitted category or tag name
         *
         * @since 8.5
         * @access public
         *
         * @param  string $name      The raw name
         * @param  int    $maxLength The column length
         * @return string
         */
        public static function cleanName(string $name, int $maxLength): string
        {

            // no control characters, no commas (the tag input splits on them), collapsed whitespace
            $name = (string) preg_replace('/[\x00-\x1F\x7F,]+/u', ' ', $name);
            $name = (string) preg_replace('/\s+/u', ' ', trim($name));
            return mb_substr($name, 0, $maxLength);
        }

        /**
         * Get a user's categories as a flat, depth-first ordered list of tree nodes
         *
         * @since 8.5
         * @access public
         *
         * @param  int $uid The user id
         * @return list<object>
         */
        public static function categories(int $uid): array
        {

            // cached for the request
            if (isset(self::$categories[$uid])) {
                return self::$categories[$uid];
            }

            // index by parent
            $rows = Db::rows('categories_list', [$uid]);
            $byParent = [];
            foreach ($rows as $row) {
                $row->children = [];
                $byParent[(int) ($row->parent_id ?? 0)][] = $row;
            }

            // walk depth-first from the roots
            $flat = [];
            $walk = static function (int $parent, int $depth, string $path) use (&$walk, &$flat, $byParent): void {
                foreach ($byParent[$parent] ?? [] as $node) {
                    $node->depth = $depth;
                    $node->path = $path === '' ? (string) $node->name : $path . ' › ' . $node->name;
                    $flat[] = $node;
                    $node->children = $byParent[(int) $node->id] ?? [];
                    $walk((int) $node->id, $depth + 1, $node->path);
                }
            };
            $walk(0, 0, '');

            // hold and return
            self::$categories[$uid] = $flat;
            return $flat;
        }

        /**
         * Map category ids to their tree nodes
         *
         * @since 8.5
         * @access public
         *
         * @param  int $uid The user id
         * @return array<int, object>
         */
        public static function categoryMap(int $uid): array
        {
            $map = [];
            foreach (self::categories($uid) as $node) {
                $map[(int) $node->id] = $node;
            }
            return $map;
        }

        /**
         * Get the ids of a category and all of its descendants
         *
         * @since 8.5
         * @access public
         *
         * @param  int $uid The user id
         * @param  int $id  The category id
         * @return list<int>
         */
        public static function descendantIds(int $uid, int $id): array
        {
            $map = self::categoryMap($uid);
            if (! isset($map[$id])) {
                return [];
            }
            $ids = [];
            $stack = [$map[$id]];
            while ($stack !== []) {
                $node = array_pop($stack);
                $ids[] = (int) $node->id;
                foreach ($node->children as $child) {
                    $stack[] = $child;
                }
            }
            return $ids;
        }

        /**
         * Get a user's tags
         *
         * @since 8.5
         * @access public
         *
         * @param  int $uid The user id
         * @return list<object>
         */
        public static function tags(int $uid): array
        {
            return self::$tags[$uid] ??= Db::rows('tags_list', [$uid]);
        }

        /**
         * Map tag ids to their rows
         *
         * @since 8.5
         * @access public
         *
         * @param  int $uid The user id
         * @return array<int, object>
         */
        public static function tagMap(int $uid): array
        {
            $map = [];
            foreach (self::tags($uid) as $tag) {
                $map[(int) $tag->id] = $tag;
            }
            return $map;
        }

        /**
         * Parse a comma separated id list into positive ints
         *
         * @since 8.5
         * @access public
         *
         * @param  string|null $csv The list
         * @return list<int>
         */
        public static function ids(?string $csv): array
        {
            if ($csv === null || $csv === '') {
                return [];
            }
            return array_values(array_unique(array_filter(
                array_map('intval', explode(',', $csv)),
                static fn (int $id): bool => $id > 0
            )));
        }

        /**
         * Forget the per-request caches after a change
         *
         * @since 8.5
         * @access public
         *
         * @return void
         */
        public static function flush(): void
        {
            self::$categories = [];
            self::$tags = [];
        }
    }
}
