# Meu Calendário Acadêmico — Back-end (Etapa 1)

Implementação **PHP 8.2 + PostgreSQL**, aproveitando a interface do projeto e as tabelas do grupo. Esta etapa entrega **banco migrado, contas de aluno e professor, login real, consulta de sessão e logout**. O calendário e as demais funcionalidades da interface **continuam com dados de demonstração no navegador**: não são publicados no servidor nesta versão.

## 1. Requisitos

- PHP 8.2 ou superior, com as extensões `PDO` e `pdo_pgsql` habilitadas.
- PostgreSQL (por exemplo 14, 15, 16 ou mais recente).
- Terminal (Windows PowerShell, CMD ou terminal do VS Code) e acesso ao `psql` ou pgAdmin.
- Internet para bibliotecas de interface carregadas via CDN (Bootstrap, FullCalendar etc.).

Verifique:

```powershell
php -v
php -m
```

Em `php -m`, procure `PDO` e **`pdo_pgsql`**. Em instalações com XAMPP, habilite `extension=pdo_pgsql` no `php.ini` usado pelo PHP e reinicie o terminal/servidor. O PostgreSQL precisa estar em execução.

## 2. Preparar o PostgreSQL

**Atenção:** `database/schema_original.sql` é o arquivo que você enviou. Ele contém `DROP TABLE` e **apaga dados existentes**. Execute-o **apenas num banco vazio de desenvolvimento**. Se já tem as tabelas, pule esse arquivo e use apenas a migração `001_autenticacao.sql`.

Crie um banco chamado `meu_calendario_academico` no pgAdmin (ou rode `CREATE DATABASE meu_calendario_academico;` conectado ao banco `postgres`).

### Banco NOVO (vazio)

Na raiz do projeto, execute no terminal:

```powershell
psql -U postgres -d meu_calendario_academico -f database/schema_original.sql
psql -U postgres -d meu_calendario_academico -f database/seed_original.sql
psql -U postgres -d meu_calendario_academico -f database/001_autenticacao.sql
```

O `seed_original.sql` é **opcional**: adiciona exemplos de disciplinas, atividades etc. Se não quiser dados fictícios, omita-o. Com o seed, **os hashes de senhas de estudante são fictícios** e não permitem login. Use o script de contas de teste, descrito adiante.

### Banco que JÁ contém `schema.sql`

**Não execute** `schema_original.sql` novamente. Faça backup e depois:

```powershell
psql -U postgres -d meu_calendario_academico -f database/001_autenticacao.sql
```

No pgAdmin, você também pode abrir e executar os arquivos SQL na mesma ordem, selecionando o banco recém-criado. A migração é aditiva e pode ser reaplicada em condições normais sem duplicar as contas importadas.

### O que foi acrescentado

- `conta`: e-mail único (sem diferenciar maiúsculas/minúsculas), hash de senha e papel `ALUNO` / `PROFESSOR`.
- `estudante.id_conta`: vínculo da tabela original com a conta. Matrículas e demais FKs da base original não mudam.
- `professor`: perfil de docente vinculado a `conta`.
- `professor_disciplina`: disciplinas que cada professor pode administrar nas próximas etapas.

**Compatibilidade:** `estudante.email` e `estudante.senha` continuam fisicamente na tabela original, mas **não são usados para autenticar**: o login consulta `conta`. A remoção gradual da duplicidade pode ser feita numa migração futura.

## 3. Configurar PHP

Copie `.env.example` para `.env` e preencha `DB_PASSWORD` com a senha do seu usuário PostgreSQL. Não compartilhe nem adicione `.env` ao GitHub.

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Exemplo do arquivo `.env`:

```ini
APP_ENV=local
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=meu_calendario_academico
DB_USER=postgres
DB_PASSWORD=sua_senha_local
```

O arquivo `.env` fica **fora** da pasta pública. Se o servidor ou terminal já define essas variáveis de ambiente, elas têm prioridade.

## 4. Criar contas de teste

Após importar o SQL e configurar o `.env`:

```powershell
php scripts/criar_contas_teste.php
```

Esse comando cria (somente se ainda não existirem):

- **Aluno:** `aluno.demo@alunos.utfpr.edu.br`
- **Professor:** `professor.demo@utfpr.edu.br`

O script gera **senhas aleatórias diferentes** e as mostra **uma única vez** no terminal. Copie-as para testar. Ao executar novamente, ele não troca as senhas das contas existentes.

O professor de teste é associado automaticamente à primeira disciplina disponível. Para vincular a outra disciplina, por exemplo `ES64A` da base original:

```powershell
php scripts/vincular_professor.php professor.demo@utfpr.edu.br ES64A
```

**Importante:** e-mail institucional é validado por formato, mas **não existe validação pela UTFPR ou envio de e-mail** nesta etapa. As contas são criadas de maneira controlada pelo terminal; não há cadastro público nem integração com login institucional.

## 5. Iniciar o servidor

Na **raiz do projeto**:

```powershell
php -S 127.0.0.1:8000 -t public
```

Abra:

- [http://127.0.0.1:8000](http://127.0.0.1:8000) — aplicação, login integrado.
- [http://127.0.0.1:8000/api/health.php](http://127.0.0.1:8000/api/health.php) — verifica conexão com PostgreSQL.

**Não** abra o HTML diretamente por `file://` nem use apenas o Live Server do VS Code: essas opções não executam endpoints PHP. Para usar Apache, configure a raiz pública (DocumentRoot) para a pasta `public`, sem expor `.env`, `app` ou `database`.

## 6. API de autenticação

| Método | Rota | Finalidade |
|---|---|---|
| `GET` | `/api/auth/csrf.php` | Inicia sessão, fornece token CSRF para requisições POST |
| `POST` | `/api/auth/login.php` | Verifica credenciais reais e cria sessão |
| `GET` | `/api/auth/me.php` | Retorna o usuário autenticado; HTTP 401 se não há sessão |
| `POST` | `/api/auth/logout.php` | Finaliza sessão no servidor |
| `GET` | `/api/health.php` | Verifica conexão com PostgreSQL |

Para chamar **login**:

1. Faça `GET /api/auth/csrf.php` para obter `csrf_token` e o cookie de sessão.
2. Envie `POST /api/auth/login.php` com `Content-Type: application/json`, cabeçalho `X-CSRF-Token: <token>` e corpo:

```json
{
  "email": "aluno.demo@alunos.utfpr.edu.br",
  "senha": "SENHA_EXIBIDA_NO_TERMINAL"
}
```

Resposta esperada de sucesso (IDs são exemplos):

```json
{
  "ok": true,
  "usuario": {
    "id": 4,
    "nome": "Aluno Demonstração",
    "email": "aluno.demo@alunos.utfpr.edu.br",
    "papel": "ALUNO",
    "id_estudante": 4,
    "id_professor": null
  },
  "csrf_token": "TOKEN_NOVO_DA_SESSAO"
}
```

Se o e-mail/senha não confere, o retorno é **HTTP 401**. Uma sessão ausente em `me.php` também recebe **HTTP 401**. CSRF incorreto retorna **HTTP 419**. O login tem limitação básica de tentativas por sessão (não substitui um limitador por IP num ambiente público).

O JavaScript fornecido no projeto já faz essas chamadas usando `fetch` e cookies da mesma origem. A opção **Sair** encerra a sessão de servidor, e atualizar a página mantém o login enquanto a sessão for válida. Aluno e professor são identificados pelo campo `papel` retornado pelo banco.

## 7. Testes manuais essenciais

1. Execute `GET /api/health.php` — deve responder `ok: true`.
2. Faça login como aluno com senha correta — deve exibir o nome do aluno de teste.
3. Atualize a página — deve continuar autenticado via cookie de sessão.
4. Clique em **Sair** — a tela de login retorna; `GET /api/auth/me.php` deve responder 401.
5. Tente senha errada — deve responder 401 e não entrar.
6. Faça login como professor — o servidor retorna `papel: "PROFESSOR"`.
7. Faça `POST /api/auth/logout.php` sem `X-CSRF-Token` — deve responder 419.

## 8. Estrutura da entrega

```text
meu-calendario-academico-backend/
├── .env.example
├── .gitignore
├── README.md
├── app/
│   ├── auth.php               # Autenticação, sessão e papéis
│   ├── bootstrap.php          # Configuração comum
│   ├── database.php           # Conexão PDO/PostgreSQL
│   └── http.php               # Respostas/validação de requisições
├── database/
│   ├── schema_original.sql    # Seu DDL original (destrutivo)
│   ├── seed_original.sql      # Seu seed original (hashes fictícios)
│   └── 001_autenticacao.sql   # Migração incremental
├── public/
│   ├── index.html             # Login conectado à API
│   ├── css/style.css
│   ├── js/data.js             # Dados locais apenas para demonstração
│   ├── js/app.js              # Sessões reais, outros recursos locais
│   └── api/
│       ├── health.php
│       └── auth/
│           ├── csrf.php
│           ├── login.php
│           ├── logout.php
│           └── me.php
└── scripts/
    ├── criar_contas_teste.php
    └── vincular_professor.php
```

## 9. Próximas etapas (ainda NÃO implementadas)

- Endpoints de disciplinas e matrículas filtradas pelo aluno autenticado.
- Endpoints de criação/alteração de atividades, apenas por professor com vínculo à disciplina.
- Importação PDF/CSV/ICS no servidor e detecção de atividades repetidas.
- Histórico de mudanças, alertas e notificações reais no banco.
- Conclusão de atividades por aluno (no protótipo esse estado é apenas local).
- Edição real do perfil, alteração e recuperação de senha e verificação de titularidade do e-mail.
- Proteções adicionais para produção: HTTPS, controle de tentativas por IP e conta, logs de auditoria, backups, testes integrados e política de expiração de sessão.

A migração e as rotas tiveram verificação estática de sintaxe. A execução fim a fim depende de uma instância PostgreSQL e da extensão `pdo_pgsql` configuradas no seu computador.
