<?php
/**
 * ScreenBites: Schedule Controller
 * 
 * Handles hall showtime scheduling, timeframe overlap prevention,
 * and schedule grid delivery (FR1).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\Screening;
use App\Models\Movie;
use App\Models\Hall;
use DateTime;

class ScheduleController extends Controller
{
    private Screening $screeningModel;
    private Movie $movieModel;
    private Hall $hallModel;

    public function __construct()
    {
        // Enforce RBAC: Scheduling access is open to Admin & Ticketing Staff
        Auth::requireRole([ROLE_ADMIN, ROLE_TICKETING]);

        $this->screeningModel = new Screening();
        $this->movieModel     = new Movie();
        $this->hallModel      = new Hall();
    }

    /**
     * Render the showtime calendar and scheduling interface
     */
    public function index(): void
    {
        $selectedDate = (string)$this->get('date', date('Y-m-d'));
        
        $screenings = $this->screeningModel->getScheduleByDate($selectedDate);
        $movies     = $this->movieModel->getActiveMovies();
        $halls      = $this->hallModel->getActiveHalls();

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        // Renders app/views/ticketing/schedule.php
        $this->view('ticketing/schedule', [
            'screenings'   => $screenings,
            'movies'       => $movies,
            'halls'        => $halls,
            'selectedDate' => $selectedDate,
            'flashSuccess' => $flashSuccess,
            'flashError'   => $flashError,
            'csrfToken'    => Auth::csrfToken(),
            'userRole'     => Auth::role()
        ]);
    }

    /**
     * Process creation of a new screening slot with collision detection
     */
    public function create(): void
    {
        // Only Administrators can assign or create screening slots
        Auth::requireRole(ROLE_ADMIN);

        if (!Auth::verifyCsrfToken((string)$this->post('csrf_token'))) {
            $_SESSION['flash_error'] = 'Security validation failed. Please try again.';
            $this->redirect('schedules');
            return;
        }

        $movieId   = (int)$this->post('movie_id', 0);
        $hallId    = (int)$this->post('hall_id', 0);
        $date      = trim((string)$this->post('screening_date', ''));
        $startTime = trim((string)$this->post('start_time', ''));
        $basePrice = (float)$this->post('base_price', 0.00);

        if ($movieId <= 0 || $hallId <= 0 || empty($date) || empty($startTime) || $basePrice <= 0) {
            $_SESSION['flash_error'] = 'All schedule parameters and a valid base ticket price are required.';
            $this->redirect('schedules?date=' . urlencode($date));
            return;
        }

        $movie = $this->movieModel->findById($movieId);
        $hall  = $this->hallModel->findById($hallId);

        if (!$movie || !$hall) {
            $_SESSION['flash_error'] = 'Selected movie or cinema hall does not exist.';
            $this->redirect('schedules');
            return;
        }

        // Calculate end_time based on movie duration + 20-minute turnaround/cleaning buffer
        $startDateTime = DateTime::createFromFormat('H:i', $startTime);
        if (!$startDateTime) {
            $startDateTime = DateTime::createFromFormat('H:i:s', $startTime);
        }

        if (!$startDateTime) {
            $_SESSION['flash_error'] = 'Invalid start time format supplied.';
            $this->redirect('schedules?date=' . urlencode($date));
            return;
        }

        $totalRuntime = (int)$movie['duration_minutes'] + 20; // 20 min hall turnaround buffer
        $endDateTime = (clone $startDateTime)->modify("+{$totalRuntime} minutes");

        $formattedStartTime = $startDateTime->format('H:i:00');
        $formattedEndTime   = $endDateTime->format('H:i:00');

        // Check for room conflicts: FR1 collision check
        $hasCollision = $this->screeningModel->hasScheduleCollision(
            $hallId,
            $date,
            $formattedStartTime,
            $formattedEndTime
        );

        if ($hasCollision) {
            $_SESSION['flash_error'] = "Schedule Conflict: {$hall['hall_name']} is already occupied between {$formattedStartTime} and {$formattedEndTime} on {$date}.";
            $this->redirect('schedules?date=' . urlencode($date));
            return;
        }

        $this->screeningModel->create(
            $movieId,
            $hallId,
            $date,
            $formattedStartTime,
            $formattedEndTime,
            $basePrice
        );

        $_SESSION['flash_success'] = "Screening for '{$movie['title']}' in {$hall['hall_name']} scheduled successfully ({$formattedStartTime} - {$formattedEndTime}).";
        $this->redirect('schedules?date=' . urlencode($date));
    }
}