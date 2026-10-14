<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($d->title ?? 'Iniciar sesión · CEPTRI', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { font-family: system-ui, sans-serif; color: #0f172a; background: #f8fafc; }
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; margin: 0; place-items: center; padding: 1rem; }
        main { width: min(100%, 27rem); padding: 2rem; border: 1px solid #e2e8f0; border-radius: 1rem; background: #fff; box-shadow: 0 15px 40px #0f172a12; }
        h1 { margin: 0 0 .4rem; color: #1e3a8a; }
        p { color: #475569; }
        label { display: block; margin-top: 1rem; font-weight: 600; }
        input { display: block; width: 100%; min-height: 2.8rem; margin-top: .4rem; padding: .65rem .75rem; border: 1px solid #cbd5e1; border-radius: .5rem; font: inherit; }
        button { width: 100%; min-height: 2.8rem; margin-top: 1.25rem; border: 0; border-radius: .5rem; background: #1e3a8a; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .error { padding: .7rem; border-radius: .5rem; background: #fff1f2; color: #9f1239; }
    </style>
</head>
<body>
    <main>
        <h1>CEPTRI</h1>
        <p>Inicia sesión para entrar al backend de migración.</p>
        <?php if (!empty($d->error)): ?>
            <p class="error" role="alert"><?= htmlspecialchars($d->error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(URL . 'auth/login', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($d->csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="identifier">Usuario o correo</label>
            <input id="identifier" name="identifier" type="text" autocomplete="username" maxlength="254" required autofocus>
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button type="submit">Iniciar sesión</button>
        </form>
    </main>
</body>
</html>
