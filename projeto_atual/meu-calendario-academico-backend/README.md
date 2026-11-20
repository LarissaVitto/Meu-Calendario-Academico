# Meu Calendário Acadêmico

Sistema web para organização de atividades acadêmicas, desenvolvido com PHP, PostgreSQL, HTML, CSS e JavaScript.

## Funcionalidades

- Autenticação com sessões e perfis de **professor** e **aluno**.
- Controle de acesso às disciplinas e atividades.
- Cadastro, edição, listagem e exclusão de atividades por professores autorizados.
- Visualização de atividades publicadas por alunos matriculados.
- Marcação individual de atividades como concluídas e reabertura.
- Histórico de alterações das atividades.
- Notificações relacionadas à publicação e alteração de atividades.
- Calendário acadêmico com visualização e filtros.

**Em desenvolvimento futuro:** importação de planejamentos em CSV, ICS e PDF para o PostgreSQL, com deduplicação e registro de sincronizações. A existência da tabela `sincronizacao` não significa que a importação esteja implementada.

## Tecnologias e requisitos

- PHP 8 com extensões `pdo_pgsql` e `mbstring` habilitadas.
- PostgreSQL (o esquema foi exportado de PostgreSQL 18.7).
- Navegador moderno.
- pgAdmin 4 (opcional, para administrar o banco).
- Conexão com a internet para carregar bibliotecas de frontend distribuídas por CDN, quando aplicável.

## Estrutura do projeto

```text
app/                    Configuração, autenticação, HTTP e notificações
public/
  index.html             Interface web
  api/                   Endpoints PHP
    auth/                Login, logout, sessão e CSRF
    atividades/          CRUD de atividades e conclusão individual
    disciplinas/         Consulta de disciplinas
    historico/           Histórico de alterações
    notificacoes/        Notificações
  css/                   Estilos
  js/                    Código JavaScript
database/
  schema_original.sql    Estrutura original (referência histórica)
  002_planejamento.sql   Migração de planejamento
  schema_atualizado.sql  Exportação da estrutura atual do banco
  seed_original.sql      Dados de exemplo originais
scripts/                 Scripts auxiliares de desenvolvimento
tests/                   Testes auxiliares
.env.example             Modelo de configuração
.gitignore               Arquivos que não devem ser versionados
```

## Instalação local

### 1. Obter o código

Clone o repositório ou abra a pasta do projeto no VS Code.

### 2. Criar o banco PostgreSQL

Crie **um banco de dados vazio** no PostgreSQL usando o pgAdmin 4.

No Query Tool desse banco vazio, abra e execute `database/schema_atualizado.sql` para criar a estrutura atual de tabelas, chaves e índices.

> **Atenção:** não execute esse arquivo sobre o banco já utilizado no desenvolvimento. Ele é destinado à criação de uma nova estrutura. Não execute `schema_original.sql`, `001_autenticacao.sql` e `002_planejamento.sql` em sequência após o schema atualizado, pois isso pode tentar criar objetos já existentes.

O arquivo `schema_atualizado.sql` contém **somente a estrutura**, sem usuários, disciplinas, matrículas ou atividades. Para uma instalação nova, será necessário cadastrar ou preparar os dados iniciais adequados. O arquivo `seed_original.sql` é legado e deve ser revisado antes de ser usado com o schema atualizado.

### 3. Configurar o ambiente

Copie `.env.example` para `.env` e preencha as configurações de conexão com o banco conforme os nomes de variáveis existentes no exemplo:

```powershell
Copy-Item .env.example .env
```

Nunca publique o arquivo `.env`, senhas ou credenciais no GitHub.

### 4. Iniciar o servidor

No terminal, na raiz do projeto:

```powershell
php -S localhost:8000 -t public
```

Acesse `http://localhost:8000`.

> O login depende de contas existentes no banco. Os scripts de `scripts/` são auxiliares de desenvolvimento e devem ser revisados antes de executar em uma instalação nova.

## API

Os endpoints ficam sob `public/api/`, acessíveis pelo navegador a partir de `/api/`:

- `auth/`: autenticação, sessão e token CSRF.
- `disciplinas/`: consulta das disciplinas acessíveis.
- `atividades/`: listagem e operações de cadastro, edição e exclusão, conforme o perfil.
- `atividades/conclusao.php`: conclusão individual de atividades pelo aluno.
- `historico/`: consulta de alterações.
- `notificacoes/`: consulta de notificações.

As operações de escrita exigem autenticação, autorização e proteção CSRF conforme o endpoint.

## Banco de dados

`database/schema_atualizado.sql` é uma exportação da estrutura atual do PostgreSQL, com 13 tabelas, incluindo `conta`, `professor`, `estudante`, `disciplina`, `matricula`, `atividade`, `atividade_conclusao`, `historico_alteracao`, `notificacao`, `lembrete` e `sincronizacao`.

O schema foi exportado sem os registros de dados. A restauração em um banco vazio ainda deve ser testada antes de considerar a instalação reproduzível integralmente.

## Segurança e publicação

- Confirme que `.env` está listado no `.gitignore`.
- Não envie credenciais, backups com dados pessoais ou arquivos temporários de teste.
- Revise os scripts de criação de contas de teste antes de disponibilizá-los publicamente.
- Antes do primeiro `push`, verifique o histórico e os arquivos existentes no repositório remoto para evitar sobrescritas.

## Estado do projeto

O cadastro e a edição de atividades, o histórico e as notificações foram testados no ambiente local. A importação persistente de arquivos CSV, ICS e PDF e o teste de restauração integral do banco permanecem pendentes.
