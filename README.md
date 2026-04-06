# Plataforma de Contratos (Laravel) – Revisión por link + OTP + carga de PDF firmado + evidencias

Este ZIP es un **starter kit** listo para ejecutar en un entorno Laravel (requiere `composer install`).
Incluye:
- Código Laravel (estructura mínima) para:
  - Carga de contrato (PDF)
  - Generación de **link de revisión** con token firmado (expira)
  - Flujo de **aceptación + OTP** (correo) y **subida del PDF firmado**
  - Registro de **evidencias** (IP, UA, timestamps, eventos)
  - Hash SHA-256 del PDF original y del firmado
  - Descarga del PDF firmado y export de evidencia (JSON)
- SQL con tablas equivalentes (además de migraciones)

> Nota legal/técnica: aquí implementamos **recolección de evidencia + OTP + hash + almacenamiento** y recepción del **PDF ya firmado**.
> La firma criptográfica PAdES (e.firma/certificado) se asume realizada por el firmante en su equipo/herramienta externa.
> Para producción, es altamente recomendable agregar validación de la firma del PDF (PAdES) con un validador especializado.

---

## Requisitos
- PHP 8.2+
- Composer
- MySQL 8+ (o MariaDB 10.5+)
- Extensiones PHP: openssl, mbstring, pdo_mysql, fileinfo

## Instalación rápida
1) Descomprime el proyecto
2) Entra a la carpeta y ejecuta:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
3) Configura DB en `.env`:
   ```
   DB_DATABASE=contratos
   DB_USERNAME=...
   DB_PASSWORD=...
   ```
4) Migra:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
5) Storage:
   ```bash
   php artisan storage:link
   ```
6) Mail (para OTP):
   Configura MAIL_* en `.env`. Para pruebas rápidas puedes usar `log`:
   ```
   MAIL_MAILER=log
   ```
7) Levanta el servidor:
   ```bash
   php artisan serve
   ```

## Credenciales demo (seeder)
- Email: admin@demo.local
- Password: Admin123!

---

## Flujo
- Admin:
  - Login
  - Subir contrato PDF y asignar firmante (email + nombre)
  - Generar link de revisión (se muestra en pantalla y se registra evento)
- Firmante:
  - Abre link (solo lectura) y descarga para revisar
  - Acepta términos y solicita OTP
  - Ingresa OTP
  - Sube el **PDF firmado** (ya firmado fuera de la plataforma)
- Admin:
  - Descarga PDF firmado
  - Descarga evidencia (JSON) y revisa bitácora

---

## Estructura de evidencia
Se guarda en `document_events` (append-only):
- event_type: created/sent/opened/downloaded/otp_requested/otp_verified/signed_uploaded/...
- ip, user_agent, occurred_at
- metadata JSON (por ejemplo: email, token_id, filename)

Se guarda hash SHA-256 en `document_hashes`:
- original_sha256
- signed_sha256

---

## SQL
Archivo: `database/sql/schema.sql`

> Para producción: agregar retención, cifrado en reposo, hardening de tokens, rate limiting, WORM/append-only real, y validación PAdES.
