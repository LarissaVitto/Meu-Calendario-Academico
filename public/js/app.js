/* ===== Meu Calendário Acadêmico — front end =====
   O professor altera o planejamento no Moodle; o servidor sincroniza essas mudanças
   (PHP + PostgreSQL) e o estudante consulta o calendário e marca o que já fez.
   Funcionalidades:
   1) Login e perfil do estudante                (HU1, HU2)
   2) Disciplinas e atividades por disciplina    (HU3, HU4)
   3) Calendário com visões e filtros            (HU5, HU6, HU7)
   4) Sincronização com o planejamento do Moodle (HU8, HU9, HU10)
   5) Lembretes e avisos de alteração            (HU11, HU12)
   6) Marcar atividades como concluídas
   Todos os dados vêm da API; o navegador só guarda o tema (claro/escuro). */

(() => {
  'use strict';

  /* ---------- Utilidades ---------- */
  const $  = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const MS_DIA = 86400000;
  const cores = ['#5b4fe6', '#e0559c', '#1fa37a', '#2b8de0', '#f08c2e', '#8a5cf0'];

  function normalizarData(valor) {
    if (!valor) return null;
    const d = new Date(valor);
    return Number.isNaN(d.getTime()) ? null : d;
  }
  const fmtData = v => { const d = normalizarData(v); return d ? d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }).replace('.', '') : 'Data indisponível'; };
  const fmtHora = v => { const d = normalizarData(v); return d ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '--:--'; };
  const fmtCompleta = v => {
    const d = normalizarData(v);
    if (!d) return 'Data indisponível';
    return d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' }) + ' às ' + fmtHora(v);
  };
  const fmtDataHoraISO = v => { const d = normalizarData(v); return d ? d.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'Data indisponível'; };
  const diasRestantes = valor => {
    const alvo = normalizarData(valor);
    if (!alvo) return null;
    const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
    alvo.setHours(0, 0, 0, 0);
    return Math.round((alvo - hoje) / MS_DIA);
  };
  const iniciais = nome => String(nome || '?').trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase();

  /* ---------- Estado ---------- */
  const dadosVazios = () => ({
    estudante: { nome: '', email: '', curso: '', preferencias: { lembretes_ativos: true, antecedencia_dias: 3, avisos_alteracao: true } },
    disciplinas: [], atividades: [], notificacoes: [], sync: null
  });
  let DB = dadosVazios();
  let usuarioAtual = null;
  let csrfAtual = null;
  let viewAtual = 'home';
  let calendario = null;
  let sincronizando = false;
  const filtros = { disciplinas: new Set(), tipo: 'todos', ocultarConcluidas: false };

  const disc = id => DB.disciplinas.find(d => d.id === id) || { id, nome: 'Disciplina', codigo: '?', professor: '', cor: cores[0] };
  const atv  = id => DB.atividades.find(a => a.id === id);
  const futuras = () => {
    const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
    return DB.atividades
      .filter(a => { const d = normalizarData(a.data); return !a.concluida && d !== null && d >= hoje; })
      .sort((a, b) => normalizarData(a.data) - normalizarData(b.data));
  };

  /* ---------- Toast ---------- */
  function toast(msg, tipo = 'ok') {
    const el = $('#toast');
    el.className = 'toast align-items-center border-0 text-bg-' + (tipo === 'erro' ? 'danger' : 'dark');
    $('#toastMsg').textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  }

  /* ---------- Modo claro / escuro ---------- */
  function aplicarTema(t) {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
    $$('.js-tema i').forEach(i => i.className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars');
    try { localStorage.setItem('mca_tema', t); } catch (e) { /* ignora */ }
  }
  document.addEventListener('click', e => {
    if (e.target.closest('.js-tema')) aplicarTema(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
  });
  aplicarTema(document.documentElement.getAttribute('data-theme') || 'light');

  /* ---------- Comunicação com a API ---------- */
  async function api(url, options = {}) {
    const resposta = await fetch(url, {
      credentials: 'same-origin',
      ...options,
      headers: { Accept: 'application/json', ...options.headers }
    });
    const dados = await resposta.json().catch(() => ({}));
    if (!resposta.ok) {
      const erro = new Error(dados.mensagem || `Erro HTTP ${resposta.status}`);
      erro.status = resposta.status;
      throw erro;
    }
    return dados;
  }
  async function buscarCsrf() {
    const dados = await api('api/auth/csrf.php');
    csrfAtual = dados.csrf_token;
  }
  // Requisição que altera dados: sempre com o token CSRF da sessão.
  async function enviar(url, metodo, corpo) {
    if (!csrfAtual) await buscarCsrf();
    return api(url, {
      method: metodo,
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfAtual },
      body: JSON.stringify(corpo ?? {})
    });
  }

  /* ---------- Adaptadores (API -> estado do front) ---------- */
  function adaptarDisciplina(d) {
    const id = Number(d.id_disciplina);
    return { id, nome: d.nome, codigo: d.codigo_disc, professor: d.professor || '', cor: cores[id % cores.length] };
  }
  function adaptarAtividade(a) {
    return {
      id: a.id_atividade, disciplinaId: a.id_disciplina, codigo: a.codigo_atv, titulo: a.titulo, descricao: a.descricao || '',
      tipo: a.tipo, data: a.data_hora, dataAnterior: a.data_anterior, atualizadaEm: a.alterada_em,
      alterada: Boolean(a.alterada_em), concluida: a.concluida === true, concluidaEm: a.concluida_em
    };
  }
  const TIPOS_AVISO = {
    LEMBRETE_PRAZO:      ['lembrete',  'Prazo se aproximando', 'bi-alarm'],
    ALTERACAO_DATA:      ['alteracao', 'Data alterada',        'bi-arrow-repeat'],
    ALTERACAO_ATIVIDADE: ['alteracao', 'Atividade atualizada', 'bi-pencil'],
    NOVA_ATIVIDADE:      ['nova',      'Nova atividade',       'bi-plus-circle'],
    ATIVIDADE_REMOVIDA:  ['removida',  'Atividade removida',   'bi-x-circle']
  };
  function adaptarAviso(n) {
    const [classe, titulo, icone] = TIPOS_AVISO[n.tipo] || ['alteracao', 'Aviso', 'bi-bell'];
    return { id: n.id_notificacao, atividadeId: n.id_atividade, classe, titulo, icone, mensagem: n.mensagem, data: n.data_envio, lida: n.visualizacao === true };
  }

  async function carregarAvisos() {
    const r = await api('api/notificacoes/');
    DB.notificacoes = r.notificacoes.map(adaptarAviso);
  }
  async function carregarSync() {
    DB.sync = await api('api/sincronizacao/');
  }
  async function atualizarDadosServidor() {
    const [ds, as] = await Promise.all([api('api/disciplinas/'), api('api/atividades/')]);
    DB.disciplinas = ds.disciplinas.map(adaptarDisciplina);
    DB.atividades = as.atividades.map(adaptarAtividade);
    const ids = new Set(DB.disciplinas.map(d => d.id));
    const anteriores = new Set(filtros.disciplinas);
    filtros.disciplinas = new Set([...anteriores].filter(id => ids.has(id)));
    ids.forEach(id => { if (!anteriores.has(id)) filtros.disciplinas.add(id); });
    await carregarAvisos();
    atualizarTudo();
  }

  /* ---------- Login / sessão (HU1) ---------- */
  function mostrarErroLogin(mensagem) {
    const el = $('#loginErro');
    el.textContent = mensagem;
    el.classList.remove('d-none');
  }
  function mostrarTela() {
    const logado = Boolean(usuarioAtual);
    $('#login').classList.toggle('d-none', logado);
    $('#app').classList.toggle('d-none', !logado);
    if (logado) iniciarApp();
  }
  async function entrar(usuario) {
    usuarioAtual = usuario;
    DB = dadosVazios();
    DB.estudante = usuario;
    filtros.disciplinas = new Set(); filtros.tipo = 'todos'; filtros.ocultarConcluidas = false;
    mostrarTela();
    try {
      await atualizarDadosServidor();
      await carregarSync();
      // Mantém os dados em dia: sincroniza sozinho se a última sincronização estiver velha.
      if (DB.sync.precisa_sincronizar) await executarSync('automatica');
    } catch (erro) {
      toast('Não foi possível carregar o calendário: ' + erro.message, 'erro');
    }
  }
  function sair() {
    usuarioAtual = null; csrfAtual = null; DB = dadosVazios();
    if (calendario) { calendario.destroy(); calendario = null; }
    $('#loginSenha').value = '';
    mostrarTela();
  }

  $('#loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const email = $('#loginEmail').value.trim();
    const senha = $('#loginSenha').value;
    if (!/^[^\s@]+@(alunos\.)?utfpr\.edu\.br$/i.test(email) || !senha) {
      mostrarErroLogin('Informe seu e-mail institucional e sua senha.');
      return;
    }
    const botao = $('#loginForm button[type="submit"]');
    botao.disabled = true;
    $('#loginErro').classList.add('d-none');
    try {
      await buscarCsrf();
      const dados = await api('api/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfAtual },
        body: JSON.stringify({ email, senha })
      });
      csrfAtual = dados.csrf_token;
      entrar(dados.usuario);
    } catch (erro) {
      mostrarErroLogin(erro.message || 'Falha ao entrar no sistema.');
    } finally {
      botao.disabled = false;
    }
  });
  $('#togglePass').addEventListener('click', () => {
    const inp = $('#loginSenha');
    const mostrar = inp.type === 'password';
    inp.type = mostrar ? 'text' : 'password';
    $('#togglePass i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
  $('#btnLogout').addEventListener('click', async () => {
    try { await enviar('api/auth/logout.php', 'POST', {}); sair(); }
    catch (erro) { toast(erro.message || 'Não foi possível encerrar a sessão.', 'erro'); }
  });
  async function restaurarSessao() {
    try {
      const dados = await api('api/auth/me.php');
      entrar(dados.usuario);
    } catch (erro) {
      mostrarTela();
      if (erro.status !== 401) {
        mostrarErroLogin('Não foi possível verificar a sessão. Confirme se o servidor PHP e o PostgreSQL estão ativos.');
      }
    }
  }

  /* ---------- Navegação ---------- */
  const TITULOS = {
    home: ['Início', 'Seu resumo acadêmico'],
    calendario: ['Calendário', 'Todas as atividades em um só lugar'],
    disciplinas: ['Disciplinas', 'Matérias do semestre'],
    sync: ['Sincronização', 'Planejamento das disciplinas no Moodle'],
    notificacoes: ['Avisos', 'Lembretes e alterações'],
    perfil: ['Perfil', 'Seus dados e preferências']
  };
  const RENDER = () => ({ home: renderHome, calendario: renderCalendario, disciplinas: renderDisciplinas, sync: renderSync, notificacoes: renderAvisos, perfil: renderPerfil });
  function ir(view) {
    viewAtual = view;
    $$('.view').forEach(v => v.classList.add('d-none'));
    $('#view-' + view).classList.remove('d-none');
    $$('.nav-item').forEach(b => b.classList.toggle('active', b.dataset.view === view));
    $('#pageTitle').textContent = TITULOS[view][0];
    $('#pageSub').textContent = TITULOS[view][1];
    RENDER()[view]();
    window.scrollTo({ top: 0 });
  }
  $('#nav').addEventListener('click', e => { const b = e.target.closest('.nav-item'); if (b) ir(b.dataset.view); });
  $('#btnBell').addEventListener('click', () => ir('notificacoes'));
  $('#topAvatar').addEventListener('click', () => ir('perfil'));

  /* ---------- Componentes ---------- */
  function badgePrazo(a) {
    const d = diasRestantes(a.data);
    if (d === null) return '<span class="badge-soft badge-tipo">Data indisponível</span>';
    if (a.concluida) return '<span class="badge-soft badge-ok"><i class="bi bi-check2"></i> Concluída</span>';
    if (d < 0)  return '<span class="badge-soft badge-tipo">Encerrada</span>';
    if (d === 0) return '<span class="badge-soft badge-urgente">Hoje</span>';
    if (d <= 2) return `<span class="badge-soft badge-urgente">${d === 1 ? 'Amanhã' : 'em ' + d + ' dias'}</span>`;
    if (d <= 7) return `<span class="badge-soft badge-breve">em ${d} dias</span>`;
    return `<span class="badge-soft badge-ok">em ${d} dias</span>`;
  }
  function itemAtividade(a) {
    const d = disc(a.disciplinaId);
    return `
      <button class="atv-item ${a.concluida ? 'done' : ''}" data-atv="${a.id}">
        <span class="atv-check ${a.concluida ? 'on' : ''}" data-done="${a.id}" role="checkbox" aria-checked="${a.concluida}" title="${a.concluida ? 'Reabrir' : 'Marcar como concluída'}"><i class="bi bi-check-lg"></i></span>
        <span class="atv-bar" style="background:${d.cor}"></span>
        <span class="atv-info">
          <div class="atv-title">${esc(a.titulo)}</div>
          <div class="atv-meta">${esc(d.nome)} · ${fmtData(a.data)}, ${fmtHora(a.data)}</div>
        </span>
        ${a.alterada ? '<span class="badge-soft badge-alt" title="Alterada no planejamento"><i class="bi bi-arrow-repeat"></i></span>' : ''}
        <span class="badge-soft badge-tipo">${esc(a.tipo)}</span>
        ${badgePrazo(a)}
      </button>`;
  }
  // Clique no "check" marca/desmarca; clique no resto abre os detalhes (HU7).
  document.addEventListener('click', e => {
    const chk = e.target.closest('[data-done]');
    if (chk) { e.preventDefault(); e.stopPropagation(); alternarConcluida(Number(chk.dataset.done)); return; }
    const b = e.target.closest('[data-atv]');
    if (b) abrirAtividade(Number(b.dataset.atv));
  });

  /* ---------- Início ---------- */
  function renderHome() {
    const prox = futuras();
    const prox7 = prox.filter(a => diasRestantes(a.data) <= 7);
    const provas = prox7.filter(a => a.tipo === 'Prova').length;
    const alteradas = prox.filter(a => a.alterada).length;
    const primeiro = (DB.estudante.nome || '').split(' ')[0];
    const ultima = DB.sync?.ultima;

    const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
    let faixa = '';
    for (let i = 0; i < 7; i++) {
      const dia = new Date(hoje.getTime() + i * MS_DIA);
      const doDia = DB.atividades.filter(a => diasRestantes(a.data) === i);
      const dots = doDia.slice(0, 3).map(a => `<i style="background:${disc(a.disciplinaId).cor}"></i>`).join('');
      faixa += `<button class="day-pill ${i === 0 ? 'today' : ''}" data-ir-cal="${dia.toISOString()}">
        <small>${dia.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '')}</small>
        <span class="n">${dia.getDate()}</span><span class="dots">${dots}</span></button>`;
    }

    $('#view-home').innerHTML = `
      <div class="hero mb-4">
        <h3>Olá, ${esc(primeiro)}! 👋</h3>
        <p>${prox7.length ? `Você tem <b>${prox7.length}</b> compromisso(s) nos próximos 7 dias.` : 'Nenhum compromisso nos próximos 7 dias. Aproveite!'}</p>
        <p class="small mb-0" style="opacity:.85"><i class="bi bi-arrow-repeat"></i> ${ultima ? `Planejamento do Moodle sincronizado em ${fmtDataHoraISO(ultima.data)}.` : 'Ainda não há sincronização com o Moodle.'}</p>
      </div>

      <div class="row g-3 mb-4">
        ${[
          ['bi-calendar-event', 's-primary', prox7.length, 'Próximos 7 dias'],
          ['bi-journal-check', 's-red', provas, 'Provas na semana'],
          ['bi-arrow-repeat', 's-amber', alteradas, 'Datas/atividades alteradas'],
          ['bi-bell', 's-green', naoLidas(), 'Avisos novos']
        ].map(([ic, bg, n, l]) => `
          <div class="col-6 col-xl-3"><div class="card-soft stat">
            <div class="stat-ico ${bg}"><i class="bi ${ic}"></i></div>
            <div><div class="stat-num">${n}</div><div class="stat-lbl">${l}</div></div>
          </div></div>`).join('')}
      </div>

      <div class="row g-3">
        <div class="col-xl-7">
          <div class="card-soft h-100">
            <div class="section-title">Próximos prazos <button class="link-btn" data-ir="calendario">Ver calendário</button></div>
            ${prox.slice(0, 6).map(itemAtividade).join('') || '<p class="text-muted">Nenhuma atividade futura.</p>'}
          </div>
        </div>
        <div class="col-xl-5">
          <div class="card-soft mb-3">
            <div class="section-title">Esta semana</div>
            <div class="week-strip">${faixa}</div>
          </div>
          <div class="card-soft">
            <div class="section-title">Avisos recentes <button class="link-btn" data-ir="notificacoes">Ver todos</button></div>
            ${DB.notificacoes.slice(0, 3).map(n => itemAviso(n, true)).join('') || '<p class="text-muted mb-0">Sem avisos por enquanto.</p>'}
          </div>
        </div>
      </div>`;
  }
  document.addEventListener('click', e => {
    const go = e.target.closest('[data-ir]');
    if (go) ir(go.dataset.ir);
    const pill = e.target.closest('[data-ir-cal]');
    if (pill) { ir('calendario'); calendario.changeView('timeGridDay', pill.dataset.irCal); }
  });

  /* ---------- Disciplinas (HU3, HU4) ---------- */
  function renderDisciplinas() {
    $('#view-disciplinas').innerHTML = (DB.disciplinas.length ? '<div class="row g-3">' : '<p class="text-muted">Você ainda não está matriculado em nenhuma disciplina.</p><div class="row g-3">') + DB.disciplinas.map(d => {
      const lista = DB.atividades.filter(a => a.disciplinaId === d.id);
      const feitas = lista.filter(a => a.concluida).length;
      const pend = lista.filter(a => !a.concluida && normalizarData(a.data) >= new Date());
      const pct = lista.length ? Math.round((feitas / lista.length) * 100) : 0;
      const proxima = pend.sort((a, b) => normalizarData(a.data) - normalizarData(b.data))[0];
      return `
        <div class="col-md-6 col-xl-4">
          <div class="disc-card" style="--c:${d.cor}">
            <span class="disc-code">${esc(d.codigo)}</span>
            <div class="disc-name">${esc(d.nome)}</div>
            <div class="atv-meta mb-3"><i class="bi bi-person"></i> ${esc(d.professor || 'Professor não informado')}</div>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Atividades concluídas</span><b>${feitas}/${lista.length}</b></div>
            <div class="progress progress-thin mb-3"><div class="progress-bar" style="width:${pct}%"></div></div>
            <div class="small text-muted mb-2">${proxima ? `Próxima: <b class="text-body">${esc(proxima.titulo)}</b> — ${fmtData(proxima.data)}` : 'Sem atividades pendentes'}</div>
            <button class="btn btn-soft btn-sm" data-disc="${d.id}">Ver atividades</button>
          </div>
        </div>`;
    }).join('') + '</div><div id="discDetalhe" class="card-soft mt-4 d-none"></div>';
  }
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-disc]');
    if (!b) return;
    const d = disc(Number(b.dataset.disc));
    const lista = DB.atividades.filter(a => a.disciplinaId === d.id).sort((x, y) => normalizarData(x.data) - normalizarData(y.data));
    const box = $('#discDetalhe');
    box.classList.remove('d-none');
    box.innerHTML = `<div class="section-title">${esc(d.nome)} — atividades</div>` + (lista.map(itemAtividade).join('') || '<p class="text-muted">Sem atividades.</p>');
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  /* ---------- Calendário (HU5, HU6, HU7) ---------- */
  function eventosFiltrados() {
    return DB.atividades
      .filter(a => filtros.disciplinas.has(a.disciplinaId) && (filtros.tipo === 'todos' || a.tipo === filtros.tipo) && !(filtros.ocultarConcluidas && a.concluida))
      .map(a => ({
        id: String(a.id),
        title: (a.concluida ? '✓ ' : '') + (a.alterada ? '↻ ' : '') + a.titulo,
        classNames: a.concluida ? ['done'] : [],
        start: a.data,
        backgroundColor: disc(a.disciplinaId).cor,
        borderColor: disc(a.disciplinaId).cor
      }));
  }
  function desenharFiltros() {
    $('#chipsDisc').innerHTML = DB.disciplinas.map(d =>
      `<button class="chip ${filtros.disciplinas.has(d.id) ? '' : 'off'}" style="--c:${d.cor}" data-chip="${d.id}"><span class="dot"></span>${esc(d.nome)}</button>`
    ).join('');
    const tipos = [...new Set(DB.atividades.map(a => a.tipo))].sort((a, b) => a.localeCompare(b, 'pt-BR'));
    if (filtros.tipo !== 'todos' && !tipos.includes(filtros.tipo)) filtros.tipo = 'todos';
    $('#filtroTipo').innerHTML = '<option value="todos">Todos os tipos</option>' + tipos.map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('');
    $('#filtroTipo').value = filtros.tipo;
    $('#filtroConcluidas').checked = filtros.ocultarConcluidas;
  }
  function renderCalendario() {
    desenharFiltros();
    if (!calendario) {
      calendario = new FullCalendar.Calendar($('#calendar'), {
        locale: 'pt-br',
        buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia', list: 'Lista' },
        allDayText: 'Dia todo',
        noEventsText: 'Nenhuma atividade neste período',
        initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
        height: 'auto',
        events: (info, ok) => ok(eventosFiltrados()),
        eventClick: info => abrirAtividade(Number(info.event.id)),
        dayMaxEvents: 3
      });
    }
    calendario.render();
    calendario.refetchEvents();
  }
  $('#chipsDisc').addEventListener('click', e => {
    const c = e.target.closest('[data-chip]'); if (!c) return;
    const id = Number(c.dataset.chip);
    filtros.disciplinas.has(id) ? filtros.disciplinas.delete(id) : filtros.disciplinas.add(id);
    desenharFiltros(); calendario.refetchEvents();
  });
  $('#filtroConcluidas').addEventListener('change', e => { filtros.ocultarConcluidas = e.target.checked; calendario.refetchEvents(); });
  $('#filtroTipo').addEventListener('change', e => { filtros.tipo = e.target.value; calendario.refetchEvents(); });

  /* ---------- Detalhe da atividade (HU4, HU7, HU10) ---------- */
  async function alternarConcluida(id) {
    const a = atv(id); if (!a) return;
    try {
      const r = await enviar('api/atividades/', 'PATCH', { id_atividade: id, concluida: !a.concluida });
      a.concluida = r.concluida === true;
      a.concluidaEm = r.concluida_em;
      await carregarAvisos();      // lembretes de atividade concluída deixam de aparecer
      atualizarTudo();
      if ($('#modalAtv').classList.contains('show')) abrirAtividade(id);
      toast(a.concluida ? 'Atividade marcada como concluída. ✓' : 'Atividade reaberta.');
    } catch (erro) { toast(erro.message, 'erro'); }
  }
  const ROTULO_HISTORICO = { CRIADA: 'Incluída', DATA: 'Data alterada', INFO: 'Atualizada', REMOVIDA: 'Removida', REINSERIDA: 'Reinserida' };
  async function carregarHistorico(id) {
    const caixa = $('#historicoAtividade');
    try {
      const r = await api('api/historico/?id_atividade=' + encodeURIComponent(id));
      if (!$('#historicoAtividade')) return;
      caixa.innerHTML = r.historico.length
        ? r.historico.map(h => `<div class="hist-item"><small>${esc(ROTULO_HISTORICO[h.tipo_alteracao] || h.tipo_alteracao)} · ${fmtDataHoraISO(h.data_alteracao)}</small>${esc(h.descricao_alteracao)}</div>`).join('')
        : 'Nenhuma alteração registrada desde que a atividade entrou no planejamento.';
    } catch (erro) {
      if ($('#historicoAtividade')) caixa.textContent = 'Não foi possível carregar o histórico.';
    }
  }
  function abrirAtividade(id) {
    const a = atv(id); if (!a) return;
    const d = disc(a.disciplinaId);
    const dias = diasRestantes(a.data);
    $('#modalAtvBody').innerHTML = `
      <div class="modal-header border-0 pb-0">
        <div class="detail-head" style="--c:${d.cor}">
          <span class="badge-soft badge-tipo">${esc(a.tipo)}</span>
          <h5 class="fw-bold mt-2 mb-0 ${a.concluida ? 'text-decoration-line-through' : ''}">${esc(a.titulo)}</h5>
          <div class="text-muted small">${esc(d.nome)}${d.professor ? ' · ' + esc(d.professor) : ''}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-clock text-muted"></i>
          <span class="text-capitalize">${fmtCompleta(a.data)}</span> ${badgePrazo(a)}</div>
        ${a.concluida ? `<div class="alert alert-success py-2 small"><i class="bi bi-check2-circle"></i> Concluída${a.concluidaEm ? ` em ${fmtDataHoraISO(a.concluidaEm)}` : ''}.</div>` : ''}
        ${!a.concluida && dias !== null && dias >= 0 && dias <= 2 ? '<div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle"></i> Prazo muito próximo!</div>' : ''}
        ${a.alterada ? `<div class="alert alert-warning py-2 small"><i class="bi bi-arrow-repeat"></i> Alterada no planejamento do Moodle${a.dataAnterior ? ` — data anterior: <b>${fmtDataHoraISO(a.dataAnterior)}</b>` : ''}. Atualizada em ${fmtDataHoraISO(a.atualizadaEm)}.</div>` : ''}
        <div class="filter-label">Orientações</div>
        <p>${esc(a.descricao) || '<span class="text-muted">Sem descrição.</span>'}</p>
        <div class="filter-label mt-3">Histórico de alterações</div>
        <div id="historicoAtividade" class="small text-muted">Carregando histórico...</div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Fechar</button>
        <button type="button" class="btn ${a.concluida ? 'btn-soft' : 'btn-primary'}" data-done="${a.id}">
          <i class="bi bi-${a.concluida ? 'arrow-counterclockwise' : 'check2-circle'}"></i> ${a.concluida ? 'Reabrir atividade' : 'Marcar como concluída'}
        </button>
      </div>`;
    bootstrap.Modal.getOrCreateInstance($('#modalAtv')).show();
    carregarHistorico(id);
  }

  /* ---------- Sincronização com o Moodle (HU8, HU9, HU10) ---------- */
  const ICONE_SYNC = { SUCESSO: ['bi-check2-circle', ''], PARCIAL: ['bi-exclamation-triangle', 'warn'], FALHA: ['bi-x-circle', 'fail'] };
  const ROTULO_SYNC = { SUCESSO: 'Sincronizado', PARCIAL: 'Sincronizado com ressalvas', FALHA: 'Falha na sincronização' };

  async function executarSync(origem = 'manual') {
    if (sincronizando) return;
    sincronizando = true;
    if (viewAtual === 'sync') renderSync();
    try {
      const r = await enviar('api/sincronizacao/', 'POST', { origem });
      DB.sync = r.status;
      await atualizarDadosServidor();
      const s = r.sincronizacao;
      if (r.ignorada) {
        if (origem === 'manual') toast('Os dados já foram sincronizados há instantes.');
      } else if (s && s.resultado === 'FALHA') {
        toast('A sincronização falhou. Seus dados anteriores foram preservados.', 'erro');
      } else if (s) {
        const mudou = s.novas + s.alteradas + s.removidas;
        if (mudou > 0) toast(`Planejamento atualizado: ${s.novas} nova(s), ${s.alteradas} alterada(s), ${s.removidas} removida(s).`);
        else if (origem === 'manual') toast('Tudo em dia: nenhuma mudança no planejamento.');
      }
    } catch (erro) {
      toast('Não foi possível sincronizar: ' + erro.message, 'erro');
    } finally {
      sincronizando = false;
      if (viewAtual === 'sync') renderSync();
    }
  }
  function renderSync() {
    const s = DB.sync;
    if (!s) { $('#view-sync').innerHTML = '<div class="card-soft"><p class="text-muted mb-0">Carregando...</p></div>'; return; }
    const u = s.ultima;
    const [ic, cls] = u ? ICONE_SYNC[u.resultado] : ['bi-cloud-slash', 'warn'];
    $('#view-sync').innerHTML = `
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card-soft">
            <div class="sync-status mb-3">
              <div class="sync-ico ${cls}"><i class="bi ${sincronizando ? 'bi-arrow-repeat spin' : ic}"></i></div>
              <div>
                <div class="fw-bold">${u ? ROTULO_SYNC[u.resultado] : 'Nunca sincronizado'}</div>
                <div class="small text-muted">${u ? 'Última vez: ' + fmtDataHoraISO(u.data) : 'Clique em sincronizar para buscar o planejamento.'}</div>
              </div>
            </div>
            <p class="small text-muted">O professor altera o planejamento no Moodle. O sistema lê essas mudanças, atualiza o seu calendário e avisa quando uma data ou atividade muda. Atividades retiradas do planejamento saem do calendário, e as que você concluiu continuam registradas.</p>
            <button class="btn btn-primary w-100" id="btnSincronizar" ${sincronizando ? 'disabled' : ''}>
              <i class="bi bi-arrow-repeat ${sincronizando ? 'spin' : ''}"></i> ${sincronizando ? 'Sincronizando...' : 'Sincronizar agora'}
            </button>
            <div class="small text-muted mt-3"><i class="bi bi-database"></i> Fonte: ${esc(s.fonte)}<br><i class="bi bi-clock-history"></i> Sincronização automática ao entrar, se a última tiver mais de ${s.intervalo_min} min.</div>
          </div>
          ${htmlCardPdf()}
        </div>
        <div class="col-lg-7">
          <div class="card-soft">
            <div class="section-title">Histórico de sincronizações</div>
            ${s.execucoes.length ? s.execucoes.map(r => {
              const [i2, c2] = ICONE_SYNC[r.resultado];
              return `<div class="sync-run">
                <div class="d-flex align-items-center gap-2">
                  <span class="notif-ico ${c2 === 'fail' ? 'n-removida' : c2 === 'warn' ? 'n-lembrete' : 'n-nova'}" style="width:34px;height:34px;border-radius:10px"><i class="bi ${i2}"></i></span>
                  <div class="flex-grow-1">
                    <div class="fw-bold small">${ROTULO_SYNC[r.resultado]} <span class="text-muted fw-normal">· ${r.origem === 'manual' ? 'manual' : r.origem === 'automatica' ? 'automática' : r.origem === 'pdf' ? 'importação de PDF' : 'terminal'}</span></div>
                    <div class="sync-sub">${fmtDataHoraISO(r.data)} — ${r.novas} nova(s), ${r.alteradas} alterada(s), ${r.removidas} removida(s), ${r.iguais} sem mudança</div>
                  </div>
                </div>
                ${r.resultado !== 'SUCESSO' ? '<div class="small mt-2">' + r.itens.filter(i => i.resultado !== 'SUCESSO').map(i => `<div><b>${esc(i.codigo || 'Geral')}</b>: ${esc(i.detalhes)}</div>`).join('') + '</div>' : ''}
              </div>`;
            }).join('') : '<p class="text-muted mb-0">Nenhuma sincronização registrada ainda.</p>'}
          </div>
        </div>
      </div>`;
  }
  document.addEventListener('click', e => { if (e.target.closest('#btnSincronizar')) executarSync('manual'); });

  /* ---------- Importação de atividades por PDF ---------- */
  let pdfPrevia = null; // { disciplinaId, itens: [...], ignoradas: [...] }
  let lendoPdf = false;

  function htmlCardPdf() {
    const anoAtual = new Date().getFullYear();
    if (!DB.disciplinas.length) {
      return `<div class="card-soft mt-3"><div class="section-title">Importar atividades de um PDF</div>
        <p class="small text-muted mb-0">Você precisa estar matriculado em uma disciplina para importar atividades.</p></div>`;
    }
    return `
      <div class="card-soft mt-3">
        <div class="section-title">Importar atividades de um PDF</div>
        <p class="small text-muted">Envie o PDF do planejamento da disciplina. O arquivo é lido no seu navegador e você confere a lista antes de importar. Nada é removido, e importar de novo não duplica.</p>
        <label class="form-label small mb-1" for="pdfDisc">Disciplina</label>
        <select id="pdfDisc" class="form-select mb-2">${DB.disciplinas.map(d => `<option value="${d.id}">${esc(d.codigo)} — ${esc(d.nome)}</option>`).join('')}</select>
        <label class="form-label small mb-1" for="pdfAno">Ano das datas sem ano (ex.: 12/03)</label>
        <input id="pdfAno" type="number" min="2020" max="2100" value="${anoAtual}" class="form-control mb-2">
        <label class="form-label small mb-1" for="pdfArquivo">Arquivo PDF</label>
        <input id="pdfArquivo" type="file" accept="application/pdf,.pdf" class="form-control mb-3">
        <button class="btn btn-primary w-100" id="btnLerPdf" ${lendoPdf ? 'disabled' : ''}>
          <i class="bi ${lendoPdf ? 'bi-arrow-repeat spin' : 'bi-file-earmark-pdf'}"></i> ${lendoPdf ? 'Lendo PDF...' : 'Ler PDF e ver prévia'}
        </button>
      </div>`;
  }

  const fmtDataPdf = iso => `${iso.slice(8, 10)}/${iso.slice(5, 7)}/${iso.slice(0, 4)} ${iso.slice(11, 16)}`;

  /** nova | muda (mesmo código, data diferente) | igual */
  function situacaoPdf(disciplinaId, item) {
    const ex = DB.atividades.find(a => a.disciplinaId === disciplinaId && a.codigo === item.codigo_atv);
    if (!ex) return 'nova';
    return new Date(ex.data).getTime() === new Date(item.data_hora).getTime() ? 'igual' : 'muda';
  }

  async function lerPdfSelecionado() {
    if (lendoPdf) return;
    const arquivo = $('#pdfArquivo').files[0];
    const disciplinaId = Number($('#pdfDisc').value);
    const ano = parseInt($('#pdfAno').value, 10);
    if (!arquivo) { toast('Escolha um arquivo PDF.', 'erro'); return; }
    if (!(ano >= 2020 && ano <= 2100)) { toast('Informe um ano válido (2020 a 2100).', 'erro'); return; }
    lendoPdf = true;
    renderSync();
    try {
      const linhas = await PdfImportacao.extrairLinhas(arquivo);
      const { itens, ignoradas } = PdfImportacao.interpretarLinhas(linhas, ano);
      if (!itens.length) {
        toast('Não encontrei nenhuma data neste PDF. Confira se o planejamento tem datas como 12/03/2026.', 'erro');
      } else {
        pdfPrevia = { disciplinaId, itens, ignoradas };
        mostrarPreviaPdf();
      }
    } catch (erro) {
      toast(erro.message, 'erro');
    } finally {
      lendoPdf = false;
      if (viewAtual === 'sync') renderSync();
    }
  }

  function mostrarPreviaPdf() {
    const { disciplinaId, itens, ignoradas } = pdfPrevia;
    const d = disc(disciplinaId);
    const rotulo = { nova: ['Nova', 'text-bg-success'], muda: ['Data muda', 'text-bg-warning'], igual: ['Já existe', 'text-bg-secondary'] };
    $('#modalPdfBody').innerHTML = `
      <div class="modal-header">
        <h5 class="modal-title">Prévia da importação — ${esc(d.codigo)}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted">Encontrei ${itens.length} linha(s) com data. Marque o que é atividade (provas, entregas e trabalhos já vêm marcados) e corrija o que precisar depois no Moodle/PDF.</p>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th></th><th>Data</th><th>Tipo</th><th>Título</th><th></th></tr></thead>
            <tbody>
              ${itens.map((it, i) => {
                const sit = situacaoPdf(disciplinaId, it);
                const [txt, cls] = rotulo[sit];
                return `<tr>
                  <td><input class="form-check-input js-pdf-item" type="checkbox" data-i="${i}" ${it.sugerida && sit !== 'igual' ? 'checked' : ''} ${sit === 'igual' ? 'disabled' : ''} aria-label="Importar ${esc(it.titulo)}"></td>
                  <td class="text-nowrap small">${fmtDataPdf(it.data_hora)}${it.temHora ? '' : ' <span class="text-muted" title="Sem horário no PDF: assumido 23:59">*</span>'}</td>
                  <td class="small">${esc(it.tipo)}</td>
                  <td class="small">${esc(it.titulo)}${it.descricao ? `<div class="text-muted" style="font-size:.72rem">${esc(it.descricao.slice(0, 120))}</div>` : ''}</td>
                  <td><span class="badge ${cls}">${txt}</span></td>
                </tr>`;
              }).join('')}
            </tbody>
          </table>
        </div>
        <div class="small text-muted">* sem horário no PDF: assumido 23:59.</div>
        ${ignoradas.length ? `<div class="small text-warning-emphasis mt-2"><i class="bi bi-exclamation-triangle"></i> ${ignoradas.length} linha(s) ignorada(s): ${ignoradas.slice(0, 3).map(esc).join('; ')}</div>` : ''}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnConfirmarPdf"><i class="bi bi-check2-circle"></i> Importar selecionadas</button>
      </div>`;
    bootstrap.Modal.getOrCreateInstance($('#modalPdf')).show();
  }

  async function confirmarImportacaoPdf() {
    if (!pdfPrevia) return;
    const escolhidas = [...document.querySelectorAll('.js-pdf-item:checked')].map(c => pdfPrevia.itens[Number(c.dataset.i)]);
    if (!escolhidas.length) { toast('Marque ao menos uma atividade.', 'erro'); return; }
    const botao = $('#btnConfirmarPdf');
    botao.disabled = true;
    try {
      const r = await enviar('api/importacao-pdf/', 'POST', {
        id_disciplina: pdfPrevia.disciplinaId,
        itens: escolhidas.map(({ codigo_atv, titulo, tipo, data_hora, descricao }) => ({ codigo_atv, titulo, tipo, data_hora, descricao }))
      });
      bootstrap.Modal.getOrCreateInstance($('#modalPdf')).hide();
      pdfPrevia = null;
      const s = r.resumo;
      toast(`PDF importado: ${s.novas} nova(s), ${s.alteradas} alterada(s), ${s.iguais} sem mudança.`);
      await atualizarDadosServidor();
      await carregarSync();
      if (viewAtual === 'sync') renderSync();
    } catch (erro) {
      toast('Não foi possível importar: ' + erro.message, 'erro');
      botao.disabled = false;
    }
  }

  document.addEventListener('click', e => {
    if (e.target.closest('#btnLerPdf')) lerPdfSelecionado();
    if (e.target.closest('#btnConfirmarPdf')) confirmarImportacaoPdf();
  });

  /* ---------- Avisos e lembretes (HU11, HU12) ---------- */
  const naoLidas = () => DB.notificacoes.filter(n => !n.lida).length;
  function atualizarBadges() {
    const n = naoLidas();
    $('#navBadge').textContent = n; $('#navBadge').classList.toggle('d-none', n === 0);
    $('#bellDot').classList.toggle('d-none', n === 0);
  }
  function itemAviso(n, compacto = false) {
    const clicavel = n.atividadeId && atv(n.atividadeId);
    return `
      <div class="notif ${n.lida ? '' : 'unread'}" data-notificacao="${n.id}" ${clicavel ? `data-atv="${n.atividadeId}" style="cursor:pointer"` : ''}>
        <div class="notif-ico n-${n.classe}"><i class="bi ${n.icone}"></i></div>
        <div class="flex-grow-1">
          <div class="fw-bold small">${esc(n.titulo)}</div>
          <div class="small ${compacto ? 'text-muted' : ''}">${esc(n.mensagem)}</div>
          ${compacto ? '' : `<div class="text-muted" style="font-size:.72rem">${fmtDataHoraISO(n.data)}</div>`}
        </div>
      </div>`;
  }
  function renderAvisos() {
    const p = DB.estudante.preferencias;
    $('#view-notificacoes').innerHTML = `
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="card-soft">
            <div class="section-title">Seus avisos
              <button class="link-btn" id="btnLerTodas">Marcar todas como lidas</button></div>
            ${DB.notificacoes.map(n => itemAviso(n)).join('') || '<p class="text-muted">Nenhum aviso por enquanto.</p>'}
          </div>
        </div>
        <div class="col-lg-4"><div class="card-soft">
          <div class="section-title">Como funcionam</div>
          <p class="small text-muted">${p.lembretes_ativos ? `Você recebe lembretes de atividades que vencem em até <b>${p.antecedencia_dias}</b> dia(s), enquanto não estiverem concluídas.` : 'Os lembretes de prazo estão desativados.'}</p>
          <p class="small text-muted mb-2">${p.avisos_alteracao ? 'Você é avisado quando o Moodle muda uma data, inclui ou retira uma atividade.' : 'Os avisos de alteração estão desativados.'}</p>
          <button class="link-btn" data-ir="perfil">Mudar preferências</button>
        </div></div>
      </div>`;
  }
  document.addEventListener('click', async e => {
    if (e.target.closest('#btnLerTodas')) {
      try { await enviar('api/notificacoes/', 'PATCH', { todas: true }); await carregarAvisos(); atualizarBadges(); renderAvisos(); }
      catch (erro) { toast(erro.message, 'erro'); }
      return;
    }
    const aviso = e.target.closest('.notif');
    if (aviso && aviso.dataset.notificacao) {
      const id = Number(aviso.dataset.notificacao);
      const registro = DB.notificacoes.find(n => n.id === id);
      if (registro && !registro.lida) {
        try {
          await enviar('api/notificacoes/', 'PATCH', { id_notificacao: id });
          await carregarAvisos(); atualizarBadges();
          if (viewAtual === 'notificacoes') renderAvisos();
        } catch (erro) { toast(erro.message, 'erro'); }
      }
    }
  });

  /* ---------- Perfil (HU2) ---------- */
  function renderPerfil() {
    const e = DB.estudante;
    const p = e.preferencias;
    $('#view-perfil').innerHTML = `
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="card-soft text-center">
            <div class="avatar mx-auto mb-3" style="width:88px;height:88px;font-size:2rem">${esc(iniciais(e.nome))}</div>
            <h5 class="fw-bold mb-0">${esc(e.nome)}</h5>
            <div class="text-muted small mb-3">${esc(e.email)}</div>
            <span class="badge-soft badge-tipo">${esc(e.curso)}</span>
          </div>
        </div>
        <div class="col-lg-8">
          <form class="card-soft" id="formPerfil">
            <div class="section-title">Dados do perfil</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6"><label class="form-label" for="pfNome">Nome</label><input id="pfNome" class="form-control" value="${esc(e.nome)}" maxlength="120" required></div>
              <div class="col-md-6"><label class="form-label">E-mail institucional</label><input class="form-control" value="${esc(e.email)}" disabled></div>
            </div>
            <div class="section-title">Lembretes e avisos</div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="pfLembretes" ${p.lembretes_ativos ? 'checked' : ''}>
              <label class="form-check-label" for="pfLembretes">Lembretes de prazo</label>
            </div>
            <div class="row g-3 align-items-center mb-2">
              <div class="col-auto"><label class="form-label mb-0" for="pfDias">Avisar com antecedência de</label></div>
              <div class="col-auto"><select id="pfDias" class="form-select form-select-sm">${[1, 2, 3, 5, 7, 14].map(n => `<option value="${n}" ${n === p.antecedencia_dias ? 'selected' : ''}>${n} dia${n > 1 ? 's' : ''}</option>`).join('')}</select></div>
            </div>
            <div class="form-check form-switch mb-4">
              <input class="form-check-input" type="checkbox" id="pfAvisos" ${p.avisos_alteracao ? 'checked' : ''}>
              <label class="form-check-label" for="pfAvisos">Avisos de alteração no planejamento</label>
            </div>
            <button class="btn btn-primary" type="submit">Salvar alterações</button>
          </form>
        </div>
      </div>`;
  }
  document.addEventListener('submit', async e => {
    if (e.target.id !== 'formPerfil') return;
    e.preventDefault();
    try {
      const r = await enviar('api/perfil/', 'PATCH', {
        nome: $('#pfNome').value.trim(),
        lembretes_ativos: $('#pfLembretes').checked,
        antecedencia_dias: Number($('#pfDias').value),
        avisos_alteracao: $('#pfAvisos').checked
      });
      DB.estudante = r.usuario;
      await carregarAvisos();
      atualizarTudo();
      toast('Perfil atualizado.');
    } catch (erro) { toast(erro.message, 'erro'); }
  });

  /* ---------- Inicialização ---------- */
  function atualizarTopo() { $('#topAvatar').textContent = iniciais(DB.estudante.nome); }
  function atualizarTudo() {
    atualizarBadges(); atualizarTopo();
    if (usuarioAtual) RENDER()[viewAtual]();
  }
  function iniciarApp() {
    atualizarBadges(); atualizarTopo();
    ir('home');
  }
  restaurarSessao();
})();
