<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'CEPTRI · Bee Framework', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; color: #0f172a; background: #f8fafc; }
        body { display: grid; min-height: 100vh; margin: 0; place-items: center; }
        main { max-width: 38rem; margin: 1.5rem; padding: 2rem; border: 1px solid #e2e8f0; border-radius: 1rem; background: white; }
        h1 { color: #1e3a8a; }
        code { padding: .2rem .4rem; border-radius: .3rem; background: #f1f5f9; }
    </style>
</head>
<body>
    <main>
        <h1>CEPTRI · Backend Bee</h1>
        <p>Bee Framework carga correctamente y se conecta a la base local de desarrollo.</p>
        <p>El backend ya incluye autenticación, administración de usuarios y padrón escolar con inscripciones por cuatrimestre. Las demás funciones se migrarán de forma gradual sin reemplazar todavía la aplicación actual.</p>
        <p><a href="<?= htmlspecialchars(URL . 'auth', ENT_QUOTES, 'UTF-8') ?>">Iniciar sesión</a></p>
    </main>
</body>
</html>
