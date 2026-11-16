# Meu Calendário Acadêmico — Front end (protótipo)

Projeto da disciplina de Arquitetura de Software (UTFPR Dois Vizinhos).
Tecnologias: HTML, CSS, Bootstrap 5, JavaScript e FullCalendar 6 (via CDN).

## Como executar
Abra `index.html` no navegador (precisa de internet para carregar Bootstrap/FullCalendar).
Ou, na pasta do projeto: `php -S localhost:8000` e acesse http://localhost:8000.
Login de teste: e-mail institucional (@utfpr.edu.br ou @alunos.utfpr.edu.br) + senha com 4 ou mais caracteres. Outros domínios são bloqueados. O sistema entra com um perfil genérico ("Estudante Exemplo"); todos os nomes de professores são fictícios.

## Funcionalidades (e histórias de usuário)
1. Login e perfil — HU1, HU2
2. Disciplinas e atividades por disciplina — HU3, HU4
3. Calendário (mês/semana/dia/lista) com filtros por disciplina e tipo, detalhes e prazos — HU5, HU6, HU7
4. Sincronização: simulação do portal, importação de .pdf, .csv e .ics, sem duplicar, histórico e falhas — HU8, HU9, HU10
5. Lembretes e avisos de alteração de data, com preferências — HU11, HU12
6. Cadastro manual de atividades

7. Editar atividades, marcar como concluída (na lista ou nos detalhes) e reabrir
8. Modo claro e escuro (botão no topo; lembra a escolha)

## Importar planejamento
Aceita PDF com texto selecionável: cada linha com uma data vira uma atividade, ex.: `02/12/2026 19:00 - Prova 2 - Camada de transporte`.
A disciplina é detectada pelo nome ou código no arquivo (ou escolhida na lista).
PDFs de exemplo em `exemplos/` (um por disciplina): importe primeiro `1-planejamento-inicial` (cria atividades e muda datas das existentes) e depois `2-planejamento-atualizado` (datas alteradas → avisos e marcação "alterada"; reimportar o mesmo arquivo não duplica).

## Estrutura
- index.html — telas e modais
- css/style.css — design (responsivo, RNF09)
- js/data.js — dados de exemplo (espelham o modelo do banco: Disciplina, Atividade, Notificação, Sincronização, Histórico)
- exemplos/ — PDF de exemplo para testar a importação
- js/app.js — lógica; dados salvos no localStorage

## Integração futura
Trocar as funções de `salvar/carregar` por chamadas `fetch` à API PHP (PostgreSQL).
