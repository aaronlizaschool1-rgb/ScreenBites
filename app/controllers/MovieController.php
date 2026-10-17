<?php
/**
 * ScreenBites: Movie Controller
 * 
 * Handles movie catalog view rendering and movie creation.
 * Restricted to Administrators (FR13).
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use App\Models\Movie;

class MovieController extends Controller
{
    private Movie $movieModel;

    public function __construct()
    {
        // Enforce RBAC: Movie management requires Administrator permissions
        Auth::requireRole(ROLE_ADMIN);
        $this->movieModel = new Movie();
    }

    /**
     * Display list of all active movies and registration modal
     */
    public function index(): void
    {
        $movies = $this->movieModel->getActiveMovies();
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $this->view('movies/index', [
            'movies'       => $movies,
            'flashSuccess' => $flashSuccess,
            'flashError'   => $flashError,
            'csrfToken'    => Auth::csrfToken()
        ]);
    }

    /**
     * Process creation of a new movie record with optional poster upload
     */
    public function create(): void
    {
        if (!Auth::verifyCsrfToken((string)$this->post('csrf_token'))) {
            $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
            $this->redirect('movies');
            return;
        }

        $title           = trim((string)$this->post('title', ''));
        $durationMinutes = (int)$this->post('duration_minutes', 0);
        $rating          = (string)$this->post('rating', 'PG');

        if ($title === '' || $durationMinutes <= 0) {
            $_SESSION['flash_error'] = 'Movie title and valid duration in minutes are required.';
            $this->redirect('movies');
            return;
        }

        $allowedRatings = ['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18'];
        if (!in_array($rating, $allowedRatings, true)) {
            $rating = 'PG';
        }

        // Process poster file upload if provided
        $posterRelativePath = null;
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $posterRelativePath = $this->handlePosterUpload($_FILES['poster']);
            if ($posterRelativePath === false) {
                $this->redirect('movies');
                return;
            }
        }

        $this->movieModel->create($title, $durationMinutes, $rating, $posterRelativePath);

        $_SESSION['flash_success'] = "Movie '{$title}' successfully added to the catalog.";
        $this->redirect('movies');
    }

    /**
     * Validate and upload movie poster to uploads/posters/
     * 
     * @param array<string, mixed> $file
     * @return string|false Path string or false on upload error
     */
    private function handlePosterUpload(array $file): string|false
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            $_SESSION['flash_error'] = 'Invalid file type. Only JPG, PNG, and WebP poster images are supported.';
            return false;
        }

        // 5MB limit
        if ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Poster image exceeds the 5MB size limit.';
            return false;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename  = 'poster_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $destinationDir = ROOT_PATH . '/public/uploads/posters';

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $targetPath = $destinationDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return 'uploads/posters/' . $filename;
        }

        $_SESSION['flash_error'] = 'Failed to save poster image file.';
        return false;
    }
}