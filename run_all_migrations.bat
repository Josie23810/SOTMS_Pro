@echo off
cd /d "d:\BISF 3.2\Advanced Network Forensics\4.2\SOTMS_Pro\SOTMS_Pro"
echo Running Complete Migration Setup...
C:\xampp\php\php run_complete_migration.php > migration_output.txt 2>&1
echo Migration output saved to migration_output.txt
echo Opening migration_output.txt...
notepad migration_output.txt
pause
