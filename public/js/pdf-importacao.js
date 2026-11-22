/* =========================================================
   Importação de atividades a partir de PDF.

   1) extrairLinhas(arquivo): lê o PDF no navegador (pdf.js) e devolve as linhas de texto.
   2) interpretarLinhas(linhas, ano): procura datas e monta a lista de atividades.

   O PDF nunca é enviado ao servidor: só as atividades escolhidas na prévia.
   ========================================================= */
(function (raiz) {
  'use strict';

  const PDFJS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
  const PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  const MESES = {
    janeiro: 1, fevereiro: 2, marco: 3, abril: 4, maio: 5, junho: 6,
    julho: 7, agosto: 8, setembro: 9, outubro: 10, novembro: 11, dezembro: 12
  };
  const MESES_ABREV = { jan: 1, fev: 2, mar: 3, abr: 4, mai: 5, jun: 6, jul: 7, ago: 8, set: 9, out: 10, nov: 11, dez: 12 };

  const semAcento = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const pad = n => String(n).padStart(2, '0');

  /* ---------- 1. Leitura do PDF ---------- */
  function carregarPdfJs() {
    if (raiz.pdfjsLib) return Promise.resolve(raiz.pdfjsLib);
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = PDFJS_URL;
      s.onload = () => {
        if (!raiz.pdfjsLib) return reject(new Error('A biblioteca de leitura de PDF não carregou.'));
        raiz.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER;
        resolve(raiz.pdfjsLib);
      };
      s.onerror = () => reject(new Error('Não foi possível carregar a biblioteca de leitura de PDF (verifique a internet).'));
      document.head.appendChild(s);
    });
  }

  /** Agrupa os pedaços de texto de uma página em linhas (mesma altura = mesma linha). */
  function linhasDaPagina(itens) {
    const pedacos = itens
      .filter(i => i.str && i.str.trim() !== '')
      .map(i => ({ x: i.transform[4], y: i.transform[5], w: i.width || 0, txt: i.str }));
    pedacos.sort((a, b) => (Math.abs(a.y - b.y) > 3 ? b.y - a.y : a.x - b.x));

    const linhas = [];
    let atual = null;
    for (const p of pedacos) {
      if (atual && Math.abs(atual.y - p.y) <= 3) {
        atual.itens.push(p);
      } else {
        atual = { y: p.y, itens: [p] };
        linhas.push(atual);
      }
    }
    return linhas.map(l => {
      l.itens.sort((a, b) => a.x - b.x);
      let texto = '';
      let fim = null;
      for (const p of l.itens) {
        if (fim !== null) texto += (p.x - fim > 12 ? '  ' : (p.x - fim > 1 ? ' ' : ''));
        texto += p.txt;
        fim = p.x + p.w;
      }
      return texto.replace(/[ \t]{3,}/g, '  ').trim();
    }).filter(Boolean);
  }

  async function extrairLinhas(arquivo) {
    if (!arquivo || arquivo.size === 0) throw new Error('O arquivo está vazio.');
    if (arquivo.size > 15 * 1024 * 1024) throw new Error('O PDF é muito grande (máximo 15 MB).');
    const pdfjs = await carregarPdfJs();
    const dados = new Uint8Array(await arquivo.arrayBuffer());
    if (String.fromCharCode(...dados.slice(0, 4)) !== '%PDF') throw new Error('Este arquivo não parece ser um PDF.');

    let doc;
    try {
      doc = await pdfjs.getDocument({ data: dados }).promise;
    } catch (e) {
      if (e && e.name === 'PasswordException') throw new Error('O PDF está protegido por senha.');
      throw new Error('Não foi possível abrir o PDF (arquivo corrompido?).');
    }
    const linhas = [];
    const paginas = Math.min(doc.numPages, 60);
    for (let n = 1; n <= paginas; n++) {
      const pagina = await doc.getPage(n);
      const conteudo = await pagina.getTextContent();
      linhas.push(...linhasDaPagina(conteudo.items));
    }
    if (linhas.length === 0) {
      throw new Error('Não encontrei texto neste PDF. Se for um documento escaneado (imagem), ele precisa de OCR antes de ser importado.');
    }
    return linhas;
  }

  /* ---------- 2. Interpretação das linhas ---------- */
  const RE_DATA_COMPLETA = /(?<!\d)(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*(\d{4}|\d{2})(?!\d)/;
  const RE_DATA_ISO = /(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)/;
  const RE_DATA_CURTA = /(?<![\d\/.])(\d{1,2})\/(\d{1,2})(?![\d\/])/;
  const RE_DATA_EXTENSO = /(?<!\d)(\d{1,2})\s*(?:º|°)?\s*(?:de\s+)?(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\s+de\s+(\d{4}))?/i;
  const RE_HORA = /(?<![\d\/.])([01]?\d|2[0-3])\s*(?::\s*([0-5]\d)|h\s*([0-5]\d)?)(?![\p{L}\d])/iu;

  const TIPOS = [
    ['Prova', /\b(provas?|p[1-4]|avalia[cç][aã]o|exame|testes?|quiz|recupera[cç][aã]o)\b/i],
    ['Entrega', /\b(entregas?|entregar|submiss[aã]o|enviar|envio|upload|relat[oó]rio|prazo|devolu[cç][aã]o)\b/i],
    ['Trabalho', /\b(trabalhos?|projetos?|semin[aá]rio|apresenta[cç][aã]o|lista de exerc[ií]cios|exerc[ií]cios?|atividade|tarefa|laborat[oó]rio)\b/i],
    ['Outro', /\b(reuni[aã]o|palestra|visita|evento|orienta[cç][aã]o)\b/i]
  ];

  function acharData(texto, anoPadrao) {
    let m, dia, mes, ano, trecho;
    if ((m = RE_DATA_ISO.exec(texto))) {
      [ano, mes, dia] = [+m[1], +m[2], +m[3]];
    } else if ((m = RE_DATA_COMPLETA.exec(texto))) {
      [dia, mes, ano] = [+m[1], +m[2], +m[3]];
      if (m[3].length === 2) ano += 2000;
    } else if ((m = RE_DATA_EXTENSO.exec(texto))) {
      dia = +m[1];
      mes = MESES[semAcento(m[2].toLowerCase())];
      ano = m[3] ? +m[3] : anoPadrao;
    } else if ((m = RE_DATA_CURTA.exec(texto))) {
      [dia, mes, ano] = [+m[1], +m[2], anoPadrao];
    } else {
      return null;
    }
    trecho = m[0];
    const d = new Date(ano, mes - 1, dia);
    if (d.getFullYear() !== ano || d.getMonth() !== mes - 1 || d.getDate() !== dia) return { invalida: true, trecho }; // ex.: 31/02
    return { dia, mes, ano, trecho };
  }

  function detectarTipo(texto) {
    for (const [tipo, re] of TIPOS) if (re.test(texto)) return tipo;
    return null;
  }

  function limparTitulo(texto) {
    return texto
      .replace(/\(\s*\)/g, ' ')
      .replace(/\b(?:às|as|até|ate|dia|data|prazo)\s*[:\-]?\s*$/i, ' ')
      .replace(/^[\s|;:,.\-–—•*]+|[\s|;:,.\-–—•*]+$/g, '')
      .replace(/\s{2,}/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function chaveTitulo(titulo) {
    return semAcento(titulo.toLowerCase()).replace(/[^a-z0-9]+/g, '');
  }

  // Hash curto e estável (FNV-1a) para o código da atividade: mesmo título => mesmo código.
  function hashCurto(texto) {
    let h = 0x811c9dc5;
    for (let i = 0; i < texto.length; i++) {
      h ^= texto.charCodeAt(i);
      h = Math.imul(h, 0x01000193) >>> 0;
    }
    return h.toString(16).padStart(8, '0');
  }

  /**
   * Transforma as linhas do PDF em atividades.
   * Cada linha com data inicia uma atividade; linhas seguintes sem data viram descrição.
   */
  function interpretarLinhas(linhas, anoPadrao) {
    const itens = [];
    const ignoradas = [];
    const usados = new Map();
    let atual = null;
    let pendenteTitulo = false;

    for (const bruta of linhas) {
      const linha = bruta.replace(/[ \t]{3,}/g, '  ').trim(); // 2 espaços = separador de colunas
      if (!linha) continue;

      const data = acharData(linha, anoPadrao);
      if (data && data.invalida) {
        ignoradas.push(`${linha.slice(0, 80)} (data inexistente)`);
        atual = null;
        continue;
      }
      if (!data) {
        if (atual) {
          if (pendenteTitulo) {
            let texto = linha;
            const hp = RE_HORA.exec(texto);
            if (hp && !atual.temHora) {
              atual.data = atual.data.slice(0, 11) + pad(+hp[1]) + ':' + pad(+(hp[2] || hp[3] || 0));
              atual.temHora = true;
              const pre = /(?<![\p{L}])(?:às|as|até|ate)\s+$/iu.exec(texto.slice(0, hp.index));
              texto = texto.slice(0, pre ? pre.index : hp.index) + ' ' + texto.slice(hp.index + hp[0].length);
            }
            atual.titulo = limparTitulo(texto.split(/\s{2,}/)[0]).slice(0, 150);
            pendenteTitulo = false;
          } else if (atual.descricao.length < 600 && atual.linhasExtras < 4) {
            atual.descricao += (atual.descricao ? ' ' : '') + linha.replace(/\s{2,}/g, ' ');
            atual.linhasExtras++;
          }
        }
        continue;
      }

      let resto = linha.replace(data.trecho, '  ');
      let hora = 23, minuto = 59, temHora = false;
      const h = RE_HORA.exec(resto);
      if (h) {
        hora = +h[1];
        minuto = +(h[2] || h[3] || 0);
        temHora = true;
        const prefixo = /(?<![\p{L}])(?:às|as|até|ate)\s+$/iu.exec(resto.slice(0, h.index));
        resto = resto.slice(0, prefixo ? prefixo.index : h.index) + ' ' + resto.slice(h.index + h[0].length);
      }
      // Segunda data na mesma linha (ex.: "10/11 a 14/11"): ignorada, fica só a primeira.
      const segunda = acharData(resto, anoPadrao);
      if (segunda && !segunda.invalida) resto = resto.replace(segunda.trecho, ' ').replace(/\s+(?:a|à|até|e)\s+(?=\s|$)/gi, ' ');

      // Colunas do PDF: a primeira com texto é o título; as demais viram descrição.
      const colunas = resto.split(/\s{2,}/).map(limparTitulo).filter(Boolean);
      const titulo = (colunas[0] || '').slice(0, 150);
      const tipo = detectarTipo(titulo) || detectarTipo(linha);

      atual = {
        titulo,
        tipo: tipo || 'Outro',
        reconhecida: tipo !== null,
        data: `${data.ano}-${pad(data.mes)}-${pad(data.dia)}T${pad(hora)}:${pad(minuto)}`,
        temHora,
        descricao: colunas.slice(1).join(' · '),
        linhasExtras: 0,
        linhaOriginal: linha.replace(/\s{2,}/g, ' ')
      };
      pendenteTitulo = titulo === '';
      itens.push(atual);
    }

    // Finalização: título, tipo pelo texto completo, código estável.
    const resultado = [];
    for (const it of itens) {
      if (!it.titulo) it.titulo = it.descricao.slice(0, 150) || `Atividade de ${it.data.slice(8, 10)}/${it.data.slice(5, 7)}`;
      if (!it.reconhecida) {
        const t = detectarTipo(it.titulo + ' ' + it.descricao);
        if (t) { it.tipo = t; it.reconhecida = true; }
      }
      const base = 'PDF-' + hashCurto(chaveTitulo(it.titulo));
      const n = (usados.get(base) || 0) + 1;
      usados.set(base, n);
      resultado.push({
        codigo_atv: n === 1 ? base : `${base}-${n}`,
        titulo: it.titulo,
        tipo: it.tipo,
        data_hora: it.data,
        descricao: it.descricao.slice(0, 2000),
        sugerida: it.reconhecida,
        temHora: it.temHora,
        linhaOriginal: it.linhaOriginal
      });
    }
    return { itens: resultado, ignoradas };
  }

  const api = { extrairLinhas, interpretarLinhas };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  raiz.PdfImportacao = api;
})(typeof window !== 'undefined' ? window : globalThis);
