# Backend CEPTRI con Bee Framework

Esta aplicación Bee Framework 1.5.8 es una base de migración aislada. El sistema HTML/JavaScript actual permanece en la raíz del repositorio; ya se migraron el padrón, los grupos y asignaciones, la revisión de expedientes y Finanzas. El portal del alumno, el portal docente y los demás módulos escolares siguen en la aplicación original.

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

6. Crea el catálogo de materias y las tablas para grupos y asignaciones:

   ```bash
   php backend/bin/migrate-academics.php
   ```

   La migración requiere `APP_ENV=development`, es idempotente y agrega las materias base de las tres carreras. Los grupos, docentes y alumnos deben registrarse desde la interfaz; no se importan datos de demostración.

7. Crea el catálogo de requisitos y las tablas para revisión de expedientes digitales:

   ```bash
   php backend/bin/migrate-documents.php
   ```

   Esta migración requiere `APP_ENV=development` y es idempotente. Los archivos se almacenan fuera de las rutas públicas, en `backend/private/student-documents/`; solo cuentas con `records.review` pueden cargarlos, revisarlos o consultarlos. Se aceptan PDF, JPG y PNG, hasta 5 MB, sujetos a los límites PHP del servidor. Los archivos y sus versiones anteriores no se publican en Git.

8. Crea las cuentas financieras cuatrimestrales y el registro de pagos:

   ```bash
   php backend/bin/migrate-finance.php
   ```

   Esta migración requiere `APP_ENV=development` y `DB_NAME=ceptri_dev`, es idempotente y crea una cuenta con estado `adeudo` por cada inscripción existente. Las cuotas se configuran individualmente en Finanzas para cada alumno y cuatrimestre; no se fija una tarifa global. El sistema conserva folios, referencias, importes, fechas, método de pago, correcciones, anulaciones y auditoría. Solo cuentas autorizadas con `finance.view` pueden consultar; las acciones de registro, edición, anulación y exportación también verifican sus permisos específicos.

9. Cuando estés listo para crear la cuenta administradora inicial, ejecuta el instalador interactivo desde la raíz. Pedirá usuario, correo, nombre y contraseña; la contraseña se escribe oculta y solo se almacena como hash:

   ```bash
   php backend/bin/install-rbac.php
   ```

   El instalador no reemplaza usuarios existentes. El acceso inicial se asigna al rol Administrador, que conserva acceso global desde el servidor.

10. Desde `backend/`, inicia el servidor local. Los valores `-d` habilitan el máximo de carga de 5 MB para la prueba:

   ```bash
   cd backend
   php -d upload_max_filesize=5M -d post_max_size=6M -S 127.0.0.1:8081 router.php
   ```

11. Abre `http://127.0.0.1:8081/`.

12. Para probar inicio/cierre de sesión y permisos HTTP, con el servidor activo ejecuta desde la raíz:

   ```bash
   php backend/bin/smoke-auth.php
   ```

   El test crea alumnos, grupos, cuentas, roles y documentos aleatorios, prueba las pantallas de administración, Control Escolar y Finanzas, registra y corrige pagos, prueba anulaciones y estados de cuenta, descarga los historiales y reportes PDF y elimina los datos temporales al finalizar.

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
- Navegación lateral responsiva en Administración, padrón y grupos; sus enlaces se muestran según los permisos efectivos de cada cuenta.
- Padrón escolar: expedientes de alumnos e inscripciones por cuatrimestre con historial, búsqueda, validación de matrícula y estados académicos; requiere `students.manage`.
- Grupos académicos: grupos por carrera y cuatrimestre, materias impartidas, asignación de docentes activos y reinscripción de alumnos a materias; requiere `academic.assign`.
- Reportes de grupo descargables en PDF con docentes, materias y alumnos inscritos.
- Revisión de expedientes digitales: cinco requisitos, carga de PDF/JPG/PNG en almacenamiento privado, nuevas versiones, aprobación o rechazo con motivo y auditoría de carga/revisión; requiere `records.review`.
- Finanzas: estado de cuenta por inscripción y cuatrimestre, adeudo inicial, cuota editable individualmente, registro de pagos con folio y referencia, edición y anulación auditadas y descarga del historial de pagos por alumno en PDF; requiere `finance.view` y permisos específicos por operación.
- El padrón no crea todavía cuentas de acceso al portal ni importa los datos de demostración locales del sistema anterior.
- Los registros financieros de la aplicación anterior son datos de demostración en JavaScript y no se importan. Los pagos nuevos se guardan en MariaDB.
- Flujos HTTP administrativos, RBAC, CSRF, menú por permisos, inscripción duplicada, revisión documental, acceso privado, cuentas y pagos financieros, asignaciones y reportes PDF: cubiertos por `php backend/bin/smoke-auth.php`.
- Portal del alumno y portal docente: aún no se han migrado.
