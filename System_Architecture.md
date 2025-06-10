# Smart Exam Portal - System Architecture

## 1. High-Level System Architecture

```mermaid
graph TB
    subgraph "Client Layer (Browser)"
        A[Web Browser]
        B[Student Interface]
        C[Trainer Interface]
        D[Supervisor Interface]
    end

    subgraph "Web Server Layer (Apache/XAMPP)"
        E[Apache HTTP Server]
        F[PHP Runtime]
    end

    subgraph "Application Layer"
        G[Authentication Module]
        H[Course Management]
        I[Exam Management]
        J[Question Management]
        K[Result Processing]
        L[AI Chat System]
    end

    subgraph "Database Layer"
        M[(MySQL Database)]
    end

    subgraph "External Services"
        N[PHPMailer Service]
        O[AI API Service]
    end

    A --> E
    B --> E
    C --> E
    D --> E
    E --> F
    F --> G
    F --> H
    F --> I
    F --> J
    F --> K
    F --> L
    G --> M
    H --> M
    I --> M
    J --> M
    K --> M
    L --> M
    L --> O
    G --> N
```

## 2. Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    USERS {
        int id PK
        varchar user_id UK
        varchar password
        varchar email UK
        enum role
        timestamp created_at
        varchar first_name
        varchar last_name
        varchar phone
    }

    INVITATION_CODES {
        int id PK
        varchar code UK
        enum role
        boolean used
        timestamp created_at
    }

    COURSES {
        int id PK
        varchar title
        text description
        int trainer_id FK
        timestamp created_at
        timestamp updated_at
    }

    COURSE_TOPICS {
        int id PK
        int course_id FK
        varchar title
        int sort_order
        timestamp created_at
    }

    COURSE_REGISTRATIONS {
        int id PK
        int course_id FK
        int student_id FK
        timestamp enrollment_date
    }

    EXAMS {
        int id PK
        varchar title
        text description
        int course_id FK
        int duration
        int total_marks
        datetime start_time
        int created_by FK
        timestamp created_at
        enum status
    }

    EXAM_STUDENTS {
        int id PK
        int exam_id FK
        int student_id FK
        timestamp assigned_at
    }

    QUESTIONS {
        int id PK
        int exam_id FK
        text question_text
        enum question_type
        int marks
        timestamp created_at
    }

    QUESTION_OPTIONS {
        int id PK
        int question_id FK
        text option_text
        tinyint is_correct
    }

    EXAM_ATTEMPTS {
        int id PK
        int exam_id FK
        int student_id FK
        timestamp start_time
        timestamp end_time
        int score
        enum status
    }

    STUDENT_ANSWERS {
        int id PK
        int attempt_id FK
        int question_id FK
        text answer_text
        int option_id FK
        boolean is_correct
        int points_earned
    }

    RESULTS {
        int id PK
        int exam_id FK
        int student_id FK
        int score
        int total_marks
        decimal percentage
        timestamp submission_time
    }

    AI_CHAT_HISTORY {
        int id PK
        int user_id FK
        text message
        text response
        timestamp created_at
    }

    PASSWORD_RESETS {
        int id PK
        int user_id FK
        varchar token
        datetime expires_at
        timestamp created_at
    }

    %% Relationships
    USERS ||--o{ COURSES : creates
    USERS ||--o{ COURSE_REGISTRATIONS : enrolls
    USERS ||--o{ EXAMS : creates
    USERS ||--o{ EXAM_STUDENTS : assigned_to
    USERS ||--o{ EXAM_ATTEMPTS : attempts
    USERS ||--o{ RESULTS : achieves
    USERS ||--o{ AI_CHAT_HISTORY : chats
    USERS ||--o{ PASSWORD_RESETS : requests

    COURSES ||--o{ COURSE_TOPICS : contains
    COURSES ||--o{ COURSE_REGISTRATIONS : has
    COURSES ||--o{ EXAMS : includes

    EXAMS ||--o{ EXAM_STUDENTS : assigns
    EXAMS ||--o{ QUESTIONS : contains
    EXAMS ||--o{ EXAM_ATTEMPTS : attempted
    EXAMS ||--o{ RESULTS : generates

    QUESTIONS ||--o{ QUESTION_OPTIONS : has
    QUESTIONS ||--o{ STUDENT_ANSWERS : answered

    EXAM_ATTEMPTS ||--o{ STUDENT_ANSWERS : contains

    QUESTION_OPTIONS ||--o{ STUDENT_ANSWERS : selected
```

## 3. System Flow Diagram

```mermaid
flowchart TD
    A[User Access] --> B{Authentication}
    B -->|Login Success| C{Role Check}
    B -->|Login Failed| D[Login Page]

    C -->|Student| E[Student Dashboard]
    C -->|Trainer| F[Trainer Dashboard]
    C -->|Supervisor| G[Supervisor Dashboard]

    subgraph "Student Workflow"
        E --> H[View Available Exams]
        H --> I[Start Exam]
        I --> J[Answer Questions]
        J --> K{Time Check}
        K -->|Time Left| J
        K -->|Time Up| L[Auto Submit]
        J --> M[Manual Submit]
        M --> N[View Results]
        L --> N
    end

    subgraph "Trainer Workflow"
        F --> O[Manage Courses]
        F --> P[Create Exams]
        F --> Q[Manage Questions]
        F --> R[View Results]
        F --> S[AI Question Generator]

        P --> T[Set Exam Parameters]
        T --> U[Add Questions]
        U --> V[Assign Students]
        V --> W[Publish Exam]
    end

    subgraph "Supervisor Workflow"
        G --> X[Manage Users]
        G --> Y[Generate Invitation Codes]
        G --> Z[Monitor System]
        G --> AA[Generate Reports]
    end

    N --> BB[Performance Analytics]
    R --> BB
    AA --> BB
```

## 4. Class Diagram

```mermaid
classDiagram
    class User {
        +int id
        +string user_id
        +string password
        +string email
        +enum role
        +string first_name
        +string last_name
        +string phone
        +timestamp created_at
        +login()
        +logout()
        +resetPassword()
        +changePassword()
        +updateProfile()
    }

    class Student {
        +viewCourses()
        +enrollInCourse()
        +takeExam()
        +viewResults()
        +viewExamHistory()
        +chatWithAI()
    }

    class Trainer {
        +createCourse()
        +editCourse()
        +deleteCourse()
        +manageCourseStudents()
        +createExam()
        +editExam()
        +deleteExam()
        +manageQuestions()
        +generateAIQuestions()
        +assignStudentsToExam()
        +gradeExams()
        +viewReports()
    }

    class Supervisor {
        +manageUsers()
        +generateInvitationCodes()
        +monitorSystem()
        +generateAnalytics()
        +manageTrainers()
        +viewAllCourses()
        +viewSystemReports()
    }

    class Course {
        +int id
        +string title
        +text description
        +int trainer_id
        +timestamp created_at
        +timestamp updated_at
        +addStudent()
        +removeStudent()
        +getEnrolledStudents()
        +getExams()
        +getTopics()
    }

    class CourseTopic {
        +int id
        +int course_id
        +string title
        +int sort_order
        +timestamp created_at
        +updateOrder()
    }

    class CourseRegistration {
        +int id
        +int course_id
        +int student_id
        +timestamp enrollment_date
        +enroll()
        +unenroll()
    }

    class Exam {
        +int id
        +string title
        +text description
        +int course_id
        +int duration
        +int total_marks
        +datetime start_time
        +int created_by
        +enum status
        +timestamp created_at
        +publish()
        +addQuestion()
        +assignStudent()
        +calculateResults()
        +getStatistics()
    }

    class Question {
        +int id
        +int exam_id
        +text question_text
        +enum question_type
        +int marks
        +timestamp created_at
        +addOption()
        +editQuestion()
        +deleteQuestion()
        +getOptions()
    }

    class QuestionOption {
        +int id
        +int question_id
        +text option_text
        +boolean is_correct
        +markAsCorrect()
        +updateText()
    }

    class ExamStudent {
        +int id
        +int exam_id
        +int student_id
        +timestamp assigned_at
        +assign()
        +unassign()
    }

    class ExamAttempt {
        +int id
        +int exam_id
        +int student_id
        +timestamp start_time
        +timestamp end_time
        +int score
        +enum status
        +startAttempt()
        +submitAttempt()
        +abandonAttempt()
        +calculateScore()
    }

    class StudentAnswer {
        +int id
        +int attempt_id
        +int question_id
        +text answer_text
        +int option_id
        +boolean is_correct
        +int points_earned
        +saveAnswer()
        +gradeAnswer()
    }

    class Result {
        +int id
        +int exam_id
        +int student_id
        +int score
        +int total_marks
        +decimal percentage
        +timestamp submission_time
        +calculatePercentage()
        +generateReport()
    }

    class InvitationCode {
        +int id
        +string code
        +enum role
        +boolean used
        +timestamp created_at
        +generate()
        +use()
        +validate()
    }

    class AIChatHistory {
        +int id
        +int user_id
        +text message
        +text response
        +timestamp created_at
        +saveChat()
        +getChatHistory()
    }

    class PasswordReset {
        +int id
        +int user_id
        +string token
        +datetime expires_at
        +timestamp created_at
        +generateToken()
        +validateToken()
        +resetPassword()
    }

    %% Inheritance Relationships
    User <|-- Student
    User <|-- Trainer
    User <|-- Supervisor

    %% Association Relationships
    Trainer ||--o{ Course : creates
    Course ||--o{ CourseTopic : contains
    Course ||--o{ CourseRegistration : has
    Student ||--o{ CourseRegistration : enrolls
    Course ||--o{ Exam : includes
    Trainer ||--o{ Exam : creates
    Exam ||--o{ Question : contains
    Question ||--o{ QuestionOption : has
    Exam ||--o{ ExamStudent : assigns
    Student ||--o{ ExamStudent : assigned_to
    Student ||--o{ ExamAttempt : attempts
    Exam ||--o{ ExamAttempt : attempted_by
    ExamAttempt ||--o{ StudentAnswer : contains
    Question ||--o{ StudentAnswer : answered
    QuestionOption ||--o{ StudentAnswer : selected
    Student ||--o{ Result : achieves
    Exam ||--o{ Result : generates
    User ||--o{ AIChatHistory : chats
    User ||--o{ PasswordReset : requests

    %% Composition Relationships
    Course *-- CourseTopic
    Exam *-- Question
    Question *-- QuestionOption
    ExamAttempt *-- StudentAnswer
```

## 5. Use Case Diagram

```mermaid
graph LR
    subgraph "Smart Exam Portal System"
        subgraph "Authentication"
            UC1[Login/Logout]
            UC2[Register with Code]
            UC3[Reset Password]
            UC4[Change Password]
        end

        subgraph "Student Functions"
            UC5[View Courses]
            UC6[Take Exam]
            UC7[View Results]
            UC8[View Exam History]
            UC9[Use AI Chat]
        end

        subgraph "Trainer Functions"
            UC10[Create Course]
            UC11[Manage Questions]
            UC12[Create Exam]
            UC13[Assign Students]
            UC14[Grade Exams]
            UC15[Generate AI Questions]
            UC16[View Reports]
        end

        subgraph "Supervisor Functions"
            UC17[Manage Users]
            UC18[Generate Invitation Codes]
            UC19[Monitor System]
            UC20[Generate Analytics]
            UC21[Manage Trainers]
        end
    end

    Student --> UC1
    Student --> UC5
    Student --> UC6
    Student --> UC7
    Student --> UC8
    Student --> UC9

    Trainer --> UC1
    Trainer --> UC10
    Trainer --> UC11
    Trainer --> UC12
    Trainer --> UC13
    Trainer --> UC14
    Trainer --> UC15
    Trainer --> UC16

    Supervisor --> UC1
    Supervisor --> UC17
    Supervisor --> UC18
    Supervisor --> UC19
    Supervisor --> UC20
    Supervisor --> UC21

    Student --> UC2
    Trainer --> UC2
    Supervisor --> UC2

    Student --> UC3
    Trainer --> UC3
    Supervisor --> UC3

    Student --> UC4
    Trainer --> UC4
    Supervisor --> UC4
```

## 6. Application Architecture (MVC Pattern)

```mermaid
graph TB
    subgraph "View Layer (Presentation)"
        A[Student UI]
        B[Trainer UI]
        C[Supervisor UI]
        D[Authentication UI]
        E[Error Pages]
    end

    subgraph "Controller Layer (Business Logic)"
        F[Authentication Controller]
        G[Course Controller]
        H[Exam Controller]
        I[Question Controller]
        J[Result Controller]
        K[User Management Controller]
        L[AI Chat Controller]
    end

    subgraph "Model Layer (Data Access)"
        M[User Model]
        N[Course Model]
        O[Exam Model]
        P[Question Model]
        Q[Result Model]
        R[Chat Model]
    end

    subgraph "Database"
        S[(MySQL Database)]
    end

    subgraph "External Services"
        T[Email Service - PHPMailer]
        U[AI API Service]
    end

    A --> F
    A --> G
    A --> H
    A --> J
    A --> L

    B --> F
    B --> G
    B --> H
    B --> I
    B --> J
    B --> L

    C --> F
    C --> K

    D --> F

    F --> M
    G --> N
    H --> O
    I --> P
    J --> Q
    K --> M
    L --> R

    M --> S
    N --> S
    O --> S
    P --> S
    Q --> S
    R --> S

    F --> T
    L --> U
```

## 7. Directory Structure Architecture

```
SmartExamPortal/
├── 📁 auth/                    # Authentication Module
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── reset-password.php
│   └── change-password.php
├── 📁 dashboard/               # Role-based Dashboards
│   ├── 📁 student/            # Student Interface
│   ├── 📁 trainer/            # Trainer Interface
│   └── 📁 supervisor/         # Supervisor Interface
├── 📁 assets/                  # Static Resources
│   ├── 📁 css/               # Stylesheets
│   └── 📁 js/                # JavaScript Files
├── 📁 includes/               # Shared Components
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   ├── sidebar.php
│   └── error_handler.php
├── 📁 database/               # Database Schema
├── 📁 vendor/                 # Third-party Libraries
├── 📁 ai-chat/               # AI Integration
├── 📁 errors/                # Error Pages
├── 📁 logs/                  # System Logs
├── config.php                # Configuration
└── index.php                 # Entry Point
```

## 8. Security Architecture

```mermaid
graph TB
    subgraph "Security Layers"
        A[HTTPS/SSL Encryption]
        B[Session Management]
        C[Authentication Layer]
        D[Authorization Layer]
        E[Input Validation]
        F[SQL Injection Prevention]
        G[XSS Protection]
    end

    subgraph "Security Features"
        H[Password Hashing]
        I[Role-based Access Control]
        J[Invitation Code System]
        K[Password Reset Tokens]
        L[Session Timeout]
        M[Error Handling]
    end

    A --> B
    B --> C
    C --> D
    D --> E
    E --> F
    F --> G

    C --> H
    C --> I
    C --> J
    C --> K
    B --> L
    G --> M
```

## 9. Technology Stack

```mermaid
graph TB
    subgraph "Frontend Technologies"
        A[HTML5]
        B[CSS3/Bootstrap]
        C[JavaScript]
        D[Responsive Design]
    end

    subgraph "Backend Technologies"
        E[PHP 8.x]
        F[MySQL 8.x]
        G[Apache Server]
        H[XAMPP Environment]
    end

    subgraph "External Libraries"
        I[PHPMailer]
        J[AI API Integration]
        K[Chart.js for Analytics]
    end

    subgraph "Development Tools"
        L[Git Version Control]
        M[VS Code/IDE]
        N[MySQL Workbench]
    end

    A --> E
    B --> E
    C --> E
    D --> E

    E --> F
    E --> G
    G --> H

    E --> I
    E --> J
    C --> K
```

## Key Architectural Features

### **1. Three-Tier Architecture**

- **Presentation Tier**: HTML/CSS/JavaScript frontend
- **Application Tier**: PHP business logic
- **Data Tier**: MySQL database

### **2. Role-Based Access Control**

- **Student**: Exam taking, result viewing
- **Trainer**: Course/exam management, question creation
- **Supervisor**: User management, system administration

### **3. Modular Design**

- Independent modules for easy maintenance
- Separation of concerns
- Reusable components

### **4. Security Features**

- Session-based authentication
- Password hashing
- SQL injection prevention
- Input validation and sanitization

### **5. Scalability Considerations**

- Database indexing for performance
- Modular code structure
- Efficient query optimization
- Session management

This architecture ensures a robust, secure, and maintainable system that supports the complex requirements of an online examination portal while providing excellent user experience across all stakeholder roles.
