# Smart Exam Portal

A comprehensive web-based examination management system built with PHP and MySQL that facilitates online exam creation, management, and taking for educational institutions.

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Architecture](#architecture)
- [Installation](#installation)
- [User Roles](#user-roles)
- [Database Schema](#database-schema)
- [Directory Structure](#directory-structure)
- [API Integration](#api-integration)
- [Security Features](#security-features)
- [Usage Guide](#usage-guide)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

## Overview

The Smart Exam Portal is a role-based online examination system designed to streamline the entire examination process from creation to evaluation. It supports three distinct user roles with specific permissions and provides AI-powered features for enhanced user experience.

### Key Capabilities

- **Multi-role Access Control**: Students, Trainers, and Supervisors with distinct permissions
- **Automated Question Generation**: AI-powered question creation from PDF documents
- **Real-time Exam Taking**: Timed exams with auto-submission features
- **Comprehensive Reporting**: Detailed analytics and performance tracking
- **AI Assistant**: Context-aware chatbot for user support
- **Responsive Design**: Mobile-friendly interface

## Features

### For Students

- **Dashboard**: View upcoming exams, course enrollment, and performance analytics
- **Exam Taking**:
  - Real-time timer with automatic submission
  - Progress tracking and question navigation
  - Anti-cheating measures (session validation, attempt tracking)
- **Results & Analytics**: Detailed performance reports with question-wise analysis
- **Course Management**: Browse and enroll in available courses
- **AI Assistant**: Get help with platform navigation and exam preparation

### For Trainers/Instructors

- **Course Management**: Create, edit, and manage courses with topics
- **Exam Creation**:
  - Manual question creation (MCQ, True/False)
  - AI-powered question generation from PDF content
  - Question bank management and reuse
- **Student Management**: Assign students to courses and exams
- **Result Analysis**: Comprehensive exam analytics and student performance tracking
- **Question Generation**: Upload PDFs and generate questions automatically using Google Gemini AI

### For Supervisors/Administrators

- **System Overview**: Dashboard with comprehensive statistics
- **User Management**:
  - Manage instructors and students
  - Generate invitation codes for registration
  - Reset passwords and manage user profiles
- **Reporting**: Advanced analytics and system-wide reports
- **Course Oversight**: View and manage all courses across the platform
- **System Administration**: Monitor system health and user activities

## Architecture

### Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 8.x
- **Database**: MySQL 8.x
- **AI Integration**: Google Gemini API
- **Email Service**: PHPMailer with Gmail SMTP
- **Session Management**: PHP Sessions
- **File Handling**: PDF processing for AI question generation

### Design Patterns

- **MVC Architecture**: Separation of concerns with modular file structure
- **Role-Based Access Control**: Hierarchical permission system
- **Database Abstraction**: Prepared statements for security
- **Error Handling**: Centralized error management and logging

## Installation

### Prerequisites

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache/Nginx web server
- XAMPP/WAMP (for local development)

### Setup Instructions

1. **Clone/Download** the project files to your web server directory:

   ```bash
   git clone [repository-url]
   cd SmartExamPortal
   ```

2. **Database Configuration**:

   - Update database credentials in `config.php`:
     ```php
     define('DB_SERVER', 'localhost');
     define('DB_USERNAME', 'your_username');
     define('DB_PASSWORD', 'your_password');
     define('DB_NAME', 'smart_exam_portal');
     ```

3. **Database Setup**:

   - The system automatically creates the database and tables on first run
   - Schema files: `database/schema.sql` and `database/studenttrainerschema.sql`

4. **API Configuration**:

   - Get Google Gemini API key from Google AI Studio
   - Update API key in AI-related files (`ai-chat/api-connector.php`)

5. **Email Configuration**:

   - Configure SMTP settings in `includes/mail_helper.php`
   - Set up Gmail app password for email functionality

6. **Permissions**:
   - Ensure write permissions for logs directory
   - Set appropriate file permissions for security

### Initial Setup

1. Access the application via web browser
2. Register as a supervisor using an invitation code
3. Generate invitation codes for trainers and students
4. Begin creating courses and exams

## User Roles

### Student

**Access**: Limited to assigned courses and exams

- View enrolled courses and upcoming exams
- Take assigned exams within time limits
- View results and performance analytics
- Access AI assistant for help

### Trainer/Instructor

**Access**: Course and exam management within assigned scope

- Create and manage courses
- Design exams with manual or AI-generated questions
- Assign students to courses and exams
- Monitor student performance and results
- Generate questions from PDF documents

### Supervisor/Administrator

**Access**: Full system administration

- Manage all users (trainers and students)
- Oversee all courses and exams
- Generate invitation codes
- Access comprehensive system reports
- Monitor system activities

## Database Schema

### Core Tables

#### Users Table

```sql
users (
    id, user_id, password, email, role,
    first_name, last_name, phone, created_at
)
```

#### Courses Table

```sql
courses (
    id, title, description, trainer_id,
    created_at, updated_at
)
```

#### Exams Table

```sql
exams (
    id, title, description, course_id, duration,
    total_marks, start_time, created_by, status
)
```

#### Questions & Options

```sql
questions (
    id, exam_id, question_text, question_type, marks
)

question_options (
    id, question_id, option_text, is_correct
)
```

#### Exam Management

```sql
exam_students (
    id, exam_id, student_id, has_viewed,
    has_attempted, auto_graded
)

exam_attempts (
    id, exam_id, student_id, start_time,
    end_time, score, status
)

student_answers (
    id, attempt_id, question_id, option_id,
    is_correct, points_earned
)
```

### Advanced Features Tables

- **AI Chat History**: Stores chat interactions with AI assistant
- **Invitation Codes**: Manages registration codes by role
- **Course Topics**: Structured course content organization
- **Results**: Comprehensive exam result storage

## Directory Structure

```
SmartExamPortal/
├── ai-chat/                    # AI Assistant functionality
│   ├── api-connector.php       # Google Gemini API integration
│   ├── chat.php               # Main chat interface
│   ├── floating-chat-api.php  # AJAX chat handler
│   └── get-chat-history.php   # Chat history retrieval
├── assets/
│   ├── css/                   # Stylesheets
│   └── js/                    # JavaScript files
├── auth/                      # Authentication system
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── change-password.php
│   └── reset-password.php
├── dashboard/
│   ├── student/               # Student-specific pages
│   ├── trainer/               # Trainer-specific pages
│   └── supervisor/            # Supervisor-specific pages
├── database/                  # Database schema files
├── includes/                  # Shared components
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   ├── sidebar.php
│   ├── error_handler.php
│   └── mail_helper.php
├── vendor/                    # Third-party libraries
├── logs/                      # Application logs
├── config.php                 # Database configuration
├── index.php                 # Application entry point
└── profile.php               # User profile management
```

## API Integration

### Google Gemini AI Integration

The system integrates with Google Gemini API for:

1. **Question Generation**: Upload PDF documents to generate exam questions
2. **AI Chat Assistant**: Context-aware responses based on user roles
3. **Content Analysis**: Extract relevant information from educational materials

#### Implementation Details

- **API Connector**: `ai-chat/api-connector.php` handles all Gemini API communications
- **PDF Processing**: Converts PDF content to base64 for API submission
- **Question Parsing**: Processes AI responses into structured question format
- **Context Management**: Role-based prompts for relevant assistance

### Email Integration

- **Service**: PHPMailer with Gmail SMTP
- **Features**: Password reset, notifications, system alerts
- **Configuration**: Centralized in `includes/mail_helper.php`

## Security Features

### Authentication & Authorization

- **Session Management**: Secure PHP sessions with timeout
- **Password Security**: Hashed passwords using PHP password_hash()
- **Role-Based Access**: Strict permission checking on all pages
- **SQL Injection Prevention**: Prepared statements throughout

### Exam Security

- **Attempt Tracking**: Prevent multiple exam attempts
- **Time Enforcement**: Server-side timer validation
- **Session Validation**: Ensure exam integrity
- **Auto-submission**: Prevent time manipulation
- **Anti-cheating**: Session monitoring and validation

### Data Protection

- **Input Validation**: Server-side validation for all inputs
- **XSS Prevention**: Output escaping and sanitization
- **CSRF Protection**: Token-based form validation
- **Error Handling**: Secure error messages without information disclosure

## Usage Guide

### Getting Started

1. **Initial Access**: Use invitation code to register
2. **Profile Setup**: Complete profile information
3. **Role-specific Navigation**: Access features based on assigned role

### For Supervisors

1. **System Setup**:

   - Generate invitation codes for trainers and students
   - Monitor system statistics on dashboard

2. **User Management**:

   - View and manage all users
   - Reset passwords when needed
   - Monitor user activities

3. **Reporting**:
   - Access comprehensive system reports
   - Monitor exam performance across all courses
   - Generate analytics for decision making

### For Trainers

1. **Course Creation**:

   - Create courses with detailed descriptions
   - Add course topics for better organization
   - Assign students to courses

2. **Exam Development**:

   - Create exams manually or use AI generation
   - Upload PDFs for automatic question creation
   - Set exam parameters (duration, marks, timing)

3. **Student Management**:
   - Monitor student progress
   - Assign exams to specific students
   - Review exam results and analytics

### For Students

1. **Course Enrollment**:

   - Browse available courses
   - View course details and topics

2. **Exam Taking**:

   - Access assigned exams during scheduled times
   - Navigate through questions with progress tracking
   - Submit exams before time expiration

3. **Results Review**:
   - View detailed exam results
   - Analyze performance with question-wise breakdown
   - Track progress over time

## Configuration

### Database Configuration (`config.php`)

```php
// Database settings
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'smart_exam_portal');

// Timezone
date_default_timezone_set('Africa/Cairo');
```

### Email Configuration (`includes/mail_helper.php`)

```php
// SMTP settings
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
$mail->Port = 587;
```

### AI API Configuration

```php
// Google Gemini API key
$gemini = new GeminiConnector("YOUR_API_KEY_HERE");
```

## Troubleshooting

### Common Issues

1. **Database Connection Errors**:

   - Verify database credentials in `config.php`
   - Ensure MySQL service is running
   - Check database permissions

2. **Email Not Sending**:

   - Verify SMTP credentials
   - Check Gmail app password setup
   - Review firewall settings

3. **AI Features Not Working**:

   - Validate Google Gemini API key
   - Check API quota limits
   - Verify internet connectivity

4. **Session Issues**:
   - Clear browser cookies
   - Check PHP session configuration
   - Verify file permissions

### Error Logging

- Application logs are stored in the `logs/` directory
- Email logs include detailed error information
- Database errors are logged with timestamps

## Contributing

### Development Guidelines

1. **Code Standards**: Follow PSR-12 coding standards
2. **Security**: Always use prepared statements for database queries
3. **Documentation**: Comment complex logic and functions
4. **Testing**: Test all features across different user roles

### File Naming Conventions

- Use lowercase with hyphens for file names
- Organize files by functionality and user role
- Include appropriate file extensions (.php, .css, .js)

### Database Guidelines

- Use meaningful table and column names
- Implement foreign key constraints
- Add appropriate indexes for performance
- Document schema changes

## Support

For technical support or questions:

1. Check the troubleshooting section
2. Review error logs in the `logs/` directory
3. Verify configuration settings
4. Test with different user roles to isolate issues

## License

This project is developed for educational purposes. Please ensure compliance with relevant data protection and privacy regulations when deploying in production environments.

---

**Version**: 1.0
**Last Updated**: May 2025
**PHP Version**: 8.x
**Database**: MySQL 8.x
