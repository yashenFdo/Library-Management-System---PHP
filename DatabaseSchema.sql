-- Library Management System Database Schema

CREATE DATABASE IF NOT EXISTS library_management;
USE library_management;

-- Users Table (All system users)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    role ENUM('super_admin', 'admin', 'staff', 'user') NOT NULL DEFAULT 'user',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories Table
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Authors Table
CREATE TABLE authors (
    author_id INT PRIMARY KEY AUTO_INCREMENT,
    author_name VARCHAR(100) NOT NULL,
    biography TEXT,
    birth_date DATE,
    nationality VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (author_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Books Table
CREATE TABLE books (
    book_id INT PRIMARY KEY AUTO_INCREMENT,
    isbn VARCHAR(20) UNIQUE,
    title VARCHAR(255) NOT NULL,
    author_id INT,
    category_id INT,
    publisher VARCHAR(100),
    publish_year YEAR,
    pages INT,
    quantity INT DEFAULT 1,
    available_quantity INT DEFAULT 1,
    description TEXT,
    cover_image VARCHAR(255),
    location VARCHAR(50),
    added_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(author_id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_title (title),
    INDEX idx_isbn (isbn),
    INDEX idx_category (category_id),
    INDEX idx_author (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Borrowing Records Table
CREATE TABLE borrowings (
    borrowing_id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    borrowed_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    status ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    issued_by INT,
    returned_to INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (returned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_book (book_id),
    INDEX idx_dates (borrowed_date, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reservations Table
CREATE TABLE reservations (
    reservation_id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    reservation_date DATE NOT NULL,
    status ENUM('pending', 'fulfilled', 'cancelled', 'expired') DEFAULT 'pending',
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_book (book_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity Logs Table
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fines Table
CREATE TABLE fines (
    fine_id INT PRIMARY KEY AUTO_INCREMENT,
    borrowing_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    paid_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (borrowing_id) REFERENCES borrowings(borrowing_id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings Table
CREATE TABLE settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('borrowing_period_days', '14', 'Default borrowing period in days'),
('max_books_per_user', '5', 'Maximum books a user can borrow'),
('fine_per_day', '5.00', 'Fine amount per day for overdue books'),
('reservation_expiry_days', '3', 'Days before reservation expires');

-- Insert default users (password for all: admin123)
-- Password hash for 'admin123': $2y$10$e0MYzXyjpJS7Pd0RVvHwHe9Fh.bN7E7h7P5dqN/5RHZ6cRCZqVxHG
INSERT INTO users (username, email, password, full_name, role, status) VALUES
('superadmin', 'superadmin@library.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe9Fh.bN7E7h7P5dqN/5RHZ6cRCZqVxHG', 'Super Administrator', 'super_admin', 'active'),
('admin', 'admin@library.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe9Fh.bN7E7h7P5dqN/5RHZ6cRCZqVxHG', 'Library Admin', 'admin', 'active'),
('staff', 'staff@library.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe9Fh.bN7E7h7P5dqN/5RHZ6cRCZqVxHG', 'Library Staff', 'staff', 'active'),
('user', 'user@library.com', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe9Fh.bN7E7h7P5dqN/5RHZ6cRCZqVxHG', 'Regular User', 'user', 'active');

-- Insert sample categories
INSERT INTO categories (category_name, description, created_by) VALUES
('Fiction', 'Fiction books and novels', 1),
('Non-Fiction', 'Non-fiction and educational books', 1),
('Science', 'Science and technology books', 1),
('History', 'Historical books and biographies', 1),
('Programming', 'Computer programming and software development', 1);

-- Insert sample authors
INSERT INTO authors (author_name, biography, nationality) VALUES
('J.K. Rowling', 'British author, best known for Harry Potter series', 'British'),
('Stephen King', 'American author of horror and suspense novels', 'American'),
('Agatha Christie', 'English writer known for detective novels', 'British'),
('Isaac Asimov', 'American writer and professor of biochemistry', 'American');