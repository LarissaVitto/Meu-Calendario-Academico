# Meu Calendário Acadêmico

Sistema web (UTFPR – Campus Dois Vizinhos) que centraliza as atividades acadêmicas do estudante em um único calendário.

**Como funciona:** o professor altera o planejamento da disciplina na tabela do **Moodle**. O sistema **sincroniza** essas mudanças (atividades novas, datas alteradas, atividades retiradas), avisa o estudante e atualiza o calendário. O estudante consulta o calendário e **marca o que já fez**. Não existe publicação de atividades dentro do sistema.

Stack: HTML, CSS, Bootstrap, JavaScript, FullCalendar, PHP e PostgreSQL.

---

## Requisitos

- PHP **8.1 ou superior** com as extensões `pdo_pgsql`, `pgsql` e `mbstring` habilitadas (no Windows, descomente-as no `php.ini`)
- PostgreSQL 12 ou superior
- Internet no navegador (Bootstrap, FullCalendar e fontes vêm de CDN)

## Instalação (uma vez)

1. Crie o banco no PostgreSQL, com codificação UTF8:
   ```sql
   CREATE DATABASE meu_calendario_academico ENCODING 'UTF8';
   ```
2. Confira o arquivo `.env` (host, usuário e senha do seu PostgreSQL). Há um modelo em `.env.example`.
3. Na pasta do projeto, crie as tabelas e carregue os dados de exemplo:
   ```bash
   php scripts/instalar.php
   ```
   (se já existirem tabelas de uma versão anterior: `php scripts/instalar.php --recriar`, que **apaga tudo**)
4. Inicie o servidor — **a raiz do servidor é a pasta `public`**:
   ```bash
   php -S localhost:8000 -t public
   ```
5. Abra http://localhost:8000 e entre com uma conta de demonstração:

   | E-mail | Senha | Disciplinas |
   |---|---|---|
   | `aluno.demo@alunos.utfpr.edu.br` | `demo1234` | todas |
   | `aluno2.demo@alunos.utfpr.edu.br` | `demo1234` | AED2, ARSW, RDCP |

## Testando a sincronização (o professor "mexe" no Moodle)

1. Entre e deixe algumas atividades marcadas como concluídas.
2. Simule o professor alterando o planejamento:
   ```bash
   php scripts/simular_alteracao.php atualizado
   ```
   Isso troca os CSVs de `moodle_simulado/` pela versão atualizada: datas mudam, uma atividade nova aparece (ARSW) e outra some (PWB1).
3. No sistema, abra **Sincronização → Sincronizar agora** (ou rode `php scripts/sincronizar.php`).
4. Veja: atividades com o selo ↻ e a data anterior, avisos novos, histórico de alterações em cada atividade e o registro da sincronização. O que você marcou como concluído continua marcado.
5. Para voltar ao início: `php scripts/simular_alteracao.php inicial` e sincronize de novo.

O sistema também sincroniza sozinho quando o estudante entra, se a última sincronização tiver mais de `SYNC_INTERVALO_MIN` minutos. Dá para agendar `php scripts/sincronizar.php` (cron / Agendador de Tarefas do Windows).

## Importar atividades de um PDF

Em **Sincronização → Importar atividades de um PDF**:

1. Escolha a disciplina (só aparecem as que você cursa), informe o ano (usado em datas como `12/03`, sem ano) e selecione o PDF.
2. O PDF é lido **no seu navegador** (pdf.js, via CDN). Aparece uma prévia com as linhas que têm data. Provas, entregas e trabalhos já vêm marcados; aulas e outros itens ficam desmarcados.
3. Clique em **Importar selecionadas**. O servidor valida de novo e grava.

Como o PDF é interpretado: cada linha com data (`25/11/2026`, `25/11/26`, `25/11`, `2026-11-25`, `25 de novembro de 2026`) vira uma atividade; horário (`19:00`, `19h`, `19h30`) é opcional (sem horário = 23:59); a primeira coluna de texto é o título e as demais, a descrição; o tipo (Prova, Entrega, Trabalho, Outro) vem de palavras-chave. Linhas com data inexistente (ex.: 31/02) são ignoradas e avisadas.

Regras: importar o mesmo PDF de novo não duplica (o código `PDF-xxxxxxxx` vem do título) e, se a data mudou, a atividade é atualizada com histórico e aviso. A importação **nunca remove** atividades, e a sincronização com o Moodle **nunca remove** o que veio de PDF. PDF escaneado (só imagem) não funciona: precisa de OCR antes. A coluna `atividade.origem` é criada automaticamente em bancos antigos.

## Formato da "tabela de planejamento" (CSV por disciplina)

Um arquivo por disciplina em `moodle_simulado/`, nomeado com o código (`ARSW.csv`). Separador `;`, UTF-8, uma atividade por linha:

```
#nome=Arquitetura de Software
#professor=Profa. Helena Carvalho
codigo_atv;titulo;tipo;data_hora;descricao
ARSW-01;Entrega 2 — Decisões Arquiteturais;Entrega;23/11/2026 23:59;Documento com estilos...
```

- `codigo_atv` identifica a linha de forma estável. É ele que permite detectar que uma atividade **mudou** (em vez de criar duplicata).
- `data_hora`: `dd/mm/aaaa hh:mm` ou `aaaa-mm-dd hh:mm`, horário de Brasília (só a data = 23:59).
- Disciplinas que ainda não existem são criadas pela sincronização.

**Regras de segurança dos dados (RNF05/RNF11):** reimportar o mesmo arquivo não duplica nem notifica; linhas inválidas são ignoradas e relatadas; arquivo ilegível ou sem nenhuma atividade válida **não apaga nada** (a falha é registrada); atividade que some do planejamento é só marcada como removida (e volta se reaparecer); só se marca remoção quando o arquivo foi lido sem erros.

## Integração com o Moodle real

Hoje a fonte são CSVs (simulando a exportação da tabela). Integrar com o Moodle da UTFPR depende de forma **autorizada** de acesso (Web Services, exportação iCal etc.). Para isso, troque `listarPlanejamentosMoodle()` e `lerPlanejamentoCsv()` em `app/moodle.php` por funções que devolvam a mesma estrutura (`meta`, `itens`, `erros`); o restante do sistema não muda.

## Scripts úteis

| Comando | O que faz |
|---|---|
| `php scripts/instalar.php [--recriar]` | cria o banco, sincroniza e cria as contas de demonstração |
| `php scripts/sincronizar.php` | sincroniza com o planejamento agora |
| `php scripts/simular_alteracao.php inicial\|atualizado` | simula o professor editando o planejamento |
| `php scripts/criar_aluno.php "Nome" email [senha]` | cria um estudante |
| `php scripts/matricular.php email COD [COD...]` | matricula um estudante em disciplinas |
| `sh tests/validar_sintaxe.sh` | valida PHP/JS e roda o teste do leitor de planejamento |
| `php tests/testar_planejamentos.php` | testa o leitor de CSV, sem banco |
| `node tests/testar_pdf.js` | testa o interpretador de PDF, sem banco nem navegador |

## Estrutura

```
public/            raiz do servidor (index.html, css/, js/app.js, api/)
  api/auth/        login, logout, me, csrf
  api/disciplinas  GET  disciplinas do estudante
  api/atividades   GET  atividades | PATCH marcar/desmarcar concluída
  api/historico    GET  histórico de alterações de uma atividade
  api/notificacoes GET  avisos (gera lembretes) | PATCH marcar como lido
  api/perfil       GET/PATCH  nome e preferências de lembretes
  api/sincronizacao GET status/histórico | POST sincronizar agora
  api/importacao-pdf POST importar atividades escolhidas na prévia do PDF
app/               regras do sistema (fora de public/, não acessível pelo navegador)
database/          schema.sql, drop.sql
moodle_simulado/   planejamento "vindo do Moodle" (CSV por disciplina)
exemplos/          planejamento-inicial/ e planejamento-atualizado/
scripts/ tests/    utilitários
```

## O que mudou em relação à versão anterior

- **Removido:** perfil de professor, criação/edição/exclusão de atividades, rascunho/publicado, importação de CSV/ICS pelo navegador, dados de exemplo no front e a duplicata do protótipo na raiz.
- **Banco:** agora é um único `database/schema.sql`. O estudante guarda as credenciais (sem `conta`/`professor`); novas tabelas `atividade_concluida` (progresso do aluno) e colunas `data_anterior`, `alterada_em`, `removida_em` em `atividade`; `sincronizacao` e `historico_alteracao` detalhadas; preferências de lembrete no estudante (a tabela `lembrete` foi substituída por elas).
- **Novo:** importação de atividades por PDF (lida no navegador, com prévia), sincronização com o Moodle, marcação de concluídas, avisos de atividade nova/removida/alterada, preferências de lembrete salvas no servidor.
- Corrigido: calendário voltou ao português, avisos usam chave única por evento, `CAST` nos parâmetros SQL.

## Limitações conhecidas

- Os lembretes de prazo são gerados quando o estudante abre os avisos (não há envio por e-mail/push).
- A matrícula é feita por script (`matricular.php`); o Moodle real poderia fornecê-la.
- O limite de tentativas de login é por sessão; em produção use também limite por IP e HTTPS.
- As contas de demonstração e o `.env` são apenas para uso local: **não publique o `.env`**.
