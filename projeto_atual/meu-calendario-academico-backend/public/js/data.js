/* ===== Dados de exemplo (mock) =====
   Em produção, estes dados viriam da API PHP / PostgreSQL.
   Os nomes seguem o modelo do banco do grupo: DISCIPLINA, ATIVIDADE, NOTIFICACAO,
   SINCRONIZACAO, HISTORICO_ALTERACAO e ESTUDANTE.
   Todas as pessoas abaixo são FICTÍCIAS. */

const VERSAO_DADOS = 2;

function dtLocal(diasAPartirDeHoje, hora = 23, min = 59) {
  const d = new Date();
  d.setDate(d.getDate() + diasAPartirDeHoje);
  d.setHours(hora, min, 0, 0);
  const p = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

function criarDadosIniciais() {
  return {
    versao: VERSAO_DADOS,
    // usuário genérico
    estudante: { nome: 'Estudante Exemplo', email: 'estudante.exemplo@alunos.utfpr.edu.br', curso: 'Engenharia de Software', semestre: '2026/2', logado: false },
    config: { lembretes: true, antecedenciaDias: 3, avisosAlteracao: true },
    disciplinas: [
      { id: 1, nome: 'Algoritmos e Estrutura de Dados 2',  codigo: 'AED2', professor: 'Prof. Otávio Brandão',     cor: '#5b4fe6' },
      { id: 2, nome: 'Arquitetura de Software',            codigo: 'ARSW', professor: 'Profa. Helena Carvalho',   cor: '#e0559c' },
      { id: 3, nome: 'Ética, Normas e Postura Profissional', codigo: 'ENPP', professor: 'Prof. Marcos Teixeira',  cor: '#f08c2e' },
      { id: 4, nome: 'Programação para Web 1',             codigo: 'PWB1', professor: 'Profa. Luíza Andrade',     cor: '#1fa37a' },
      { id: 5, nome: 'Redes de Computadores',              codigo: 'RDCP', professor: 'Prof. Ricardo Menezes',    cor: '#2b8de0' },
      { id: 6, nome: 'Sistemas Operacionais',              codigo: 'SOPE', professor: 'Profa. Camila Furtado',    cor: '#8a5cf0' }
    ],
    atividades: [
      { id: 1,  disciplinaId: 1, titulo: 'Lista de exercícios — Árvores',         tipo: 'Trabalho', data: dtLocal(2, 23, 59),  descricao: 'Árvores binárias de busca e percursos.', alterada: false },
      { id: 2,  disciplinaId: 1, titulo: 'Prova 1 — Estruturas não lineares',     tipo: 'Prova',    data: dtLocal(10, 19, 0),  descricao: 'Árvores, heaps e grafos básicos.', alterada: false },
      { id: 3,  disciplinaId: 2, titulo: 'Entrega 2 — Decisões Arquiteturais',    tipo: 'Entrega',  data: dtLocal(1, 23, 59),  descricao: 'Documento com estilos arquiteturais, justificativas e atributos de qualidade.', alterada: false },
      { id: 4,  disciplinaId: 2, titulo: 'Prova 1 — Padrões e estilos',           tipo: 'Prova',    data: dtLocal(9, 19, 0),   descricao: 'Cliente-servidor, camadas, MVC e microsserviços.', alterada: false },
      { id: 5,  disciplinaId: 3, titulo: 'Seminário — Ética na computação',       tipo: 'Trabalho', data: dtLocal(6, 20, 40),  descricao: 'Apresentação em grupo de 15 minutos.', alterada: false },
      { id: 6,  disciplinaId: 3, titulo: 'Resenha — Código de ética profissional', tipo: 'Entrega', data: dtLocal(14, 23, 59), descricao: 'Texto de 2 páginas.', alterada: false },
      { id: 7,  disciplinaId: 4, titulo: 'Projeto front end — Sprint 1',          tipo: 'Entrega',  data: dtLocal(5, 23, 59),  descricao: 'HTML, CSS, Bootstrap e JavaScript.', alterada: false },
      { id: 8,  disciplinaId: 4, titulo: 'Prova de JavaScript',                   tipo: 'Prova',    data: dtLocal(18, 21, 0),  descricao: 'DOM, eventos, fetch e localStorage.', alterada: false },
      { id: 9,  disciplinaId: 5, titulo: 'Laboratório — Sub-redes e roteamento',  tipo: 'Trabalho', data: dtLocal(3, 18, 30),  descricao: 'Relatório do laboratório de endereçamento IP.', alterada: false },
      { id: 10, disciplinaId: 5, titulo: 'Prova 1 — Camadas de rede',             tipo: 'Prova',    data: dtLocal(12, 20, 40), descricao: 'Modelo OSI, TCP/IP, endereçamento e roteamento.', alterada: false },
      { id: 11, disciplinaId: 6, titulo: 'Trabalho — Escalonamento de processos', tipo: 'Trabalho', data: dtLocal(7, 17, 0),   descricao: 'Simulador de algoritmos de escalonamento.', alterada: false },
      { id: 12, disciplinaId: 6, titulo: 'Prova 1 — Processos e threads',         tipo: 'Prova',    data: dtLocal(21, 19, 0),  descricao: 'Processos, threads, sincronização e deadlock.', alterada: false },
      { id: 13, disciplinaId: 2, titulo: 'Reunião de orientação do projeto',      tipo: 'Outro',    data: dtLocal(0, 21, 0),   descricao: 'Alinhamento do projeto com a professora.', alterada: false }
    ],
    notificacoes: [],
    sincronizacoes: [
      { id: 1, data: new Date(Date.now() - 86400000).toISOString(), resultado: 'sucesso', detalhes: 'Importação inicial: 13 atividades.' }
    ],
    historico: []
  };
}
