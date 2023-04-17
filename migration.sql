CREATE TABLE users (
   id INT AUTO_INCREMENT PRIMARY KEY,
   username VARCHAR(255) NOT NULL,
   email VARCHAR(255) NOT NULL UNIQUE,
   validts TIMESTAMP NOT NULL,
   confirmed TINYINT(1) NOT NULL
);

CREATE TABLE emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    checked TINYINT(1) NOT NULL,
    valid TINYINT(1) NOT NULL,
    expiration_notice TINYINT(1) NOT NULL DEFAULT 0
);

DELIMITER //

CREATE PROCEDURE generate_users()
BEGIN
  DECLARE i INT DEFAULT 0;
  DECLARE email_prefix VARCHAR(20);
  DECLARE email_domain VARCHAR(20);
  DECLARE random_email VARCHAR(50);
  DECLARE random_validts TIMESTAMP;
  DECLARE random_confirmed VARCHAR(1);

  WHILE i < 1000000 DO
    SET email_prefix = CONCAT('user', i);
    SET email_domain = 'example.com';
    SET random_email = CONCAT(email_prefix, '@', email_domain);
    SET random_validts = TIMESTAMPADD(DAY, FLOOR(RAND() * 30), NOW());
    SET random_confirmed = ROUND(RAND());

INSERT INTO users (username, email, validts, confirmed)
VALUES (email_prefix, random_email, random_validts, random_confirmed);

SET i = i + 1;
END WHILE;
END //

DELIMITER ;

CALL generate_users();
DROP PROCEDURE generate_users;