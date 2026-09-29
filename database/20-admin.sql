-- ---------------------------------------------------------------------------
-- KP Memos Procedures: user administration
--
-- Admins manage accounts only. Nothing here reads note content.
--
-- status codes returned by the mutating procedures:
--   > 0  success (the affected user id)
--     0  user not found
--    -1  username already taken
--    -2  refused: would leave no active admin
--
-- @since 8.5
-- @author Kevin Pirnie <me@kpirnie.com>
-- @package KP Memos
-- ---------------------------------------------------------------------------

DELIMITER $$

-- list users, paged, with the total row count on every row
DROP PROCEDURE IF EXISTS `kpm_admin_users_list`$$
CREATE PROCEDURE `kpm_admin_users_list`(
    IN p_search VARCHAR(128),
    IN p_offset INT UNSIGNED,
    IN p_limit INT UNSIGNED
)
    READS SQL DATA
BEGIN
    DECLARE v_like VARCHAR(260) DEFAULT NULL;

    -- escape like wildcards in the search term
    IF p_search IS NOT NULL AND p_search <> '' THEN
        SET v_like = CONCAT('%', REPLACE(REPLACE(REPLACE(p_search, '\\', '\\\\'), '%', '\\%'), '_', '\\_'), '%');
    END IF;

    SELECT `id`, `username`, `display_name`, `email`, `role`, `is_active`, `totp_enabled`,
           `must_change_password`, `last_login_at`, `last_login_ip`, `created_at`,
           COUNT(*) OVER () AS `total`
      FROM `kpm_users`
     WHERE v_like IS NULL
        OR `username` LIKE v_like
        OR `display_name` LIKE v_like
        OR `email` LIKE v_like
     ORDER BY `username` ASC
     LIMIT p_offset, p_limit;
END$$

-- create a user
DROP PROCEDURE IF EXISTS `kpm_admin_user_create`$$
CREATE PROCEDURE `kpm_admin_user_create`(
    IN p_username VARCHAR(64),
    IN p_display_name VARCHAR(128),
    IN p_email VARCHAR(255),
    IN p_hash VARCHAR(255),
    IN p_role VARCHAR(16)
)
    MODIFIES SQL DATA
BEGIN
    IF EXISTS (SELECT 1 FROM `kpm_users` WHERE `username` = p_username) THEN
        SELECT -1 AS `status`;
    ELSE
        INSERT INTO `kpm_users` (`username`, `display_name`, `email`, `password_hash`, `role`, `must_change_password`)
        VALUES (p_username, p_display_name, NULLIF(p_email, ''), p_hash,
                IF(p_role = 'admin', 'admin', 'user'), 1);
        SELECT LAST_INSERT_ID() AS `status`;
    END IF;
END$$

-- update a user's account details
DROP PROCEDURE IF EXISTS `kpm_admin_user_update`$$
CREATE PROCEDURE `kpm_admin_user_update`(
    IN p_id INT UNSIGNED,
    IN p_display_name VARCHAR(128),
    IN p_email VARCHAR(255),
    IN p_role VARCHAR(16),
    IN p_is_active TINYINT(1)
)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_role VARCHAR(16) DEFAULT IF(p_role = 'admin', 'admin', 'user');
    DECLARE v_admins INT DEFAULT 0;

    START TRANSACTION;

    -- count the other active admins, locking them so two demotions can't race
    SELECT COUNT(*) INTO v_admins
      FROM `kpm_users`
     WHERE `role` = 'admin' AND `is_active` = 1 AND `id` <> p_id
       FOR UPDATE;

    IF NOT EXISTS (SELECT 1 FROM `kpm_users` WHERE `id` = p_id) THEN
        ROLLBACK;
        SELECT 0 AS `status`;
    ELSEIF v_admins = 0 AND (v_role <> 'admin' OR p_is_active = 0) THEN
        ROLLBACK;
        SELECT -2 AS `status`;
    ELSE
        -- a role or activation change ends the user's sessions
        UPDATE `kpm_users`
           SET `session_version` = `session_version` + IF(`role` <> v_role OR `is_active` <> p_is_active, 1, 0),
               `display_name` = p_display_name,
               `email` = NULLIF(p_email, ''),
               `role` = v_role,
               `is_active` = IF(p_is_active = 1, 1, 0)
         WHERE `id` = p_id;
        COMMIT;
        SELECT p_id AS `status`;
    END IF;
END$$

-- list the stored attachment files belonging to a user, before deleting them
DROP PROCEDURE IF EXISTS `kpm_admin_user_files`$$
CREATE PROCEDURE `kpm_admin_user_files`(IN p_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT `stored_name` FROM `kpm_attachments` WHERE `user_id` = p_id;
END$$

-- delete a user and everything they own
DROP PROCEDURE IF EXISTS `kpm_admin_user_delete`$$
CREATE PROCEDURE `kpm_admin_user_delete`(IN p_id INT UNSIGNED)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_admins INT DEFAULT 0;
    DECLARE v_role VARCHAR(16) DEFAULT NULL;

    START TRANSACTION;

    SELECT `role` INTO v_role FROM `kpm_users` WHERE `id` = p_id FOR UPDATE;
    SELECT COUNT(*) INTO v_admins
      FROM `kpm_users`
     WHERE `role` = 'admin' AND `is_active` = 1 AND `id` <> p_id
       FOR UPDATE;

    IF v_role IS NULL THEN
        ROLLBACK;
        SELECT 0 AS `status`;
    ELSEIF v_role = 'admin' AND v_admins = 0 THEN
        ROLLBACK;
        SELECT -2 AS `status`;
    ELSE
        DELETE FROM `kpm_users` WHERE `id` = p_id;
        COMMIT;
        SELECT p_id AS `status`;
    END IF;
END$$

-- count active admins; used by the cli bootstrap
DROP PROCEDURE IF EXISTS `kpm_admin_count`$$
CREATE PROCEDURE `kpm_admin_count`()
    READS SQL DATA
BEGIN
    SELECT COUNT(*) AS `status` FROM `kpm_users` WHERE `role` = 'admin' AND `is_active` = 1;
END$$

DELIMITER ;
