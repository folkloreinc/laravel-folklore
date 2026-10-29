@servers(['web' => 'deploy@example.com'])

@task('deploy', ['on' => 'web'])
    cd /var/www/site
    git pull origin main
    composer install --no-dev
    # php artisan users:create, once, by hand
    php artisan migrate --force
    php artisan assets:view
@endtask
