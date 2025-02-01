# WhatsApp-like Chat Application Documentation

## Overview
This is a modern, real-time chat application built with PHP, MySQL, and JavaScript. It features a WhatsApp-inspired interface with real-time messaging, friend management, and user authentication.

## Features

### User Management
- User registration and authentication
- Profile customization with avatars
- Last seen status tracking
- Online/offline status indicators

### Friend System
- Send and receive friend requests
- Accept/reject friend requests
- Block/unblock users
- View friend's online status and last seen
- Search for users by username

### Real-time Chat
- Instant message delivery
- Message status indicators (sent, delivered)
- Emoji support
- Message history
- Auto-scrolling chat window
- Typing indicators
- File sharing support (images, documents)

### User Interface
- Modern, responsive design
- Dark mode support
- Mobile-friendly layout
- Real-time updates
- Clean and intuitive navigation

## Technical Implementation

### Backend
- PHP 7.4+ for server-side logic
- MySQL database for data storage
- RESTful API endpoints for client communication
- Session-based authentication
- Prepared statements for SQL security

### Frontend
- Vanilla JavaScript for DOM manipulation
- AJAX for asynchronous communication
- CSS with Tailwind for styling
- Responsive design principles
- Real-time updates using polling

### Database Schema
```sql
-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE,
    password VARCHAR(255),
    avatar VARCHAR(255),
    last_seen DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Friends table
CREATE TABLE friends (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    friend_id INT,
    status ENUM('pending', 'accepted', 'blocked'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (friend_id) REFERENCES users(id)
);

-- Messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT,
    user_id INT,
    content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Security Features
- Password hashing using bcrypt
- SQL injection prevention
- XSS protection
- CSRF protection
- Session security
- Input validation
- File upload validation

## Performance Optimizations
- Message caching
- Optimized database queries
- Lazy loading of messages
- Efficient polling system
- Image optimization
- Minified assets

## Installation

1. Clone the repository
2. Set up a PHP 7.4+ environment with MySQL
3. Import the database schema
4. Configure database connection in `config/database.php`
5. Set up virtual host or use PHP's built-in server
6. Access the application through the web browser

## Configuration
```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'chat_db');
```

## Directory Structure
```
/
├── config/             # Configuration files
├── css/               # Stylesheets
├── docs/              # Documentation
├── includes/          # PHP includes
├── uploads/           # User uploads
│   └── avatars/       # Profile pictures
├── index.php          # Entry point
├── chat.php           # Chat interface
├── friends.php        # Friend management
├── login.php          # User authentication
└── README.md          # Project documentation
```

## Screenshots
![Login Page](docs/images/login.png)
![Chat Interface](docs/images/chat.png)
![Friends Page](docs/images/friends.png)
![Direct Messages](docs/images/direct_messages.png)
![Admin Dashboard](docs/images/admin_dashboard.png)

## Contributing
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License
This project is licensed under the MIT License - see the LICENSE file for details.
