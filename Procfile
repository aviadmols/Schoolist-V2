web: mkdir -p storage/app/public storage/app/livewire-tmp storage/framework/cache storage/framework/cache/data storage/framework/sessions storage/framework/views && chmod -R 775 storage bootstrap/cache && php artisan migrate --force && php artisan filament:assets && php artisan storage:link && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work
