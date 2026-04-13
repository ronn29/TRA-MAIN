<?php
// Resolve project base path from the current script URL so favicon works in subfolders (admin/, student/, etc.)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$segments = explode('/', trim($scriptName, '/'));
$projectRoot = $segments[0] ?? '';
$basePath = $projectRoot ? ('/' . $projectRoot) : '';
?>
<link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($basePath . '/assets/img/icon.svg'); ?>">

