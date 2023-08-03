DROP TABLE IF EXISTS `field_email`;
CREATE TABLE `field_email` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` varchar(250) NOT NULL default '',
  PRIMARY KEY  (`pages_id`),
  KEY `data_exact` (`data`),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `field_email` (`pages_id`, `data`) VALUES (41,'');

DROP TABLE IF EXISTS `field_pass`;
CREATE TABLE `field_pass` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` char(40) NOT NULL,
  `salt` char(32) NOT NULL,
  PRIMARY KEY  (`pages_id`),
  KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii;
INSERT INTO `field_pass` (`pages_id`, `data`, `salt`) VALUES (41,'','');
INSERT INTO `field_pass` (`pages_id`, `data`, `salt`) VALUES (40,'','');

DROP TABLE IF EXISTS `field_permissions`;
CREATE TABLE `field_permissions` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` int(11) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`pages_id`,`sort`),
  KEY `data` (`data`,`pages_id`,`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,32,1);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,34,2);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,35,3);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (37,36,0);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,36,0);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,50,4);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,51,5);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,52,7);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,53,8);
INSERT INTO `field_permissions` (`pages_id`, `data`, `sort`) VALUES (38,54,6);

DROP TABLE IF EXISTS `field_process`;
CREATE TABLE `field_process` (
  `pages_id` int(11) NOT NULL default '0',
  `data` int(11) NOT NULL default '0',
  PRIMARY KEY  (`pages_id`),
  KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (6,17);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (3,12);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (8,12);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (9,14);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (10,7);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (11,47);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (16,48);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (300,104);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (21,50);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (29,66);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (23,10);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (304,138);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (31,136);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (22,76);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (30,68);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (303,129);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (2,87);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (302,121);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (301,109);
INSERT INTO `field_process` (`pages_id`, `data`) VALUES (28,76);

DROP TABLE IF EXISTS `field_roles`;
CREATE TABLE `field_roles` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` int(11) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`pages_id`,`sort`),
  KEY `data` (`data`,`pages_id`,`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `field_roles` (`pages_id`, `data`, `sort`) VALUES (40,37,0);
INSERT INTO `field_roles` (`pages_id`, `data`, `sort`) VALUES (41,37,0);
INSERT INTO `field_roles` (`pages_id`, `data`, `sort`) VALUES (41,38,2);

DROP TABLE IF EXISTS `field_title`;
CREATE TABLE `field_title` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY  (`pages_id`),
  KEY `data_exact` (`data`(255)),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_title` (`pages_id`, `data`) VALUES (11,'Templates');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (16,'Fields');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (22,'Setup');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (3,'Pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (6,'Add Page');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (8,'Page List');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (9,'Save Sort');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (10,'Edit Page');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (21,'Modules');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (29,'Users');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (30,'Roles');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (2,'Admin');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (7,'Trash');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (27,'404 Page Not Found');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (302,'Insert Link');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (23,'Login');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (304,'Profile');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (301,'Empty Trash');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (300,'Search');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (303,'Insert Image');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (28,'Access');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (31,'Permissions');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (32,'Edit pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (34,'Delete pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (35,'Move pages (change parent)');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (36,'View pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (50,'Sort child pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (51,'Change templates on pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (52,'Administer users');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (53,'User can update profile/password');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES (54,'Lock or unlock a page');

DROP TABLE IF EXISTS `templates`;
CREATE TABLE `templates` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(250) character set ascii NOT NULL,
  `fieldgroups_id` int(10) unsigned NOT NULL default '0',
  `flags` int(11) NOT NULL default '0',
  `cache_time` mediumint(9) NOT NULL default '0',
  `data` text NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fieldgroups_id` (`fieldgroups_id`)
) ENGINE=MyISAM AUTO_INCREMENT=43 DEFAULT CHARSET=utf8;
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES (2,'admin',2,8,0,'{\"useRoles\":1,\"parentTemplates\":[2],\"allowPageNum\":1,\"redirectLogin\":23,\"slashUrls\":1,\"noGlobal\":1}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES (3,'user',3,8,0,'{\"useRoles\":1,\"noChildren\":1,\"parentTemplates\":[2],\"slashUrls\":1,\"pageClass\":\"User\",\"noGlobal\":1,\"noMove\":1,\"noTrash\":1,\"noSettings\":1,\"noChangeTemplate\":1,\"nameContentTab\":1}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES (4,'role',4,8,0,'{\"noChildren\":1,\"parentTemplates\":[2],\"slashUrls\":1,\"pageClass\":\"Role\",\"noGlobal\":1,\"noMove\":1,\"noTrash\":1,\"noSettings\":1,\"noChangeTemplate\":1,\"nameContentTab\":1}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES (5,'permission',5,8,0,'{\"noChildren\":1,\"parentTemplates\":[2],\"slashUrls\":1,\"guestSearchable\":1,\"pageClass\":\"Permission\",\"noGlobal\":1,\"noMove\":1,\"noTrash\":1,\"noSettings\":1,\"noChangeTemplate\":1,\"nameContentTab\":1}');


DROP TABLE IF EXISTS `fieldgroups`;

CREATE TABLE `fieldgroups` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(250) character set ascii NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=97 DEFAULT CHARSET=utf8;

INSERT INTO `fieldgroups` (`id`, `name`) VALUES (2,'admin');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES (3,'user');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES (4,'role');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES (5,'permission');


DROP TABLE IF EXISTS `fieldgroups_fields`;
CREATE TABLE `fieldgroups_fields` (
  `fieldgroups_id` int(10) unsigned NOT NULL default '0',
  `fields_id` int(10) unsigned NOT NULL default '0',
  `sort` int(11) unsigned NOT NULL default '0',
  `data` text,
  PRIMARY KEY  (`fieldgroups_id`,`fields_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES (2,2,1,NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES (2,1,0,NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES (3,3,0,NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES (3,4,2,NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES (4,5,0,NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES (5,1,0,NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES (3,92,1,NULL);

DROP TABLE IF EXISTS `fields`;
CREATE TABLE `fields` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `type` varchar(128) character set ascii NOT NULL,
  `name` varchar(250) character set ascii NOT NULL,
  `flags` int(11) NOT NULL default '0',
  `label` varchar(250) NOT NULL default '',
  `data` text NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=97 DEFAULT CHARSET=utf8;
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES (1,'FieldtypePageTitle','title',13,'Title','{\"required\":1,\"textformatters\":[\"TextformatterEntities\"],\"size\":0,\"maxlength\":255}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES (2,'FieldtypeModule','process',25,'Process','{\"description\":\"The process that is executed on this page. Since this is mostly used by ProcessWire internally, it is recommended that you don\'t change the value of this unless adding your own pages in the admin.\",\"collapsed\":1,\"required\":1,\"moduleTypes\":[\"Process\"],\"permanent\":1}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES (3,'FieldtypePassword','pass',24,'Set Password','{\"collapsed\":1,\"size\":50,\"maxlength\":128}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES (5,'FieldtypePage','permissions',24,'Permissions','{\"derefAsPage\":0,\"parent_id\":31,\"labelFieldName\":\"title\",\"inputfield\":\"InputfieldCheckboxes\"}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES (4,'FieldtypePage','roles',24,'Roles','{\"derefAsPage\":0,\"parent_id\":30,\"labelFieldName\":\"name\",\"inputfield\":\"InputfieldCheckboxes\",\"description\":\"User will inherit the permissions assigned to each role. You may assign multiple roles to a user. When accessing a page, the user will only inherit permissions from the roles that are also assigned to the page\'s template.\"}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES (92,'FieldtypeEmail','email',9,'E-Mail Address','{\"size\":70,\"maxlength\":255}');

DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `class` varchar(128) character set ascii NOT NULL,
  `flags` int(11) NOT NULL default '0',
  `data` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `class` (`class`)
) ENGINE=MyISAM AUTO_INCREMENT=148 DEFAULT CHARSET=utf8;
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (1,'FieldtypeTextarea',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (3,'FieldtypeText',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (4,'FieldtypePage',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (30,'InputfieldForm',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (6,'FieldtypeFile',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (7,'ProcessPageEdit',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (10,'ProcessLogin',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (12,'ProcessPageList',0,'{\"pageLabelField\":\"title\",\"paginationLimit\":25,\"limit\":50}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (121,'ProcessPageEditLink',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (14,'ProcessPageSort',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (15,'InputfieldPageListSelect',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (117,'JqueryUI',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (17,'ProcessPageAdd',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (125,'SessionLoginThrottle',3,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (122,'InputfieldPassword',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (25,'InputfieldAsmSelect',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (116,'JqueryCore',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (27,'FieldtypeModule',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (28,'FieldtypeDatetime',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (29,'FieldtypeEmail',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (108,'InputfieldURL',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (32,'InputfieldSubmit',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (34,'InputfieldText',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (35,'InputfieldTextarea',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (36,'InputfieldSelect',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (37,'InputfieldCheckbox',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (38,'InputfieldCheckboxes',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (39,'InputfieldRadios',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (40,'InputfieldHidden',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (41,'InputfieldName',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (43,'InputfieldSelectMultiple',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (45,'JqueryWireTabs',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (47,'ProcessTemplate',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (48,'ProcessField',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (50,'ProcessModule',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (114,'PagePermissions',3,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (97,'FieldtypeCheckbox',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (115,'PageRender',3,'{\"clearCache\":1}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (55,'InputfieldFile',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (56,'InputfieldImage',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (57,'FieldtypeImage',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (60,'InputfieldPage',0,'{\"inputfieldClasses\":[\"InputfieldSelect\",\"InputfieldSelectMultiple\",\"InputfieldCheckboxes\",\"InputfieldRadios\",\"InputfieldAsmSelect\",\"InputfieldPageListSelect\",\"InputfieldPageListSelectMultiple\"]}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (61,'TextformatterEntities',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (66,'ProcessUser',0,'{\"showFields\":[\"name\",\"email\",\"roles\"]}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (67,'MarkupAdminDataTable',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (68,'ProcessRole',0,'{\"showFields\":[\"name\"]}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (76,'ProcessList',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (78,'InputfieldFieldset',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (79,'InputfieldMarkup',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (80,'InputfieldEmail',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (89,'FieldtypeFloat',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (83,'ProcessPageView',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (84,'FieldtypeInteger',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (85,'InputfieldInteger',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (86,'InputfieldPageName',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (87,'ProcessHome',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (90,'InputfieldFloat',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (94,'InputfieldDatetime',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (98,'MarkupPagerNav',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (129,'ProcessPageEditImageSelect',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (103,'JqueryTableSorter',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (104,'ProcessPageSearch',1,'{\"searchFields\":\"title body\",\"displayField\":\"title path\"}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (105,'FieldtypeFieldsetOpen',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (106,'FieldtypeFieldsetClose',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (107,'FieldtypeFieldsetTabOpen',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (109,'ProcessPageTrash',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (111,'FieldtypePageTitle',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (112,'InputfieldPageTitle',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (113,'MarkupPageArray',3,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (131,'InputfieldButton',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (133,'FieldtypePassword',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (134,'ProcessPageType',1,'{\"showFields\":[]}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (135,'FieldtypeURL',1,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (136,'ProcessPermission',1,'{\"showFields\":[\"name\",\"title\"]}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (137,'InputfieldPageListSelectMultiple',0,'');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (138,'ProcessProfile',1,'{\"profileFields\":[\"pass\",\"email\"]}');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`) VALUES (139,'SystemUpdater', 1, '{"systemVersion":1}');

DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `parent_id` int(11) unsigned NOT NULL default '0',
  `templates_id` int(11) unsigned NOT NULL default '0',
  `name` varchar(128) character set ascii NOT NULL,
  `status` int(10) unsigned NOT NULL default '1',
  `modified` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `modified_users_id` int(10) unsigned NOT NULL default '2',
  `created` timestamp NOT NULL default '2015-12-18 06:09:00',
  `created_users_id` int(10) unsigned NOT NULL default '2',
  `sort` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name_parent_id` (`name`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  KEY `templates_id` (`templates_id`),
  KEY `modified` (`modified`),
  KEY `created` (`created`),
  KEY `status` (`status`)
) ENGINE=MyISAM AUTO_INCREMENT=1006 DEFAULT CHARSET=utf8;
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (1,0,2,'home',9,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (2,1,2,'processwire',1035,NOW(),41,NOW(),2,5);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (3,2,2,'page',21,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (6,3,2,'add',21,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (7,1,2,'trash',1039,NOW(),41,NOW(),2,6);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (8,3,2,'list',21,NOW(),41,NOW(),2,1);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (9,3,2,'sort',23,NOW(),41,NOW(),2,2);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (10,3,2,'edit',21,NOW(),41,NOW(),2,3);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (11,22,2,'template',21,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (16,22,2,'field',21,NOW(),41,NOW(),2,2);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (21,2,2,'module',21,NOW(),41,NOW(),2,2);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (22,2,2,'setup',21,NOW(),41,NOW(),2,1);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (23,2,2,'login',1035,NOW(),41,NOW(),2,4);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (27,1,2,'http404',1035,NOW(),41,NOW(),3,4);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (28,2,2,'access',13,NOW(),41,NOW(),2,3);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (29,28,2,'users',29,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (30,28,2,'roles',29,NOW(),41,NOW(),2,1);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (31,28,2,'permissions',29,NOW(),41,NOW(),2,2);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (32,31,5,'page-edit',25,NOW(),41,NOW(),2,2);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (34,31,5,'page-delete',25,NOW(),41,NOW(),2,3);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (35,31,5,'page-move',25,NOW(),41,NOW(),2,4);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (36,31,5,'page-view',25,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (37,30,4,'guest',25,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (38,30,4,'superuser',25,NOW(),41,NOW(),2,1);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (41,29,3,'admin',1,NOW(),41,NOW(),2,0);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (40,29,3,'guest',25,NOW(),41,NOW(),2,1);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (50,31,5,'page-sort',25,NOW(),41,NOW(),41,5);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (51,31,5,'page-template',25,NOW(),41,NOW(),41,6);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (52,31,5,'user-admin',25,NOW(),41,NOW(),41,10);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (53,31,5,'profile-edit',1,NOW(),41,NOW(),41,13);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (54,31,5,'page-lock',1,NOW(),41,NOW(),41,8);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (300,3,2,'search',21,NOW(),41,NOW(),2,5);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (301,3,2,'trash',23,NOW(),41,NOW(),2,5);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (302,3,2,'link',17,NOW(),41,NOW(),2,6);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (303,3,2,'image',17,NOW(),41,NOW(),2,7);
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `sort`) VALUES (304,2,2,'profile',1025,NOW(),41,NOW(),41,5);

DROP TABLE IF EXISTS `pages_access`;
CREATE TABLE `pages_access` (
  `pages_id` int(11) NOT NULL,
  `templates_id` int(11) NOT NULL,
  `ts` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`pages_id`),
  KEY `templates_id` (`templates_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `pages_access` VALUES (37, 2, NOW());
INSERT INTO `pages_access` VALUES (38, 2, NOW());
INSERT INTO `pages_access` VALUES (32, 2, NOW());
INSERT INTO `pages_access` VALUES (34, 2, NOW());
INSERT INTO `pages_access` VALUES (35, 2, NOW());
INSERT INTO `pages_access` VALUES (36, 2, NOW());
INSERT INTO `pages_access` VALUES (50, 2, NOW());
INSERT INTO `pages_access` VALUES (51, 2, NOW());
INSERT INTO `pages_access` VALUES (52, 2, NOW());
INSERT INTO `pages_access` VALUES (53, 2, NOW());
INSERT INTO `pages_access` VALUES (54, 2, NOW());

DROP TABLE IF EXISTS `pages_parents`;
CREATE TABLE `pages_parents` (
  `pages_id` int(10) unsigned NOT NULL,
  `parents_id` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`pages_id`,`parents_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (2,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (3,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (3,2);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (7,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (22,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (22,2);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (28,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (28,2);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (29,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (29,2);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (29,28);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (30,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (30,2);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (30,28);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (31,1);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (31,2);
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES (31,28);

DROP TABLE IF EXISTS `pages_sortfields`;
CREATE TABLE `pages_sortfields` (
  `pages_id` int(10) unsigned NOT NULL default '0',
  `sortfield` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`pages_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `session_login_throttle`;
CREATE TABLE `session_login_throttle` (
  `name` varchar(128) NOT NULL,
  `attempts` int(10) unsigned NOT NULL default '0',
  `last_attempt` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;