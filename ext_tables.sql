#
# Table structure for table 'tx_pastecode_code'
#
CREATE TABLE tx_pastecode_code (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
    problem tinyint(4) DEFAULT '0' NOT NULL,
	title tinytext,
	language tinytext,
	poster tinytext,
	code text,
	tags text,
	description text,
	links tinytext,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);
