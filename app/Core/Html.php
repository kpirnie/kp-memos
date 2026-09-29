<?php

/**
 * KP Memos HTML Sanitizer
 *
 * Every note body passes through HTMLPurifier before it is stored. The
 * allowlist keeps the formatting the editor produces (inline css included,
 * property by property) and drops anything that can script, frame, or
 * phone home beyond plain images and links.
 *
 * @since 8.5
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Memos
 */

declare(strict_types=1);

// throw it under our namespace
namespace KPM\Core;

// if the class does not exist already
if (! class_exists('\KPM\Core\Html')) {

    /**
     * Html
     *
     * Purification and plain text extraction.
     *
     * @since 8.5
     * @access public
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Memos
     */
    final class Html
    {
        /** @var int the largest purified body we store, in bytes */
        public const MAX_BYTES = 8388608;

        /** @var \HTMLPurifier|null the configured purifier */
        private static ?\HTMLPurifier $purifier = null;

        /**
         * Get the configured purifier
         *
         * @since 8.5
         * @access private
         *
         * @return \HTMLPurifier
         */
        private static function purifier(): \HTMLPurifier
        {

            // build once
            if (self::$purifier !== null) {
                return self::$purifier;
            }
            $config = \HTMLPurifier_Config::createDefault();

            // cache the compiled definitions in storage when it's writable
            $cache = KPM_PATH . '/storage/cache/htmlpurifier';
            if (is_dir($cache) || @mkdir($cache, 0750, true)) {
                $config->set('Cache.SerializerPath', $cache);
                $config->set('Cache.SerializerPermissions', 0640);
            } else {
                $config->set('Cache.DefinitionImpl', null);
            }

            // document settings
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.Doctype', 'XHTML 1.0 Transitional');

            // the elements and attributes the editor produces; nothing that scripts, embeds, or submits,
            // and no class attributes, so stored html can't borrow the app's own styling
            $config->set('HTML.Allowed', implode(',', [
                'p[style]', 'div[style]', 'span[style]', 'br', 'hr',
                'h1[style]', 'h2[style]', 'h3[style]', 'h4[style]', 'h5[style]', 'h6[style]',
                'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'sub', 'sup', 'small',
                'blockquote[style]', 'pre[style]', 'code', 'kbd', 'samp', 'var',
                'ul[style]', 'ol[style|start]', 'li[style]', 'dl', 'dt', 'dd',
                'a[href|title|target|rel]',
                'img[src|alt|title|width|height|style]',
                'table[style|border|cellpadding|cellspacing|width]', 'caption', 'colgroup', 'col[span|width]',
                'thead', 'tbody', 'tfoot', 'tr[style]',
                'th[style|colspan|rowspan|scope|width]', 'td[style|colspan|rowspan|width]',
            ]));

            // presentational css only, validated property by property
            $config->set('CSS.AllowedProperties', [
                'color', 'background-color', 'font-weight', 'font-style', 'font-size', 'font-family',
                'text-decoration', 'text-align', 'vertical-align', 'line-height', 'letter-spacing',
                'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
                'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
                'border', 'border-width', 'border-style', 'border-color', 'border-collapse',
                'width', 'height', 'max-width', 'list-style-type', 'white-space',
            ]);
            $config->set('CSS.AllowImportant', false);
            $config->set('CSS.AllowTricky', false);
            $config->set('CSS.Proprietary', false);

            // links and images: safe schemes only; data: is image-only inside purifier
            $config->set('URI.AllowedSchemes', [
                'http' => true,
                'https' => true,
                'mailto' => true,
                'tel' => true,
                'data' => true,
            ]);
            $config->set('URI.DisableExternalResources', false);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.TargetBlank', true);
            $config->set('HTML.TargetNoopener', true);
            $config->set('HTML.TargetNoreferrer', true);
            $config->set('HTML.Nofollow', true);

            // no ids or names, so stored html can't clobber dom globals
            $config->set('Attr.EnableID', false);

            // tidy output
            $config->set('AutoFormat.RemoveEmpty', false);
            $config->set('Output.Newline', "\n");

            // hold and return it
            self::$purifier = new \HTMLPurifier($config);
            return self::$purifier;
        }

        /**
         * Purify editor html
         *
         * @since 8.5
         * @access public
         *
         * @param  string $html The untrusted html
         * @return string
         */
        public static function purify(string $html): string
        {

            // drop invalid utf-8 before purifying
            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
            return trim(self::purifier()->purify($html));
        }

        /**
         * Extract searchable plain text from purified html
         *
         * @since 8.5
         * @access public
         *
         * @param  string $html Purified html
         * @return string
         */
        public static function text(string $html): string
        {

            // images carry no text; data uris would bloat the index
            $html = (string) preg_replace('/<img\b[^>]*>/i', ' ', $html);

            // keep word boundaries at block and line breaks
            $breaks = '/<(br|\/p|\/div|\/li|\/h[1-6]|\/tr|\/td|\/th|\/pre|\/blockquote)\b[^>]*>/i';
            $html = (string) preg_replace($breaks, "$0\n", $html);

            // strip, decode, and collapse
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
            $text = (string) preg_replace('/\s*\n\s*/u', "\n", $text);
            return trim($text);
        }
    }
}
