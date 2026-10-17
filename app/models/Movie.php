<?php
/**
 * ScreenBites: Movie Model
 * 
 * Manages titles, durations, age ratings, and poster assets for the `movies` table.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace App\Models;

use Core\Database;

class Movie
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Retrieve all active movies ordered by creation
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getActiveMovies(): array
    {
        $sql = "SELECT movie_id, title, duration_minutes, rating, poster_path, created_at 
                FROM movies 
                WHERE is_active = 1 
                ORDER BY title ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Find a movie by its primary key
     * 
     * @param int $movieId
     * @return array<string, mixed>|null
     */
    public function findById(int $movieId): ?array
    {
        $sql = "SELECT movie_id, title, duration_minutes, rating, poster_path, is_active, created_at 
                FROM movies 
                WHERE movie_id = :movie_id 
                LIMIT 1";

        return $this->db->fetchOne($sql, [':movie_id' =>$movieId]);
    }

    /**
     * Insert a new movie record into the database
     * 
     * @param string $title
     * @param int $durationMinutes
     * @param string $rating ENUM('G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18')
     * @param string|null $posterPath
     * @return int Inserted movie_id
     */
    public function create(string $title, int$durationMinutes, string $rating, ?string $posterPath = null): int
    {
        $sql = "INSERT INTO movies (title, duration_minutes, rating, poster_path, is_active) 
                VALUES (:title, :duration_minutes, :rating, :poster_path, 1)";

        $this->db->query($sql, [
            ':title'            => trim($title),
            ':duration_minutes' => $durationMinutes,
            ':rating'           => $rating,
            ':poster_path'      => $posterPath
        ]);

        return (int)$this->db->lastInsertId();
    }
}