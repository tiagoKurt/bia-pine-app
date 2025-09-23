@echo off
cd /d "C:\Users\tiago.moliveira\Documents\GitHub\bia-pine-app\bia-pine-app-to-php"
php "C:\Users\tiago.moliveira\Documents\GitHub\bia-pine-app\bia-pine-app-to-php/worker.php" >> "C:\Users\tiago.moliveira\Documents\GitHub\bia-pine-app\bia-pine-app-to-php/logs/cron.log" 2>&1
