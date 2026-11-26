# SENIAT Control - Plaza Venezuela

Sistema web interno para la **Gerencia Regional de Tributos Internos (Región Libertador)** / **Centro Auxiliar de Atención al Contribuyente Plaza Venezuela**, construido con **HTML, CSS, JavaScript, PHP y PostgreSQL**.

## Alcance funcional

- Autenticación: inicio de sesión, registro, recuperación de usuario y contraseña por correo, cierre de sesión.
- Dashboard interactivo: tarjetas con incrementos/disminuciones, gráficos, porcentajes, balance de 3 estadísticas y estado operativo.
- Módulos: expedientes, productividad de expedientes, solvencia, multas, requerimientos.
- Control diario con exportación a Excel.

## Estructura (MVC)

```text
seniat-control/
├── config/                  # Configuración app y BD
├── database/                # schema.sql
├── public/                  # Document root (punto de entrada web)
│   ├── assets/css/
│   ├── assets/js/
│   ├── preview-*.html       # Vistas previas estáticas para pruebas UI
│   └── *.php                # Entradas ligeras que llaman controladores
├── src/
│   ├── bootstrap.php        # Arranque, autoload App\, seguridad, sesión
│   ├── Controllers/         # Controladores (lógica de petición/flujo)
│   ├── Models/              # Modelos (acceso a datos PostgreSQL)
│   ├── helpers/             # Helpers: env, db, auth, security, mail, view
│   └── middleware/          # Reservado para middleware adicional
├── storage/
│   ├── logs/
│   ├── uploads/
│   └── cache/
└── templates/               # Vistas
    ├── auth/
    ├── dashboard/
    ├── partials/
    ├── layout_start.php
    └── layout_end.php
```

## Requisitos

- PHP 8.1+ con extensiones: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `session`, `fileinfo`.
- PostgreSQL 13+.
- Apache con `mod_rewrite` y `mod_headers`, o Nginx equivalente.
- Servidor SMTP para recuperación por correo.

## Instalación

1. **Copiar entorno**
   ```bash
   copy .env.example .env
   ```
   En Linux/macOS: `cp .env.example .env`.

2. **Configurar `.env`**
   - Ajusta `APP_URL`, `APP_KEY`, credenciales de BD y correo.
   - Genera una `APP_KEY` aleatoria larga.
   - En producción: `APP_ENV=production`, `APP_DEBUG=false`, `FORCE_HTTPS=true`.

3. **Crear base de datos**
   ```sql
   CREATE DATABASE seniat_control;
   ```

4. **Ejecutar esquema**
   ```bash
   psql -U postgres -d seniat_control -f database/schema.sql
   ```

5. **Configurar servidor web**
   - Apunta el *DocumentRoot* a `seniat-control/public`.
   - Asegura HTTPS (certificado local o válido).
   - Verifica que `.env`, `config`, `database`, `src`, `storage` no sean accesibles por web.

6. **Permisos de escritura**
   - Dar permiso de escritura a `storage/logs` y `storage/uploads`.

## Credencial inicial

El esquema crea un usuario administrador inicial:

- **Usuario**: `admin`
- **Correo**: `admin@seniat-control.local`
- **Contraseña**: `Admin123*`

> **Importante**: cambia esta contraseña inmediatamente. El sistema marcará el cambio obligatorio en los siguientes pasos de implementación.

## Seguridad implementada en Paso 1 (base)

- `.env` para secretos/configuración; `.env.example` sin secretos reales.
- `.gitignore` para evitar subir secretos, logs, uploads y archivos sensibles.
- Conexión PDO con consultas preparadas habilitadas (`ATTR_EMULATE_PREPARES=false`).
- Sesiones endurecidas: `httponly`, `samesite=Strict`, `secure` en HTTPS, modo estricto, expiración por inactividad.
- Protección CSRF base (`csrfToken`, `csrfField`, `verifyCsrf`).
- Escape HTML con `e()` para mitigar XSS en salida.
- Cabeceras de seguridad base (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS cuando HTTPS).
- Forzado de HTTPS configurable.
- CORS restringido por `ALLOWED_ORIGINS`.
- `.htaccess` para denegar acceso a archivos sensibles y deshabilitar listado de directorios.
- Esquema con tablas para auditoría, intentos de login, recuperación con expiración y control de estado de usuarios.

## Seguridad pendiente por implementar en pasos siguientes

Los siguientes puntos se implementarán y verificarán en los pasos funcionales/finales:

- Recuperación por correo con contraseña temporal automática y caducidad.
- Validación de formularios en servidor en todos los módulos.
- Límite de peticiones/rate limit aplicado en endpoints sensibles.
- Blindaje de rutas admin por rol.
- Registro y vigilancia de actividad sospechosa en acciones críticas.
- Validación estricta de tipo/tamaño de archivos subidos.
- Revisión de exposición de secretos/archivos, dependencias y hardening final.
- Informe final de seguridad con pruebas.

## Nota sobre correo

La recuperación solicitada enviará usuario y contraseña temporal automática al correo registrado. Por seguridad, la contraseña temporal será **hasheada en BD**, tendrá **caducidad** y el usuario deberá **cambiarla al iniciar sesión**.
