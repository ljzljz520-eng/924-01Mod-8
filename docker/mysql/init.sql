USE templates_db;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  article TEXT,
  preview_images TEXT,
  download_url VARCHAR(255) NOT NULL,
  tags VARCHAR(255),
  is_paid TINYINT(1) DEFAULT 0,
  price DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  real_name VARCHAR(50),
  role ENUM('admin', 'member') DEFAULT 'member',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  category ENUM('wedding', 'catering', 'corporate') NOT NULL,
  cover_image VARCHAR(255),
  created_by INT NOT NULL,
  is_public TINYINT(1) DEFAULT 0,
  share_token VARCHAR(64) UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collection_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  template_id INT NOT NULL,
  sort_order INT DEFAULT 0,
  added_by INT NOT NULL,
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
  FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collection_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  user_id INT NOT NULL,
  can_comment TINYINT(1) DEFAULT 1,
  can_vote TINYINT(1) DEFAULT 1,
  can_edit TINYINT(1) DEFAULT 0,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_member (collection_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  item_id INT NULL,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  is_internal TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES collection_items(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS votes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  item_id INT NOT NULL,
  user_id INT NOT NULL,
  vote_type ENUM('up', 'down') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES collection_items(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_vote (collection_id, item_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  user_id INT NOT NULL,
  purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  amount DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_purchase (template_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS internal_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_id INT NOT NULL,
  item_id INT NULL,
  user_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES collection_items(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO templates (title, description, article, preview_images, download_url, tags, is_paid, price)
VALUES
('极简企业官网', '适合科技类公司的响应式企业官网模板，含产品、案例与联系表单。', '这是一套现代化的企业官网模板，支持移动端自适应，包含首页、产品、案例与联系我们板块。', '["https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/company-template.zip', '企业,响应式,科技', 0, 0.00),
('作品集展示', '为设计师或自由职业者打造的作品集模板，配色大胆，支持多图预览。', '模板包含首页、作品列表、作品详情与联系页面，可快速替换图片与文案。', '["https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1506765515384-028b60a970df?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/portfolio-template.zip', '作品集,创意,展示', 0, 0.00),
('浪漫婚礼邀请函', '精美的婚礼电子邀请函模板，支持倒计时、地图导航和宾客回执。', '专为新人设计的婚礼邀请函，包含浪漫动画效果、照片轮播、婚礼流程展示等功能。', '["https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1511285560929-80b456fea0bc?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/wedding-invite.zip', '婚礼,邀请函,浪漫', 1, 99.00),
('高端婚礼策划方案', '完整的婚礼策划PPT模板，含流程安排、预算管理、场地布置方案。', '专业婚礼策划方案模板，包含20+精美幻灯片，可自定义配色和内容。', '["https://images.unsplash.com/photo-1465495976277-4387d4b0b4c6?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/wedding-plan.zip', '婚礼,策划,PPT', 1, 199.00),
('餐饮开业宣传单', '餐厅开业活动宣传单设计模板，PSD分层可编辑。', '适合各类餐饮门店开业使用的宣传单模板，包含双面设计，可自由修改文字和图片。', '["https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/restaurant-flyer.zip', '餐饮,开业,宣传单', 0, 0.00),
('餐饮品牌VI设计', '整套餐饮品牌VI视觉识别系统模板，含Logo、菜单、工装等。', '完整的餐饮品牌VI设计方案，包含50+个设计文件，适用于各类餐厅和咖啡店。', '["https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/restaurant-vi.zip', '餐饮,VI设计,品牌', 1, 299.00),
('企业年会策划方案', '专业企业年会活动策划方案模板，含流程、节目、预算。', '一站式年会策划解决方案，包含活动主题、流程安排、节目推荐、预算清单等。', '["https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1492684223066-81342ee5ff30?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/annual-meeting-plan.zip', '企业,年会,策划', 1, 159.00),
('企业年会舞台背景', '大气企业年会舞台背景设计素材，PSD高清分层。', '多款年会主题舞台背景设计，适合不同行业企业使用，可快速修改文字和Logo。', '["https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/annual-stage.zip', '企业,年会,舞台背景', 0, 0.00),
('婚礼场地布置效果图', '多款婚礼场地布置3D效果图参考素材。', '精选室内外婚礼场地布置效果图，包含仪式区、签到区、用餐区等设计参考。', '["https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1519671482749-fd09be7ccebf?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/wedding-venue.zip', '婚礼,场地布置,效果图', 1, 79.00),
('餐饮菜单设计模板', '中西餐厅菜单设计模板合集，PSD+CDR格式。', '包含正餐菜单、下午茶菜单、酒水单等多种风格设计，可直接使用或修改。', '["https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1551218808-94e220e084d2?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/menu-templates.zip', '餐饮,菜单,设计', 0, 0.00);

INSERT INTO users (username, email, password_hash, real_name, role)
VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员', 'admin'),
('zhangwei', 'zhangwei@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '张伟', 'member'),
('liming', 'liming@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '李明', 'member'),
('wanghong', 'wanghong@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '王红', 'member');

CREATE TABLE IF NOT EXISTS editable_regions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  region_type VARCHAR(50) DEFAULT 'text',
  region_name VARCHAR(100) NOT NULL,
  config JSON,
  is_editable TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  template_id INT NOT NULL,
  custom_config JSON,
  preview_token VARCHAR(64) UNIQUE,
  download_token VARCHAR(64) UNIQUE,
  status ENUM('pending', 'paid', 'expired') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
