<?php
// Set page title and base URL for includes
$page_title = "Upcoming Exams";
$base_url = "../..";

// Include config file
require_once "../../config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../../auth/login.php");
    exit;
}

// Check if user is a student
if ($_SESSION["role"] !== "student") {
    // Redirect to appropriate dashboard based on role
    if ($_SESSION["role"] == "trainer") {
        header("location: ../trainer/index.php");
    } elseif ($_SESSION["role"] == "supervisor") {
        header("location: ../supervisor/index.php");
    }
    exit;
}

// Get student ID
$student_id = $_SESSION["id"];

// Get upcoming exams for the student
$upcoming_exams = [];
$sql = "SELECT e.id, e.title, e.description, e.start_time, 
               DATE_ADD(e.start_time, INTERVAL e.duration MINUTE) AS end_time, 
               e.duration, c.title AS course_title, c.id AS course_code
        FROM exams e 
        JOIN exam_students es ON e.id = es.exam_id 
        LEFT JOIN courses c ON e.course_id = c.id
        WHERE es.student_id = ? AND e.status = 'published' AND (
            e.start_time >= NOW() OR 
            (e.start_time <= NOW() AND DATE_ADD(e.start_time, INTERVAL e.duration MINUTE) > NOW())
        )
        ORDER BY e.start_time ASC";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $student_id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            // Add a status field to indicate if the exam is running
            $start_time = new DateTime($row['start_time']);
            $end_time = new DateTime($row['end_time']);
            $now = new DateTime();

            if ($now >= $start_time && $now <= $end_time) {
                $row['status'] = 'running';
            } else {
                $row['status'] = 'upcoming';
            }

            $upcoming_exams[] = $row;
        }
    }

    mysqli_stmt_close($stmt);
}

// Format exams for calendar
$calendar_events = [];
foreach ($upcoming_exams as $exam) {
    $calendar_events[] = [
        'id' => $exam['id'],
        'title' => $exam['title'],
        'start' => $exam['start_time'],
        'end' => $exam['end_time'],
        'description' => $exam['description'],
        'course' => $exam['course_title'] ?? 'N/A',
        'course_code' => $exam['course_code'] ?? '',
        'duration' => $exam['duration']
    ];
}

// Include header
include_once "../../includes/header.php";
?>

<div class="container-fluid">
    <h1 class="mt-4 mb-4">Upcoming Exams</h1>

    <div class="row">
        <!-- Calendar View -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Calendar</h6>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>

        <!-- Exam Details -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Exam Details</h6>
                </div>
                <div class="card-body" id="examDetails">
                    <div class="text-center">
                        <p>Select an exam from the calendar to view details</p>
                        <i class="fas fa-calendar-alt fa-4x text-gray-300 mb-4"></i>
                    </div>
                </div>
            </div>

            <!-- Next Exam Card -->
            <?php if (count($upcoming_exams) > 0): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Next Exam</h6>
                    </div>
                    <div class="card-body">
                        <h5><?php echo htmlspecialchars($upcoming_exams[0]['title']); ?></h5>
                        <p class="mb-1">
                            <i class="fas fa-book me-2"></i>
                            <?php echo htmlspecialchars($upcoming_exams[0]['course_title'] ?? 'N/A'); ?>
                            <?php if (!empty($upcoming_exams[0]['course_code'])): ?>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($upcoming_exams[0]['course_code']); ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-calendar-day me-2"></i>
                            <?php echo date('l, F j, Y', strtotime($upcoming_exams[0]['start_time'])); ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-clock me-2"></i>
                            <?php echo date('h:i A', strtotime($upcoming_exams[0]['start_time'])); ?> -
                            <?php echo date('h:i A', strtotime($upcoming_exams[0]['end_time'])); ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-hourglass-half me-2"></i>
                            <?php echo $upcoming_exams[0]['duration']; ?> minutes
                        </p>

                        <hr>

                        <?php
                        $now = new DateTime();
                        $exam_time = new DateTime($upcoming_exams[0]['start_time']);
                        $interval = $now->diff($exam_time);
                        ?>

                        <div class="text-center">
                            <p class="mb-1">Time until exam:</p>
                            <div class="countdown-timer">
                                <?php if ($interval->days > 0): ?>
                                    <span class="countdown-item">
                                        <span class="countdown-value"><?php echo $interval->days; ?></span>
                                        <span class="countdown-label">Days</span>
                                    </span>
                                <?php endif; ?>
                                <span class="countdown-item">
                                    <span class="countdown-value"><?php echo $interval->h; ?></span>
                                    <span class="countdown-label">Hours</span>
                                </span>
                                <span class="countdown-item">
                                    <span class="countdown-value"><?php echo $interval->i; ?></span>
                                    <span class="countdown-label">Minutes</span>
                                </span>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <?php if ($upcoming_exams[0]['status'] == 'running'): ?>
                                <a href="take-exam.php?id=<?php echo $upcoming_exams[0]['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-play-circle me-1"></i> Take Exam Now
                                </a>
                            <?php else: ?>
                                <a href="view-exam.php?id=<?php echo $upcoming_exams[0]['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-info-circle me-1"></i> View Exam Details
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- List View -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Upcoming Exams List</h6>
        </div>
        <div class="card-body">
            <?php if (count($upcoming_exams) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="examsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Exam Title</th>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_exams as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($exam['course_title'] ?? 'N/A'); ?>
                                        <?php if (!empty($exam['course_code'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($exam['course_code']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($exam['start_time'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($exam['start_time'])); ?></td>
                                    <td><?php echo $exam['duration']; ?> min</td>
                                    <td>
                                        <?php if ($exam['status'] == 'running'): ?>
                                            <span class="badge bg-success">Running</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Upcoming</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($exam['status'] == 'running'): ?>
                                            <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-play-circle"></i> Take Exam
                                            </a>
                                        <?php else: ?>
                                            <a href="view-exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> You don't have any upcoming exams.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include FullCalendar -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>

<!-- Custom styles for countdown timer -->
<style>
    .countdown-timer {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .countdown-item {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .countdown-value {
        font-size: 24px;
        font-weight: bold;
        color: #4e73df;
    }

    .countdown-label {
        font-size: 12px;
        color: #858796;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize FullCalendar
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },
            events: <?php echo json_encode($calendar_events); ?>,
            eventClick: function(info) {
                showExamDetails(info.event);
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: 'short'
            },
            height: 'auto'
        });
        calendar.render();

        // Function to show exam details
        function showExamDetails(event) {
            var examData = event.extendedProps;
            var startTime = new Date(event.start);
            var endTime = new Date(event.end);
            var now = new Date();
            var isRunning = (now >= startTime && now <= endTime);

            var detailsHtml = `
            <h5>${event.title}</h5>
            <p class="mb-1"><i class="fas fa-book me-2"></i> ${examData.course}`;

            if (examData.course_code) {
                detailsHtml += ` <span class="badge bg-secondary">${examData.course_code}</span>`;
            }

            detailsHtml += `</p>
            <p class="mb-1"><i class="fas fa-calendar-day me-2"></i> ${startTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</p>
            <p class="mb-1"><i class="fas fa-clock me-2"></i> ${startTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })} - ${endTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</p>
            <p class="mb-1"><i class="fas fa-hourglass-half me-2"></i> ${examData.duration} minutes</p>
            <p class="mb-1">
                <i class="fas fa-info-circle me-2"></i> Status: 
                ${isRunning ? 
                    '<span class="badge bg-success">Running</span>' : 
                    '<span class="badge bg-primary">Upcoming</span>'}
            </p>
            
            <hr>
            
            <h6>Description:</h6>
            <p>${examData.description || 'No description available.'}</p>
            
            <div class="d-grid gap-2 mt-3">
                ${isRunning ? 
                    `<a href="take-exam.php?id=${event.id}" class="btn btn-success">
                        <i class="fas fa-play-circle me-1"></i> Take Exam Now
                    </a>` : 
                    `<a href="view-exam.php?id=${event.id}" class="btn btn-primary">
                        <i class="fas fa-info-circle me-1"></i> View Exam Details
                    </a>`}
            </div>
        `;

            document.getElementById('examDetails').innerHTML = detailsHtml;
        }

        // Initialize DataTable
        $('#examsTable').DataTable({
            order: [
                [2, 'asc'],
                [3, 'asc']
            ], // Sort by date and time
            responsive: true
        });
    });
</script>

<?php
// Include footer
include_once "../../includes/footer.php";
?>