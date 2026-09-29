-- ---------------------------------------------------------------------------
-- KP Memos Procedures: users, authentication, totp, recovery codes, audit
--
-- @since 8.5
-- @author Kevin Pirnie <me@kpirnie.com>
-- @package KP Memos
-- ---------------------------------------------------------------------------

DELIMITER $$

-- get the credentials needed to authenticate a username
DROP PROCEDURE IF EXISTS `kpm_user_auth_get`$$
CREATE PROCEDURE `kpm_user_auth_get`(IN p_username VARCHAR(64))
    READS SQL DATA
BEGIN
    SELECT `id`, `username`, `password_hash`, `role`, `is_active`, `totp_enabled`,
           `must_change_password`, `session_version`
      FROM `kpm_users`
     WHERE `username` = p_username
     LIMIT 1;
END$$

-- get a user's session state; used on every authenticated request
DROP PROCEDURE IF EXISTS `kpm_user_get`$$
CREATE PROCEDURE `kpm_user_get`(IN p_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT `id`, `username`, `display_name`, `email`, `role`, `is_active`, `must_change_password`,
           `totp_enabled`, `session_version`, `last_login_at`, `last_login_ip`, `created_at`
      FROM `kpm_users`
     WHERE `id` = p_id
     LIMIT 1;
END$$

-- get a user's password hash, for re-authentication
DROP PROCEDURE IF EXISTS `kpm_user_password_get`$$
CREATE PROCEDURE `kpm_user_password_get`(IN p_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT `password_hash` FROM `kpm_users` WHERE `id` = p_id LIMIT 1;
END$$

-- set a user's password hash; invalidates every other session
DROP PROCEDURE IF EXISTS `kpm_user_password_set`$$
CREATE PROCEDURE `kpm_user_password_set`(
    IN p_id INT UNSIGNED,
    IN p_hash VARCHAR(255),
    IN p_must_change TINYINT(1)
)
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_users`
       SET `password_hash` = p_hash,
           `must_change_password` = p_must_change,
           `session_version` = `session_version` + 1
     WHERE `id` = p_id;
    SELECT `session_version` AS `status` FROM `kpm_users` WHERE `id` = p_id;
END$$

-- rehash a password transparently (algorithm / cost upgrade); sessions stay valid
DROP PROCEDURE IF EXISTS `kpm_user_password_rehash`$$
CREATE PROCEDURE `kpm_user_password_rehash`(IN p_id INT UNSIGNED, IN p_hash VARCHAR(255))
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_users` SET `password_hash` = p_hash WHERE `id` = p_id;
    SELECT ROW_COUNT() AS `status`;
END$$

-- record a successful login
DROP PROCEDURE IF EXISTS `kpm_user_login_record`$$
CREATE PROCEDURE `kpm_user_login_record`(IN p_id INT UNSIGNED, IN p_ip VARCHAR(45))
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_users` SET `last_login_at` = NOW(), `last_login_ip` = p_ip WHERE `id` = p_id;
    SELECT ROW_COUNT() AS `status`;
END$$

-- update a user's own profile
DROP PROCEDURE IF EXISTS `kpm_user_profile_set`$$
CREATE PROCEDURE `kpm_user_profile_set`(
    IN p_id INT UNSIGNED,
    IN p_display_name VARCHAR(128),
    IN p_email VARCHAR(255)
)
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_users`
       SET `display_name` = p_display_name, `email` = NULLIF(p_email, '')
     WHERE `id` = p_id;
    SELECT COUNT(*) AS `status` FROM `kpm_users` WHERE `id` = p_id;
END$$

-- get a user's totp state
DROP PROCEDURE IF EXISTS `kpm_user_totp_get`$$
CREATE PROCEDURE `kpm_user_totp_get`(IN p_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT `totp_secret`, `totp_enabled`, `totp_last_step` FROM `kpm_users` WHERE `id` = p_id LIMIT 1;
END$$

-- enable totp with a verified, encrypted secret
DROP PROCEDURE IF EXISTS `kpm_user_totp_enable`$$
CREATE PROCEDURE `kpm_user_totp_enable`(
    IN p_id INT UNSIGNED,
    IN p_secret VARCHAR(255),
    IN p_step BIGINT UNSIGNED
)
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_users`
       SET `totp_secret` = p_secret,
           `totp_enabled` = 1,
           `totp_last_step` = p_step,
           `session_version` = `session_version` + 1
     WHERE `id` = p_id AND `totp_enabled` = 0;
    SELECT ROW_COUNT() AS `status`;
END$$

-- claim a totp time step; refuses replays of the same or an older step
DROP PROCEDURE IF EXISTS `kpm_user_totp_step_claim`$$
CREATE PROCEDURE `kpm_user_totp_step_claim`(IN p_id INT UNSIGNED, IN p_step BIGINT UNSIGNED)
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_users`
       SET `totp_last_step` = p_step
     WHERE `id` = p_id
       AND `totp_enabled` = 1
       AND (`totp_last_step` IS NULL OR `totp_last_step` < p_step);
    SELECT ROW_COUNT() AS `status`;
END$$

-- remove totp and recovery codes; the user must enroll again on next login
DROP PROCEDURE IF EXISTS `kpm_user_totp_reset`$$
CREATE PROCEDURE `kpm_user_totp_reset`(IN p_id INT UNSIGNED)
    MODIFIES SQL DATA
BEGIN
    START TRANSACTION;
    DELETE FROM `kpm_recovery_codes` WHERE `user_id` = p_id;
    UPDATE `kpm_users`
       SET `totp_secret` = NULL,
           `totp_enabled` = 0,
           `totp_last_step` = NULL,
           `session_version` = `session_version` + 1
     WHERE `id` = p_id;
    SELECT ROW_COUNT() AS `status`;
    COMMIT;
END$$

-- replace a user's recovery codes; p_hashes is a comma separated list of 64 char hex hashes
DROP PROCEDURE IF EXISTS `kpm_recovery_codes_replace`$$
CREATE PROCEDURE `kpm_recovery_codes_replace`(IN p_user_id INT UNSIGNED, IN p_hashes TEXT)
    MODIFIES SQL DATA
BEGIN
    DECLARE v_rest TEXT DEFAULT p_hashes;
    DECLARE v_hash VARCHAR(64);
    DECLARE v_count INT DEFAULT 0;

    START TRANSACTION;
    DELETE FROM `kpm_recovery_codes` WHERE `user_id` = p_user_id;

    -- walk the list
    WHILE v_rest IS NOT NULL AND v_rest <> '' DO
        SET v_hash = SUBSTRING_INDEX(v_rest, ',', 1);
        SET v_rest = IF(LOCATE(',', v_rest) > 0, SUBSTRING(v_rest, LOCATE(',', v_rest) + 1), '');
        IF v_hash REGEXP '^[a-f0-9]{64}$' THEN
            INSERT INTO `kpm_recovery_codes` (`user_id`, `code_hash`) VALUES (p_user_id, v_hash);
            SET v_count = v_count + 1;
        END IF;
    END WHILE;

    COMMIT;
    SELECT v_count AS `status`;
END$$

-- consume a recovery code; returns 1 when it was valid and unused
DROP PROCEDURE IF EXISTS `kpm_recovery_code_use`$$
CREATE PROCEDURE `kpm_recovery_code_use`(IN p_user_id INT UNSIGNED, IN p_hash CHAR(64))
    MODIFIES SQL DATA
BEGIN
    UPDATE `kpm_recovery_codes`
       SET `used_at` = NOW()
     WHERE `user_id` = p_user_id AND `code_hash` = p_hash AND `used_at` IS NULL
     LIMIT 1;
    SELECT ROW_COUNT() AS `status`;
END$$

-- count a user's unused recovery codes
DROP PROCEDURE IF EXISTS `kpm_recovery_codes_remaining`$$
CREATE PROCEDURE `kpm_recovery_codes_remaining`(IN p_user_id INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT COUNT(*) AS `status` FROM `kpm_recovery_codes` WHERE `user_id` = p_user_id AND `used_at` IS NULL;
END$$

-- write an audit event
DROP PROCEDURE IF EXISTS `kpm_audit_add`$$
CREATE PROCEDURE `kpm_audit_add`(
    IN p_user_id INT UNSIGNED,
    IN p_event VARCHAR(64),
    IN p_ip VARCHAR(45),
    IN p_user_agent VARCHAR(255),
    IN p_detail VARCHAR(1024)
)
    MODIFIES SQL DATA
BEGIN
    INSERT INTO `kpm_audit_log` (`user_id`, `event`, `ip`, `user_agent`, `detail`)
    VALUES (NULLIF(p_user_id, 0), p_event, p_ip, p_user_agent, p_detail);
    SELECT LAST_INSERT_ID() AS `status`;
END$$

-- list a user's recent audit events
DROP PROCEDURE IF EXISTS `kpm_audit_list`$$
CREATE PROCEDURE `kpm_audit_list`(IN p_user_id INT UNSIGNED, IN p_limit INT UNSIGNED)
    READS SQL DATA
BEGIN
    SELECT `event`, `ip`, `user_agent`, `detail`, `created_at`
      FROM `kpm_audit_log`
     WHERE `user_id` = p_user_id
     ORDER BY `id` DESC
     LIMIT p_limit;
END$$

DELIMITER ;
