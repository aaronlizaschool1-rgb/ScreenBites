<?php
/**
 * ScreenBites - Movie Management Catalog
 * Modeled after itim211-2b-shop/item/index.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_admin();

$pageTitle = 'Manage Movies';
require_once dirname(__DIR__) . '/includes/header.php';

$sql = "SELECT movie_id, title, duration_minutes, rating, poster_path, created_at 
        FROM movies 
        WHERE is_active = 1 
        ORDER BY created_at DESC";
$res = mysqli_query($conn, $sql);
?>

<div class="sb-page-header">
    <div>
        <h1>Movie Management</h1>
        <p>Add, update, or remove movies featured in theaters.</p>
    </div>
    <a href="<?= BASE_URL; ?>/movies/create.php" class="btn btn-primary">+ Add New Movie</a>
</div>

<div class="sb-card">
    <div class="sb-table-wrap">
        <table class="sb-table">
            <thead>
                <tr>
                    <th style="width: 70px;">Poster</th>
                    <th>Movie Title</th>
                    <th>Rating</th>
                    <th>Duration</th>
                    <th>Date Added</th>
                    <th style="text-align: right; width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res && mysqli_num_rows($res) > 0): ?>
                    <?php while ($m = mysqli_fetch_assoc($res)): 
                        $mId = (int)$m['movie_id'];
                        $poster = !empty($m['poster_path']) ? BASE_URL . '/' . $m['poster_path'] : BASE_URL . '/assets/img/default-poster.jpg';
                    ?>
                        <tr>
                            <td>
                                <img src="<?= $poster; ?>" alt="Poster" style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px;">
                            </td>
                            <td>
                                <strong style="font-size: 1.05rem;"><?= sanitize($m['title']); ?></strong>
                            </td>
                            <td>
                                <span style="border: 1px solid var(--card-border); padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 600; font-size: 0.8rem;">
                                    <?= sanitize($m['rating'] ?? 'PG'); ?>
                                </span>
                            </td>
                            <td><?= (int)$m['duration_minutes']; ?> mins</td>
                            <td><?= !empty($m['created_at']) ? date('M d, Y', strtotime($m['created_at'])) : '&mdash;'; ?></td>
                            <td style="text-align: right;">
                                <a href="<?= BASE_URL; ?>/movies/edit.php?id=<?= $mId; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <a href="<?= BASE_URL; ?>/movies/delete.php?id=<?= $mId; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this movie?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No movies found. Click "+ Add New Movie" to register one.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
