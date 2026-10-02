# syntax=docker/dockerfile:1

# nginx serves the static files under public/ and fastcgi-proxies PHP requests to
# the php-fpm container. Both share the same /var/www/html/public path so
# SCRIPT_FILENAME resolves in php-fpm's own filesystem.
FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public

EXPOSE 80
