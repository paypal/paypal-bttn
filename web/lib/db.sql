CREATE DATABASE button;

CREATE TABLE button.`button_user` (
  `email` varchar(200) NOT NULL,
  `consumer_stage` varchar(45) DEFAULT NULL COMMENT 'ONBOARDED, ACTIVE, DELETED',
  `button_id` varchar(120) DEFAULT NULL,
  `customer_id` varchar(45) DEFAULT NULL,
  `amount_to_charge` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE USER 'button'@'127.0.0.1' IDENTIFIED BY 'button1';
GRANT ALL PRIVILEGES ON *.* TO 'button'@'127.0.0.1';
FLUSH privileges;
