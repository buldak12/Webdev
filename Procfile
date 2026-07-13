web: php bin/console cache:clear --env=prod --no-warmup && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && php -S 0.0.0.0:${PORT:-8000} -t public
