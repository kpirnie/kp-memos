<?php

/**
 * KP Memos Note Query
 *
 * Turns the notes page's query string into validated filters and the
 * parameters for kpm_notes_list, including a safe boolean-mode fulltext
 * expression.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// we need the sanitizers
use KPT\Sanitize;

// if the class does not exist already
if (! class_exists('\KPM\Core\NoteQuery')) {

    /**
     * NoteQuery
     *
     * Validated note list filters.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class NoteQuery
    {
        /** @var list<string> accepted sort orders */
        public const SORTS = ['relevance', 'updated', 'oldest', 'created', 'title'];

        /** @var int innodb's default minimum fulltext token length */
        private const MIN_TOKEN = 3;

        /**
         * Build the filters from the query string
         *
         * @since 8.5
         * @access public
         *
         * @param  int $uid The user id, used to validate tag and category ids
         * @return object
         */
        public static function fromRequest(int $uid): object
        {

            // raw inputs
            $q = trim(mb_substr((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', Request::query('q')), 0, 200));
            $category = max(0, (int) Request::query('category'));
            $tags = Taxonomy::ids(Request::query('tags'));
            $visibility = Request::query('visibility');
            $dateField = Request::query('date_field') === 'created' ? 'created' : 'updated';
            $from = Request::query('from');
            $to = Request::query('to');
            $sort = Request::query('sort');

            // only the user's own taxonomy
            $categories = Taxonomy::categoryMap($uid);
            $tagMap = Taxonomy::tagMap($uid);
            $category = isset($categories[$category]) ? $category : 0;
            $tags = array_filter($tags, static fn (int $t): bool => isset($tagMap[$t]));
            $tags = array_slice(array_values($tags), 0, 20);

            // enums and dates
            $visibility = in_array($visibility, ['public', 'private'], true) ? $visibility : 'all';
            $from = self::day($from);
            $to = self::day($to);
            $sort = in_array($sort, self::SORTS, true) ? $sort : ($q !== '' ? 'relevance' : 'updated');
            if ($sort === 'relevance' && $q === '') {
                $sort = 'updated';
            }

            return (object) [
                'q' => $q,
                'category' => $category,
                'tags' => $tags,
                'visibility' => $visibility,
                'date_field' => $dateField,
                'from' => $from,
                'to' => $to,
                'sort' => $sort,
                'page' => max(1, min(100000, (int) Request::query('page', '1'))),
            ];
        }

        /**
         * Whether any filter narrows the list (pinned drag ordering needs the full list)
         *
         * @since 8.5
         * @access public
         *
         * @param  object $f The filters
         * @return bool
         */
        public static function isFiltered(object $f): bool
        {
            return $f->q !== '' || $f->category > 0 || $f->tags !== [] || $f->visibility !== 'all'
                || $f->from !== '' || $f->to !== '';
        }

        /**
         * Build the kpm_notes_list parameters
         *
         * @since 8.5
         * @access public
         *
         * @param  int    $uid    The user id
         * @param  object $f      The filters
         * @param  bool   $pinned Pinned notes, or unpinned notes
         * @param  int    $offset Row offset
         * @param  int    $limit  Row limit
         * @return list<mixed>
         */
        public static function params(int $uid, object $f, bool $pinned, int $offset, int $limit): array
        {
            [$ft, $like] = self::search($f->q);

            // the upper date bound is inclusive of the whole day
            $to = null;
            if ($f->to !== '') {
                $to = Format::toUtc((new \DateTimeImmutable($f->to))->modify('+1 day')->format('Y-m-d'));
            }

            return [
                $uid,
                $ft,
                $like,
                $f->category,
                implode(',', $f->tags),
                $f->visibility,
                $f->date_field,
                $f->from !== '' ? Format::toUtc($f->from) : null,
                $to,
                $pinned ? 1 : 0,
                $f->sort,
                $offset,
                $limit,
            ];
        }

        /**
         * Build a boolean-mode fulltext expression, with a like fallback
         *
         * Every term is required and prefix-matched; quoted phrases stay
         * phrases. Operator characters are stripped from user input so it
         * can never form invalid or unintended boolean syntax. Terms shorter
         * than innodb's token size can't be indexed, so a query made only of
         * those falls back to a like match.
         *
         * @since 8.5
         * @access public
         *
         * @param  string $q The search text
         * @return array{0: string, 1: string} The fulltext expression and the like term
         */
        public static function search(string $q): array
        {
            if ($q === '') {
                return ['', ''];
            }

            // pull out quoted phrases first
            $parts = [];
            $rest = (string) preg_replace_callback('/"([^"]+)"/u', static function (array $m) use (&$parts): string {
                $words = self::words($m[1]);
                if (count($words) > 0) {
                    $parts[] = '+"' . implode(' ', $words) . '"';
                }
                return ' ';
            }, $q);

            // then single words
            foreach (self::words($rest) as $word) {
                if (mb_strlen($word) >= self::MIN_TOKEN) {
                    $parts[] = '+' . $word . '*';
                }
            }

            // fulltext when anything indexable remains, otherwise a like match on the raw text
            if ($parts !== []) {
                return [mb_substr(implode(' ', $parts), 0, 500), ''];
            }
            return ['', mb_substr(trim(str_replace('"', '', $q)), 0, 128)];
        }

        /**
         * Accept only a real calendar date in Y-m-d form
         *
         * @since 8.5
         * @access private
         *
         * @param  string $value The candidate
         * @return string The date, or ''
         */
        private static function day(string $value): string
        {
            $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && Sanitize::date($value, 'Y-m-d') === $value;
            return $valid ? $value : '';
        }

        /**
         * Split text into operator-free words
         *
         * @since 8.5
         * @access private
         *
         * @param  string $text The text
         * @return list<string>
         */
        private static function words(string $text): array
        {
            $words = preg_split('/[^\p{L}\p{N}_]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            return array_slice(is_array($words) ? $words : [], 0, 16);
        }

        /**
         * Build the query string for the current filters
         *
         * @since 8.5
         * @access public
         *
         * @param  object               $f         The filters
         * @param  array<string, mixed> $overrides Values to replace
         * @return string
         */
        public static function queryString(object $f, array $overrides = []): string
        {
            $params = array_merge([
                'q' => $f->q,
                'category' => $f->category ?: null,
                'tags' => $f->tags !== [] ? implode(',', $f->tags) : null,
                'visibility' => $f->visibility !== 'all' ? $f->visibility : null,
                'date_field' => $f->date_field !== 'updated' ? $f->date_field : null,
                'from' => $f->from ?: null,
                'to' => $f->to ?: null,
                'sort' => $f->sort,
                'page' => $f->page > 1 ? $f->page : null,
            ], $overrides);
            $params = array_filter($params, static fn (mixed $v): bool => $v !== null && $v !== '');
            return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
    }
}
