@echo off
cd /d "d:\BISF 3.2\Advanced Network Forensics\4.2\SOTMS_Pro\SOTMS_Pro"
echo Running fix for missing columns...
C:\xampp\mysql\bin\mysql -u root tutoring_management_db < fix_missing_columns.sql > fix_output.txt 2>&1
echo Fix output saved to fix_output.txt
echo Opening fix_output.txt...
notepad fix_output.txt
pause
