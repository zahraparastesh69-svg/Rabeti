UPDATE settings SET `value` = 'e299e52ddccc32029350a5764f6cd41094d62a21' WHERE `key` = 'asa_sms_token';
UPDATE settings SET `key` = 'sms_api_key' WHERE `key` = 'asa_sms_token';
DELETE FROM settings WHERE `key` = 'sms_provider';
--INSERT INTO settings (`key`, `value`) VALUES ('sms_api_key', 'e299e52ddccc32029350a5764f6cd41094d62a21') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);