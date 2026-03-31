-- Create database
CREATE DATABASE IF NOT EXISTS `softandpix` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `softandpix`;

-- Hero Section
CREATE TABLE IF NOT EXISTS `hero` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` TEXT,
  `subtitle` TEXT,
  `btn_text` VARCHAR(100),
  `btn_link` VARCHAR(255),
  `hero_image` VARCHAR(255),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- About Section
CREATE TABLE IF NOT EXISTS `about` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tag` VARCHAR(100),
  `title` TEXT,
  `desc1` TEXT,
  `desc2` TEXT,
  `btn_text` VARCHAR(100),
  `about_image` VARCHAR(255)
);

-- Values Section
CREATE TABLE IF NOT EXISTS `values_section` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255),
  `description` TEXT,
  `image` VARCHAR(255),
  `sort_order` INT DEFAULT 0
);

-- Stats/Counts Section
CREATE TABLE IF NOT EXISTS `stats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `icon` VARCHAR(100),
  `icon_color` VARCHAR(50),
  `count_end` INT,
  `label` VARCHAR(100),
  `sort_order` INT DEFAULT 0
);

-- Features Section
CREATE TABLE IF NOT EXISTS `features` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255),
  `features_image` VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS `feature_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `feature_id` INT,
  `title` VARCHAR(255),
  `sort_order` INT DEFAULT 0
);

-- Services Section
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `icon` VARCHAR(100),
  `color_class` VARCHAR(50),
  `title` VARCHAR(255),
  `description` TEXT,
  `sort_order` INT DEFAULT 0
);

-- Pricing Section
CREATE TABLE IF NOT EXISTS `pricing` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255),
  `title_color` VARCHAR(50),
  `image` VARCHAR(255),
  `is_featured` TINYINT DEFAULT 0,
  `sort_order` INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS `pricing_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pricing_id` INT,
  `item_text` VARCHAR(255),
  `sort_order` INT DEFAULT 0
);

-- FAQ Section
CREATE TABLE IF NOT EXISTS `faq` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question` TEXT,
  `answer` TEXT,
  `sort_order` INT DEFAULT 0
);

-- Portfolio Section
CREATE TABLE IF NOT EXISTS `portfolio` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255),
  `category` VARCHAR(50),
  `image` VARCHAR(255),
  `link` VARCHAR(255),
  `sort_order` INT DEFAULT 0
);

-- Testimonials Section
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `role` VARCHAR(255),
  `message` TEXT,
  `image` VARCHAR(255),
  `sort_order` INT DEFAULT 0
);

-- Team Section
CREATE TABLE IF NOT EXISTS `team` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `role` VARCHAR(255),
  `bio` TEXT,
  `image` VARCHAR(255),
  `twitter` VARCHAR(255),
  `facebook` VARCHAR(255),
  `instagram` VARCHAR(255),
  `linkedin` VARCHAR(255),
  `sort_order` INT DEFAULT 0
);

-- Clients Section
CREATE TABLE IF NOT EXISTS `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `logo` VARCHAR(255),
  `sort_order` INT DEFAULT 0
);

-- Contact/Site Settings
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) UNIQUE,
  `setting_value` TEXT
);

-- Contact Messages
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `email` VARCHAR(255),
  `subject` VARCHAR(255),
  `message` TEXT,
  `is_read` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Newsletter subscribers
CREATE TABLE IF NOT EXISTS `newsletter` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin Users
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) UNIQUE,
  `password` VARCHAR(255),
  `email` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default Data Inserts

INSERT INTO `hero` (`title`, `subtitle`, `btn_text`, `btn_link`, `hero_image`) VALUES
('We Offer Expert Resources for Growing Your Business', 'We helped the IT organizations in head hunting- Front-end, Back-end, Designers, Business Analysts etc.', 'Request a Quote', '#contact', 'assets/img/hero-img.png');

INSERT INTO `about` (`tag`, `title`, `desc1`, `desc2`, `btn_text`, `about_image`) VALUES
('Who We Are', 'We are a Canada based firm offering Front-end, Back-end, Designers, Business Analysts for hire on hourly, part time or monthly basis.', 'We employ resources and our focus on just to ensure highest quality assurance and maintain quality of product developement and other services. Our processes are simple and transparent. We do not bind you to long term agreements and work closely with you to ensure a successful engagement.', 'Do you need on-demand software developers to extend your software development team? Or want to hire a team of dedicated professional resources to be a part of your industry? Get in touch now!', 'Read More', 'assets/img/about.jpg');

INSERT INTO `values_section` (`title`, `description`, `image`, `sort_order`) VALUES
('Global Quality Standards', 'We hire various quality standard resources with global recognition and certification for your projects.', 'assets/img/values-1.png', 1),
('Budget Friendly Services', 'Plenty of ways to market your company without breaking your bank which will be both effective and affordable.', 'assets/img/values-2.png', 2),
('Time-Zone Compatible.', 'Capable of working in flexible mode and stay available as per clients'' need, timezone, and requirement.', 'assets/img/values-3.png', 3);

INSERT INTO `stats` (`icon`, `icon_color`, `count_end`, `label`, `sort_order`) VALUES
('bi bi-emoji-smile', '', 72, 'Happy Clients', 1),
('bi bi-journal-richtext', '#ee6c20', 360, 'Total Projects', 2),
('bi bi-headset', '#15be56', 1280, 'Hours of Support', 3),
('bi bi-people', '#bb0852', 130, 'Total Resources', 4);

INSERT INTO `features` (`title`, `features_image`) VALUES
('Our Core Values', 'assets/img/features.png');

INSERT INTO `feature_items` (`feature_id`, `title`, `sort_order`) VALUES
(1, 'Integrity & Transparency', 1),
(1, '3+ Years Of Average Experience', 2),
(1, 'Free No Obligation Quote', 3),
(1, 'Hassle-free Project Management', 4),
(1, 'Transparency Is Guaranteed', 5),
(1, 'Flexible Engagement Models', 6);

INSERT INTO `services` (`icon`, `color_class`, `title`, `description`, `sort_order`) VALUES
('ri-amazon-fill', 'blue', 'Amazon Affiliate Marketing', 'Amazon Affiliate Marketing has been the most effective option of passive income during the last few years. But only a guideline proper can lead you to the ultimate goal here. And we have come with 360-degree service for your Amazon Affiliate Marketing.', 1),
('ri-gallery-fill', 'orange', 'Social Media Marketing (SMM)', 'Social Media Marketing lets your product to be explored to your targeted audience through social media. This online marketing platform plays a vital role now in product and service marketing online. Our in-depth Social Media Marketing will let you get your desired customers.', 2),
('ri-window-fill', 'green', 'Web Design & Development', 'A good looking website can boost your business much more. It helps to explore your product and service more conveniently. And for that, you need just a unique and resourceful website. We provide web design & development service with the real experts in this field.', 3),
('ri-file-word-2-fill', 'red', 'WordPress Theme Development', 'WordPress is the most popular platform in website building. And an exclusive WordPress theme can let you have a unique website. Our WordPress Theme Development service is dedicated to designing a unique theme for you that will expand your business much.', 4),
('ri-file-search-fill', 'purple', 'SEO & SEM', 'More traffic, more business, and popularity. And to get your desired traffic, your website must be ranked on Google''s first page in the search result. Here you need to go through proper Search Engine Optimization (SEO) and Search Engine Marketing (SEM). We will provide you SEO & SEM service based on our experience.', 5),
('ri-edit-2-fill', 'pink', 'Content Writing', '''Content is king'', a very popular saying of today''s digital marketing. To grab the targeted readers, you need resourceful and engaging content. And we will ensure you very engaging and errorless content for your website. Our experienced content writing team can deal with all types of content.', 6),
('ri-mail-unread-fill', 'orange', 'Email Marketing', 'Email marketing is one of the effective weapons of online marketing. A strong email letter can help you get your expected sales. With our strong email marketing team, we can ensure your best email marketing service. We will design a unique email template for you.', 7),
('ri-facebook-box-fill', 'blue', 'Facebook Marketing', 'Facebook Marketing is the best marketing trend now. It is the only platform, where you will find almost all of your desired customers. But only the expert Facebook marker can give the value of your money. We have several years'' experience on Facebook marketing that let you have a maximum result.', 8),
('ri-secure-payment-fill', 'green', 'E-commerce Site Development', 'Planning to start your own e-commerce business? Then the first thing that you need is a well-designed website. We have already designed some of the prominent e-commerce websites. And our experience will let you have a strong e-commerce website from us.', 9),
('ri-youtube-fill', 'red', 'Youtube Marketing', 'Research shows that people pass their maximum time watching youtube videos on the internet. And this social media platform is the most effective for online marketing now. You need just go through the proper way and we ensure you the best service here. You apply the updated youtube marketing policy for our clients.', 10),
('ri-linkedin-box-fill', 'purple', 'Lead Generation (LinkedIn)', 'A real lead from LinkedIn means a real customer of your product and service. And we provide 100% trustworthiness lead service for you. We will ensure the true LinkedIn lead so that you can get your sales from these leads. We use all the effective tools for that.', 11),
('ri-global-fill', 'pink', 'Website Speed Optimization', 'The low speed of your website can spoil your all efforts. And eventually, you can lose your valuable traffic. Our Website Speed Optimization service is dedicated to bringing the loading speed of your website as minimum as possible. We ensure the minimum loading speed of our client website.', 12);

INSERT INTO `pricing` (`title`, `title_color`, `image`, `is_featured`, `sort_order`) VALUES
('Dedicated Team', '#07d5c0', 'assets/img/pricing-free.png', 0, 1),
('Fixed Price Model', '#65c600', 'assets/img/pricing-starter.png', 1, 2),
('Hourly Model', '#ff901c', 'assets/img/pricing-business.png', 0, 3),
('Ultimate Plan', '#ff0071', 'assets/img/pricing-ultimate.png', 0, 4);

INSERT INTO `pricing_items` (`pricing_id`, `item_text`, `sort_order`) VALUES
(1, 'No hidden costs', 1),
(1, '160 Hours of part & full time', 2),
(1, 'Monthly billing', 3),
(1, 'Pay only for measurable work', 4),
(2, 'No hidden costs', 1),
(2, 'Fixed deadlines & budget', 2),
(2, 'Milestone based payment', 3),
(2, 'No setup fees', 4),
(3, 'No hidden costs', 1),
(3, 'Requirement based working hours', 2),
(3, 'Monthly billing', 3),
(3, 'Pay only for measurable work', 4),
(4, 'No hidden costs', 1),
(4, 'Monthly billing', 2),
(4, 'Milestone based payment', 3),
(4, 'No setup fees', 4),
(4, 'Pay only for measurable work', 5);

INSERT INTO `faq` (`question`, `answer`, `sort_order`) VALUES
('How will I communicate with our hired dedicated developer?', 'Feugiat pretium nibh ipsum consequat. Tempus iaculis urna id volutpat lacus laoreet non curabitur gravida. Venenatis lectus magna fringilla urna porttitor rhoncus dolor purus non.', 1),
('How to find a programmer who can build my enterprise application?', 'Dolor sit amet consectetur adipiscing elit pellentesque habitant morbi. Id interdum velit laoreet id donec ultrices. Fringilla phasellus faucibus scelerisque eleifend donec pretium. Est pellentesque elit ullamcorper dignissim. Mauris ultrices eros in cursus turpis massa tincidunt dui.', 2),
('How many working days & hours in a week do I get when I rent a designers?', 'Eleifend mi in nulla posuere sollicitudin aliquam ultrices sagittis orci. Faucibus pulvinar elementum integer enim. Sem nulla pharetra diam sit amet nisl suscipit. Rutrum tellus pellentesque eu tincidunt. Lectus urna duis convallis convallis tellus. Urna molestie at elementum eu facilisis sed odio morbi quis', 3),
('How many working days & hours in a week do I get when I rent a IT Professionals?', 'Eleifend mi in nulla posuere sollicitudin aliquam ultrices sagittis orci. Faucibus pulvinar elementum integer enim. Sem nulla pharetra diam sit amet nisl suscipit. Rutrum tellus pellentesque eu tincidunt. Lectus urna duis convallis convallis tellus. Urna molestie at elementum eu facilisis sed odio morbi quis', 4),
('How much skilled and experienced are your business analyst?', 'Dolor sit amet consectetur adipiscing elit pellentesque habitant morbi. Id interdum velit laoreet id donec ultrices. Fringilla phasellus faucibus scelerisque eleifend donec pretium. Est pellentesque elit ullamcorper dignissim. Mauris ultrices eros in cursus turpis massa tincidunt dui.', 5),
('What is your work hours when I hire coders on monthly basis?', 'Molestie a iaculis at erat pellentesque adipiscing commodo. Dignissim suspendisse in est ante in. Nunc vel risus commodo viverra maecenas accumsan. Sit amet nisl suscipit adipiscing bibendum est. Purus gravida quis blandit turpis cursus in', 6),
('How to select a qualified resources with the top software skills?', 'Laoreet sit amet cursus sit amet dictum sit amet justo. Mauris vitae ultricies leo integer malesuada nunc vel. Tincidunt eget nullam non nisi est sit amet. Turpis nunc eget lorem dolor sed. Ut venenatis tellus in metus vulputate eu scelerisque. Pellentesque diam volutpat commodo sed egestas egestas fringilla phasellus faucibus. Nibh tellus molestie nunc non blandit massa enim nec.', 7),
('How to select a qualified resources with the top business skills?', 'Laoreet sit amet cursus sit amet dictum sit amet justo. Mauris vitae ultricies leo integer malesuada nunc vel. Tincidunt eget nullam non nisi est sit amet. Turpis nunc eget lorem dolor sed. Ut venenatis tellus in metus vulputate eu scelerisque. Pellentesque diam volutpat commodo sed egestas egestas fringilla phasellus faucibus. Nibh tellus molestie nunc non blandit massa enim nec.', 8);

INSERT INTO `portfolio` (`title`, `category`, `image`, `link`, `sort_order`) VALUES
('App 1', 'App', 'assets/img/portfolio/portfolio-1.jpg', '#', 1),
('Web 1', 'Web', 'assets/img/portfolio/portfolio-2.jpg', '#', 2),
('App 2', 'App', 'assets/img/portfolio/portfolio-3.jpg', '#', 3),
('Card 2', 'Card', 'assets/img/portfolio/portfolio-4.jpg', '#', 4),
('Web 2', 'Web', 'assets/img/portfolio/portfolio-5.jpg', '#', 5),
('App 3', 'App', 'assets/img/portfolio/portfolio-6.jpg', '#', 6),
('Card 1', 'Card', 'assets/img/portfolio/portfolio-7.jpg', '#', 7),
('Card 3', 'Card', 'assets/img/portfolio/portfolio-8.jpg', '#', 8),
('Web 3', 'Web', 'assets/img/portfolio/portfolio-9.jpg', '#', 9);

INSERT INTO `testimonials` (`name`, `role`, `message`, `image`, `sort_order`) VALUES
('Saul Goodman', 'Ceo & Founder', 'Proin iaculis purus consequat sem cure digni ssim donec porttitora entum suscipit rhoncus. Accusantium quam, ultricies eget id, aliquam eget nibh et. Maecen aliquam, risus at semper.', 'assets/img/testimonials/testimonials-1.jpg', 1),
('Sara Wilsson', 'Designer', 'Export tempor illum tamen malis malis eram quae irure esse labore quem cillum quid cillum eram malis quorum velit fore eram velit sunt aliqua noster fugiat irure amet legam anim culpa.', 'assets/img/testimonials/testimonials-2.jpg', 2),
('Jena Karlis', 'Store Owner', 'Enim nisi quem export duis labore cillum quae magna enim sint quorum nulla quem veniam duis minim tempor labore quem eram duis noster aute amet eram fore quis sint minim.', 'assets/img/testimonials/testimonials-3.jpg', 3),
('Matt Brandon', 'Freelancer', 'Fugiat enim eram quae cillum dolore dolor amet nulla culpa multos export minim fugiat minim velit minim dolor enim duis veniam ipsum anim magna sunt elit fore quem dolore labore illum veniam.', 'assets/img/testimonials/testimonials-4.jpg', 4),
('John Larson', 'Entrepreneur', 'Quis quorum aliqua sint quem legam fore sunt eram irure aliqua veniam tempor noster veniam enim culpa labore duis sunt culpa nulla illum cillum fugiat legam esse veniam culpa fore nisi cillum quid.', 'assets/img/testimonials/testimonials-5.jpg', 5);

INSERT INTO `team` (`name`, `role`, `bio`, `image`, `twitter`, `facebook`, `instagram`, `linkedin`, `sort_order`) VALUES
('Walter White', 'Chief Executive Officer', 'Velit aut quia fugit et et. Dolorum ea voluptate vel tempore tenetur ipsa quae aut. Ipsum exercitationem iure minima enim corporis et voluptate.', 'assets/img/team/team-1.jpg', '', '', '', '', 1),
('Sarah Jhonson', 'Product Manager', 'Quo esse repellendus quia id. Est eum et accusantium pariatur fugit nihil minima suscipit corporis. Voluptate sed quas reiciendis animi neque sapiente.', 'assets/img/team/team-2.jpg', '', '', '', '', 2),
('William Anderson', 'CTO', 'Vero omnis enim consequatur. Voluptas consectetur unde qui molestiae deserunt. Voluptates enim aut architecto porro aspernatur molestiae modi.', 'assets/img/team/team-3.jpg', '', '', '', '', 3),
('Amanda Jepson', 'Accountant', 'Rerum voluptate non adipisci animi distinctio et deserunt amet voluptas. Quia aut aliquid doloremque ut possimus ipsum officia.', 'assets/img/team/team-4.jpg', '', '', '', '', 4);

INSERT INTO `clients` (`name`, `logo`, `sort_order`) VALUES
('Client 1', 'assets/img/clients/client-1.png', 1),
('Client 2', 'assets/img/clients/client-2.png', 2),
('Client 3', 'assets/img/clients/client-3.png', 3),
('Client 4', 'assets/img/clients/client-4.png', 4),
('Client 5', 'assets/img/clients/client-5.png', 5),
('Client 6', 'assets/img/clients/client-6.png', 6),
('Client 7', 'assets/img/clients/client-7.png', 7),
('Client 8', 'assets/img/clients/client-8.png', 8);

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_title', 'Softandpix'),
('meta_description', 'We offer expert resources for growing your business'),
('meta_keywords', 'IT resources, web development, SEO, digital marketing'),
('address', 'Canada Office: 3770 Westwinds Drive NE, Calgary, AB, T3J 5H3'),
('phone', '#403 805 6999'),
('email', 'info@softandpix.com'),
('open_hours', 'Monday - Friday, Open 24 hours'),
('twitter_url', '#'),
('facebook_url', '#'),
('instagram_url', '#'),
('linkedin_url', '#'),
('footer_copyright', '© Copyright Softandpix. All Rights Reserved'),
('footer_powered_by', 'Softandpix'),
('newsletter_title', 'Our Newsletter'),
('newsletter_description', 'Subscribe to our newsletter for the latest updates');

-- Admin user password: 'admin123' (bcrypt)
INSERT INTO `admin_users` (`username`, `password`, `email`) VALUES
('admin', '$2y$10$hrAAMp2uVWw4v8g7ilIbN.nZlpRf/ipg.AuwVdqPxeDzxvK2nBB8u', 'admin@softandpix.com');

SET FOREIGN_KEY_CHECKS = 0;

-- User System (multi-role)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(100) DEFAULT 'client',
  `avatar` VARCHAR(255),
  `phone` VARCHAR(50),
  `company` VARCHAR(255),
  `country` VARCHAR(100),
  `city` VARCHAR(100) DEFAULT NULL,
  `address` TEXT,
  `bio` TEXT,
  `skills` TEXT,
  `portfolio_url` VARCHAR(500),
  `github_url` VARCHAR(500),
  `linkedin_url` VARCHAR(500),
  `dribbble_url` VARCHAR(500),
  `behance_url` VARCHAR(500),
  `timezone` VARCHAR(100) DEFAULT 'UTC',
  `website` VARCHAR(500) DEFAULT NULL,
  `social_github` VARCHAR(500) DEFAULT NULL,
  `social_linkedin` VARCHAR(500) DEFAULT NULL,
  `social_twitter` VARCHAR(500) DEFAULT NULL,
  `specialization` VARCHAR(255) DEFAULT NULL,
  `availability_status` ENUM('available','busy','on_leave') DEFAULT 'available',
  `custom_field_1_label` VARCHAR(100),
  `custom_field_1_value` VARCHAR(500),
  `custom_field_2_label` VARCHAR(100),
  `custom_field_2_value` VARCHAR(500),
  `is_active` TINYINT DEFAULT 1,
  `email_verified` TINYINT DEFAULT 0,
  `verification_token` VARCHAR(255),
  `reset_token` VARCHAR(255),
  `reset_token_expires` DATETIME NULL,
  `login_attempts` INT DEFAULT 0,
  `locked_until` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL,
  `last_active` TIMESTAMP NULL DEFAULT NULL
);

-- Custom Roles
CREATE TABLE IF NOT EXISTS `custom_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(100) UNIQUE NOT NULL,
  `role_label` VARCHAR(100) NOT NULL,
  `role_color` VARCHAR(20) DEFAULT '#6c757d',
  `role_icon` VARCHAR(100) DEFAULT 'bi-person',
  `description` TEXT,
  `profile_fields` JSON,
  `permissions` JSON,
  `is_active` TINYINT DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projects
CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `client_id` INT NULL,
  `developer_id` INT NULL,
  `admin_id` INT NULL,
  `status` ENUM('pending','in_progress','on_hold','completed','cancelled') DEFAULT 'pending',
  `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `start_date` DATE NULL,
  `deadline` DATE NULL,
  `budget` DECIMAL(10,2) DEFAULT 0,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `demo_subdomain` VARCHAR(100) NULL,
  `demo_url` VARCHAR(500) NULL,
  `demo_enabled` TINYINT DEFAULT 0,
  `demo_password` VARCHAR(255) NULL,
  `demo_expires_at` DATETIME NULL,
  `demo_has_files` TINYINT DEFAULT 0,
  `progress` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `project_milestones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `due_date` DATE NULL,
  `status` ENUM('pending','in_progress','completed') DEFAULT 'pending',
  `sort_order` INT DEFAULT 0,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_project` (`project_id`)
);

CREATE TABLE IF NOT EXISTS `project_updates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `project_files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `folder_id` INT DEFAULT NULL,
  `uploaded_by` INT NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` BIGINT DEFAULT 0,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_extension` VARCHAR(20) DEFAULT NULL,
  `version` INT DEFAULT 1,
  `parent_file_id` INT DEFAULT NULL COMMENT 'previous version',
  `description` TEXT,
  `download_count` INT DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_project` (`project_id`),
  INDEX `idx_folder` (`folder_id`),
  INDEX `idx_parent_file` (`parent_file_id`)
);

CREATE TABLE IF NOT EXISTS `deadline_extension_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `developer_id` INT NOT NULL,
  `current_deadline` DATE NOT NULL,
  `requested_deadline` DATE NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
  `admin_note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Chat (conversation-based system)
CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT DEFAULT NULL,
  `type` ENUM('project','direct','support') DEFAULT 'project',
  `title` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_project` (`project_id`)
);

CREATE TABLE IF NOT EXISTS `chat_participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `user_id` INT NOT NULL COMMENT '0 = admin',
  `role` ENUM('admin','developer','client') DEFAULT 'client',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` TIMESTAMP NULL,
  `is_typing` TINYINT(1) DEFAULT 0,
  `typing_updated_at` TIMESTAMP NULL,
  UNIQUE KEY `unique_conv_user` (`conversation_id`, `user_id`),
  INDEX `idx_user` (`user_id`)
);

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `sender_id` INT NOT NULL COMMENT '0 = admin',
  `message` TEXT,
  `message_type` ENUM('text','file','image','link','system') DEFAULT 'text',
  `file_path` VARCHAR(500) DEFAULT NULL,
  `file_name` VARCHAR(255) DEFAULT NULL,
  `file_size` INT DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_conv_created` (`conversation_id`, `created_at`),
  INDEX `idx_sender` (`sender_id`),
  INDEX `idx_unread` (`conversation_id`, `is_read`, `sender_id`)
);

-- Invoices
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) UNIQUE,
  `project_id` INT NULL,
  `client_id` INT NOT NULL,
  `admin_id` INT NULL,

  `issue_date` DATE,
  `due_date` DATE,
  `subtotal` DECIMAL(10,2) DEFAULT 0,
  `tax_rate` DECIMAL(5,2) DEFAULT 0,
  `tax_amount` DECIMAL(10,2) DEFAULT 0,
  `discount` DECIMAL(10,2) DEFAULT 0,
  `total` DECIMAL(10,2) DEFAULT 0,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `notes` TEXT,
  `amount_paid` DECIMAL(10,2) DEFAULT 0.00,
  `last_reminder_sent` TIMESTAMP NULL,
  `paid_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `quantity` DECIMAL(10,2) DEFAULT 1,
  `unit_price` DECIMAL(10,2) DEFAULT 0,
  `amount` DECIMAL(10,2) DEFAULT 0,
  `sort_order` INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS `invoice_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(100),
  `transaction_id` VARCHAR(255),
  `status` VARCHAR(100) DEFAULT 'completed',
  `notes` TEXT,
  `paid_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(100),
  `title` VARCHAR(255),
  `message` TEXT,
  `link` VARCHAR(500),
  `is_read` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Project Folders & File Manager
CREATE TABLE IF NOT EXISTS `project_folders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_project` (`project_id`),
  INDEX `idx_parent` (`parent_id`)
);

CREATE TABLE IF NOT EXISTS `file_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `file_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_file` (`file_id`)
);

-- Project Tasks & Activity
CREATE TABLE IF NOT EXISTS `project_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `milestone_id` INT DEFAULT NULL,
  `assigned_to` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `status` ENUM('todo','in_progress','review','completed') DEFAULT 'todo',
  `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
  `estimated_hours` DECIMAL(5,1) DEFAULT NULL,
  `actual_hours` DECIMAL(5,1) DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_project` (`project_id`),
  INDEX `idx_milestone` (`milestone_id`),
  INDEX `idx_assigned` (`assigned_to`),
  INDEX `idx_status` (`status`)
);

CREATE TABLE IF NOT EXISTS `project_activity_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_project` (`project_id`, `created_at`)
);

CREATE TABLE IF NOT EXISTS `project_daily_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `log_date` DATE NOT NULL,
  `hours_worked` DECIMAL(5,1) DEFAULT 0,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_date` (`project_id`, `user_id`, `log_date`),
  INDEX `idx_project_date` (`project_id`, `log_date`)
);

-- Live Contact Widget
CREATE TABLE IF NOT EXISTS `live_contacts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `message` TEXT,
  `status` ENUM('new','chatting','converted','closed') DEFAULT 'new',
  `assigned_admin_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL COMMENT 'linked user account after auto-creation',
  `session_token` VARCHAR(64) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_session` (`session_token`),
  INDEX `idx_email` (`email`)
);

CREATE TABLE IF NOT EXISTS `live_contact_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `contact_id` INT NOT NULL,
  `sender_type` ENUM('guest','admin') NOT NULL,
  `sender_id` INT DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_contact` (`contact_id`, `created_at`),
  INDEX `idx_unread` (`contact_id`, `is_read`, `sender_type`)
);

-- Internal Messages
CREATE TABLE IF NOT EXISTS `internal_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id` INT NOT NULL,
  `recipient_id` INT NOT NULL,
  `project_id` INT DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `parent_id` INT DEFAULT NULL COMMENT 'for threaded replies',
  `is_deleted_sender` TINYINT(1) DEFAULT 0,
  `is_deleted_recipient` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sender` (`sender_id`),
  INDEX `idx_recipient` (`recipient_id`, `is_read`),
  INDEX `idx_project` (`project_id`),
  INDEX `idx_parent` (`parent_id`)
);

-- Invoice Emails Log
CREATE TABLE IF NOT EXISTS `invoice_emails` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `sent_to` VARCHAR(255) NOT NULL,
  `sent_by` INT NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT,
  `pdf_path` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('sent','failed') DEFAULT 'sent',
  `error_message` TEXT DEFAULT NULL,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_invoice` (`invoice_id`)
);

-- Password Resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token` (`token`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate Limiting
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `attempts` INT DEFAULT 1,
  `first_attempt_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_attempt_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_ip_action` (`ip_address`, `action`),
  INDEX `idx_ip` (`ip_address`)
);

-- Subscription Plans
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `billing_cycle` ENUM('monthly','quarterly','yearly') DEFAULT 'monthly',
  `features` TEXT COMMENT 'JSON array of features',
  `is_popular` TINYINT(1) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `stripe_price_id` VARCHAR(255) DEFAULT NULL,
  `paypal_plan_id` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `plan_id` INT NOT NULL,
  `payment_gateway` ENUM('stripe','paypal','manual') NOT NULL,
  `gateway_subscription_id` VARCHAR(255) DEFAULT NULL,
  `gateway_customer_id` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','past_due','cancelled','expired','trialing') DEFAULT 'active',
  `current_period_start` TIMESTAMP NULL,
  `current_period_end` TIMESTAMP NULL,
  `cancelled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_gateway_sub` (`gateway_subscription_id`)
);

CREATE TABLE IF NOT EXISTS `subscription_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `payment_gateway` ENUM('stripe','paypal','manual') NOT NULL,
  `gateway_payment_id` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('succeeded','pending','failed','refunded') DEFAULT 'pending',
  `paid_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_subscription` (`subscription_id`),
  INDEX `idx_user` (`user_id`)
);

-- Email Log
CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `to_email` VARCHAR(255) NOT NULL,
  `to_name` VARCHAR(255),
  `subject` VARCHAR(500),
  `body` TEXT,
  `status` ENUM('sent','failed') DEFAULT 'sent',
  `error_message` TEXT,
  `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Email Templates
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body` TEXT NOT NULL,
  `variables` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- Additional tables: conversations / messages
-- =====================================================
CREATE TABLE IF NOT EXISTS `conversations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` ENUM('direct','group') DEFAULT 'direct',
    `name` VARCHAR(255),
    `created_by` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `conversation_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `message` TEXT,
    `message_type` ENUM('text','agreement','system','ai') DEFAULT 'text',
    `agreement_id` INT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Agreements
-- =====================================================
CREATE TABLE IF NOT EXISTS `agreements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `project_name` VARCHAR(255) DEFAULT NULL,
    `total_budget` DECIMAL(12,2) DEFAULT 0,
    `deadline` DATE DEFAULT NULL,
    `terms` TEXT,
    `status` ENUM('draft','pending','approved','rejected','signed') DEFAULT 'pending',
    `signed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Tasks & task comments
-- =====================================================
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `assigned_to` INT DEFAULT NULL,
    `created_by` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
    `status` ENUM('pending','in_progress','completed','on_hold') DEFAULT 'pending',
    `due_date` DATE DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `task_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Activity log
-- =====================================================
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Time tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS `time_entries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `project_id` INT NOT NULL,
    `task_id` INT DEFAULT NULL,
    `description` TEXT,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME DEFAULT NULL,
    `duration_minutes` INT DEFAULT 0,
    `is_manual` TINYINT(1) DEFAULT 0,
    `is_approved` TINYINT(1) DEFAULT 0,
    `approved_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_project` (`user_id`, `project_id`),
    INDEX `idx_date` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `active_timers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `project_id` INT NOT NULL,
    `task_id` INT DEFAULT NULL,
    `start_time` DATETIME NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Theme settings
-- =====================================================
CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Recurring invoices
-- =====================================================
CREATE TABLE IF NOT EXISTS `recurring_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `project_id` INT DEFAULT NULL,
    `frequency` ENUM('weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    `next_generate_date` DATE NOT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('active','paused','cancelled') DEFAULT 'active',
    `line_items` JSON NOT NULL,
    `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
    `notes` TEXT,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_next_date` (`next_generate_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recurring_invoice_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recurring_invoice_id` INT NOT NULL,
    `generated_invoice_id` INT NOT NULL,
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`generated_invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Emails & attachments
-- =====================================================
CREATE TABLE IF NOT EXISTS `emails` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `from_user_id` INT DEFAULT NULL,
    `from_email` VARCHAR(255),
    `to_user_id` INT DEFAULT NULL,
    `to_email` VARCHAR(255),
    `subject` VARCHAR(500),
    `body` TEXT,
    `attachments` TEXT,
    `is_read` TINYINT(1) DEFAULT 0,
    `is_starred` TINYINT(1) DEFAULT 0,
    `is_draft` TINYINT(1) DEFAULT 0,
    `is_trash` TINYINT(1) DEFAULT 0,
    `folder` ENUM('inbox','sent','draft','trash') DEFAULT 'inbox',
    `smtp_account` ENUM('info','support') DEFAULT 'support',
    `sent_via_smtp` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email_id` INT NOT NULL,
    `file_name` VARCHAR(255),
    `file_path` VARCHAR(500),
    `file_size` INT,
    FOREIGN KEY (`email_id`) REFERENCES `emails`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Chatbot rules
-- =====================================================
CREATE TABLE IF NOT EXISTS `chatbot_rules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `keywords` VARCHAR(500),
    `response` TEXT,
    `is_active` TINYINT(1) DEFAULT 1,
    `priority` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Payments & payment gateways
-- =====================================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `client_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `gateway` ENUM('square','stripe','paypal','manual') NOT NULL,
    `transaction_id` VARCHAR(255),
    `status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    `gateway_response` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_gateways` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gateway_name` VARCHAR(50) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 0,
    `api_key` VARCHAR(500),
    `api_secret` VARCHAR(500),
    `sandbox_mode` TINYINT(1) DEFAULT 1,
    `extra_config` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `gateway` ENUM('stripe','paypal','square','manual') NOT NULL,
    `transaction_id` VARCHAR(255),
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `status` ENUM('pending','completed','failed','refunded','partially_refunded') DEFAULT 'pending',
    `gateway_response` JSON,
    `payment_method` VARCHAR(100),
    `receipt_url` VARCHAR(500),
    `metadata` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_invoice` (`invoice_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_gateway` (`gateway`),
    INDEX `idx_status` (`status`),
    INDEX `idx_transaction` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gateway` VARCHAR(50) NOT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_gateway_key` (`gateway`, `setting_key`),
    INDEX `idx_gateway` (`gateway`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `refunds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `refund_id` VARCHAR(255),
    `amount` DECIMAL(10,2) NOT NULL,
    `reason` TEXT,
    `status` ENUM('pending','processed','failed') DEFAULT 'pending',
    `processed_by` INT DEFAULT NULL,
    `gateway_response` JSON,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME DEFAULT NULL,
    FOREIGN KEY (`transaction_id`) REFERENCES `payment_transactions`(`id`) ON DELETE CASCADE,
    INDEX `idx_transaction` (`transaction_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Progress updates
-- =====================================================
CREATE TABLE IF NOT EXISTS `progress_updates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `update_text` TEXT,
    `progress_percent` INT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- E-signature tables
-- =====================================================
CREATE TABLE IF NOT EXISTS `esign_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `content` LONGTEXT,
    `file_path` VARCHAR(500),
    `created_by` INT NOT NULL,
    `status` ENUM('draft','pending','signed','expired','revoked') DEFAULT 'draft',
    `unique_hash` VARCHAR(64) NOT NULL UNIQUE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_hash` (`unique_hash`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `esign_signatures` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `document_id` INT NOT NULL,
    `signer_id` INT DEFAULT NULL,
    `signer_name` VARCHAR(255) NOT NULL,
    `signer_email` VARCHAR(255) NOT NULL,
    `signature_data` LONGTEXT,
    `signature_type` ENUM('draw','type','upload') DEFAULT 'draw',
    `signature_ip` VARCHAR(45),
    `signed_at` DATETIME DEFAULT NULL,
    `status` ENUM('pending','signed','declined') DEFAULT 'pending',
    `decline_reason` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_document` (`document_id`),
    INDEX `idx_signer` (`signer_id`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`document_id`) REFERENCES `esign_documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `esign_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `content` LONGTEXT NOT NULL,
    `category` VARCHAR(100) DEFAULT 'general',
    `created_by` INT NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `esign_audit_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `document_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `actor_id` INT DEFAULT NULL,
    `actor_name` VARCHAR(255),
    `actor_ip` VARCHAR(45),
    `details` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_document` (`document_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`),
    FOREIGN KEY (`document_id`) REFERENCES `esign_documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Export history
-- =====================================================
CREATE TABLE IF NOT EXISTS `export_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `export_type` ENUM('csv','excel','pdf') NOT NULL,
    `data_type` VARCHAR(50) NOT NULL,
    `filters` JSON,
    `file_path` VARCHAR(500),
    `file_name` VARCHAR(255),
    `file_size` INT DEFAULT 0,
    `record_count` INT DEFAULT 0,
    `status` ENUM('pending','completed','failed') DEFAULT 'completed',
    `error_message` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_data_type` (`data_type`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Push subscriptions
-- =====================================================
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `endpoint` VARCHAR(500) NOT NULL,
    `p256dh` VARCHAR(500) NOT NULL,
    `auth` VARCHAR(500) NOT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_used_at` DATETIME DEFAULT NULL,
    `last_error` TEXT DEFAULT NULL,
    `error_count` INT DEFAULT 0,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_active` (`user_id`, `is_active`),
    UNIQUE KEY `unique_endpoint` (`endpoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Invoice reminders
-- =====================================================
CREATE TABLE IF NOT EXISTS `invoice_reminders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `sent_to` VARCHAR(255) NOT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    INDEX `idx_invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Search history
-- =====================================================
CREATE TABLE IF NOT EXISTS `search_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `query` VARCHAR(255) NOT NULL,
    `results_count` INT DEFAULT 0,
    `entity_types` VARCHAR(255),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_query` (`query`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Message translations
-- =====================================================
CREATE TABLE IF NOT EXISTS `message_translations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT NOT NULL,
    `source_lang` VARCHAR(10) DEFAULT 'auto',
    `target_lang` VARCHAR(10) NOT NULL,
    `original_text` TEXT NOT NULL,
    `translated_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_translation` (`message_id`, `target_lang`),
    INDEX `idx_message` (`message_id`),
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Video call tables
-- =====================================================
CREATE TABLE IF NOT EXISTS `video_rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_code` VARCHAR(20) UNIQUE NOT NULL,
    `room_name` VARCHAR(255) NOT NULL,
    `room_type` ENUM('instant','scheduled','recurring') DEFAULT 'instant',
    `created_by` INT NOT NULL,
    `max_participants` INT DEFAULT 6,
    `status` ENUM('waiting','active','ended','cancelled') DEFAULT 'waiting',
    `is_recording` TINYINT(1) DEFAULT 0,
    `scheduled_at` DATETIME NULL,
    `scheduled_end` DATETIME NULL,
    `recurrence_rule` VARCHAR(100) NULL,
    `description` TEXT NULL,
    `password` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ended_at` DATETIME NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`),
    INDEX `idx_scheduled` (`scheduled_at`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `video_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `is_muted` TINYINT(1) DEFAULT 0,
    `is_video_on` TINYINT(1) DEFAULT 1,
    `is_screen_sharing` TINYINT(1) DEFAULT 0,
    `role` ENUM('host','co-host','participant') DEFAULT 'participant',
    `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `left_at` DATETIME NULL,
    FOREIGN KEY (`room_id`) REFERENCES `video_rooms`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_room` (`room_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `video_signals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `from_user` INT NOT NULL,
    `to_user` INT NOT NULL,
    `signal_type` ENUM('offer','answer','ice-candidate','join','leave','mute','unmute','screen-start','screen-stop') NOT NULL,
    `signal_data` LONGTEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_to_room` (`to_user`, `room_id`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `video_chat` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `video_rooms`(`id`) ON DELETE CASCADE,
    INDEX `idx_room_time` (`room_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `meeting_invitations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `invited_by` INT NOT NULL,
    `invited_user_id` INT NULL,
    `invited_email` VARCHAR(255) NULL,
    `status` ENUM('pending','accepted','declined') DEFAULT 'pending',
    `email_sent` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_id`) REFERENCES `video_rooms`(`id`) ON DELETE CASCADE,
    INDEX `idx_room` (`room_id`),
    INDEX `idx_user` (`invited_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- Cart & Orders
-- =====================================================
CREATE TABLE IF NOT EXISTS `cart` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `session_id` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cart_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cart_id` INT NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `item_type` VARCHAR(50) DEFAULT 'service',
    `item_id` INT DEFAULT NULL,
    `quantity` INT DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cart_id`) REFERENCES `cart`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `invoice_id` INT DEFAULT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'USD',
    `payment_method` VARCHAR(50) DEFAULT NULL,
    `status` ENUM('pending','paid','failed','refunded','cancelled') DEFAULT 'pending',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Default custom roles
INSERT IGNORE INTO `custom_roles` (`role_name`, `role_label`, `role_color`, `role_icon`, `description`, `profile_fields`, `permissions`) VALUES
('admin', 'Admin', '#dc3545', 'bi-shield-fill', 'Full system access', '["name","email","phone","avatar","password"]', '{"all":true}'),
('developer', 'Developer', '#0d6efd', 'bi-code-slash', 'Can view and update assigned projects', '["name","email","phone","avatar","bio","skills","portfolio_url","github_url","linkedin_url","password"]', '{"view_projects":true,"update_progress":true,"chat":true,"deadline_request":true,"upload_files":true}'),
('client', 'Client', '#198754', 'bi-person-fill', 'Can view own projects and pay invoices', '["name","email","phone","avatar","company","country","address","password"]', '{"view_own_projects":true,"chat":true,"view_invoices":true,"make_payments":true}'),
('editor', 'Editor', '#6f42c1', 'bi-pencil-fill', 'Can view assigned projects and upload files', '["name","email","phone","avatar","bio","skills","portfolio_url","linkedin_url","custom_field_1","password"]', '{"view_assigned_projects":true,"chat":true,"upload_files":true}'),
('ui_designer', 'UI Designer', '#fd7e14', 'bi-palette-fill', 'Can view assigned projects and upload designs', '["name","email","phone","avatar","bio","skills","portfolio_url","dribbble_url","behance_url","linkedin_url","custom_field_1","password"]', '{"view_assigned_projects":true,"chat":true,"upload_files":true}'),
('seo_specialist', 'SEO Specialist', '#20c997', 'bi-graph-up', 'Can view assigned projects', '["name","email","phone","avatar","bio","skills","portfolio_url","linkedin_url","custom_field_1","custom_field_2","password"]', '{"view_assigned_projects":true,"chat":true}');

-- Default admin user in users table (password: admin123)
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `is_active`, `email_verified`) VALUES
('Admin', 'admin@softandpix.com', '$2y$10$13mlfM85ZRm4qUF48A6U2.0gSga9yxCpPqNptTSnKe52Pb88tSgLy', 'admin', 1, 1);

-- All site settings: SMTP, payments, notifications, and per-account email/webmail config
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('smtp_host', 'softandpix.com'),
('smtp_port', '465'),
('smtp_encryption', 'ssl'),
('smtp_username', 'support@softandpix.com'),
('smtp_password', ''),
('smtp_from_email', 'support@softandpix.com'),
('smtp_from_name', 'Softandpix Support'),
('admin_notification_email', 'info@softandpix.com'),
('notify_contact_form', '1'),
('notify_new_user', '1'),
('notify_project_update', '1'),
('notify_invoice_sent', '1'),
('notify_payment_received', '1'),
('notify_deadline_request', '1'),
('paypal_client_id', ''),
('paypal_secret', ''),
('paypal_mode', 'sandbox'),
('stripe_public_key', ''),
('stripe_secret_key', ''),
('stripe_mode', 'test'),
('square_app_id', ''),
('square_access_token', ''),
('square_location_id', ''),
('square_mode', 'sandbox'),
('email_account_1_email', 'info@softandpix.com'),
('email_account_1_password', ''),
('email_account_1_imap_host', 'imap.zoho.com'),
('email_account_1_imap_port', '993'),
('email_account_1_smtp_host', 'smtp.zoho.com'),
('email_account_1_smtp_port', '465'),
('email_account_1_smtp_encryption', 'ssl'),
('email_account_1_label', 'Info (Zoho)'),
('email_account_1_username', 'info@softandpix.com'),
('email_account_2_email', 'support@softandpix.com'),
('email_account_2_password', ''),
('email_account_2_imap_host', 'softandpix.com'),
('email_account_2_imap_port', '993'),
('email_account_2_smtp_host', 'softandpix.com'),
('email_account_2_smtp_port', '465'),
('email_account_2_smtp_encryption', 'ssl'),
('email_account_2_label', 'Support (Hosting)'),
('email_account_2_username', 'support@softandpix.com');
