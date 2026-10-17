<?php
/**
 * ScreenBites: Booking Controller
 * 
 * Handles interactive seat matrix display, real-time AJAX locks,
 * dynamic pricing computations, and seat release endpoints (FR2, FR3, FR4).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\SeatHold;

class BookingController extends Controller
{
    private Screening $screeningModel;
    private Seat $seatModel;
    private SeatHold $seatHoldModel;

    public function __construct()
    {
        // Enforce RBAC: Booking interface is available to Admin and Ticketing Staff
        Auth::requireRole([ROLE_ADMIN, ROLE_TICKETING]);

        $this->screeningModel = new Screening();
        $this->seatModel      = new Seat();
        $this->seatHoldModel  = new SeatHold();
    }

    /**
     * Render the interactive cinema seat matrix view (FR2, FR3)
     * Handles both HTML view rendering and JSON data requests for AJAX polling
     * 
     * @param string $id Screening ID
     */
    public function seatMatrix(string $id): void
    {
        $screeningId = (int)$id;
        $screening = $this->screeningModel->findDetailsById($screeningId);

        if (!$screening) {
            $_SESSION['flash_error'] = 'Requested screening was not found.';
            $this->redirect('schedules');
            return;
        }

        $sessionToken = session_id();
        $seats = $this->seatModel->getScreeningSeatMatrix(
            $screeningId,
            (int)$screening['hall_id'],
            $sessionToken,
            (float)$screening['base_price']
        );

        // If AJAX request or JSON header is provided, return structured data for real-time grid polling
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            $this->json([
                'success'      => true,
                'screening'    => $screening,
                'seats'        => $seats,
                'hold_timeout' => SEAT_HOLD_DURATION_SECONDS
            ]);
            return;
        }

        // Standard page view render: maps to app/views/ticketing/seat_matrix.php
        $this->view('ticketing/seat_matrix', [
            'screening'    => $screening,
            'seats'        => $seats,
            'holdTimeout'  => SEAT_HOLD_DURATION_SECONDS,
            'sessionToken' => $sessionToken,
            'csrfToken'    => Auth::csrfToken()
        ]);
    }

    /**
     * AJAX Endpoint: Temporarily hold/lock a seat (FR4)
     */
    public function holdSeat(): void
    {
        $body = $this->getJsonBody();
        $screeningId = (int)($body['screening_id'] ?? $this->post('screening_id', 0));
        $seatId      = (int)($body['seat_id'] ?? $this->post('seat_id', 0));

        if ($screeningId <= 0 || $seatId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Invalid screening or seat selection.'
            ], 400);
            return;
        }

        $screening = $this->screeningModel->findDetailsById($screeningId);
        if (!$screening) {
            $this->json([
                'success' => false,
                'message' => 'Screening not found.'
            ], 404);
            return;
        }

        $sessionToken = session_id();
        $result = $this->seatHoldModel->acquireHold(
            $screeningId,
            $seatId,
            (int)$screening['hall_id'],
            $sessionToken,
            SEAT_HOLD_DURATION_SECONDS
        );

        $status = $result['success'] ? 200 : 409; // 409 Conflict if already locked
        $this->json($result, $status);
    }

    /**
     * AJAX Endpoint: Release a held seat (FR4)
     */
    public function releaseSeat(): void
    {
        $body = $this->getJsonBody();
        $screeningId = (int)($body['screening_id'] ?? $this->post('screening_id', 0));
        $seatId      = (int)($body['seat_id'] ?? $this->post('seat_id', 0));

        if ($screeningId <= 0 || $seatId <= 0) {
            $this->json([
                'success' => false,
                'message' => 'Invalid screening or seat identifier.'
            ], 400);
            return;
        }

        $sessionToken = session_id();
        $released = $this->seatHoldModel->releaseHold($screeningId, $seatId, $sessionToken);

        $this->json([
            'success' => $released,
            'message' => $released ? 'Seat released successfully.' : 'Seat was not held by this session.'
        ]);
    }
}