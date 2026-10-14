# Backend CEPTRI con Bee Framework

Esta aplicación Bee Framework 1.5.8 es una base de migración aislada. El sistema HTML/JavaScript actual permanece en la raíz del repositorio; ya se migró el padrón e inscripción de alumnos, mientras que Finanzas, grupos, asignación académica y los demás módulos escolares siguen en la aplicación original.

## Requisitos

- PHP 8.1 o posterior con `iconv`, `pdo_mysql`, `mbstring`, `curl` y `zip`.
- Composer 2.
- MySQL o MariaDB.

## Preparar entorno local

Desde la raíz del repositorio:

1. Copia `backend/.env.example` a `backend/.env`.
2. En `backend/.env`, configura la contraseña local de `ceptri_app` y reemplaza los dos secretos de ejemplo por valores distintos, aleatorios y de al menos 32 caracteres.
3. Instala dependencias, audítalas y prueba PDO contra la base configurada:

   ```bash
   cd backend/app
   composer install
   composer audit
   cd ..
   php bin/check-db.php
   cd ..
   ```

4. Crea las tablas de roles y permisos y sus cinco roles iniciales:

   ```bash
   php backend/bin/install-rbac.php --schema-only
   ```

5. Crea las tablas del padrón e inscripciones cuatrimestrales en la base de desarrollo:

   ```bash
   php backend/bin/migrate-students.php
   ```

   Esta migración inicial requiere `APP_ENV=development` y es idempotente. No importa datos de los arreglos simulados de la aplicación anterior.

6. Cuando estés listo para crear la cuenta administradora inicial, ejecuta el instalador interactivo desde la raíz. Pedirá usuario, correo, nombre y contraseña; la contraseña se escribe oculta y solo se almacena como hash:

   ```bash
   php backend/bin/install-rbac.php
   ```

   El instalador no reemplaza usuarios existentes. El acceso inicial se asigna al rol Administrador, que conserva acceso global desde el servidor.

7. Desde `backend/`, inicia el servidor local:

   ```bash
   cd backend
   php -S 127.0.0.1:8081 router.php
   ```

8. Abre `http://127.0.0.1:8081/`.

9. Para probar inicio/cierre de sesión y permisos HTTP, con el servidor activo ejecuta desde la raíz:

   ```bash
   php backend/bin/smoke-auth.php
   ```

   El test crea alumnos, cuentas y roles aleatorios, prueba las pantallas de administración y Control Escolar, y elimina los datos temporales al finalizar.

El archivo `.env` y `app/vendor/` están excluidos de Git. Nunca agregues credenciales al repositorio.

## Dependencias y licencia

El núcleo procede de [Moxtrip69/Bee-Framework](https://github.com/Moxtrip69/Bee-Framework), versión 1.5.8, commit `425b14d8ae9fcf1b23ba075284c375fd258e613d`, bajo GPL-3.0. Se conserva la licencia en `LICENSE`.

El manifiesto de Composer excluye `verot/class.upload.php`, que Composer bloquea por avisos de seguridad, y usa Dompdf 3.x en vez de la rama 2.x solicitada por el manifiesto original. Ejecuta `composer audit` después de cualquier actualización.

## Estado de migración

- Base Bee, autoload y configuración local: instalados.
- Conexión PHP por PDO y mediante `Db::connect()` de Bee a MariaDB: comprobadas; ejecuta `php bin/check-db.php` para verificar las credenciales guardadas en `.env`.
- Esquema RBAC y catálogo inicial (cinco roles y dieciséis permisos): instalados en la base local de desarrollo.
- Cuenta administradora inicial: creada mediante instalador interactivo.
- Autenticación web de Bee, cierre de sesión y panel administrativo protegido por permisos del servidor: implementados y probados.
- Gestión administrativa: alta y edición de usuarios, cambio de rol y estado, restablecimiento de contraseña y excepciones individuales de permisos.
- Gestión RBAC: alta de roles y asignación de permisos por rol; el permiso global `admin-access` está protegido y no se puede conceder desde las pantallas.
- Padrón escolar: expedientes de alumnos e inscripciones por cuatrimestre con historial, búsqueda, validación de matrícula y estados académicos; requiere `students.manage`.
- El padrón no crea todavía cuentas de acceso al portal ni importa los datos de demostración locales del sistema anterior.
- Flujos HTTP administrativos, RBAC, CSRF, inscripción duplicada y acceso de Control Escolar: cubiertos por `php backend/bin/smoke-auth.php`.
- Asignación a grupos/materias, revisión de documentos, portal del alumno y módulo de Finanzas: aún no se han migrado.
