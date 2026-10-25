<?php
/**
 * ScreenBites: Root Entry Point
 * 
 * Automatically redirects visitors accessing the project root
 * to the public/ front controller directory.
 */
header('Location: public/', true, 302);
exit;
