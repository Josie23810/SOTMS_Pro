@echo off
cd /d "d:\BISF 3.2\Advanced Network Forensics\4.2\SOTMS_Pro/SOTMS_Pro"
C:\xampp\php\php simple_debug.php > debug_output.txt 2>&1
echo Debug output saved to debug_output.txt
echo Opening debug_output.txt...
notepad debug_output.txt
pause
