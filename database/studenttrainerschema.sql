-- Add a column to track if a student has viewed the exam
ALTER TABLE exam_students 
ADD COLUMN has_viewed BOOLEAN DEFAULT FALSE,
ADD COLUMN has_attempted BOOLEAN DEFAULT FALSE,
ADD COLUMN auto_graded BOOLEAN DEFAULT FALSE;

-- Create a procedure to automatically fail students who didn't attempt their exams
CREATE PROCEDURE auto_fail_absent_students(IN p_exam_id INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE student_id_var INT;
    DECLARE total_marks_var INT;
    
    -- Get the total marks for this exam
    SELECT total_marks INTO total_marks_var FROM exams WHERE id = p_exam_id;
    
    -- Cursor for students who were assigned but didn't attempt the exam
    DECLARE student_cursor CURSOR FOR 
        SELECT es.student_id 
        FROM exam_students es
        LEFT JOIN results r ON r.exam_id = es.exam_id AND r.student_id = es.student_id
        WHERE es.exam_id = p_exam_id 
        AND r.id IS NULL
        AND es.auto_graded = FALSE;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN student_cursor;
    
    read_loop: LOOP
        FETCH student_cursor INTO student_id_var;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Insert a failing result for this student
        INSERT INTO results (exam_id, student_id, score, total_marks, percentage, submission_time)
        VALUES (p_exam_id, student_id_var, 0, total_marks_var, 0.00, NOW());
        
        -- Mark this student as auto-graded
        UPDATE exam_students 
        SET auto_graded = TRUE
        WHERE exam_id = p_exam_id AND student_id = student_id_var;
        
    END LOOP;
    
    CLOSE student_cursor;
END;

-- Create an event to run the auto-fail procedure for exams that have ended
CREATE EVENT auto_fail_event
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE exam_id_var INT;
    
    -- Cursor for exams that have ended but haven't been auto-graded for all students
    DECLARE exam_cursor CURSOR FOR 
        SELECT e.id
        FROM exams e
        WHERE DATE_ADD(e.start_time, INTERVAL e.duration MINUTE) < NOW()
        AND EXISTS (
            SELECT 1 FROM exam_students es 
            WHERE es.exam_id = e.id 
            AND es.auto_graded = FALSE
        );
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN exam_cursor;
    
    read_loop: LOOP
        FETCH exam_cursor INTO exam_id_var;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Call the procedure to auto-fail absent students
        CALL auto_fail_absent_students(exam_id_var);
        
    END LOOP;
    
    CLOSE exam_cursor;
END;