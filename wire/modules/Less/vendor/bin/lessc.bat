@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../wikimedia/less.php/bin/lessc
php "%BIN_TARGET%" %*
