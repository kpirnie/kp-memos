-- ---------------------------------------------------------------------------
-- KP Memos Procedures: notes, pinning, and search
--
-- every procedure is scoped to p_user_id; nobody can see or touch another
-- user's notes.
--
-- status codes returned by the mutating procedures:
--   > 0  success (the note id)
--     0  not found / not owned
--
-- @since 8.5
-- @author Kevin Pirnie <me@kpirnie.com>
-- @package KP Memos
-- ---------------------------------------------------------------------------

DELIMITER $$

-- pin the connection to utc; called once per connection by the app
DROP PROCEDURE IF EXISTS `kpm_session_init`$$
CREATE PROCEDURE `kpm_session_init`()
    NO SQL
BEGIN
    SET time_zone = '+00:00';
    SELECT 1 AS `status`;
END$$

-- search / filter / page a user's notes
--
-- p_ft          boolean-mode fulltext query built by the app, or ''
-- p_like        plain fallback term for terms fulltext can't index, or ''
-- p_category_id category to filter by, including its descendants, or 0
-- p_tag_ids     comma separated tag ids; a note must carry all of them, or ''
-- p_visibility  'all', 'public', or 'private'
-- p_date_field  'created' or 'updated'
-- p_date_from   inclusive utc lower bound, or null
-- p_date_to     exclusive utc upper bound, or null
-- p_pinned      1 for pinned notes only, 0 for unpinned notes only
-- p_sort        'relevance', 'updated', 'oldest', 'created', or 'title'
DROP PROCEDURE IF EXISTS `kpm_notes_list`$$
CREATE PROCEDURE `kpm_notes_list`(
    IN p_user_id INT UNSIGNED,
    IN p_ft VARCHAR(512),
    IN p_like VARCHAR(128),
    IN p_category_id INT UNSIGNED,
    IN p_tag_ids VARCHAR(1024),
    IN p_visibility VARCHAR(8),
    IN p_date_field VARCHAR(8),
    IN p_date_from DATETIME,
    IN p_date_to DATETIME,
    IN p_pinned TINYINT(1),
    IN p_sort VARCHAR(16),
    IN p_offset INT UNSIGNED,
    IN p_limit INT UNSIGNED
)
    READS SQL DATA
BEGIN
    DECLARE v_cat_ids TEXT DEFAULT NULL;
    DECLARE v_tag_count INT DEFAULT 0;
    DECLARE v_like VARCHAR(300) DEFAULT NULL;
    DECLARE v_ft VARCHAR(512) DEFAULT NULLIF(p_ft, '');

    -- expand the category into itself plus every descendant the user owns
    IF p_category_id > 0 THEN
        WITH RECURSIVE `tree` AS (
            SELECT `id` FROM `kpm_categories` WHERE `id` = p_category_id AND `user_id` = p_user_id
            UNION ALL
            SELECT c.`id` FROM `kpm_categories` c JOIN `tree` t ON c.`parent_id` = t.`id`
        )
        SELECT IFNULL(GROUP_CONCAT(`id`), '') INTO v_cat_ids FROM `tree`;
    END IF;

    -- count the requested tags
    IF p_tag_ids IS NOT NULL AND p_tag_ids <> '' THEN
        SET v_tag_count = LENGTH(p_tag_ids) - LENGTH(REPLACE(p_tag_ids, ',', '')) + 1;
    END IF;

    -- escape like wildcards in the fallback term
    IF v_ft IS NULL AND p_like IS NOT NULL AND p_like <> '' THEN
        SET v_like = CONCAT('%', REPLACE(REPLACE(REPLACE(p_like, '\\', '\\\\'), '%', '\\%'), '_', '\\_'), '%');
    END IF;

    SELECT n.`id`, n.`title`, LEFT(n.`body_text`, 320) AS `excerpt`,
           n.`is_pinned`, n.`pin_order`, n.`is_public`, n.`share_expires_at`,
           (n.`share_password_hash` IS NOT NULL) AS `share_has_password`,
           n.`created_at`, n.`updated_at`,
           (SELECT COUNT(*) FROM `kpm_attachments` a WHERE a.`note_id` = n.`id`) AS `attachment_count`,
           (SELECT GROUP_CONCAT(nc.`category_id`) FROM `kpm_note_categories` nc WHERE nc.`note_id` = n.`id`)
               AS `category_ids`,
           (SELECT GROUP_CONCAT(nt.`tag_id`) FROM `kpm_note_tags` nt WHERE nt.`note_id` = n.`id`) AS `tag_ids`,
           IF(v_ft IS NULL, 0, MATCH (n.`title`, n.`body_text`) AGAINST (v_ft IN BOOLEAN MODE)) AS `score`,
           COUNT(*) OVER () AS `total`
      FROM `kpm_notes` n
     WHERE n.`user_id` = p_user_id
       AND n.`is_pinned` = IF(p_pinned = 1, 1, 0)
       AND (v_ft IS NULL OR MATCH (n.`title`, n.`body_text`) AGAINST (v_ft IN BOOLEAN MODE))
       AND (v_like IS NULL OR n.`title` LIKE v_like OR n.`body_text` LIKE v_like)
       AND (p_category_id = 0 OR EXISTS (
               SELECT 1 FROM `kpm_note_categories` nc
                WHERE nc.`note_id` = n.`id` AND FIND_IN_SET(nc.`category_id`, v_cat_ids)))
       AND (v_tag_count = 0 OR (
               SELECT COUNT(DISTINCT nt.`tag_id`) FROM `kpm_note_tags` nt
                WHERE nt.`note_id` = n.`id` AND FIND_IN_SET(nt.`tag_id`, p_tag_ids)) = v_tag_count)
       AND (p_visibility NOT IN ('public', 'private') OR n.`is_public` = IF(p_visibility = 'public', 1, 0))
       AND (p_date_from IS NULL OR IF(p_date_field = 'created', n.`created_at`, n.`updated_at`) >= p_date_from)
       AND (p_date_to IS NULL OR IF(p_date_field = 'created', n.`created_at`, n.`updated_at`) < p_date_to)
     ORDER BY
           CASE WHEN p_pinned = 1 THEN n.`pin_order` END ASC,
           CASE WHEN p_sort = 'relevance' AND v_ft IS NOT NULL
                THEN MATCH (n.`title`, n.`body_text`) AGAINST (v_ft IN BOOLEAN MODE) END DESC,
           CASE WHEN p_sort = 'title' THEN n.`title` END ASC,
           CASE WHEN p_sort = 'created' THEN n.`created_at` END DESC,
           CASE WHEN p_sort = 'oldest' THEN n.`updated_at` END ASC,
           n.`updated_at` DESC,
           n.`id` DESC
     LIMIT p_offset, p_limit;
END$$

-- get one note with its category and tag ids
DROP PROCEDURE IF EXISTS `kpm_note_get`$$
CREATE PROCEDURE `kpm_note_get`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT n.`id`, n.`title`, n.`body_html`, n.`is_pinned`, n.`is_public`, n.`share_token`,
           n.`share_expires_at`, (n.`share_password_hash` IS NOT NULL) AS `share_has_password`,
           n.`created_at`, n.`updated_at`,
           (SELECT GROUP_CONCAT(nc.`category_id`) FROM `kpm_note_categories` nc WHERE nc.`note_id` = n.`id`)
               AS `category_ids`,
           (SELECT GROUP_CONCAT(nt.`tag_id`) FROM `kpm_note_tags` nt WHERE nt.`note_id` = n.`id`) AS `tag_ids`
      FROM `kpm_notes` n
     WHERE n.`id` = p_id AND n.`user_id` = p_user_id
     LIMIT 1;
END$$

-- create a note
DROP PROCEDURE IF EXISTS `kpm_note_create`$$
CREATE PROCEDURE `kpm_note_create`(
    IN p_user_id INT UNSIGNED,
    IN p_title VARCHAR(255),
    IN p_body_html MEDIUMTEXT,
    IN p_body_text MEDIUMTEXT
)
    MODIFIES SQL DATA
BEGIN
    INSERT INTO `kpm_notes` (`user_id`, `title`, `body_html`, `body_text`)
    VALUES (p_user_id, p_title, p_body_html, p_body_text);
    SELECT LAST_INSERT_ID() AS `status`;
END$$

-- update a note's content
DROP PROCEDURE IF EXISTS `kpm_note_update`$$
CREATE PROCEDURE `kpm_note_update`(
    IN p_user_id INT UNSIGNED,
    IN p_id INT UNSIGNED,
    IN p_title VARCHAR(255),
    IN p_body_html MEDIUMTEXT,
    IN p_body_text MEDIUMTEXT
)
    MODIFIES SQL DATA
BEGIN
    IF NOT EXISTS (SELECT 1 FROM `kpm_notes` WHERE `id` = p_id AND `user_id` = p_user_id) THEN
        SELECT 0 AS `status`;
    ELSE
        UPDATE `kpm_notes`
           SET `title` = p_title, `body_html` = p_body_html, `body_text` = p_body_text, `updated_at` = NOW()
         WHERE `id` = p_id AND `user_id` = p_user_id;
        SELECT p_id AS `status`;
    END IF;
END$$

-- replace a note's categories; p_ids is a comma separated list, unowned ids are ignored
DROP PROCEDURE IF EXISTS `kpm_note_categories_set`$$
CREATE PROCEDURE `kpm_note_categories_set`(IN p_user_id INT UNSIGNED, IN p_note_id INT UNSIGNED, IN p_ids TEXT)
    MODIFIES SQL DATA
BEGIN
    IF NOT EXISTS (SELECT 1 FROM `kpm_notes` WHERE `id` = p_note_id AND `user_id` = p_user_id) THEN
        SELECT 0 AS `status`;
    ELSE
        START TRANSACTION;
        DELETE FROM `kpm_note_categories` WHERE `note_id` = p_note_id;
        INSERT INTO `kpm_note_categories` (`note_id`, `category_id`)
             SELECT p_note_id, `id` FROM `kpm_categories`
              WHERE `user_id` = p_user_id AND FIND_IN_SET(`id`, IFNULL(p_ids, ''));
        COMMIT;
        SELECT p_note_id AS `status`;
    END IF;
END$$

-- replace a note's tags by name, creating missing tags;
-- p_names is a list separated by the ascii unit separator, char(31)
DROP PROCEDURE IF EXISTS `kpm_note_tags_set`$$
CREATE PROCEDURE `kpm_note_tags_set`(IN p_user_id INT UNSIGNED, IN p_note_id INT UNSIGNED, IN p_names TEXT)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_rest TEXT DEFAULT IFNULL(p_names, '');
    DECLARE v_name VARCHAR(64);
    DECLARE v_pos INT;
    DECLARE v_tag_id INT UNSIGNED;

    IF NOT EXISTS (SELECT 1 FROM `kpm_notes` WHERE `id` = p_note_id AND `user_id` = p_user_id) THEN
        SELECT 0 AS `status`;
    ELSE
        START TRANSACTION;
        DELETE FROM `kpm_note_tags` WHERE `note_id` = p_note_id;

        -- walk the list
        WHILE v_rest <> '' DO
            SET v_pos = LOCATE(CHAR(31), v_rest);
            SET v_name = TRIM(IF(v_pos > 0, LEFT(v_rest, v_pos - 1), v_rest));
            SET v_rest = IF(v_pos > 0, SUBSTRING(v_rest, v_pos + 1), '');

            IF v_name <> '' THEN
                INSERT IGNORE INTO `kpm_tags` (`user_id`, `name`) VALUES (p_user_id, v_name);
                SELECT `id` INTO v_tag_id FROM `kpm_tags` WHERE `user_id` = p_user_id AND `name` = v_name LIMIT 1;
                INSERT IGNORE INTO `kpm_note_tags` (`note_id`, `tag_id`) VALUES (p_note_id, v_tag_id);
            END IF;
        END WHILE;

        COMMIT;
        SELECT p_note_id AS `status`;
    END IF;
END$$

-- delete a note; returns the stored names of its attachment files so the app can remove them
DROP PROCEDURE IF EXISTS `kpm_note_delete`$$
CREATE PROCEDURE `kpm_note_delete`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED)
    MODIFIES SQL DATA
BEGIN
    DROP TEMPORARY TABLE IF EXISTS `kpm_tmp_files`;
    CREATE TEMPORARY TABLE `kpm_tmp_files` (`stored_name` CHAR(64) NOT NULL);

    START TRANSACTION;
    INSERT INTO `kpm_tmp_files` (`stored_name`)
         SELECT a.`stored_name` FROM `kpm_attachments` a
           JOIN `kpm_notes` n ON n.`id` = a.`note_id`
          WHERE n.`id` = p_id AND n.`user_id` = p_user_id;
    DELETE FROM `kpm_notes` WHERE `id` = p_id AND `user_id` = p_user_id;
    COMMIT;

    SELECT `stored_name` FROM `kpm_tmp_files`;
    DROP TEMPORARY TABLE IF EXISTS `kpm_tmp_files`;
END$$

-- pin or unpin a note; newly pinned notes go to the end of the pinned list
DROP PROCEDURE IF EXISTS `kpm_note_pin_set`$$
CREATE PROCEDURE `kpm_note_pin_set`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED, IN p_pinned TINYINT(1))
    MODIFIES SQL DATA
BEGIN
    DECLARE v_next INT DEFAULT 0;

    IF NOT EXISTS (SELECT 1 FROM `kpm_notes` WHERE `id` = p_id AND `user_id` = p_user_id) THEN
        SELECT 0 AS `status`;
    ELSE
        SELECT IFNULL(MAX(`pin_order`), 0) + 1 INTO v_next
          FROM `kpm_notes` WHERE `user_id` = p_user_id AND `is_pinned` = 1;
        UPDATE `kpm_notes`
           SET `pin_order` = IF(p_pinned = 1, IF(`is_pinned` = 1, `pin_order`, v_next), 0),
               `is_pinned` = IF(p_pinned = 1, 1, 0)
         WHERE `id` = p_id AND `user_id` = p_user_id;
        SELECT p_id AS `status`;
    END IF;
END$$

-- reorder pinned notes; p_ids is the comma separated pinned note ids in their new order
DROP PROCEDURE IF EXISTS `kpm_notes_pin_order`$$
CREATE PROCEDURE `kpm_notes_pin_order`(IN p_user_id INT UNSIGNED, IN p_ids TEXT)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_rest TEXT DEFAULT IFNULL(p_ids, '');
    DECLARE v_id VARCHAR(16);
    DECLARE v_pos INT DEFAULT 0;
    DECLARE v_count INT DEFAULT 0;

    START TRANSACTION;
    WHILE v_rest <> '' DO
        SET v_id = SUBSTRING_INDEX(v_rest, ',', 1);
        SET v_rest = IF(LOCATE(',', v_rest) > 0, SUBSTRING(v_rest, LOCATE(',', v_rest) + 1), '');
        IF v_id REGEXP '^[0-9]{1,10}$' THEN
            SET v_pos = v_pos + 1;
            UPDATE `kpm_notes`
               SET `pin_order` = v_pos
             WHERE `id` = CAST(v_id AS UNSIGNED) AND `user_id` = p_user_id AND `is_pinned` = 1;
            SET v_count = v_count + ROW_COUNT();
        END IF;
    END WHILE;
    COMMIT;

    SELECT v_count AS `status`;
END$$

DELIMITER ;
