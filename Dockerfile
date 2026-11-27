FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html/
# O Render informa a porta pela variável PORT (padrão 10000)
CMD ["sh", "-c", "P=${PORT:-10000}; sed -i \"s/Listen 80/Listen $P/\" /etc/apache2/ports.conf; sed -i \"s/:80>/:$P>/\" /etc/apache2/sites-available/000-default.conf; apache2-foreground"]
