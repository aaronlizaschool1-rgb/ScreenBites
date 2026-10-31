<?php
/**
 * ScreenBites - Flash Alert Notification Component
 * Modeled after PedalWorks-Dynamics/includes/alert.php
 */

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError   = $_SESSION['flash_error']   ?? null;
$flashWarning = $_SESSION['flash_warning'] ?? null;

unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_warning']);
?>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success" style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; background: rgba(34, 197, 94, 0.12); border: 1px solid rgba(34, 197, 94, 0.35); color: #4ade80; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.92rem; font-weight: 500;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="font-size: 1.1rem;">✓</span>
            <span><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; color: currentColor; opacity: 0.7; cursor: pointer; font-size: 1.2rem; line-height: 1;" aria-label="Dismiss">&times;</button>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="alert alert-error" style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #f87171; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.92rem; font-weight: 500;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="font-size: 1.1rem;">⚠️</span>
            <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; color: currentColor; opacity: 0.7; cursor: pointer; font-size: 1.2rem; line-height: 1;" aria-label="Dismiss">&times;</button>
    </div>
<?php endif; ?>

<?php if ($flashWarning): ?>
    <div class="alert alert-warning" style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); color: #fbbf24; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.92rem; font-weight: 500;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="font-size: 1.1rem;">ℹ️</span>
            <span><?= htmlspecialchars($flashWarning, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove();" style="background: none; border: none; color: currentColor; opacity: 0.7; cursor: pointer; font-size: 1.2rem; line-height: 1;" aria-label="Dismiss">&times;</button>
    </div>
<?php endif; ?>
