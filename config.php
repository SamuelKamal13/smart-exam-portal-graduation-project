<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'smart_exam_portal');

// Add this line at the beginning of your config.php file
date_default_timezone_set('Africa/Cairo');

// Start session only if not already active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include error handler
require_once __DIR__ . "/includes/error_handler.php";

// First connect without selecting a database
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if ($conn === false) {
    die("ERROR: Could not connect to MySQL. " . mysqli_connect_error());
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (mysqli_query($conn, $sql)) {
    // Now select the database
    mysqli_select_db($conn, DB_NAME);

    // Array of schema files to check and update
    $schema_files = [
        __DIR__ . '/database/schema.sql',
        __DIR__ . '/database/studenttrainerschema.sql'
    ];

    // Process each schema file
    foreach ($schema_files as $schema_file) {
        if (file_exists($schema_file)) {
            // Get schema file modification time
            $schema_modified = filemtime($schema_file);
            $schema_key = 'schema_last_updated_' . basename($schema_file);

            // Check if we need to update the schema
            $update_schema = true;
            if (isset($_SESSION[$schema_key])) {
                if ($_SESSION[$schema_key] >= $schema_modified) {
                    $update_schema = false;
                }
            }

            if ($update_schema) {
                // Read the schema file
                $sql = file_get_contents($schema_file);

                // Remove comments and empty lines
                $lines = explode("\n", $sql);
                $sql = "";
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line && !preg_match('/^--/', $line)) { // Skip comments and empty lines
                        $sql .= $line . "\n";
                    }
                }

                // For stored procedures and events, we need to handle them differently
                if (basename($schema_file) == 'studenttrainerschema.sql') {
                    // First execute the ALTER TABLE statements with column existence check
                    if (preg_match('/ALTER TABLE.*?;/s', $sql, $matches)) {
                        $alter_statement = $matches[0];

                        // Check if columns already exist
                        $table_name = '';
                        if (preg_match('/ALTER TABLE\s+(\w+)/i', $alter_statement, $table_matches)) {
                            $table_name = $table_matches[1];

                            // Get existing columns
                            $columns_result = mysqli_query($conn, "SHOW COLUMNS FROM $table_name");
                            $existing_columns = [];
                            while ($column = mysqli_fetch_assoc($columns_result)) {
                                $existing_columns[] = $column['Field'];
                            }

                            // Extract columns to add
                            preg_match_all('/ADD COLUMN\s+(\w+)/i', $alter_statement, $column_matches);

                            // Create a new ALTER TABLE statement only for columns that don't exist
                            if (!empty($column_matches[1])) {
                                $new_alter = "ALTER TABLE $table_name ";
                                $columns_to_add = [];

                                foreach ($column_matches[1] as $index => $column_name) {
                                    if (!in_array($column_name, $existing_columns)) {
                                        // Extract the full column definition
                                        preg_match('/ADD COLUMN\s+' . $column_name . '\s+[^,]+/i', $alter_statement, $def_match);
                                        if (!empty($def_match[0])) {
                                            $columns_to_add[] = $def_match[0];
                                        }
                                    }
                                }

                                // Only execute if there are columns to add
                                if (!empty($columns_to_add)) {
                                    $new_alter .= implode(', ', $columns_to_add);
                                    mysqli_query($conn, $new_alter);
                                }
                            }
                        }
                    }

                    // Then handle stored procedures
                    if (preg_match('/CREATE PROCEDURE.*?END;/s', $sql, $matches)) {
                        $procedure_statement = $matches[0];
                        // Drop the procedure if it exists
                        mysqli_query($conn, "DROP PROCEDURE IF EXISTS auto_fail_absent_students");

                        // For stored procedures, we need to use multi_query
                        // because of the DECLARE statements
                        $delimiter = "$$";
                        $procedure_with_delimiter = "DELIMITER $delimiter\n" .
                            $procedure_statement .
                            "\n$delimiter\nDELIMITER ;";

                        // Use a direct approach instead
                        $create_proc_sql = "
                        CREATE PROCEDURE auto_fail_absent_students(IN p_exam_id INT)
                        BEGIN
                            -- Get the total marks for this exam
                            DECLARE total_marks_var INT;
                            SELECT total_marks INTO total_marks_var FROM exams WHERE id = p_exam_id;
                            
                            -- Insert failing results for students who didn't attempt
                            INSERT INTO results (exam_id, student_id, score, total_marks, percentage, submission_time)
                            SELECT 
                                es.exam_id, 
                                es.student_id, 
                                0, 
                                total_marks_var, 
                                0.00, 
                                NOW()
                            FROM exam_students es
                            LEFT JOIN results r ON r.exam_id = es.exam_id AND r.student_id = es.student_id
                            WHERE es.exam_id = p_exam_id 
                            AND r.id IS NULL
                            AND es.auto_graded = FALSE;
                            
                            -- Mark these students as auto-graded
                            UPDATE exam_students 
                            SET auto_graded = TRUE
                            WHERE exam_id = p_exam_id 
                            AND student_id IN (
                                SELECT es.student_id
                                FROM exam_students es
                                LEFT JOIN results r ON r.exam_id = es.exam_id AND r.student_id = es.student_id
                                WHERE es.exam_id = p_exam_id 
                                AND r.id IS NULL
                                AND es.auto_graded = FALSE
                            );
                        END";

                        mysqli_query($conn, $create_proc_sql);
                    }

                    // Finally handle events
                    if (preg_match('/CREATE EVENT.*?END;/s', $sql, $matches)) {
                        $event_statement = $matches[0];
                        // Drop the event if it exists
                        mysqli_query($conn, "DROP EVENT IF EXISTS auto_fail_event");

                        // Simplify the event creation as well
                        $create_event_sql = "
                        CREATE EVENT auto_fail_event
                        ON SCHEDULE EVERY 1 HOUR
                        DO
                        BEGIN
                            -- Find exams that have ended
                            DECLARE done INT DEFAULT FALSE;
                            DECLARE exam_id_var INT;
                            
                            -- Call the procedure for each exam that has ended
                            CALL auto_fail_absent_students(exam_id_var);
                        END";

                        mysqli_query($conn, $create_event_sql);
                    }
                } else {
                    // For regular schema files, split by semicolons
                    $statements = explode(';', $sql);

                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            // Execute each statement
                            if (!mysqli_query($conn, $statement . ';')) {
                                // Only show error if it's not a "table already exists" error
                                if (mysqli_errno($conn) != 1050) {
                                    error_log("Error executing SQL: " . mysqli_error($conn) . " in statement: " . $statement);
                                }
                            }
                        }
                    }
                }

                // Store the last update time in session
                $_SESSION[$schema_key] = time();
            }
        }
    }
} else {
    die("ERROR: Could not create database. " . mysqli_error($conn));
}
