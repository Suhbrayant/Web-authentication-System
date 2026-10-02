# Web Authentication System

A secure web-based authentication system developed with HTML, PHP, JavaScript, CSS, and MySQL. This application provides login and registration functionality for both administrators and regular users, with role-based access control and secure data management.

## Features

- **User Registration & Login** - Secure registration and login system for both admins and users
- **Role-Based Access Control** - Separate dashboards and permissions for administrators and regular users
- **Secure Password Handling** - Password encryption and secure storage in MySQL database
- **Session Management** - Secure session handling with PHP session management
- **Responsive Design** - Clean, responsive UI built with HTML5, CSS3, and modern styling
- **Form Validation** - Client-side and server-side validation for all user inputs
- **Error Handling** - User-friendly error messages and validation feedback

## Tech Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP
- **Database**: MySQL
- **Server**: Apache (XAMPP)

## Project Structure

```
Web-authentication-System/
├── admin_page.php          # Admin dashboard
├── config.php              # Database configuration
├── index.php               # Landing page
├── login_register.php      # Login & registration page
├── logout.php              # Logout functionality
├── user_page.php           # User dashboard
├── script.js               # JavaScript functionality
├── styles.css              # CSS styling
└── README.md               # Project documentation
```

## Setup Instructions

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server (XAMPP recommended)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Subrayant/Web-authentication-System.git
   cd Web-authentication-System
   ```

2. **Setup Database**
   - Open phpMyAdmin
   - Create a database named `users_db`
   - Make sure the existing `users_db` database is available to MySQL

3. **Configure Database Connection**
   - Confirm the credentials in `config.php` match your local MySQL setup:
   ```php
   $host = 'localhost';
   $db_user = 'root';
   $db_pass = '';
   $db_name = 'users_db';
   ```

4. **Run the Application**
   - Place the project folder in `htdocs` (for XAMPP)
   - Access via: `http://localhost/Web-authentication-System/`

## Usage

### Registration
1. Click on the "Register" link on the login page
2. Fill in your details:
   - Full Name
   - Email Address
   - Password
   - Select Role (User or Admin)
3. Click "Register" to create your account

### Login
1. Enter your email address
2. Enter your password
3. Click "Login" to access your dashboard

### User Dashboard
- View user profile information
- Access user-specific features (coming soon)

### Admin Dashboard
- View admin panel
- Manage system settings (coming soon)

## Current Status

✅ **Completed**
- User registration with email validation
- Secure login system with password encryption
- Role-based access control (Admin/User)
- Session management
- Form validation and error handling
- Responsive UI design

🚧 **In Development**
- Dashboard utilities and features
- Additional user management tools
- Admin control panel features
- User profile management

## Security Features

- Passwords are securely encrypted before storage
- Session-based authentication
- Input validation to prevent SQL injection
- Protected dashboard pages requiring authentication
- Secure logout functionality

## Future Enhancements

- Email verification during registration
- Password reset functionality
- Two-factor authentication (2FA)
- User profile management
- Admin user management panel
- Activity logging and audit trails
- Dashboard analytics and statistics

## Contributing

Contributions are welcome! Feel free to fork this repository and submit pull requests for any improvements.

## License

This project is open source and available under the MIT License.

## Author

**Subrayant**

## Contact

For questions or support, please create an issue in the GitHub repository.

---

**Note**: This project is actively being developed. Dashboard features and additional utilities are currently under construction.
