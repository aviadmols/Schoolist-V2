web: mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/app/public/classrooms storage/app/public/temp storage/app/livewire-tmp && chmod -R 775 storage && php artisan migrate --force && php artisan filament:assets && php artisan optimize && php artisan storage:link && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work
