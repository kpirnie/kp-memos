-- ---------------------------------------------------------------------------
-- KP Memos Procedures: nested categories and tags
--
-- every procedure is scoped to p_user_id; nobody can see or touch another
-- user's categories or tags.
--
-- status codes returned by the saving procedures:
--   > 0  success (the row id)
--     0  not found
--    -1  duplicate name
--    -2  invalid parent (missing, not owned, or would create a cycle)
--
-- @since 8.5
-- @author Kevin Pirnie <me@kpirnie.com>
-- @package KP Memos
-- ---------------------------------------------------------------------------

DELIMITER $$

-- list a user's categories flat, with direct note counts; the app builds the tree
DROP PROCEDURE IF EXISTS `kpm_categories_list`$$
CREATE PROCEDURE `kpm_categories_list`(IN p_user_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT c.`id`, c.`parent_id`, c.`name`,
           (SELECT COUNT(*) FROM `kpm_note_categories` nc WHERE nc.`category_id` = c.`id`) AS `note_count`
      FROM `kpm_categories` c
     WHERE c.`user_id` = p_user_id
     ORDER BY c.`name` ASC;
END$$

-- create or update a category
DROP PROCEDURE IF EXISTS `kpm_category_save`$$
CREATE PROCEDURE `kpm_category_save`(
    IN p_user_id INT UNSIGNED,
    IN p_id INT UNSIGNED,
    IN p_parent_id INT UNSIGNED,
    IN p_name VARCHAR(100)
)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_parent INT UNSIGNED DEFAULT NULLIF(p_parent_id, 0);
    DECLARE v_cycle INT DEFAULT 0;

    -- the parent must exist and belong to the user
    IF v_parent IS NOT NULL
        AND NOT EXISTS (SELECT 1 FROM `kpm_categories` WHERE `id` = v_parent AND `user_id` = p_user_id) THEN
        SELECT -2 AS `status`;

    -- updating a category the user doesn't own
    ELSEIF p_id > 0 AND NOT EXISTS (SELECT 1 FROM `kpm_categories` WHERE `id` = p_id AND `user_id` = p_user_id) THEN
        SELECT 0 AS `status`;

    -- sibling names must be unique
    ELSEIF EXISTS (
        SELECT 1 FROM `kpm_categories`
         WHERE `user_id` = p_user_id
           AND `name` = p_name
           AND `parent_id` <=> v_parent
           AND `id` <> p_id
    ) THEN
        SELECT -1 AS `status`;

    ELSEIF p_id > 0 THEN

        -- the new parent can't be the category itself or any of its descendants
        IF v_parent IS NOT NULL THEN
            WITH RECURSIVE `tree` AS (
                SELECT `id` FROM `kpm_categories` WHERE `id` = p_id AND `user_id` = p_user_id
                UNION ALL
                SELECT c.`id` FROM `kpm_categories` c JOIN `tree` t ON c.`parent_id` = t.`id`
            )
            SELECT COUNT(*) INTO v_cycle FROM `tree` WHERE `id` = v_parent;
        END IF;

        IF v_cycle > 0 THEN
            SELECT -2 AS `status`;
        ELSE
            UPDATE `kpm_categories`
               SET `name` = p_name, `parent_id` = v_parent
             WHERE `id` = p_id AND `user_id` = p_user_id;
            SELECT p_id AS `status`;
        END IF;

    ELSE
        INSERT INTO `kpm_categories` (`user_id`, `parent_id`, `name`) VALUES (p_user_id, v_parent, p_name);
        SELECT LAST_INSERT_ID() AS `status`;
    END IF;
END$$

-- delete a category; its children move up to its parent, its notes just lose the link
DROP PROCEDURE IF EXISTS `kpm_category_delete`$$
CREATE PROCEDURE `kpm_category_delete`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_parent INT UNSIGNED DEFAULT NULL;
    DECLARE v_found INT DEFAULT 0;

    START TRANSACTION;

    SELECT COUNT(*), MAX(`parent_id`) INTO v_found, v_parent
      FROM `kpm_categories`
     WHERE `id` = p_id AND `user_id` = p_user_id
       FOR UPDATE;

    IF v_found = 0 THEN
        ROLLBACK;
        SELECT 0 AS `status`;
    ELSE
        UPDATE `kpm_categories` SET `parent_id` = v_parent WHERE `parent_id` = p_id AND `user_id` = p_user_id;
        DELETE FROM `kpm_categories` WHERE `id` = p_id AND `user_id` = p_user_id;
        COMMIT;
        SELECT p_id AS `status`;
    END IF;
END$$

-- list a user's tags with note counts
DROP PROCEDURE IF EXISTS `kpm_tags_list`$$
CREATE PROCEDURE `kpm_tags_list`(IN p_user_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT t.`id`, t.`name`,
           (SELECT COUNT(*) FROM `kpm_note_tags` nt WHERE nt.`tag_id` = t.`id`) AS `note_count`
      FROM `kpm_tags` t
     WHERE t.`user_id` = p_user_id
     ORDER BY t.`name` ASC;
END$$

-- create or rename a tag
DROP PROCEDURE IF EXISTS `kpm_tag_save`$$
CREATE PROCEDURE `kpm_tag_save`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED, IN p_name VARCHAR(64))
    MODIFIES SQL DATA
BEGIN
    IF p_id > 0 AND NOT EXISTS (SELECT 1 FROM `kpm_tags` WHERE `id` = p_id AND `user_id` = p_user_id) THEN
        SELECT 0 AS `status`;
    ELSEIF EXISTS (SELECT 1 FROM `kpm_tags` WHERE `user_id` = p_user_id AND `name` = p_name AND `id` <> p_id) THEN
        SELECT -1 AS `status`;
    ELSEIF p_id > 0 THEN
        UPDATE `kpm_tags` SET `name` = p_name WHERE `id` = p_id AND `user_id` = p_user_id;
        SELECT p_id AS `status`;
    ELSE
        INSERT INTO `kpm_tags` (`user_id`, `name`) VALUES (p_user_id, p_name);
        SELECT LAST_INSERT_ID() AS `status`;
    END IF;
END$$

-- delete a tag
DROP PROCEDURE IF EXISTS `kpm_tag_delete`$$
CREATE PROCEDURE `kpm_tag_delete`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED)
    MODIFIES SQL DATA
BEGIN
    DELETE FROM `kpm_tags` WHERE `id` = p_id AND `user_id` = p_user_id;
    SELECT ROW_COUNT() AS `status`;
END$$

DELIMITER ;
