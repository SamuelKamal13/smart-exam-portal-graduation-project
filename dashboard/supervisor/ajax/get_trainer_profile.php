<?php
// Include config file
require_once "../../../config.php";

// Set header to return JSON
header('Content-Type: application/json');

// Initialize response array
$response = ['success' => false, 'message' => '', 'trainer' => null, 'stats' => null, 'courses' => null, 'students' => null];

// Check if user is logged in and is a supervisor
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "supervisor") {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

// Check if trainer_id is provided
if (!isset($_GET['trainer_id']) || !filter_var($_GET['trainer_id'], FILTER_VALIDATE_INT)) {
    $response['message'] = 'Invalid Trainer ID provided.';
    echo json_encode($response);
    exit;
}

$trainer_id = (int)$_GET["trainer_id"];

// Get trainer information
$sql = "SELECT id, user_id, first_name, last_name, email, phone, created_at 
        FROM users 
        WHERE id = ? AND role = 'trainer'";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 1) {
            $response['trainer'] = mysqli_fetch_assoc($result);
        } else {
            // Trainer not found
            $response['message'] = 'Trainer not found.';
            echo json_encode($response);
            exit;
        }
    } else {
        $response['message'] = 'Error executing query: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Get trainer statistics
$stats_sql = "SELECT 
                COUNT(DISTINCT c.id) as courses_count,
                COUNT(DISTINCT e.id) as exams_count,
                COUNT(DISTINCT r.student_id) as students_count,
                AVG(r.percentage) as avg_score,
                SUM(CASE WHEN r.percentage >= 60 THEN 1 ELSE 0 END) as passed_exams,
                COUNT(r.id) as total_exams
            FROM users u
            LEFT JOIN courses c ON u.id = c.trainer_id
            LEFT JOIN exams e ON c.id = e.course_id
            LEFT JOIN results r ON e.id = r.exam_id
            WHERE u.id = ?";

if ($stmt = mysqli_prepare($conn, $stats_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            // Convert numeric fields to proper types
            $row['courses_count'] = (int)$row['courses_count'];
            $row['exams_count'] = (int)$row['exams_count'];
            $row['students_count'] = (int)$row['students_count'];
            $row['passed_exams'] = (int)$row['passed_exams'];
            $row['total_exams'] = (int)$row['total_exams'];
            $row['avg_score'] = $row['avg_score'] ? round((float)$row['avg_score'], 2) : 0;

            // Calculate pass rate
            $row["pass_rate"] = ($row["total_exams"] > 0) ?
                ($row["passed_exams"] / $row["total_exams"]) * 100 : 0;

            $response["stats"] = $row;
        }
    } else {
        $response['message'] = 'Error executing query: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Get courses information
$courses_sql = "SELECT 
                c.id, c.title,
                COUNT(DISTINCT e.id) as exams_count,
                COUNT(DISTINCT cr.student_id) as students_count,
                AVG(r.percentage) as avg_score,
                SUM(CASE WHEN r.percentage >= 60 THEN 1 ELSE 0 END) as passed_exams,
                COUNT(r.id) as total_exams
              FROM courses c
              LEFT JOIN exams e ON c.id = e.course_id
              LEFT JOIN course_registrations cr ON c.id = cr.course_id
              LEFT JOIN results r ON e.id = r.exam_id
              WHERE c.trainer_id = ?
              GROUP BY c.id";

if ($stmt = mysqli_prepare($conn, $courses_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $response["courses"] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Convert numeric fields to proper types
            $row['exams_count'] = (int)$row['exams_count'];
            $row['students_count'] = (int)$row['students_count'];
            $row['passed_exams'] = (int)$row['passed_exams'];
            $row['total_exams'] = (int)$row['total_exams'];
            $row['avg_score'] = $row['avg_score'] ? round((float)$row['avg_score'], 2) : 0;

            // Calculate pass rate for each course
            $row["pass_rate"] = ($row["total_exams"] > 0) ?
                ($row["passed_exams"] / $row["total_exams"]) * 100 : 0;

            $response["courses"][] = $row;
        }
    } else {
        $response['message'] = 'Error executing query: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Get students information
$students_sql = "SELECT 
                  u.id, u.first_name, u.last_name,
                  COUNT(r.id) as exams_taken,
                  AVG(r.percentage) as avg_percentage,
                  SUM(CASE WHEN r.percentage >= 60 THEN 1 ELSE 0 END) as exams_passed
                FROM users u
                JOIN course_registrations cr ON u.id = cr.student_id
                JOIN courses c ON cr.course_id = c.id
                LEFT JOIN exams e ON c.id = e.course_id
                LEFT JOIN results r ON e.id = r.exam_id AND u.id = r.student_id
                WHERE c.trainer_id = ? AND u.role = 'student'
                GROUP BY u.id";

if ($stmt = mysqli_prepare($conn, $students_sql)) {
    mysqli_stmt_bind_param($stmt, "i", $trainer_id);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $response["students"] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Convert numeric fields to proper types
            $row['exams_taken'] = (int)$row['exams_taken'];
            $row['exams_passed'] = (int)$row['exams_passed'];
            $row['avg_percentage'] = $row['avg_percentage'] ? round((float)$row['avg_percentage'], 2) : 0;

            $response["students"][] = $row;
        }
    } else {
        $response['message'] = 'Error executing query: ' . mysqli_error($conn);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Set success flag
$response['success'] = true;

// Return the response as JSON
echo json_encode($response);
