# MaissFlow — automação própria de atendimento no WhatsApp

Aplicação interna para construir, publicar e executar fluxos de atendimento na
WhatsApp Cloud API. O projeto substitui o núcleo usado no ManyChat pela empresa:
editor visual, menus editáveis, versionamento imutável, simulador, contatos,
caixa de entrada humano/bot, auditoria e processamento seguro de webhooks.

> O produto implementa a paridade do fluxo operacional apresentado nas
> referências, mas não copia marca, código ou ativos proprietários do ManyChat.
> Recursos comerciais que não fazem parte desse fluxo estão identificados no
> roadmap de produção.

## O que já funciona

- editor visual por nós e conexões, com zoom, minimapa, arrastar e conectar;
- gatilho de mensagem recebida, mensagens, listas, botões e transferência humana;
- edição dos textos e das opções do menu diretamente no painel lateral;
- salvamento automático com revisão otimista para evitar sobrescrita concorrente;
- validação estrutural e dos limites dos menus antes da publicação;
- publicação transacional e criação automática do próximo rascunho;
- simulador do fluxo sem consumir a API da Meta;
- execução do fluxo publicado para mensagens recebidas;
- caixa de entrada com modo bot/humano e resposta manual na janela de 24 horas;
- contatos, métricas básicas e histórico das conversas;
- verificação de webhook, assinatura HMAC, idempotência e processamento em fila;
- segredos da Meta criptografados e nunca devolvidos ao navegador;
- escopo por empresa, perfis `owner`, `admin` e `agent`, e trilha de auditoria.

O fluxo inicial possui os menus de NF-e, VAF, Declarações, Cadastros, Senha,
Suporte e Solicitações (Ouvidoria), com seus submenus e retornos, conforme o
diagrama de referência.

## Ouvidoria / e-SIC (integração MAISSDoc)

A integração com o MAISSDoc cobre três ramos no menu principal:

- **Ouvidoria** — coleta a manifestação (reclamação, denúncia, sugestão,
  elogio ou solicitação), com opção de envio anônimo, e devolve protocolo +
  código de acompanhamento. Anexos (fotos/documentos enviados como mídia no
  WhatsApp, até 5) são baixados da Graph API e anexados à manifestação.
- **Pedido e-SIC** — pedido de acesso à informação (LAI). Exige identificação
  completa (nome, CPF e e-mail); não há opção anônima.
- **Acompanhar pedido** — consulta o andamento pelo par protocolo + código de
  acompanhamento (a mesma credencial do portal público) e exibe situação,
  setor, prazo e resposta.

O caminho oficial é a **API JSON do MAISSDoc** (`/api/portal/v1`), autenticada
por Bearer token server-side com `Idempotency-Key` no envio. Se
`GED_API_URL`/`GED_API_TOKEN` não estiverem configurados, a ouvidoria cai no
fallback legado (formulário público com CSRF + sessão); e-SIC, anexos e
consulta exigem a API.

Configuração no `.env`:

```dotenv
# API oficial (habilita ouvidoria+e-SIC, anexos e consulta de andamento)
GED_API_URL=https://prdmaissdoc.fgmaiss.com.br/api/portal/v1
GED_API_TOKEN=                       # mesmo valor do PORTAL_API_TOKEN no GED
GED_OUVIDORIA_TIMEOUT=20

# Fallback legado da ouvidoria (usado só sem a API configurada)
GED_OUVIDORIA_URL=https://prdmaissdoc.fgmaiss.com.br/ouvidoria
```

No MAISSDoc, configure `PORTAL_API_TOKEN` com o mesmo segredo (ver
`docs/INTEGRACAO_API_PORTAL.md` no repositório do GED). O e-SIC exige que o
GED esteja em modo **centralizado** (`departamento_destino_id` opcional) —
no modo distribuído o envio via API responde 422 por falta de setor.

A instalação em uma automação existente preserva o grafo atual e adiciona os
três ramos ao rascunho (idempotente — ramos já presentes são mantidos):

```powershell
php artisan automation:install-portal                        # automação ativa mais antiga
php artisan automation:install-portal {public_id}            # automação específica
php artisan automation:install-portal --menu=menu_principal  # outro nó de menu
```

`automation:install-ouvidoria` continua disponível e instala apenas o ramo da
ouvidoria. Em ambos os casos, revise no editor e publique para ativar.

Ações registradas no grafo (allowlist — o grafo nunca contém URL ou credencial):

- `ouvidoria.submit` — registra a manifestação/pedido na API do GED
  (`canal: ouvidoria` ou `canal: esic` no nó de ação) e envia os anexos
  coletados no nó de mídia (`validation: media`, variável `anexos`).
- `ouvidoria.consulta` — consulta o andamento por protocolo + código e expõe
  `{{flow.consulta_status_label}}`, `{{flow.consulta_resposta}}` etc.

## Stack

- PHP 8.4.1+ e Laravel 12 (conforme o `composer.lock` atual);
- MySQL 8.4+ em produção;
- React 19, Vite e React Flow;
- fila e cache do Laravel;
- WhatsApp Cloud API oficial da Meta.

## Execução local no Laragon

O projeto já está na raiz correta do Laragon e responde em:

`http://maissflow.test`

Para reinstalar do zero:

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

Crie o banco local de testes no MySQL do Laragon:

```sql
CREATE DATABASE IF NOT EXISTS bd_wchat
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Use a conexão abaixo no `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bd_wchat
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
WEBHOOK_QUEUE=default
SESSION_DRIVER=database
CACHE_STORE=database
```

Migre e carregue apenas o workspace, o master e a automação inicial:

```powershell
php artisan migrate:fresh --seed
npm run build
C:\laragon\laragon.exe reload
```

O seeder lê a credencial master do `.env`:

- usuário padrão: `admin@primaxonline.com.br`
- senha: valor de `ADMIN_PASSWORD`
- `SEED_DEMO_DATA=false` impede a criação de contatos fictícios.

Troque a credencial antes de expor o ambiente fora da máquina local.

## MySQL de produção

No Plesk, crie um banco e um usuário exclusivo da aplicação. Não utilize `root`
em produção:

```sql
CREATE DATABASE wchat
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'wchat_app'@'localhost'
    IDENTIFIED BY 'USE_UMA_SENHA_FORTE_E_EXCLUSIVA';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON wchat.* TO 'wchat_app'@'localhost';
FLUSH PRIVILEGES;
```

Preencha `DB_PASSWORD` no `.env` e execute:

```powershell
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

O esquema usa InnoDB, chaves estrangeiras, unicidade, índices compostos e
colunas `JSON`. O grafo é lido como um documento versionado; o runtime não faz
pesquisas internas nessas colunas.

## Trabalhador da fila

O endpoint confirma rapidamente o recebimento e delega o processamento:

```powershell
php artisan queue:work database --queue=default --tries=5 --backoff=5
```

Em produção, habilite esse worker no gerenciador Laravel do Plesk. A fila do
webhook é configurada por `WEBHOOK_QUEUE=default`, portanto não depende de uma
sessão SSH. Sem o worker, os eventos ficam persistidos, mas não são executados.

## Testes e qualidade

```powershell
php artisan test
vendor\bin\pint --test
npm run build
php artisan route:list --except-vendor
```

Os testes cobrem autenticação, isolamento entre empresas, concorrência de
edição, publicação/versionamento, validação do grafo, simulação, verificação e
assinatura do webhook, deduplicação, entrada real no runtime e status de falha.

## Documentação

- [Arquitetura e modelo de dados](docs/ARQUITETURA.md)
- [Configuração do aplicativo na Meta](docs/CONFIGURACAO_META.md)
- [Checklist e roadmap de produção](docs/PRODUCAO.md)
