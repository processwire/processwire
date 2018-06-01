# --- WireDatabaseBackup {"time":"2017-01-27 13:32:39","user":"","dbName":"pw_xyz","description":"","tables":[],"excludeTables":["pages_drafts","pages_roles","permissions","roles","roles_permissions","users","users_roles","user","role","permission"],"excludeCreateTables":[],"excludeExportTables":["field_roles","field_permissions","field_email","field_pass","caches","session_login_throttle","page_path_history"]}

DROP TABLE IF EXISTS `caches`;
CREATE TABLE `caches` (
  `name` varchar(191) NOT NULL,
  `data` mediumtext NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`name`),
  KEY `expires` (`expires`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `field_body`;
CREATE TABLE `field_body` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` mediumtext NOT NULL,
  PRIMARY KEY (`pages_id`),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1', '<p>This is a simple <a href=\"https://processwire.com\">ProcessWire</a> site profile that is somewhat like our default site profile, but also includes a blog. It demonstrates development of various features including some new to ProcessWire 3.x. The front-end of this profile uses the <a href=\"http://www.getuikit.com\" target=\"_blank\" rel=\"noreferrer\">Uikit 3</a> library and includes a library of time-saving functions for working with Uikit 3. Below are a few highlights you\'ll find in this site profile:</p>\n\n<ul><li>Use of markup regions and the new ProcessWire functions API.</li>\n	<li>Use of Uikit 3 in template files and includes a handy PHP library of Uikit-specific functions.</li>\n	<li>Demonstrates front-end editing features on <a	data-pwid=1024	href=\"/about/front-end-editor-demo/\">this page</a>.</li>\n	<li>Uses pagination (after 10+ blog posts) and demonstrates use of comments as well.</li>\n	<li>Demonstrates caching of markup (see mobile off-canvas navigation).</li>\n	<li>Demonstrates use of a Page reference field, as used by categories in the blog.</li>\n	<li>The template files are easy-to-read and modify, and serve as a good platform to build from.</li>\n	<li>Demonstrates implementation of a custom hook function (see in the /site/ready.php file).</li>\n</ul>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('27', '<h3>The page you were looking for is not found.</h3>\n\n<p>Please use the navigation above to find the page, or use the search engine in the footer. </p>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1001', '<p>Dolore ad nunc, mos accumsan paratus duis suscipit luptatum facilisis macto uxor iaceo quadrum. Demoveo, appellatio elit neque ad commodo ea. Wisi, iaceo, tincidunt at commoveo rusticus et, ludus. Feugait at blandit bene blandit suscipere abdo duis ideo bis commoveo pagus ex, velit. Consequat commodo roto accumsan, duis transverbero.</p>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1002', '<p>Iusto incassum appellatio cui macto genitus vel. Lobortis aliquam luctus, roto enim, imputo wisi tamen. Ratis odio, genitus acsi, neo illum consequat consectetuer ut.</p>\n\n<p>Patria iriure vel vel autem proprius indoles ille sit. Tation blandit refoveo, accumsan ut ulciscor lucidus inhibeo capto aptent opes, foras.</p>\n\n<h3>Dolore ea valde refero feugait utinam luctus</h3>\n\n<p><img alt=\"Copyright by Austin Cramer for DesignIntelligence. This is a placeholder while he makes new ones for us.\" class=\"align_right hidpi\"	src=\"/site/assets/files/1002/psych_cartoon_4-20.300x0-is-hidpi.jpg\" width=\"300\" />Usitas, nostrud transverbero, in, amet, nostrud ad. Ex feugiat opto diam os aliquam regula lobortis dolore ut ut quadrum. Esse eu quis nunc jugis iriure volutpat wisi, fere blandit inhibeo melior, hendrerit, saluto velit. Eu bene ideo dignissim delenit accumsan nunc. Usitas ille autem camur consequat typicus feugait elit ex accumsan nutus accumsan nimis pagus, occuro. Immitto populus, qui feugiat opto pneum letalis paratus. Mara conventio torqueo nibh caecus abigo sit eum brevitas. Populus, duis ex quae exerci hendrerit, si antehabeo nobis, consequat ea praemitto zelus.</p>\n\n<p>Immitto os ratis euismod conventio erat jus caecus sudo. code test Appellatio consequat, et ibidem ludus nulla dolor augue abdo tego euismod plaga lenis. Sit at nimis venio venio tego os et pecus enim pneum magna nobis ad pneum. Saepius turpis probo refero molior nonummy aliquam neque appellatio jus luctus acsi. Ulciscor refero pagus imputo eu refoveo valetudo duis dolore usitas. Consequat suscipere quod torqueo ratis ullamcorper, dolore lenis, letalis quia quadrum plaga minim.</p>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1004', '<p>Magna in gemino, gilvus iusto capto jugis abdo mos aptent acsi qui. Utrum inhibeo humo humo duis quae. Lucidus paulatim facilisi scisco quibus hendrerit conventio adsum.</p>\n\n<h2>Si lobortis singularis genitus ibidem saluto</h2>\n\n<ul><li>Feugiat eligo foras ex elit sed indoles hos elit ex antehabeo defui et nostrud.</li>\n	<li>Letatio valetudo multo consequat inhibeo ille dignissim pagus et in quadrum eum eu.</li>\n	<li>Aliquam si consequat, ut nulla amet et turpis exerci, adsum luctus ne decet, delenit.</li>\n	<li>Commoveo nunc diam valetudo cui, aptent commoveo at obruo uxor nulla aliquip augue.</li>\n</ul><p>Iriure, ex velit, praesent vulpes delenit capio vero gilvus inhibeo letatio aliquip metuo qui eros. Transverbero demoveo euismod letatio torqueo melior. Ut odio in suscipit paulatim amet huic letalis suscipere eros causa, letalis magna.</p>\n\n<ol><li>Feugiat eligo foras ex elit sed indoles hos elit ex antehabeo defui et nostrud.</li>\n	<li>Letatio valetudo multo consequat inhibeo ille dignissim pagus et in quadrum eum eu.</li>\n	<li>Aliquam si consequat, ut nulla amet et turpis exerci, adsum luctus ne decet, delenit.</li>\n	<li>Commoveo nunc diam valetudo cui, aptent commoveo at obruo uxor nulla aliquip augue.</li>\n</ol>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1015', '<p>Fixed effect pulse current remote integer potentiometer anomoly. Gigabyte recognition deviation active sequential bypass echo distributed. Embedded encapsulated mainframe reducer logarithmic potentiometer duplex. Software metafile reducer deviation boolean overflow bridgeware.</p>\n\n<p>Patch internet nano. Converter a inversion recursive adaptive encapsulated transport floating-point transistorized plasma microscopic node. PC duplex partitioned. Network scalar dithering encapsulated generator normalizing. Remote interval fixed plasma normalizing microscopic procedural scalar dynamic read-only high boolean.</p>\n\n<h3>Reducer hybrid force key</h3>\n\n<p>Cascading wave network logarithmic digital powered scan. Frequency coordinated particle transmission supporting. Log distributed bus scan force particle computer inversion servicing reverberated device. In coordinated services backbone silicon hyperlinked. Scalar error fiber transponder digital.</p>\n\n<p>Vector developer connectivity connectivity modular supporting broadband solution. For modular vector timer indeterminate debugged optical kilohertz procedural procedural. Infrared fuzzy procedural capacitance fiber. Algorithm direct procedural echo. Digital bridgeware by timer fragmentation ethernet inducer phase network.</p>\n\n<p>Transaction active by. Effect partitioned by timer system services computer. Spawned coordinated developer fuzzy. Technician fuzzy supporting protocol coordinated ethernet. Bridgeware video remote prototype development.</p>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1021', '<p>Grown plus industry open for when when sharpest ordinary offer by. Better huggable opportunity too. Rosy sleek while exclusive gentle not on. Offer colossal silky this sweet magically announcing durable sold soaking our try. Sold one zesty velvety awesome flavored ever with effervescent gentle. Screamin\' improved permanent treat now tasty we space 100%.</p>\n\n<p>Think affordable artificial blast while choice. Appetizing available really thank-you out proven desire fresh rich. Natural and flash power effective grand premium. Secret lifetime grand quenches by ocean as comfort golden youthful fast. Disposable zesty dazzling open sure spacious multi-purpose the super market rare.</p>\n\n<p>Spring special bigger wherever only this comfort tummy extravaganza save. Very messy keen leading incredible.</p>\n\n<p>Hearty brand chocolatey comfort admire ultra. Want kids touch discount love appetizing talking inside buttery. For keeps admire youthful. Wherever super thirsty lasting limited discover picky can\'t.</p>\n\n<p>Good appreciate flexible product best. Full-bodied don\'t customer gigantic also.</p>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1022', '<p>Genuine symphony solid educated de-jour regal gifted guests. Using gilded member silk dignified gilded panoramic art politically. Diamond upper brokerage pleasure society reserved. First-class topiary treasure travel is the best wishlist vacation solid penthouse world.</p>\n\n<p>Board marquis estate career blissfull treasure saphire. Delegate cultered regal marquis cigar sterling penthouse.</p>\n\n<p>Sterling butler solid penthouse gilded gilded pedigree wine using investments cigar. Cultered doctoral symphony extra accredited. Private benefactor monogram high-rise a.</p>\n\n<p>Career gilded extra aristocratic cruise brilliant impresario. European ambassador acumen ambassador. Rare suite cruise club crafted butler grande.</p>\n\n<p>Distinctly rich auction penthouse travel.</p>');
INSERT INTO `field_body` (`pages_id`, `data`) VALUES('1024', '<p>If you are logged in with edit access to this page, you can double-click this body copy to edit it. You can also do the same to edit the headline above, or the sidebar text to the right.</p>\n\n<p>Illum aliquip loquor. Hendrerit interdico dolor zelus diam metuo causa lobortis scisco. Euismod damnum quibus ideo patria opto. Haero odio jus virtus haero pagus erat cogo diam minim vulputate autem.</p>\n\n<h3>Ullamcorper venio bene</h3>\n\n<p>Amet ea oppeto nullus esse meus immitto sudo dignissim. Letalis velit utrum luptatum ullamcorper illum ad fere molior populus ut. Et augue eligo jumentum populus nonummy virtus. Valetudo odio ex opes mos delenit immitto ex. Illum tincidunt commoveo nostrud et ratis ne vulputate vereor tego.</p>\n\n<ul><li>Capto elit vel eu esse quia</li>\n	<li>Te gemino natu et augue ad</li>\n	<li>Amet aliquip valde blandit olim facilisi</li>\n</ul><p>Nulla iusto pertineo camur similis enim abigo luptatum ymo nullus. Inhibeo nutus pagus capto dolus capio pecus. Pala vereor esse melior nisl bis. Veniam eros consequat.</p>');

DROP TABLE IF EXISTS `field_categories`;
CREATE TABLE `field_categories` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` int(11) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  PRIMARY KEY (`pages_id`,`sort`),
  KEY `data` (`data`,`pages_id`,`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_categories` (`pages_id`, `data`, `sort`) VALUES('1015', '1017', '0');
INSERT INTO `field_categories` (`pages_id`, `data`, `sort`) VALUES('1015', '1018', '1');
INSERT INTO `field_categories` (`pages_id`, `data`, `sort`) VALUES('1021', '1018', '1');
INSERT INTO `field_categories` (`pages_id`, `data`, `sort`) VALUES('1021', '1019', '0');
INSERT INTO `field_categories` (`pages_id`, `data`, `sort`) VALUES('1022', '1019', '0');
INSERT INTO `field_categories` (`pages_id`, `data`, `sort`) VALUES('1022', '1020', '1');
INSERT INTO `field_categories` (`pages_id`, `data`, `sort`) VALUES('1021', '1027', '2');

DROP TABLE IF EXISTS `field_comments`;
CREATE TABLE `field_comments` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` text NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `status` tinyint(3) NOT NULL DEFAULT '0',
  `cite` varchar(128) NOT NULL DEFAULT '',
  `email` varchar(250) NOT NULL DEFAULT '',
  `created` int(10) unsigned NOT NULL,
  `created_users_id` int(10) unsigned NOT NULL,
  `ip` varchar(15) NOT NULL DEFAULT '',
  `user_agent` varchar(250) NOT NULL DEFAULT '',
  `website` varchar(250) NOT NULL DEFAULT '',
  `parent_id` int(10) unsigned NOT NULL DEFAULT '0',
  `flags` int(10) unsigned NOT NULL DEFAULT '0',
  `code` varchar(128) DEFAULT NULL,
  `subcode` varchar(40) DEFAULT NULL,
  `upvotes` int(10) unsigned NOT NULL DEFAULT '0',
  `downvotes` int(10) unsigned NOT NULL DEFAULT '0',
  `stars` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pages_id_sort` (`pages_id`,`sort`),
  KEY `status` (`status`,`email`(191)),
  KEY `pages_id` (`pages_id`,`status`,`created`),
  KEY `created` (`created`,`status`),
  KEY `code` (`code`),
  KEY `subcode` (`subcode`),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

INSERT INTO `field_comments` (`pages_id`, `data`, `sort`, `id`, `status`, `cite`, `email`, `created`, `created_users_id`, `ip`, `user_agent`, `website`, `parent_id`, `flags`, `code`, `subcode`, `upvotes`, `downvotes`, `stars`) VALUES('1021', 'They good night the piper good night good queen white as snow they magical beans winding path up the hill dragon beautiful dress. So loud magic wand took fought angry lion ding-dong. Winding path fought ran away whale swallowed crystal ball poison apple took the piper sang twinkled.', '2', '1', '1', 'Jim', 'jim@processwire.com', '1485450830', '41', '0.0.0.0', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36', '', '0', '0', 'aeHvmkn88ncb4214OXegP8uUrQy6D5UZcaD9pPxT9gPFDdEOf1EqCM6UD6JUnY7Jtv9MPNjcPrJWUxKhyh89r1H6nywk1Se_GdwAoj2guU_9YYa9MEgiuJUekuk93YvE', 'JzPW6751GqTqk1Oh__k0IbNfOi_Nc6nYvPPa2wl6', '0', '0', NULL);
INSERT INTO `field_comments` (`pages_id`, `data`, `sort`, `id`, `status`, `cite`, `email`, `created`, `created_users_id`, `ip`, `user_agent`, `website`, `parent_id`, `flags`, `code`, `subcode`, `upvotes`, `downvotes`, `stars`) VALUES('1021', 'LED harmonic nominal femtosecond data solid alphanumeric alphanumeric. By sampling bus recursive null. Modular timer recognition passive interval. Theory capacitance application fragmentation with supporting indeterminate. Microscopic record indeterminate scalar concept deviation system.', '3', '2', '1', 'ryan', 'ryan@processwire.com', '1485453231', '41', '0.0.0.0', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36', 'https://processwire.com', '0', '0', 'eWwAZPZHyC4JcShlKDPrr5Y_rC8sntJOildm2ecqUegXPwgfwmRhyOn5ssyQhABWwaweM74e_TApOLMQu4MGt9lSf7VcxH994ciwggF0f3lpEdJ3OMtjYe4MvW4gDzNF', 'CcaWnsxrcWYxfCgnnvYc2DEN7qU3bd_7HVm3NK_0', '0', '0', NULL);
INSERT INTO `field_comments` (`pages_id`, `data`, `sort`, `id`, `status`, `cite`, `email`, `created`, `created_users_id`, `ip`, `user_agent`, `website`, `parent_id`, `flags`, `code`, `subcode`, `upvotes`, `downvotes`, `stars`) VALUES('1022', 'Run Spot play help I am hungry I can help too oh no. He wants to play oh no for a ride I can help for a ride too on our bikes chase the cat for a ride. Bring it here walk we have two bark I am hungry jump high now he is funny it is Sally for a ride oh please. We can oh please down the toy I can help no Jane is looking she is happy share with them.\n\nLook too eating cake he wants to play he wants to play down don\'t worry on our bikes on our bikes. I am hungry down jump high they are silly and oh no help but. Thank you over there I can help Dick said over there on our bikes see Puff over there do it Jane is looking I can see he is fast. Quick in the wagon no jump high.', '2', '3', '1', 'Ryan', 'ryan@processwire.com', '1485528109', '41', '0.0.0.0', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36', 'https://processwire.com', '0', '0', 'CVaOB64FqwwVlmAXUbhP5KbZQDFM7OarKON1ZIb_PYP9cDkpcb0NCYp56iAPHTZoIjJEZNQ4mnuEyLkYw97XkfOgmtRDd33rfZB0Zt1yfDKOjY4tdkWP08BKUbI_MImr', 'ySOMziIfOxoi_BfPVBNxNaGVrKHGzHGtFbnbkodo', '0', '0', NULL);

DROP TABLE IF EXISTS `field_comments_votes`;
CREATE TABLE `field_comments_votes` (
  `comment_id` int(10) unsigned NOT NULL,
  `vote` tinyint(4) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip` varchar(15) NOT NULL DEFAULT '',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`comment_id`,`ip`,`vote`),
  KEY `created` (`created`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `field_date`;
CREATE TABLE `field_date` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` datetime NOT NULL,
  PRIMARY KEY (`pages_id`),
  KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_date` (`pages_id`, `data`) VALUES('1015', '2017-01-25 00:00:00');
INSERT INTO `field_date` (`pages_id`, `data`) VALUES('1022', '2017-01-26 00:00:00');
INSERT INTO `field_date` (`pages_id`, `data`) VALUES('1021', '2017-01-27 00:00:00');

DROP TABLE IF EXISTS `field_email`;
CREATE TABLE `field_email` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY (`pages_id`),
  KEY `data_exact` (`data`),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `field_headline`;
CREATE TABLE `field_headline` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`pages_id`),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_headline` (`pages_id`, `data`) VALUES('1', 'Uikit 3 site/blog profile');
INSERT INTO `field_headline` (`pages_id`, `data`) VALUES('27', '404 Page Not Found');
INSERT INTO `field_headline` (`pages_id`, `data`) VALUES('1001', 'About Us');
INSERT INTO `field_headline` (`pages_id`, `data`) VALUES('1024', 'Demonstration of front-end-editing');

DROP TABLE IF EXISTS `field_images`;
CREATE TABLE `field_images` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` varchar(191) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  `description` text NOT NULL,
  `modified` datetime DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`pages_id`,`sort`),
  KEY `data` (`data`),
  KEY `modified` (`modified`),
  KEY `created` (`created`),
  FULLTEXT KEY `description` (`description`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_images` (`pages_id`, `data`, `sort`, `description`, `modified`, `created`) VALUES('1002', 'psych_cartoon_4-20.jpg', '0', 'Copyright by Austin Cramer for DesignIntelligence. This is a placeholder while he makes new ones for us.', '2017-01-24 06:11:43', '2017-01-24 06:11:43');
INSERT INTO `field_images` (`pages_id`, `data`, `sort`, `description`, `modified`, `created`) VALUES('1021', 'screen_shot_2017-01-27_at_10_46_35_am.png', '0', '', '2017-01-27 10:56:13', '2017-01-27 10:56:13');


DROP TABLE IF EXISTS `field_pass`;
CREATE TABLE `field_pass` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` char(40) NOT NULL,
  `salt` char(32) NOT NULL,
  PRIMARY KEY (`pages_id`),
  KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii;

DROP TABLE IF EXISTS `field_permissions`;
CREATE TABLE `field_permissions` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` int(11) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  PRIMARY KEY (`pages_id`,`sort`),
  KEY `data` (`data`,`pages_id`,`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `field_process`;
CREATE TABLE `field_process` (
  `pages_id` int(11) NOT NULL DEFAULT '0',
  `data` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pages_id`),
  KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_process` (`pages_id`, `data`) VALUES('10', '7');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('23', '10');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('3', '12');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('8', '12');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('9', '14');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('6', '17');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('11', '47');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('16', '48');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('21', '50');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('29', '66');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('30', '68');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('22', '76');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('28', '76');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('2', '87');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('300', '104');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('301', '109');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('302', '121');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('303', '129');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('31', '136');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('304', '138');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('1007', '150');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('1009', '158');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('1011', '159');
INSERT INTO `field_process` (`pages_id`, `data`) VALUES('1025', '165');

DROP TABLE IF EXISTS `field_roles`;
CREATE TABLE `field_roles` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` int(11) NOT NULL,
  `sort` int(10) unsigned NOT NULL,
  PRIMARY KEY (`pages_id`,`sort`),
  KEY `data` (`data`,`pages_id`,`sort`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `field_sidebar`;
CREATE TABLE `field_sidebar` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` mediumtext NOT NULL,
  PRIMARY KEY (`pages_id`),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_sidebar` (`pages_id`, `data`) VALUES('1', '<h3>Requirements</h3>\n\n<p>This site profile requires ProcessWire 3.0.105 or newer, Uikit 3, and the server must be running on PHP 5.4 or newer.</p>');
INSERT INTO `field_sidebar` (`pages_id`, `data`) VALUES('1002', '<h3>Sudo nullus</h3>\r\n\r\n<p>Et torqueo vulpes vereor luctus augue quod consectetuer antehabeo causa patria tation ex plaga ut. Abluo delenit wisi iriure eros feugiat probo nisl aliquip nisl, patria. Antehabeo esse camur nisl modo utinam. Sudo nullus ventosus ibidem facilisis saepius eum sino pneum, vicis odio voco opto.</p>');
INSERT INTO `field_sidebar` (`pages_id`, `data`) VALUES('1024', '<h3>Double click me</h3>\n\n<p>Esca demoveo exputo sagaciter ullamcorper inhibeo ut nimis refoveo praemitto defui ut. Hendrerit ratis dignissim ea eligo. Genitus utinam suscipere caecus ad neque verto at regula saluto esse turpis. Refero autem et nulla ibidem caecus fere acsi plaga in turpis. Nobis sit nunc esse capio suscipit vulpes facilisis brevitas. Pagus odio eros accumsan et interdico nunc abdo eligo epulae.</p>');

DROP TABLE IF EXISTS `field_summary`;
CREATE TABLE `field_summary` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` mediumtext NOT NULL,
  PRIMARY KEY (`pages_id`),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_summary` (`pages_id`, `data`) VALUES('1', 'A simple blog site about nothing in particular.');
INSERT INTO `field_summary` (`pages_id`, `data`) VALUES('1001', 'This is a placeholder page with two child pages to serve as an example. ');
INSERT INTO `field_summary` (`pages_id`, `data`) VALUES('1002', 'Dolore ea valde refero feugait utinam luctus. Probo velit commoveo et, delenit praesent, suscipit zelus, hendrerit zelus illum facilisi, regula. ');
INSERT INTO `field_summary` (`pages_id`, `data`) VALUES('1004', 'Mos erat reprobo in praesent, mara premo, obruo iustum pecus velit lobortis te sagaciter populus.');
INSERT INTO `field_summary` (`pages_id`, `data`) VALUES('1005', 'View this template\'s source for a demonstration of how to create a basic site map. ');
INSERT INTO `field_summary` (`pages_id`, `data`) VALUES('1024', 'If you are logged in with edit access, pages using the basic-page-edit template (like this one) are editable on the front-end.');

DROP TABLE IF EXISTS `field_title`;
CREATE TABLE `field_title` (
  `pages_id` int(10) unsigned NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`pages_id`),
  KEY `data_exact` (`data`(191)),
  FULLTEXT KEY `data` (`data`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1', 'Home');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('2', 'Admin');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('3', 'Pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('6', 'Add Page');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('7', 'Trash');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('8', 'Tree');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('9', 'Save Sort');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('10', 'Edit');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('11', 'Templates');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('16', 'Fields');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('21', 'Modules');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('22', 'Setup');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('23', 'Login');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('27', '404 Page');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('28', 'Access');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('29', 'Users');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('30', 'Roles');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('31', 'Permissions');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('32', 'Edit pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('34', 'Delete pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('35', 'Move pages (change parent)');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('36', 'View pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('50', 'Sort child pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('51', 'Change templates on pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('52', 'Administer users');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('53', 'User can update profile/password');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('54', 'Lock or unlock a page');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('300', 'Search');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('301', 'Empty Trash');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('302', 'Insert Link');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('303', 'Insert Image');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('304', 'Profile');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1000', 'Search');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1001', 'About');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1002', 'Child page example 1');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1004', 'Child page example 2');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1005', 'Site Map');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1006', 'Use Page Lister');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1007', 'Find');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1009', 'Recent');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1010', 'Can see recently edited pages');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1011', 'Logs');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1012', 'Can view system logs');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1013', 'Can manage system logs');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1014', 'Blog');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1015', 'Phase data extended transaction');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1016', 'Categories');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1017', 'Coffee');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1018', 'Beer');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1019', 'Plants');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1020', 'Cats');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1021', 'Think affordable artificial blast');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1022', 'Genuine symphony');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1023', 'Use the front-end page editor');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1024', 'Front-end editing demo');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1025', 'Comments');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1026', 'Use the comments manager');
INSERT INTO `field_title` (`pages_id`, `data`) VALUES('1027', 'Recipes');

DROP TABLE IF EXISTS `fieldgroups`;
CREATE TABLE `fieldgroups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=102 DEFAULT CHARSET=utf8;

INSERT INTO `fieldgroups` (`id`, `name`) VALUES('2', 'admin');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('83', 'basic-page');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('101', 'basic-page-edit');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('98', 'blog');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('97', 'blog-post');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('99', 'categories');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('100', 'category');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('1', 'home');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('5', 'permission');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('4', 'role');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('80', 'search');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('88', 'sitemap');
INSERT INTO `fieldgroups` (`id`, `name`) VALUES('3', 'user');

DROP TABLE IF EXISTS `fieldgroups_fields`;
CREATE TABLE `fieldgroups_fields` (
  `fieldgroups_id` int(10) unsigned NOT NULL DEFAULT '0',
  `fields_id` int(10) unsigned NOT NULL DEFAULT '0',
  `sort` int(11) unsigned NOT NULL DEFAULT '0',
  `data` text,
  PRIMARY KEY (`fieldgroups_id`,`fields_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('1', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('1', '44', '5', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('1', '76', '3', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('1', '78', '1', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('1', '79', '2', '{\"label\":\"Site tagline\"}');
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('1', '82', '4', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('2', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('2', '2', '1', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('3', '3', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('3', '4', '2', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('3', '92', '1', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('4', '5', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('5', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('80', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('83', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('83', '44', '5', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('83', '76', '3', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('83', '78', '1', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('83', '79', '2', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('83', '82', '4', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('88', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('88', '79', '1', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('97', '1', '0', '{\"columnWidth\":75}');
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('97', '44', '3', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('97', '76', '2', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('97', '97', '1', '{\"columnWidth\":25}');
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('97', '98', '4', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('97', '99', '5', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('98', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('99', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('100', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('101', '1', '0', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('101', '44', '5', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('101', '76', '3', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('101', '78', '1', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('101', '79', '2', NULL);
INSERT INTO `fieldgroups_fields` (`fieldgroups_id`, `fields_id`, `sort`, `data`) VALUES('101', '82', '4', NULL);

DROP TABLE IF EXISTS `fields`;
CREATE TABLE `fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(128) CHARACTER SET ascii NOT NULL,
  `name` varchar(191) CHARACTER SET ascii NOT NULL,
  `flags` int(11) NOT NULL DEFAULT '0',
  `label` varchar(191) NOT NULL DEFAULT '',
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;

INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('1', 'FieldtypePageTitle', 'title', '13', 'Title', '{\"required\":1,\"textformatters\":[\"TextformatterEntities\"],\"size\":0,\"maxlength\":255}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('2', 'FieldtypeModule', 'process', '25', 'Process', '{\"description\":\"The process that is executed on this page. Since this is mostly used by ProcessWire internally, it is recommended that you don\'t change the value of this unless adding your own pages in the admin.\",\"collapsed\":1,\"required\":1,\"moduleTypes\":[\"Process\"],\"permanent\":1}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('3', 'FieldtypePassword', 'pass', '24', 'Set Password', '{\"collapsed\":1,\"size\":50,\"maxlength\":128}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('4', 'FieldtypePage', 'roles', '24', 'Roles', '{\"derefAsPage\":0,\"parent_id\":30,\"labelFieldName\":\"name\",\"inputfield\":\"InputfieldCheckboxes\",\"description\":\"User will inherit the permissions assigned to each role. You may assign multiple roles to a user. When accessing a page, the user will only inherit permissions from the roles that are also assigned to the page\'s template.\"}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('5', 'FieldtypePage', 'permissions', '24', 'Permissions', '{\"derefAsPage\":0,\"parent_id\":31,\"labelFieldName\":\"title\",\"inputfield\":\"InputfieldCheckboxes\"}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('44', 'FieldtypeImage', 'images', '0', 'Images', '{\"extensions\":\"gif jpg jpeg png\",\"adminThumbs\":1,\"inputfieldClass\":\"InputfieldImage\",\"maxFiles\":0,\"descriptionRows\":1,\"fileSchema\":2,\"textformatters\":[\"TextformatterEntities\"],\"outputFormat\":1,\"defaultValuePage\":0,\"defaultGrid\":0,\"icon\":\"camera\"}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('76', 'FieldtypeTextarea', 'body', '0', 'Body', '{\"inputfieldClass\":\"InputfieldCKEditor\",\"rows\":10,\"contentType\":1,\"toolbar\":\"Format, Bold, Italic, -, RemoveFormat\\nNumberedList, BulletedList, -, Blockquote\\nPWLink, Unlink, Anchor\\nPWImage, Table, HorizontalRule, SpecialChar\\nPasteText, PasteFromWord\\nScayt, -, Sourcedialog\",\"inlineMode\":0,\"useACF\":1,\"usePurifier\":1,\"formatTags\":\"p;h2;h3;h4;h5;h6;pre;address\",\"extraPlugins\":[\"pwimage\",\"pwlink\",\"sourcedialog\"],\"removePlugins\":\"image,magicline\",\"toggles\":[2,4,8],\"htmlOptions\":[2],\"collapsed\":0,\"minlength\":0,\"maxlength\":0,\"showCount\":0}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('78', 'FieldtypeText', 'headline', '0', 'Headline', '{\"description\":\"Use this instead of the Title if a longer headline is needed than what you want to appear in navigation.\",\"textformatters\":[\"TextformatterEntities\"],\"collapsed\":2,\"size\":0,\"maxlength\":1024,\"minlength\":0,\"showCount\":0}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('79', 'FieldtypeTextarea', 'summary', '1', 'Summary', '{\"textformatters\":[\"TextformatterEntities\"],\"inputfieldClass\":\"InputfieldTextarea\",\"collapsed\":2,\"rows\":3,\"contentType\":0}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('82', 'FieldtypeTextarea', 'sidebar', '0', 'Sidebar', '{\"inputfieldClass\":\"InputfieldCKEditor\",\"rows\":5,\"contentType\":1,\"toolbar\":\"Format, Bold, Italic, -, RemoveFormat\\r\\nNumberedList, BulletedList, -, Blockquote\\r\\nPWLink, Unlink, Anchor\\r\\nPWImage, Table, HorizontalRule, SpecialChar\\r\\nPasteText, PasteFromWord\\r\\nScayt, -, Sourcedialog\",\"inlineMode\":0,\"useACF\":1,\"usePurifier\":1,\"formatTags\":\"p;h2;h3;h4;h5;h6;pre;address\",\"extraPlugins\":[\"pwimage\",\"pwlink\",\"sourcedialog\"],\"removePlugins\":\"image,magicline\",\"toggles\":[2,4,8],\"collapsed\":2}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('92', 'FieldtypeEmail', 'email', '9', 'E-Mail Address', '{\"size\":70,\"maxlength\":255}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('97', 'FieldtypeDatetime', 'date', '0', 'Date', '{\"dateOutputFormat\":\"j F Y\",\"collapsed\":0,\"size\":25,\"datepicker\":3,\"timeInputSelect\":0,\"dateInputFormat\":\"Y\\/m\\/d\",\"defaultToday\":1,\"placeholder\":\"yyyy\\/mm\\/dd\",\"icon\":\"calendar\"}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('98', 'FieldtypePage', 'categories', '0', 'Categories', '{\"derefAsPage\":0,\"inputfield\":\"InputfieldAsmSelect\",\"parent_id\":1016,\"template_id\":46,\"labelFieldName\":\"title\",\"addable\":1,\"collapsed\":0}');
INSERT INTO `fields` (`id`, `type`, `name`, `flags`, `label`, `data`) VALUES('99', 'FieldtypeComments', 'comments', '0', 'Comments', '{\"schemaVersion\":6,\"moderate\":1,\"redirectAfterPost\":1,\"quietSave\":1,\"useNotify\":0,\"deleteSpamDays\":3,\"depth\":0,\"useWebsite\":1,\"dateFormat\":\"relative\",\"useVotes\":0,\"useStars\":0,\"useGravatar\":\"g\",\"collapsed\":0}');

DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class` varchar(128) CHARACTER SET ascii NOT NULL,
  `flags` int(11) NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `class` (`class`)
) ENGINE=MyISAM AUTO_INCREMENT=167 DEFAULT CHARSET=utf8;

INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('1', 'FieldtypeTextarea', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('2', 'FieldtypeNumber', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('3', 'FieldtypeText', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('4', 'FieldtypePage', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('6', 'FieldtypeFile', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('7', 'ProcessPageEdit', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('10', 'ProcessLogin', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('12', 'ProcessPageList', '0', '{\"pageLabelField\":\"title\",\"paginationLimit\":25,\"limit\":50}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('14', 'ProcessPageSort', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('15', 'InputfieldPageListSelect', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('17', 'ProcessPageAdd', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('25', 'InputfieldAsmSelect', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('27', 'FieldtypeModule', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('28', 'FieldtypeDatetime', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('29', 'FieldtypeEmail', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('30', 'InputfieldForm', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('32', 'InputfieldSubmit', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('33', 'InputfieldWrapper', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('34', 'InputfieldText', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('35', 'InputfieldTextarea', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('36', 'InputfieldSelect', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('37', 'InputfieldCheckbox', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('38', 'InputfieldCheckboxes', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('39', 'InputfieldRadios', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('40', 'InputfieldHidden', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('41', 'InputfieldName', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('43', 'InputfieldSelectMultiple', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('45', 'JqueryWireTabs', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('46', 'ProcessPage', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('47', 'ProcessTemplate', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('48', 'ProcessField', '32', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('50', 'ProcessModule', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('55', 'InputfieldFile', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('56', 'InputfieldImage', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('57', 'FieldtypeImage', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('60', 'InputfieldPage', '0', '{\"inputfieldClasses\":[\"InputfieldSelect\",\"InputfieldSelectMultiple\",\"InputfieldCheckboxes\",\"InputfieldRadios\",\"InputfieldAsmSelect\",\"InputfieldPageListSelect\",\"InputfieldPageListSelectMultiple\",\"InputfieldPageAutocomplete\"]}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('61', 'TextformatterEntities', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('66', 'ProcessUser', '0', '{\"showFields\":[\"name\",\"email\",\"roles\"]}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('67', 'MarkupAdminDataTable', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('68', 'ProcessRole', '0', '{\"showFields\":[\"name\"]}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('76', 'ProcessList', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('78', 'InputfieldFieldset', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('79', 'InputfieldMarkup', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('80', 'InputfieldEmail', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('83', 'ProcessPageView', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('84', 'FieldtypeInteger', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('85', 'InputfieldInteger', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('86', 'InputfieldPageName', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('87', 'ProcessHome', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('89', 'FieldtypeFloat', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('90', 'InputfieldFloat', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('94', 'InputfieldDatetime', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('97', 'FieldtypeCheckbox', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('98', 'MarkupPagerNav', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('103', 'JqueryTableSorter', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('104', 'ProcessPageSearch', '1', '{\"searchFields\":\"title\",\"displayField\":\"title path\"}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('105', 'FieldtypeFieldsetOpen', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('106', 'FieldtypeFieldsetClose', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('107', 'FieldtypeFieldsetTabOpen', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('108', 'InputfieldURL', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('109', 'ProcessPageTrash', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('111', 'FieldtypePageTitle', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('112', 'InputfieldPageTitle', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('113', 'MarkupPageArray', '3', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('114', 'PagePermissions', '3', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('115', 'PageRender', '3', '{\"clearCache\":1}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('116', 'JqueryCore', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('117', 'JqueryUI', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('121', 'ProcessPageEditLink', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('122', 'InputfieldPassword', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('125', 'SessionLoginThrottle', '11', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('129', 'ProcessPageEditImageSelect', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('131', 'InputfieldButton', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('133', 'FieldtypePassword', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('134', 'ProcessPageType', '33', '{\"showFields\":[]}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('135', 'FieldtypeURL', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('136', 'ProcessPermission', '1', '{\"showFields\":[\"name\",\"title\"]}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('137', 'InputfieldPageListSelectMultiple', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('138', 'ProcessProfile', '1', '{\"profileFields\":[\"pass\",\"email\"]}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('139', 'SystemUpdater', '1', '{\"systemVersion\":15,\"coreVersion\":\"3.0.50\"}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('148', 'AdminThemeDefault', '10', '{\"colors\":\"classic\"}', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('149', 'InputfieldSelector', '42', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('150', 'ProcessPageLister', '32', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('151', 'JqueryMagnific', '1', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('152', 'PagePathHistory', '3', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('155', 'InputfieldCKEditor', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('156', 'MarkupHTMLPurifier', '0', '', '2017-01-24 06:11:43');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('158', 'ProcessRecentPages', '1', '', '2017-01-24 06:12:09');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('159', 'ProcessLogger', '1', '', '2017-01-24 06:12:17');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('160', 'InputfieldIcon', '0', '', '2017-01-24 06:12:17');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('161', 'FieldtypeComments', '1', '', '2017-01-26 11:32:48');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('162', 'InputfieldCommentsAdmin', '0', '', '2017-01-26 11:32:48');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('163', 'InputfieldPageAutocomplete', '0', '', '2017-01-27 11:18:20');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('164', 'PageFrontEdit', '2', '', '2017-01-27 11:32:31');
INSERT INTO `modules` (`id`, `class`, `flags`, `data`, `created`) VALUES('165', 'ProcessCommentsManager', '1', '', '2017-01-27 12:17:47');

DROP TABLE IF EXISTS `page_path_history`;
CREATE TABLE `page_path_history` (
  `path` varchar(191) NOT NULL,
  `pages_id` int(10) unsigned NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`path`),
  KEY `pages_id` (`pages_id`),
  KEY `created` (`created`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0',
  `templates_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(128) CHARACTER SET ascii NOT NULL,
  `status` int(10) unsigned NOT NULL DEFAULT '1',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_users_id` int(10) unsigned NOT NULL DEFAULT '2',
  `created` timestamp NOT NULL DEFAULT '2015-12-18 06:09:00',
  `created_users_id` int(10) unsigned NOT NULL DEFAULT '2',
  `published` datetime DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_parent_id` (`name`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  KEY `templates_id` (`templates_id`),
  KEY `modified` (`modified`),
  KEY `created` (`created`),
  KEY `status` (`status`),
  KEY `published` (`published`)
) ENGINE=MyISAM AUTO_INCREMENT=1029 DEFAULT CHARSET=utf8;

INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1', '0', '1', 'home', '9', '2017-01-27 13:29:31', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('2', '1', '2', 'processwire', '1035', '2017-01-24 06:12:10', '40', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '6');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('3', '2', '2', 'page', '21', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('6', '3', '2', 'add', '21', '2017-01-24 06:12:22', '40', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('7', '1', '2', 'trash', '1039', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '7');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('8', '3', '2', 'list', '1045', '2017-01-24 06:15:58', '40', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('9', '3', '2', 'sort', '1047', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('10', '3', '2', 'edit', '1045', '2017-01-24 06:15:58', '40', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '3');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('11', '22', '2', 'template', '21', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('16', '22', '2', 'field', '21', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('21', '2', '2', 'module', '21', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('22', '2', '2', 'setup', '21', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('23', '2', '2', 'login', '1035', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '4');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('27', '1', '29', 'http404', '1035', '2017-01-27 12:25:04', '41', '2017-01-24 06:11:43', '3', '2017-01-24 06:11:43', '5');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('28', '2', '2', 'access', '13', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '3');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('29', '28', '2', 'users', '29', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('30', '28', '2', 'roles', '29', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('31', '28', '2', 'permissions', '29', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('32', '31', '5', 'page-edit', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('34', '31', '5', 'page-delete', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '3');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('35', '31', '5', 'page-move', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '4');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('36', '31', '5', 'page-view', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('37', '30', '4', 'guest', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('38', '30', '4', 'superuser', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('40', '29', '3', 'guest', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('41', '29', '3', 'admin', '1', '2017-01-24 06:12:10', '40', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('50', '31', '5', 'page-sort', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '5');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('51', '31', '5', 'page-template', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '6');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('52', '31', '5', 'user-admin', '25', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '10');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('53', '31', '5', 'profile-edit', '1', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '13');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('54', '31', '5', 'page-lock', '1', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '8');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('300', '3', '2', 'search', '1045', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '5');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('301', '3', '2', 'trash', '1047', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '5');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('302', '3', '2', 'link', '1041', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '6');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('303', '3', '2', 'image', '1041', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '7');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('304', '2', '2', 'profile', '1025', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '41', '2017-01-24 06:11:43', '5');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1000', '1', '26', 'search', '1025', '2017-01-26 09:55:14', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '4');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1001', '1', '29', 'about', '1', '2017-01-25 08:36:43', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1002', '1001', '29', 'what', '1', '2017-01-27 12:08:45', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1004', '1001', '29', 'background', '1', '2017-01-27 09:45:20', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1005', '1', '34', 'site-map', '1', '2017-01-26 09:55:10', '41', '2017-01-24 06:11:43', '2', '2017-01-24 06:11:43', '3');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1006', '31', '5', 'page-lister', '1', '2017-01-24 06:11:43', '40', '2017-01-24 06:11:43', '40', '2017-01-24 06:11:43', '9');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1007', '3', '2', 'lister', '1', '2017-01-24 06:11:43', '40', '2017-01-24 06:11:43', '40', '2017-01-24 06:11:43', '8');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1009', '3', '2', 'recent-pages', '1', '2017-01-24 06:12:09', '40', '2017-01-24 06:12:09', '40', '2017-01-24 06:12:09', '9');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1010', '31', '5', 'page-edit-recent', '1', '2017-01-24 06:12:09', '40', '2017-01-24 06:12:09', '40', '2017-01-24 06:12:09', '10');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1011', '22', '2', 'logs', '1', '2017-01-24 06:12:17', '40', '2017-01-24 06:12:17', '40', '2017-01-24 06:12:17', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1012', '31', '5', 'logs-view', '1', '2017-01-24 06:12:17', '40', '2017-01-24 06:12:17', '40', '2017-01-24 06:12:17', '11');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1013', '31', '5', 'logs-edit', '1', '2017-01-24 06:12:17', '40', '2017-01-24 06:12:17', '40', '2017-01-24 06:12:17', '12');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1014', '1', '44', 'blog', '1', '2017-01-25 15:22:52', '41', '2017-01-25 15:22:52', '41', '2017-01-25 15:22:52', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1015', '1014', '43', 'phase-data-extended-transaction', '1', '2017-01-26 06:18:13', '41', '2017-01-25 15:23:04', '41', '2017-01-25 15:23:20', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1016', '1', '45', 'categories', '1', '2017-01-26 05:55:33', '41', '2017-01-26 05:54:06', '41', '2017-01-26 05:54:06', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1017', '1016', '46', 'coffee', '1', '2017-01-26 05:54:49', '41', '2017-01-26 05:54:46', '41', '2017-01-26 05:54:46', '0');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1018', '1016', '46', 'beer', '1', '2017-01-26 05:54:53', '41', '2017-01-26 05:54:53', '41', '2017-01-26 05:54:53', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1019', '1016', '46', 'plants', '1', '2017-01-26 05:56:01', '41', '2017-01-26 05:56:01', '41', '2017-01-26 05:56:01', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1020', '1016', '46', 'cats', '1', '2017-01-26 06:10:41', '41', '2017-01-26 06:10:41', '41', '2017-01-26 06:10:41', '3');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1021', '1014', '43', 'think-affordable-artificial-blast', '1', '2017-01-27 12:37:31', '41', '2017-01-26 06:38:37', '41', '2017-01-26 06:39:03', '1');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1022', '1014', '43', 'genuine-symphony', '1', '2017-01-27 12:18:48', '41', '2017-01-26 09:50:20', '41', '2017-01-26 09:50:54', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1023', '31', '5', 'page-edit-front', '1', '2017-01-27 11:32:31', '41', '2017-01-27 11:32:31', '41', '2017-01-27 11:32:31', '13');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1024', '1001', '47', 'front-end-editor-demo', '1', '2017-01-27 12:31:38', '41', '2017-01-27 12:01:56', '41', '2017-01-27 12:03:43', '2');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1025', '22', '2', 'comments', '1', '2017-01-27 12:17:47', '41', '2017-01-27 12:17:47', '41', '2017-01-27 12:17:47', '3');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1026', '31', '5', 'comments-manager', '1', '2017-01-27 12:17:47', '41', '2017-01-27 12:17:47', '41', '2017-01-27 12:17:47', '14');
INSERT INTO `pages` (`id`, `parent_id`, `templates_id`, `name`, `status`, `modified`, `modified_users_id`, `created`, `created_users_id`, `published`, `sort`) VALUES('1027', '1016', '46', 'recipes', '1', '2017-01-27 12:37:06', '41', '2017-01-27 12:37:06', '41', '2017-01-27 12:37:06', '4');

DROP TABLE IF EXISTS `pages_access`;
CREATE TABLE `pages_access` (
  `pages_id` int(11) NOT NULL,
  `templates_id` int(11) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pages_id`),
  KEY `templates_id` (`templates_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('32', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('34', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('35', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('36', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('37', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('38', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('50', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('51', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('52', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('53', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('54', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1006', '2', '2017-01-24 06:11:43');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1010', '2', '2017-01-24 06:12:09');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1012', '2', '2017-01-24 06:12:17');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1013', '2', '2017-01-24 06:12:17');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1014', '1', '2017-01-25 15:22:52');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1015', '1', '2017-01-25 15:23:04');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1016', '1', '2017-01-26 05:54:06');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1017', '1', '2017-01-26 05:54:46');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1018', '1', '2017-01-26 05:54:53');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1019', '1', '2017-01-26 05:56:01');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1020', '1', '2017-01-26 06:10:41');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1021', '1', '2017-01-26 06:38:37');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1022', '1', '2017-01-26 09:50:20');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1023', '2', '2017-01-27 11:32:31');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1024', '1', '2017-01-27 12:01:56');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1026', '2', '2017-01-27 12:17:47');
INSERT INTO `pages_access` (`pages_id`, `templates_id`, `ts`) VALUES('1027', '1', '2017-01-27 12:37:06');

DROP TABLE IF EXISTS `pages_parents`;
CREATE TABLE `pages_parents` (
  `pages_id` int(10) unsigned NOT NULL,
  `parents_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`pages_id`,`parents_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('2', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('3', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('3', '2');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('7', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('22', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('22', '2');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('28', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('28', '2');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('29', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('29', '2');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('29', '28');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('30', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('30', '2');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('30', '28');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('31', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('31', '2');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('31', '28');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('1001', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('1002', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('1002', '1001');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('1004', '1');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('1004', '1001');
INSERT INTO `pages_parents` (`pages_id`, `parents_id`) VALUES('1005', '1');

DROP TABLE IF EXISTS `pages_sortfields`;
CREATE TABLE `pages_sortfields` (
  `pages_id` int(10) unsigned NOT NULL DEFAULT '0',
  `sortfield` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`pages_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `pages_sortfields` (`pages_id`, `sortfield`) VALUES('1016', 'name');


DROP TABLE IF EXISTS `session_login_throttle`;
CREATE TABLE `session_login_throttle` (
  `name` varchar(128) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT '0',
  `last_attempt` int(10) unsigned NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `templates`;
CREATE TABLE `templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET ascii NOT NULL,
  `fieldgroups_id` int(10) unsigned NOT NULL DEFAULT '0',
  `flags` int(11) NOT NULL DEFAULT '0',
  `cache_time` mediumint(9) NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fieldgroups_id` (`fieldgroups_id`)
) ENGINE=MyISAM AUTO_INCREMENT=48 DEFAULT CHARSET=utf8;

INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('1', 'home', '1', '0', '0', '{\"useRoles\":1,\"noParents\":1,\"slashUrls\":1,\"compile\":3,\"label\":\"Home\",\"modified\":1485537359,\"ns\":\"ProcessWire\",\"roles\":[37]}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('2', 'admin', '2', '8', '0', '{\"useRoles\":1,\"parentTemplates\":[2],\"allowPageNum\":1,\"redirectLogin\":23,\"slashUrls\":1,\"noGlobal\":1,\"compile\":3,\"modified\":1453457709,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('3', 'user', '3', '8', '0', '{\"useRoles\":1,\"noChildren\":1,\"parentTemplates\":[2],\"slashUrls\":1,\"pageClass\":\"User\",\"noGlobal\":1,\"noMove\":1,\"noTrash\":1,\"noSettings\":1,\"noChangeTemplate\":1,\"nameContentTab\":1}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('4', 'role', '4', '8', '0', '{\"noChildren\":1,\"parentTemplates\":[2],\"slashUrls\":1,\"pageClass\":\"Role\",\"noGlobal\":1,\"noMove\":1,\"noTrash\":1,\"noSettings\":1,\"noChangeTemplate\":1,\"nameContentTab\":1}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('5', 'permission', '5', '8', '0', '{\"noChildren\":1,\"parentTemplates\":[2],\"slashUrls\":1,\"guestSearchable\":1,\"pageClass\":\"Permission\",\"noGlobal\":1,\"noMove\":1,\"noTrash\":1,\"noSettings\":1,\"noChangeTemplate\":1,\"nameContentTab\":1}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('26', 'search', '80', '0', '0', '{\"noChildren\":1,\"noParents\":1,\"allowPageNum\":1,\"slashUrls\":1,\"compile\":3,\"label\":\"Search\",\"modified\":1485526981,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('29', 'basic-page', '83', '0', '0', '{\"slashUrls\":1,\"compile\":3,\"label\":\"Basic page\",\"modified\":1485526981,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('34', 'sitemap', '88', '0', '0', '{\"noChildren\":1,\"noParents\":1,\"redirectLogin\":23,\"slashUrls\":1,\"compile\":3,\"label\":\"Sitemap\",\"modified\":1485427810,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('43', 'blog-post', '97', '0', '0', '{\"parentTemplates\":[44],\"slashUrls\":1,\"compile\":3,\"label\":\"Blog post\",\"modified\":1485532830,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('44', 'blog', '98', '0', '0', '{\"sortfield\":\"-97\",\"noParents\":-1,\"childTemplates\":[43],\"allowPageNum\":1,\"slashUrls\":1,\"compile\":3,\"label\":\"Blog\",\"modified\":1485530079,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('45', 'categories', '99', '0', '0', '{\"noParents\":-1,\"childTemplates\":[46],\"slashUrls\":1,\"compile\":3,\"label\":\"Categories\",\"modified\":1485541446,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('46', 'category', '100', '0', '0', '{\"noChildren\":1,\"parentTemplates\":[45],\"allowPageNum\":1,\"slashUrls\":1,\"compile\":3,\"label\":\"Category\",\"modified\":1485530079,\"ns\":\"ProcessWire\"}');
INSERT INTO `templates` (`id`, `name`, `fieldgroups_id`, `flags`, `cache_time`, `data`) VALUES('47', 'basic-page-edit', '101', '0', '0', '{\"slashUrls\":1,\"compile\":3,\"label\":\"Basic page (front-end editable)\",\"modified\":1485536717,\"ns\":\"ProcessWire\"}');

UPDATE pages SET created_users_id=41, modified_users_id=41, created=NOW(), modified=NOW();

# --- /WireDatabaseBackup {"numTables":26,"numCreateTables":33,"numInserts":416,"numSeconds":0}