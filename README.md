# ElectroHR ERP

Sistema ERP desarrollado para [ElectroHR](https://electrohr.com/), diseñado para centralizar y automatizar la gestión empresarial de operaciones internas.

## Descripción

ElectroHR ERP es una aplicación web construida con **Laravel** que integra los principales módulos de gestión empresarial en una sola plataforma, facilitando el control de procesos, personal y operaciones del negocio.

## Tecnologías

- **Backend:** PHP / Laravel
- **Frontend:** CSS
- **Base de datos:** MySQL


### Requisitos de Servidor

La configuración recomendada es LAMP Stack.

* Apache2 
* MySQL 
* PHP - 8.4


### Instalar Git, Unzip.

```
sudo apt-get install git
sudo apt-get install unzip

```

### Instalar CURL + Composer

```
sudo apt install php8.4-common php8.4-mysql php8.4-xml php8.4-xmlrpc php8.4-curl php8.4-gd php8.4-imagick php8.4-cli php8.4-dev php8.4-imap php8.4-mbstring php8.4-opcache php8.4-soap php8.4-zip php8.4-intl -y

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Habilitar Mods

```
sudo phpenmod mbstring
sudo a2enmod rewrite
sudo systemctl restart apache2

```

### Git CLONE del Proyecto en carpeta HTML

```
cd /var/www/html
git clone https://github.com/estoriastudio/web-electrohr.git
```

### Habilitar Rewrite para la carpeta

```
sudo chmod -R 777 [NOMBRE_DE_LA_CARPETA]

```

### Entrar en carpeta de proyecto

```
cd /[NOMBRE_DE_LA_CARPETA]
```

### Actualizar carpeta con COMPOSER 

```
composer update
```

### Crear una Llave de Encriptación

```
cp .env.example .env
php artisan key:generate
```
Es importante abrir el archivo .env para configurar la conexión a la base de datos si es que se requiere.

### Configurar Directorio de Proyecto

/etc/apache2/sites-available/default.com.conf 

```
<VirtualHost *:80>
	ServerName [RUTA].com
	DocumentRoot /var/www/html/[[ NOMBRE_DE_LA_CARPETA ]]/public

	<Directory /var/www/html/[[ NOMBRE_DE_LA_CARPETA ]]/public>
		AllowOverride All
		Require all granted
	</Directory>
</VirtualHost>
```
Si es necesario utilizar un certificado de seguridad utilizar el puerto 443 y activar las capacidades SSL del servidor por medio de la linea de comandos. Es importante que el certificado se encuentre en la ruta correcta que se determina en ese documento.

### Reiniciar Servidor

```
service apache2 reload

```

## Licencia

Uso interno — ElectroHR © 2026
