-- ---------------------------------------------------------------------------
-- KP Memos Procedures: attachments and public share links
--
-- owner procedures are scoped to p_user_id. the share procedures only ever
-- return data for a note that is public, carries the given token, and has
-- not expired.
--
-- @since 8.5
-- @author Kevin Pirnie <me@kpirnie.com>
-- @package KP Memos
-- ---------------------------------------------------------------------------

DELIMITER $$

-- record an uploaded attachment
DROP PROCEDURE IF EXISTS `kpm_attachment_add`$$
CREATE PROCEDURE `kpm_attachment_add`(
    IN p_user_id INT UNSIGNED,
    IN p_note_id INT UNSIGNED,
    IN p_original_name VARCHAR(255),
    IN p_stored_name CHAR(64),
    IN p_mime_type VARCHAR(127),
    IN p_size_bytes BIGINT UNSIGNED,
    IN p_sha256 CHAR(64)
)
    MODIFIES SQL DATA
BEGIN
    IF NOT EXISTS (SELECT 1 FROM `kpm_notes` WHERE `id` = p_note_id AND `user_id` = p_user_id) THEN
        SELECT 0 AS `status`;
    ELSE
        INSERT INTO `kpm_attachments`
               (`note_id`, `user_id`, `original_name`, `stored_name`, `mime_type`, `size_bytes`, `sha256`)
        VALUES (p_note_id, p_user_id, p_original_name, p_stored_name, p_mime_type, p_size_bytes, p_sha256);
        SELECT LAST_INSERT_ID() AS `status`;
    END IF;
END$$

-- list a note's attachments
DROP PROCEDURE IF EXISTS `kpm_attachments_list`$$
CREATE PROCEDURE `kpm_attachments_list`(IN p_user_id INT UNSIGNED, IN p_note_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT a.`id`, a.`original_name`, a.`mime_type`, a.`size_bytes`, a.`created_at`
      FROM `kpm_attachments` a
     WHERE a.`note_id` = p_note_id AND a.`user_id` = p_user_id
     ORDER BY a.`id` ASC;
END$$

-- get one attachment for download
DROP PROCEDURE IF EXISTS `kpm_attachment_get`$$
CREATE PROCEDURE `kpm_attachment_get`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT `id`, `note_id`, `original_name`, `stored_name`, `mime_type`, `size_bytes`, `sha256`
      FROM `kpm_attachments`
     WHERE `id` = p_id AND `user_id` = p_user_id
     LIMIT 1;
END$$

-- delete an attachment; returns its stored name so the app can remove the file
DROP PROCEDURE IF EXISTS `kpm_attachment_delete`$$
CREATE PROCEDURE `kpm_attachment_delete`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_stored CHAR(64) DEFAULT NULL;

    SELECT `stored_name` INTO v_stored
      FROM `kpm_attachments`
     WHERE `id` = p_id AND `user_id` = p_user_id
     LIMIT 1;

    IF v_stored IS NOT NULL THEN
        DELETE FROM `kpm_attachments` WHERE `id` = p_id AND `user_id` = p_user_id;
    END IF;

    SELECT v_stored AS `stored_name`;
END$$

-- update a note's sharing settings; the token is only used when the note has none yet
--   p_password_mode: 0 keep the current password, 1 set p_password_hash, 2 remove it
DROP PROCEDURE IF EXISTS `kpm_note_share_set`$$
CREATE PROCEDURE `kpm_note_share_set`(
    IN p_user_id INT UNSIGNED,
    IN p_id INT UNSIGNED,
    IN p_is_public TINYINT(1),
    IN p_token VARCHAR(64),
    IN p_expires_at DATETIME,
    IN p_password_mode TINYINT,
    IN p_password_hash VARCHAR(255)
)
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_notes`
       SET `is_public` = IF(p_is_public = 1, 1, 0),
           `share_token` = IFNULL(`share_token`, p_token),
           `share_expires_at` = p_expires_at,
           `share_password_hash` = CASE p_password_mode
                                       WHEN 1 THEN p_password_hash
                                       WHEN 2 THEN NULL
                                       ELSE `share_password_hash`
                                   END
     WHERE `id` = p_id AND `user_id` = p_user_id;

    SELECT `share_token`, `is_public`, `share_expires_at`, (`share_password_hash` IS NOT NULL) AS `share_has_password`
      FROM `kpm_notes`
     WHERE `id` = p_id AND `user_id` = p_user_id;
END$$

-- replace a note's share token, killing the old link
DROP PROCEDURE IF EXISTS `kpm_note_share_regenerate`$$
CREATE PROCEDURE `kpm_note_share_regenerate`(IN p_user_id INT UNSIGNED, IN p_id INT UNSIGNED, IN p_token VARCHAR(64))
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_notes` SET `share_token` = p_token WHERE `id` = p_id AND `user_id` = p_user_id;
    SELECT `share_token` FROM `kpm_notes` WHERE `id` = p_id AND `user_id` = p_user_id;
END$$

-- get a shared note by token
DROP PROCEDURE IF EXISTS `kpm_share_get`$$
CREATE PROCEDURE `kpm_share_get`(IN p_token VARCHAR(64))
    READS SQL DATA
BEGIN
    SELECT n.`id`, n.`title`, n.`body_html`, n.`share_password_hash`, n.`share_expires_at`,
           n.`created_at`, n.`updated_at`
      FROM `kpm_notes` n
     WHERE n.`share_token` = p_token
       AND n.`is_public` = 1
       AND (n.`share_expires_at` IS NULL OR n.`share_expires_at` > UTC_TIMESTAMP())
     LIMIT 1;
END$$

-- list a shared note's attachments
DROP PROCEDURE IF EXISTS `kpm_share_attachments`$$
CREATE PROCEDURE `kpm_share_attachments`(IN p_token VARCHAR(64))
    READS SQL DATA
BEGIN
    SELECT a.`id`, a.`original_name`, a.`mime_type`, a.`size_bytes`
      FROM `kpm_attachments` a
      JOIN `kpm_notes` n ON n.`id` = a.`note_id`
     WHERE n.`share_token` = p_token
       AND n.`is_public` = 1
       AND (n.`share_expires_at` IS NULL OR n.`share_expires_at` > UTC_TIMESTAMP())
     ORDER BY a.`id` ASC;
END$$

-- get one attachment of a shared note for download
DROP PROCEDURE IF EXISTS `kpm_share_attachment_get`$$
CREATE PROCEDURE `kpm_share_attachment_get`(IN p_token VARCHAR(64), IN p_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT a.`id`, a.`original_name`, a.`stored_name`, a.`mime_type`, a.`size_bytes`, a.`sha256`
      FROM `kpm_attachments` a
      JOIN `kpm_notes` n ON n.`id` = a.`note_id`
     WHERE a.`id` = p_id
       AND n.`share_token` = p_token
       AND n.`is_public` = 1
       AND (n.`share_expires_at` IS NULL OR n.`share_expires_at` > UTC_TIMESTAMP())
     LIMIT 1;
END$$

DELIMITER ;
