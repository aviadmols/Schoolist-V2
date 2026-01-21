web: mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public storage/app/livewire-tmp && php artisan migrate --force && php artisan filament:assets && php artisan optimize:clear && php artisan storage:link && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work
