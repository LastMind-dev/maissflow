<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Entrar — MaissFlow</title>
    @vite(['resources/css/app.css'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-brand">
            <div class="brand-pill">Atendimento próprio</div>
            <div class="login-brand-copy">
                <div class="login-logo">F</div>
                <h1>MaissFlow</h1>
                <p>Automação visual, conversas e operação do WhatsApp em um único lugar.</p>
            </div>
            <div class="login-flow-preview" aria-hidden="true">
                <div class="preview-node preview-trigger"><span></span>Nova mensagem</div>
                <div class="preview-line one"></div>
                <div class="preview-node preview-menu">Menu principal<br><small>6 opções</small></div>
                <div class="preview-line two"></div>
                <div class="preview-node preview-answer">Atendimento<br><small>Equipe online</small></div>
            </div>
        </section>

        <section class="login-panel">
            <form method="POST" action="{{ url('/login') }}" class="login-card">
                @csrf
                <div class="mobile-logo">F</div>
                <p class="eyebrow">PAINEL ADMINISTRATIVO</p>
                <h2>Bem-vindo de volta</h2>
                <p class="muted">Entre para gerenciar seus fluxos e atendimentos.</p>

                @if ($errors->any())
                    <div class="form-alert">{{ $errors->first() }}</div>
                @endif

                <label for="email">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="voce@empresa.com.br">

                <div class="password-label">
                    <label for="password">Senha</label>
                </div>
                <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Digite sua senha">

                <label class="remember-row">
                    <input type="checkbox" name="remember" value="1">
                    <span>Lembrar meu acesso</span>
                </label>

                <button type="submit" class="login-submit">Entrar no MaissFlow</button>
                <p class="login-help">Ambiente corporativo protegido e auditável.</p>
            </form>
        </section>
    </main>
</body>
</html>
