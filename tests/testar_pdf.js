// Teste do interpretador de PDF (sem navegador e sem banco). Uso: node tests/testar_pdf.js
const P = require('../public/js/pdf-importacao.js');
let falhas = 0;
const ok = (cond, desc) => { console.log((cond ? 'OK    ' : 'ERRO  ') + desc); if (!cond) falhas++; };

const linhas = [
  'Planejamento ARSW 2026/2',
  'Data  Atividade  Observações',
  '25/11/2026  Entrega 2 - Decisões Arquiteturais  Até 23:59 pelo Moodle',
  '30/11/2026 19:00  Prova 1 - Padrões e estilos  Cliente-servidor, camadas',
  '02/12  Aula expositiva: MVC',
  '11 de dezembro de 2026  Trabalho final - Protótipo  Entrega às 21h30',
  '18/12/26  Prova 2',
  '31/02/2026  Data impossível',
  '05/03/2026',
  'Seminário de Redes às 19h30',
  'Grupos de 3 alunos',
  '30/11/2026 19:00  Prova 1 - Padrões e estilos'
];
const { itens, ignoradas } = P.interpretarLinhas(linhas, 2026);

ok(itens.length === 7, 'encontra as 7 linhas com data válida (' + itens.length + ')');
ok(ignoradas.length === 1, 'data inexistente (31/02) é ignorada e relatada');
ok(itens[0].data_hora === '2026-11-25T23:59' && itens[0].tipo === 'Entrega', 'dd/mm/aaaa, tipo Entrega, 23:59 por padrão');
ok(itens[1].data_hora === '2026-11-30T19:00' && itens[1].tipo === 'Prova', 'data com horário e tipo Prova');
ok(itens[1].titulo === 'Prova 1 - Padrões e estilos' && itens[1].descricao === 'Cliente-servidor, camadas', 'colunas: título e descrição separados');
ok(itens[2].data_hora === '2026-12-02T23:59' && !itens[2].sugerida, 'dd/mm usa o ano informado; aula não vem marcada');
ok(itens[3].data_hora === '2026-12-11T21:30' && itens[3].tipo === 'Trabalho', 'data por extenso + horário 21h30');
ok(itens[4].data_hora === '2026-12-18T23:59', 'ano com 2 dígitos');
ok(itens[5].titulo === 'Seminário de Redes' && itens[5].data_hora === '2026-03-05T19:30', 'título e horário na linha seguinte à data');
ok(itens[5].codigo_atv !== itens[1].codigo_atv, 'códigos diferentes para títulos diferentes');
ok(itens[6].codigo_atv === itens[1].codigo_atv + '-2', 'título repetido recebe sufixo -2');
ok(itens.every(i => /^PDF-[A-Za-z0-9._-]{1,46}$/.test(i.codigo_atv)), 'todos os códigos seguem o formato aceito pelo servidor');
const novamente = P.interpretarLinhas(linhas, 2026).itens;
ok(JSON.stringify(novamente) === JSON.stringify(itens), 'mesmo PDF gera os mesmos códigos (reimportar não duplica)');
console.log(falhas === 0 ? '\nTudo certo.' : `\n${falhas} verificação(ões) falharam.`);
process.exit(falhas === 0 ? 0 : 1);
