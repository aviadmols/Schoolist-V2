web: php artisan migrate --force && php artisan filament:assets && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work
