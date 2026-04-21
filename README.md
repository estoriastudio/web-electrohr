# ElectroHR ERP

Sistema ERP desarrollado para [ElectroHR](https://electrohr.com/), diseñado para centralizar y automatizar la gestión empresarial de operaciones internas.

## Descripción

ElectroHR ERP es una aplicación web construida con **Laravel** que integra los principales módulos de gestión empresarial en una sola plataforma, facilitando el control de procesos, personal y operaciones del negocio.

## Tecnologías

- **Backend:** PHP / Laravel
- **Frontend:** CSS
- **Base de datos:** MySQL

## Requisitos

- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL

## Instalación

```bash
git clone <repositorio>
cd web-electrohr
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

## Licencia

Uso interno — ElectroHR © 2026
