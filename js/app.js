/* ===== Meu Calendário Acadêmico — lógica do front end =====
   Funcionalidades:
   1) Login e perfil do estudante            (HU1, HU2)
   2) Disciplinas e atividades por disciplina (HU3, HU4)
   3) Calendário com visões e filtros         (HU5, HU6, HU7)
   4) Sincronização/importação (CSV/.ics)     (HU8, HU9, HU10)
   5) Lembretes e avisos de alteração         (HU11, HU12)
   6) Cadastro manual de atividades
   Os dados ficam no localStorage (protótipo sem back end). */

(() => {
  'use strict';

  /* ---------- Utilidades ---------- */
  const $  = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const KEY = 'mca_dados_v2';
  const MS_DIA = 86400000;

  const fmtData = iso => new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }).replace('.', '');
  const fmtHora = iso => new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  const fmtCompleta = iso => new Date(iso).toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' }) + ' às ' + fmtHora(iso);
  const fmtDataHoraISO = iso => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  const diasRestantes = iso => {
    const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
    const alvo = new Date(iso); alvo.setHours(0, 0, 0, 0);
    return Math.round((alvo - hoje) / MS_DIA);
  };
  const proximoId = lista => lista.reduce((m, x) => Math.max(m, x.id), 0) + 1;
  const iniciais = nome => nome.trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase();

  /* ---------- Estado e persistência ---------- */
  let DB = carregar();
  function carregar() {
    try {
      const salvo = JSON.parse(localStorage.getItem(KEY));
      if (salvo && salvo.versao === VERSAO_DADOS) return salvo;
    } catch (e) { /* ignora storage indisponível */ }
    return criarDadosIniciais();
  }
  function salvar() {
    try { localStorage.setItem(KEY, JSON.stringify(DB)); } catch (e) { /* ignora */ }
  }

  const filtros = { disciplinas: new Set(DB.disciplinas.map(d => d.id)), tipo: 'todos', ocultarConcluidas: false };
  let viewAtual = 'home';
  let calendario = null;

  const disc = id => DB.disciplinas.find(d => d.id === id);
  const atv  = id => DB.atividades.find(a => a.id === id);
  const futuras = () => DB.atividades.filter(a => !a.concluida && new Date(a.data) >= new Date(new Date().setHours(0, 0, 0, 0)))
                                     .sort((a, b) => new Date(a.data) - new Date(b.data));

  /* ---------- Toast ---------- */
  function toast(msg, tipo = 'ok') {
    const el = $('#toast');
    el.className = 'toast align-items-center border-0 text-bg-' + (tipo === 'erro' ? 'danger' : 'dark');
    $('#toastMsg').textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
  }

  /* ---------- Notificações (HU11, HU12) ---------- */
  function notificar(tipo, titulo, mensagem, atividadeId = null, chave = null) {
    if (chave && DB.notificacoes.some(n => n.chave === chave)) return false; // evita repetição
    DB.notificacoes.unshift({ id: proximoId(DB.notificacoes), tipo, titulo, mensagem, atividadeId, chave, data: new Date().toISOString(), lida: false });
    return true;
  }
  function gerarLembretes() {
    if (!DB.config.lembretes) return;
    futuras().forEach(a => {
      const d = diasRestantes(a.data);
      if (d <= DB.config.antecedenciaDias) {
        const quando = d === 0 ? 'hoje' : d === 1 ? 'amanhã' : `em ${d} dias`;
        notificar('lembrete', 'Prazo se aproximando', `${a.titulo} (${disc(a.disciplinaId).nome}) — ${quando}, às ${fmtHora(a.data)}.`, a.id, `lem-${a.id}-${a.data}`);
      }
    });
    salvar();
  }
  const naoLidas = () => DB.notificacoes.filter(n => !n.lida).length;
  function atualizarBadges() {
    const n = naoLidas();
    $('#navBadge').textContent = n; $('#navBadge').classList.toggle('d-none', n === 0);
    $('#bellDot').classList.toggle('d-none', n === 0);
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

  /* ---------- Login / sessão (HU1) ---------- */
  function mostrarTela() {
    const logado = DB.estudante.logado;
    $('#login').classList.toggle('d-none', logado);
    $('#app').classList.toggle('d-none', !logado);
    if (logado) { iniciarApp(); }
  }

  $('#loginForm').addEventListener('submit', e => {
    e.preventDefault();
    const email = $('#loginEmail').value.trim();
    const senha = $('#loginSenha').value;
    const erro = $('#loginErro');
    // somente e-mails institucionais da UTFPR (@utfpr.edu.br ou @alunos.utfpr.edu.br)
    const formatoOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const institucional = /^[^\s@]+@(alunos\.)?utfpr\.edu\.br$/i.test(email);
    let msg = '';
    if (!formatoOk) msg = 'Informe um e-mail válido.';
    else if (!institucional) msg = 'Acesso restrito: use seu e-mail institucional (@utfpr.edu.br ou @alunos.utfpr.edu.br).';
    else if (senha.length < 4) msg = 'A senha deve ter ao menos 4 caracteres.';
    if (msg) {
      erro.textContent = msg;
      erro.classList.remove('d-none');
      return;
    }
    erro.classList.add('d-none');
    DB.estudante.logado = true;   // entra sempre com o perfil genérico de exemplo
    salvar();
    mostrarTela();
  });
  $('#togglePass').addEventListener('click', () => {
    const inp = $('#loginSenha');
    const mostrar = inp.type === 'password';
    inp.type = mostrar ? 'text' : 'password';
    $('#togglePass i').className = mostrar ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
  $('#btnLogout').addEventListener('click', () => {
    DB.estudante.logado = false; salvar();
    $('#loginSenha').value = '';
    mostrarTela();
  });

  /* ---------- Navegação ---------- */
  const TITULOS = {
    home: ['Início', 'Seu resumo acadêmico'],
    calendario: ['Calendário', 'Todas as atividades em um só lugar'],
    disciplinas: ['Disciplinas', 'Matérias do semestre'],
    sync: ['Sincronização', 'Planejamentos das disciplinas'],
    notificacoes: ['Avisos', 'Lembretes e alterações'],
    perfil: ['Perfil', 'Seus dados e preferências']
  };
  function ir(view) {
    viewAtual = view;
    $$('.view').forEach(v => v.classList.add('d-none'));
    $('#view-' + view).classList.remove('d-none');
    $$('.nav-item').forEach(b => b.classList.toggle('active', b.dataset.view === view));
    $('#pageTitle').textContent = TITULOS[view][0];
    $('#pageSub').textContent = TITULOS[view][1];
    const render = { home: renderHome, calendario: renderCalendario, disciplinas: renderDisciplinas, sync: renderSync, notificacoes: renderAvisos, perfil: renderPerfil }[view];
    render();
    window.scrollTo({ top: 0 });
  }
  $('#nav').addEventListener('click', e => { const b = e.target.closest('.nav-item'); if (b) ir(b.dataset.view); });
  $('#btnBell').addEventListener('click', () => ir('notificacoes'));
  $('#topAvatar').addEventListener('click', () => ir('perfil'));

  /* ---------- Componentes ---------- */
  function badgePrazo(a) {
    const d = diasRestantes(a.data);
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
        <span class="atv-check ${a.concluida ? 'on' : ''}" data-done="${a.id}" role="checkbox" aria-checked="${!!a.concluida}" title="${a.concluida ? 'Reabrir' : 'Marcar como concluída'}"><i class="bi bi-check-lg"></i></span>
        <span class="atv-bar" style="background:${d.cor}"></span>
        <span class="atv-info">
          <div class="atv-title">${esc(a.titulo)}</div>
          <div class="atv-meta">${esc(d.nome)} · ${fmtData(a.data)}, ${fmtHora(a.data)}</div>
        </span>
        ${a.alterada ? '<span class="badge-soft badge-alt" title="Data alterada"><i class="bi bi-arrow-repeat"></i></span>' : ''}
        <span class="badge-soft badge-tipo">${esc(a.tipo)}</span>
        ${badgePrazo(a)}
      </button>`;
  }
  // clique em qualquer atividade abre o detalhe (HU7)
  document.addEventListener('click', e => {
    const chk = e.target.closest('[data-done]');
    if (chk) { alternarConcluida(Number(chk.dataset.done)); return; }
    const b = e.target.closest('[data-atv]');
    if (b) abrirAtividade(Number(b.dataset.atv));
  });

  /* ---------- Início ---------- */
  function renderHome() {
    const prox = futuras();
    const prox7 = prox.filter(a => diasRestantes(a.data) <= 7);
    const provas = prox7.filter(a => a.tipo === 'Prova').length;
    const alteradas = DB.atividades.filter(a => a.alterada).length;
    const primeiro = DB.estudante.nome.split(' ')[0];

    // faixa da semana
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
      </div>

      <div class="row g-3 mb-4">
        ${[
          ['bi-calendar-event', 's-primary', '', prox7.length, 'Próximos 7 dias'],
          ['bi-journal-check', 's-red', '', provas, 'Provas na semana'],
          ['bi-arrow-repeat', 's-amber', '', alteradas, 'Datas alteradas'],
          ['bi-bell', 's-green', '', naoLidas(), 'Avisos novos']
        ].map(([ic, bg, fg, n, l]) => `
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
    $('#view-disciplinas').innerHTML = `<div class="row g-3">` + DB.disciplinas.map(d => {
      const lista = DB.atividades.filter(a => a.disciplinaId === d.id);
      const feitas = lista.filter(a => a.concluida).length;
      const pend = lista.filter(a => !a.concluida && new Date(a.data) >= new Date());
      const pct = lista.length ? Math.round((feitas / lista.length) * 100) : 0;
      const proxima = pend.sort((a, b) => new Date(a.data) - new Date(b.data))[0];
      return `
        <div class="col-md-6 col-xl-4">
          <div class="disc-card" style="--c:${d.cor}">
            <span class="disc-code">${esc(d.codigo)}</span>
            <div class="disc-name">${esc(d.nome)}</div>
            <div class="atv-meta mb-3"><i class="bi bi-person"></i> ${esc(d.professor)}</div>
            <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Atividades concluídas</span><b>${feitas}/${lista.length}</b></div>
            <div class="progress progress-thin mb-3"><div class="progress-bar" style="width:${pct}%"></div></div>
            <div class="small text-muted mb-2">${proxima ? `Próxima: <b class="text-body">${esc(proxima.titulo)}</b> — ${fmtData(proxima.data)}` : 'Sem atividades pendentes'}</div>
            <button class="btn btn-soft btn-sm" data-disc="${d.id}">Ver atividades</button>
          </div>
        </div>`;
    }).join('') + `</div><div id="discDetalhe" class="card-soft mt-4 d-none"></div>`;
  }
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-disc]');
    if (!b) return;
    const d = disc(Number(b.dataset.disc));
    const lista = DB.atividades.filter(a => a.disciplinaId === d.id).sort((a, b) => new Date(a.data) - new Date(b.data));
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
  function desenharChips() {
    $('#chipsDisc').innerHTML = DB.disciplinas.map(d =>
      `<button class="chip ${filtros.disciplinas.has(d.id) ? '' : 'off'}" style="--c:${d.cor}" data-chip="${d.id}"><span class="dot"></span>${esc(d.nome)}</button>`
    ).join('');
  }
  function renderCalendario() {
    desenharChips();
    $('#filtroTipo').value = filtros.tipo;
    $('#filtroConcluidas').checked = filtros.ocultarConcluidas;
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
    desenharChips(); calendario.refetchEvents();
  });
  $('#filtroConcluidas').addEventListener('change', e => { filtros.ocultarConcluidas = e.target.checked; calendario.refetchEvents(); });
  $('#filtroTipo').addEventListener('change', e => { filtros.tipo = e.target.value; calendario.refetchEvents(); });

  /* ---------- Detalhe da atividade (HU4, HU7, HU10) ---------- */
  function alternarConcluida(id) {
    const a = atv(id); if (!a) return;
    a.concluida = !a.concluida;
    a.concluidaEm = a.concluida ? new Date().toISOString() : null;
    salvar(); atualizarTudo();
    if ($('#modalAtv').classList.contains('show')) abrirAtividade(id);
    toast(a.concluida ? 'Atividade marcada como concluída. ✓' : 'Atividade reaberta.');
  }

  function abrirAtividade(id, editar = false) {
    const a = atv(id); if (!a) return;
    const d = disc(a.disciplinaId);
    const corpo = $('#modalAtvBody');

    if (editar) {
      corpo.innerHTML = `
        <form id="formEditar">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold">Editar atividade</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <label class="form-label">Disciplina</label>
            <select id="edDisc" class="form-select mb-3">${DB.disciplinas.map(x => `<option value="${x.id}" ${x.id === a.disciplinaId ? 'selected' : ''}>${esc(x.nome)}</option>`).join('')}</select>
            <label class="form-label">Título</label>
            <input id="edTitulo" class="form-control mb-3" value="${esc(a.titulo)}" required>
            <div class="row g-3 mb-3">
              <div class="col-6"><label class="form-label">Tipo</label>
                <select id="edTipo" class="form-select">${['Prova', 'Trabalho', 'Entrega', 'Outro'].map(t => `<option ${t === a.tipo ? 'selected' : ''}>${t}</option>`).join('')}</select></div>
              <div class="col-6"><label class="form-label">Data e hora</label>
                <input id="edData" type="datetime-local" class="form-control" value="${esc(a.data)}" required></div>
            </div>
            <label class="form-label">Descrição</label>
            <textarea id="edDesc" class="form-control" rows="3">${esc(a.descricao)}</textarea>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-soft" id="btnCancelarEdicao">Cancelar</button>
            <button type="submit" class="btn btn-primary">Salvar alterações</button>
          </div>
        </form>`;
      $('#btnCancelarEdicao').onclick = () => abrirAtividade(id);
      $('#formEditar').onsubmit = ev => {
        ev.preventDefault();
        const novo = { disciplinaId: Number($('#edDisc').value), titulo: $('#edTitulo').value.trim(), tipo: $('#edTipo').value, data: $('#edData').value, descricao: $('#edDesc').value.trim() };
        if (!novo.titulo || !novo.data) return;
        if (novo.data !== a.data) {
          DB.historico.unshift({ id: proximoId(DB.historico), atividadeId: a.id, descricao: `${novo.titulo}: ${fmtDataHoraISO(a.data)} → ${fmtDataHoraISO(novo.data)} (edição manual)`, data: new Date().toISOString() });
        }
        Object.assign(a, novo);
        gerarLembretes(); salvar(); atualizarTudo();
        toast('Atividade atualizada.');
        abrirAtividade(id);
      };
      bootstrap.Modal.getOrCreateInstance($('#modalAtv')).show();
      return;
    }

    const dias = diasRestantes(a.data);
    corpo.innerHTML = `
      <div class="modal-header border-0 pb-0">
        <div class="detail-head" style="--c:${d.cor}">
          <span class="badge-soft badge-tipo">${esc(a.tipo)}</span>
          <h5 class="fw-bold mt-2 mb-0 ${a.concluida ? 'text-decoration-line-through' : ''}">${esc(a.titulo)}</h5>
          <div class="text-muted small">${esc(d.nome)} · ${esc(d.professor)}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-clock text-muted"></i>
          <span class="text-capitalize">${fmtCompleta(a.data)}</span> ${badgePrazo(a)}</div>
        ${a.concluida ? `<div class="alert alert-success py-2 small"><i class="bi bi-check2-circle"></i> Concluída${a.concluidaEm ? ` em ${fmtDataHoraISO(a.concluidaEm)}` : ''}.</div>` : ''}
        ${!a.concluida && dias >= 0 && dias <= 2 ? '<div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle"></i> Prazo muito próximo!</div>' : ''}
        ${a.alterada ? `<div class="alert alert-warning py-2 small"><i class="bi bi-arrow-repeat"></i> Atividade alterada${a.dataAnterior ? ` — data anterior: <b>${fmtDataHoraISO(a.dataAnterior)}</b>` : ''}.${a.atualizadaEm ? ` Atualizada em ${fmtDataHoraISO(a.atualizadaEm)}.` : ''}</div>` : ''}
        <div class="filter-label">Orientações</div>
        <p>${esc(a.descricao) || '<span class="text-muted">Sem descrição.</span>'}</p>
      </div>
      <div class="modal-footer border-0 pt-0 flex-wrap">
        <button class="btn btn-danger-soft me-auto" id="btnExcluirAtv"><i class="bi bi-trash"></i> Excluir</button>
        <button class="btn btn-soft" id="btnEditarAtv"><i class="bi bi-pencil"></i> Editar</button>
        <button class="btn ${a.concluida ? 'btn-soft' : 'btn-primary'}" id="btnConcluirAtv">
          <i class="bi ${a.concluida ? 'bi-arrow-counterclockwise' : 'bi-check2-circle'}"></i> ${a.concluida ? 'Reabrir' : 'Marcar como concluída'}</button>
      </div>`;
    $('#btnEditarAtv').onclick = () => abrirAtividade(id, true);
    $('#btnConcluirAtv').onclick = () => alternarConcluida(id);
    $('#btnExcluirAtv').onclick = () => {
      if (!confirm('Excluir esta atividade?')) return;
      DB.atividades = DB.atividades.filter(x => x.id !== id);
      salvar(); bootstrap.Modal.getInstance($('#modalAtv')).hide(); toast('Atividade excluída.'); atualizarTudo();
    };
    bootstrap.Modal.getOrCreateInstance($('#modalAtv')).show();
  }

  /* ---------- Nova atividade manual ---------- */
  $('#btnNovaAtv').addEventListener('click', () => {
    $('#novaDisc').innerHTML = DB.disciplinas.map(d => `<option value="${d.id}">${esc(d.nome)}</option>`).join('');
    $('#formNova').reset();
    bootstrap.Modal.getOrCreateInstance($('#modalNova')).show();
  });
  $('#formNova').addEventListener('submit', e => {
    e.preventDefault();
    const titulo = $('#novaTitulo').value.trim();
    const data = $('#novaData').value;
    if (!titulo || !data) return;
    DB.atividades.push({ id: proximoId(DB.atividades), disciplinaId: Number($('#novaDisc').value), titulo, tipo: $('#novaTipo').value, data, descricao: $('#novaDesc').value.trim(), alterada: false });
    gerarLembretes(); salvar();
    bootstrap.Modal.getInstance($('#modalNova')).hide();
    toast('Atividade adicionada ao calendário.');
    atualizarTudo();
  });

  /* ---------- Sincronização (HU8, HU9, HU10) ---------- */
  function renderSync() {
    const ultima = DB.sincronizacoes[0];
    const ok = ultima && ultima.resultado === 'sucesso';
    $('#view-sync').innerHTML = `
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card-soft mb-3">
            <div class="sync-status mb-3">
              <div class="sync-ico ${ok ? '' : 'fail'}"><i class="bi ${ok ? 'bi-check2-circle' : 'bi-exclamation-octagon'}"></i></div>
              <div>
                <div class="fw-bold">${ok ? 'Tudo atualizado' : 'Falha na última sincronização'}</div>
                <div class="text-muted small">Última tentativa: ${ultima ? fmtDataHoraISO(ultima.data) : '—'}</div>
              </div>
            </div>
            <p class="small text-muted">Consulta os planejamentos das disciplinas (portal institucional da UTFPR), identifica atividades novas ou alteradas e atualiza o calendário sem duplicar registros.</p>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-primary" id="btnSync"><i class="bi bi-arrow-repeat"></i> Sincronizar agora</button>
              <button class="btn btn-soft" id="btnSyncFalha">Simular falha</button>
            </div>
          </div>

          <div class="card-soft">
            <div class="section-title">Importar arquivo</div>
            <div class="drop mb-3">
              <i class="bi bi-cloud-arrow-up fs-2"></i>
              <div class="small mb-2">Planejamento em <b>.pdf</b>, <b>.csv</b> ou <b>.ics</b></div>
              <input type="file" id="arquivo" accept=".pdf,.csv,.ics,application/pdf,text/csv,text/calendar" class="form-control">
            </div>
            <label class="form-label small">Disciplina do planejamento (.pdf e .ics)</label>
            <select id="syncDisc" class="form-select mb-3"><option value="">Detectar automaticamente no arquivo</option>${DB.disciplinas.map(d => `<option value="${d.id}">${esc(d.nome)}</option>`).join('')}</select>
            <button class="btn btn-soft w-100 mb-2" id="btnImportar"><i class="bi bi-box-arrow-in-down"></i> Importar atividades</button>
            <button class="btn btn-link btn-sm p-0" id="btnModelo">Baixar modelo CSV</button>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card-soft mb-3">
            <div class="section-title">Histórico de sincronizações</div>
            <ul class="timeline">${DB.sincronizacoes.slice(0, 6).map(s => `
              <li><div class="tl-dot ${s.resultado === 'sucesso' ? 'tl-ok' : 'tl-fail'}"><i class="bi ${s.resultado === 'sucesso' ? 'bi-check-lg' : 'bi-x-lg'}"></i></div>
              <div><div class="fw-semibold small">${s.resultado === 'sucesso' ? 'Concluída' : 'Não concluída'}</div>
              <div class="text-muted small">${fmtDataHoraISO(s.data)} — ${esc(s.detalhes)}</div></div></li>`).join('')}</ul>
          </div>
          <div class="card-soft">
            <div class="section-title">Alterações registradas</div>
            ${DB.historico.length ? `<ul class="timeline">${DB.historico.slice(0, 6).map(h => `
              <li><div class="tl-dot n-alteracao"><i class="bi bi-arrow-repeat"></i></div>
              <div><div class="fw-semibold small">${esc(h.descricao)}</div><div class="text-muted small">${fmtDataHoraISO(h.data)}</div></div></li>`).join('')}</ul>`
              : '<p class="text-muted small mb-0">Nenhuma alteração detectada ainda.</p>'}
          </div>
        </div>
      </div>`;
  }

  // Aplica uma lista de atividades vindas de importação/sincronização sem duplicar (RNF05).
  function aplicarImportacao(itens) {
    let novas = 0, alteradas = 0, iguais = 0;
    itens.forEach(it => {
      const existente = DB.atividades.find(a => a.disciplinaId === it.disciplinaId && a.titulo.toLowerCase() === it.titulo.toLowerCase());
      if (!existente) {
        DB.atividades.push({ id: proximoId(DB.atividades), alterada: false, descricao: '', tipo: 'Outro', ...it });
        novas++;
      } else if (existente.data !== it.data) {
        existente.dataAnterior = existente.data;
        existente.data = it.data;
        existente.alterada = true;
        existente.atualizadaEm = new Date().toISOString();
        DB.historico.unshift({ id: proximoId(DB.historico), atividadeId: existente.id, descricao: `${existente.titulo}: ${fmtDataHoraISO(existente.dataAnterior)} → ${fmtDataHoraISO(it.data)}`, data: new Date().toISOString() });
        if (DB.config.avisosAlteracao) {
          notificar('alteracao', 'Data alterada', `${existente.titulo} (${disc(existente.disciplinaId).nome}) mudou de ${fmtDataHoraISO(existente.dataAnterior)} para ${fmtDataHoraISO(it.data)}.`, existente.id);
        }
        alteradas++;
      } else iguais++;
    });
    return { novas, alteradas, iguais };
  }
  function registrarSync(resultado, detalhes) {
    DB.sincronizacoes.unshift({ id: proximoId(DB.sincronizacoes), data: new Date().toISOString(), resultado, detalhes });
    DB.sincronizacoes = DB.sincronizacoes.slice(0, 20);
  }
  function concluirSync(res, origem) {
    const detalhes = `${origem}: ${res.novas} nova(s), ${res.alteradas} alterada(s), ${res.iguais} sem mudança.`;
    registrarSync('sucesso', detalhes);
    notificar('sync', 'Sincronização concluída', detalhes);
    gerarLembretes(); salvar(); atualizarTudo();
    toast(detalhes);
  }

  document.addEventListener('click', e => {
    if (e.target.closest('#btnSync')) {
      const btn = $('#btnSync');
      btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Sincronizando...';
      setTimeout(() => {
        // Simulação do que o back end faria ao consultar os planejamentos:
        // uma atividade futura muda de data e uma atividade nova aparece.
        const itens = [];
        const alvo = futuras().find(a => !a.alterada && diasRestantes(a.data) > 3);
        if (alvo) { const nova = new Date(new Date(alvo.data).getTime() + 3 * MS_DIA); itens.push({ disciplinaId: alvo.disciplinaId, titulo: alvo.titulo, data: toLocalISO(nova) }); }
        itens.push({ disciplinaId: 4, titulo: 'Lista de exercícios — DOM', tipo: 'Trabalho', data: dtLocal(8, 23, 59), descricao: 'Novo item publicado no planejamento.' });
        concluirSync(aplicarImportacao(itens), 'Portal UTFPR (simulado)');
      }, 1400);
    }
    if (e.target.closest('#btnSyncFalha')) {
      registrarSync('falha', 'Não foi possível acessar o portal institucional. Os dados anteriores foram mantidos.');
      salvar(); renderSync(); toast('Sincronização não concluída. Seus dados foram preservados.', 'erro');
    }
    if (e.target.closest('#btnImportar')) importarArquivo();
    if (e.target.closest('#btnModelo')) baixarModelo();
  });

  function toLocalISO(d) {
    const p = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
  }
  function parseData(txt) {
    txt = (txt || '').trim();
    let m = txt.match(/^(\d{2})\/(\d{2})\/(\d{4})[ T]?(\d{2})?:?(\d{2})?/);          // 20/11/2026 19:00
    if (m) return `${m[3]}-${m[2]}-${m[1]}T${m[4] || '23'}:${m[5] || '59'}`;
    m = txt.match(/^(\d{4})-(\d{2})-(\d{2})[ T]?(\d{2})?:?(\d{2})?/);                // 2026-11-20 19:00
    if (m) return `${m[1]}-${m[2]}-${m[3]}T${m[4] || '23'}:${m[5] || '59'}`;
    m = txt.match(/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2}))?/);                     // 20261120T190000
    if (m) return `${m[1]}-${m[2]}-${m[3]}T${m[4] || '23'}:${m[5] || '59'}`;
    return null;
  }
  function achaDisciplina(txt) {
    const t = (txt || '').trim().toLowerCase();
    return DB.disciplinas.find(d => d.codigo.toLowerCase() === t || d.nome.toLowerCase() === t);
  }
  function lerCSV(texto) {
    const linhas = texto.split(/\r?\n/).filter(l => l.trim());
    const sep = (linhas[0] || '').includes(';') ? ';' : ',';
    const itens = []; let invalidas = 0;
    linhas.forEach((l, i) => {
      const c = l.split(sep).map(x => x.trim().replace(/^"|"$/g, ''));
      if (i === 0 && /disciplina/i.test(c[0])) return; // cabeçalho
      const d = achaDisciplina(c[0]); const data = parseData(c[3]);
      if (!d || !c[1] || !data) { invalidas++; return; }   // valida antes de armazenar
      const tipo = ['Prova', 'Trabalho', 'Entrega'].find(t => t.toLowerCase() === (c[2] || '').toLowerCase()) || 'Outro';
      itens.push({ disciplinaId: d.id, titulo: c[1], tipo, data, descricao: c[4] || '' });
    });
    return { itens, invalidas };
  }
  function lerICS(texto, disciplinaId) {
    const itens = []; let invalidas = 0;
    texto.replace(/\r?\n[ \t]/g, '').split('BEGIN:VEVENT').slice(1).forEach(bloco => {
      const campo = nome => (bloco.match(new RegExp('^' + nome + '[^:\\n]*:(.*)$', 'mi')) || [])[1];
      const titulo = (campo('SUMMARY') || '').trim(); const data = parseData(campo('DTSTART'));
      if (!titulo || !data) { invalidas++; return; }
      const tipo = /prova/i.test(titulo) ? 'Prova' : /trabalho/i.test(titulo) ? 'Trabalho' : /entrega/i.test(titulo) ? 'Entrega' : 'Outro';
      itens.push({ disciplinaId, titulo, tipo, data, descricao: (campo('DESCRIPTION') || '').replace(/\\n/g, ' ').trim() });
    });
    return { itens, invalidas };
  }
  /* Remove acentos e põe em minúsculas, para comparar nomes de disciplinas. */
  const norm = t => String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  function detectarDisciplina(texto) {
    const t = norm(texto);
    let melhor = null, pos = Infinity;
    DB.disciplinas.forEach(d => {
      const iNome = t.indexOf(norm(d.nome));
      const mCod = new RegExp('\\b' + d.codigo.toLowerCase() + '\\b').exec(t);
      [iNome, mCod ? mCod.index : -1].filter(i => i >= 0).forEach(i => { if (i < pos) { pos = i; melhor = d; } });
    });
    return melhor;
  }
  function tipoPeloTitulo(t) {
    return /prova|avalia[cç][aã]o|exame/i.test(t) ? 'Prova' : /trabalho|semin[aá]rio|projeto|laborat[oó]rio/i.test(t) ? 'Trabalho' : /entrega|relat[oó]rio|lista/i.test(t) ? 'Entrega' : 'Outro';
  }
  // Lê um PDF de planejamento e devolve as linhas de texto na ordem de leitura.
  async function linhasDoPDF(arquivo) {
    if (!window.pdfjsLib) throw new Error('O leitor de PDF não foi carregado. Verifique a conexão com a internet.');
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    const pdf = await pdfjsLib.getDocument({ data: await arquivo.arrayBuffer() }).promise;
    const saida = [];
    for (let n = 1; n <= pdf.numPages; n++) {
      const conteudo = await (await pdf.getPage(n)).getTextContent();
      const linhas = [];
      conteudo.items.forEach(it => {
        if (!it.str || !it.str.trim()) return;
        const y = it.transform[5], x = it.transform[4];
        let l = linhas.find(l => Math.abs(l.y - y) <= 3);
        if (!l) { l = { y, partes: [] }; linhas.push(l); }
        l.partes.push({ x, t: it.str });
      });
      linhas.sort((a, b) => b.y - a.y).forEach(l => saida.push(l.partes.sort((a, b) => a.x - b.x).map(p => p.t).join(' ').replace(/\s+/g, ' ').trim()));
    }
    return saida;
  }
  // Cada linha com uma data (dd/mm/aaaa, hora opcional) vira uma atividade.
  function lerLinhasPlanejamento(linhas, disciplinaId) {
    const itens = []; let invalidas = 0;
    const reData = /(\d{2})\/(\d{2})\/(\d{4})(?:\s*(?:às|as|-|,)?\s*(\d{1,2})\s*[:h]\s*(\d{2})?)?/i;
    linhas.forEach(l => {
      const m = l.match(reData);
      if (!m) return;                                  // linha sem data: não é atividade
      const titulo = l.replace(reData, ' ').replace(/\s+/g, ' ').replace(/^[\s\-–—:|•·]+|[\s\-–—:|•·]+$/g, '');
      const mes = Number(m[2]), dia = Number(m[1]);
      if (!titulo || mes < 1 || mes > 12 || dia < 1 || dia > 31) { invalidas++; return; }
      const hora = String(m[4] ?? 23).padStart(2, '0'), min = String(m[5] ?? (m[4] ? 0 : 59)).padStart(2, '0');
      itens.push({ disciplinaId, titulo, tipo: tipoPeloTitulo(titulo), data: `${m[3]}-${m[2]}-${m[1]}T${hora}:${min}`, descricao: '' });
    });
    return { itens, invalidas };
  }

  async function importarArquivo() {
    const f = $('#arquivo').files[0];
    if (!f) { toast('Selecione um arquivo .pdf, .csv ou .ics.', 'erro'); return; }
    const btn = $('#btnImportar'); btn.disabled = true;
    try {
      const nome = f.name.toLowerCase();
      const escolhida = Number($('#syncDisc').value) || null;
      let resultado;
      if (nome.endsWith('.csv')) {
        resultado = lerCSV(await f.text());
      } else if (nome.endsWith('.ics') || nome.endsWith('.pdf')) {
        const ehPDF = nome.endsWith('.pdf');
        const linhas = ehPDF ? await linhasDoPDF(f) : null;
        const texto = ehPDF ? linhas.join('\n') : await f.text();
        if (ehPDF && !texto.trim()) throw new Error('Este PDF não tem texto selecionável (parece ser uma imagem escaneada).');
        const d = escolhida ? disc(escolhida) : detectarDisciplina(texto);
        if (!d) throw new Error('Não foi possível identificar a disciplina no arquivo. Escolha-a na lista e tente novamente.');
        resultado = ehPDF ? lerLinhasPlanejamento(linhas, d.id) : lerICS(texto, d.id);
      } else {
        throw new Error('Formato não suportado. Use .pdf, .csv ou .ics.');
      }
      const { itens, invalidas } = resultado;
      if (!itens.length) {
        registrarSync('falha', `Arquivo "${f.name}" sem atividades válidas (${invalidas} ignorada(s)).`);
        salvar(); renderSync(); toast('Nenhuma atividade válida encontrada no arquivo.', 'erro'); return;
      }
      concluirSync(aplicarImportacao(itens), `Arquivo ${f.name}${invalidas ? ` (${invalidas} linha(s) inválida(s) ignorada(s))` : ''}`);
    } catch (err) {
      registrarSync('falha', err.message || 'Erro ao ler o arquivo.');
      salvar(); renderSync(); toast(err.message || 'Erro ao ler o arquivo.', 'erro');
    }
  }
  function baixarModelo() {
    const csv = 'disciplina;titulo;tipo;data;descricao\nARSW;Prova 2 — Arquitetura;Prova;2026-12-05 19:00;Conteúdo do módulo 2\nPWB1;Entrega final — Front end;Entrega;2026-12-10 23:59;Projeto completo\n';
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    a.download = 'modelo-planejamento.csv'; a.click(); URL.revokeObjectURL(a.href);
  }

  /* ---------- Avisos e lembretes (HU11, HU12) ---------- */
  const ICONES = { lembrete: 'bi-alarm', alteracao: 'bi-arrow-repeat', sync: 'bi-check2-circle' };
  function itemAviso(n, compacto = false) {
    return `
      <div class="notif ${n.lida ? '' : 'unread'}" ${n.atividadeId && atv(n.atividadeId) ? `data-atv="${n.atividadeId}" style="cursor:pointer"` : ''}>
        <div class="notif-ico n-${n.tipo}"><i class="bi ${ICONES[n.tipo]}"></i></div>
        <div class="flex-grow-1">
          <div class="fw-bold small">${esc(n.titulo)}</div>
          <div class="small ${compacto ? 'text-muted' : ''}">${esc(n.mensagem)}</div>
          ${compacto ? '' : `<div class="text-muted" style="font-size:.72rem">${fmtDataHoraISO(n.data)}</div>`}
        </div>
      </div>`;
  }
  function renderAvisos() {
    $('#view-notificacoes').innerHTML = `
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="card-soft">
            <div class="section-title">Seus avisos
              <button class="link-btn" id="btnLerTodas">Marcar todas como lidas</button></div>
            ${DB.notificacoes.map(n => itemAviso(n)).join('') || '<p class="text-muted">Nenhum aviso por enquanto.</p>'}
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card-soft">
            <div class="section-title">Preferências</div>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="cfgLembretes" ${DB.config.lembretes ? 'checked' : ''}>
              <label class="form-check-label" for="cfgLembretes">Lembretes de prazos</label>
            </div>
            <label class="form-label small" for="cfgDias">Avisar com antecedência de</label>
            <select id="cfgDias" class="form-select mb-3">
              ${[1, 2, 3, 5, 7].map(n => `<option value="${n}" ${DB.config.antecedenciaDias === n ? 'selected' : ''}>${n} dia${n > 1 ? 's' : ''}</option>`).join('')}
            </select>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="cfgAlt" ${DB.config.avisosAlteracao ? 'checked' : ''}>
              <label class="form-check-label" for="cfgAlt">Avisar quando uma data mudar</label>
            </div>
          </div>
        </div>
      </div>`;
  }
  document.addEventListener('click', e => {
    if (e.target.closest('#btnLerTodas')) {
      DB.notificacoes.forEach(n => n.lida = true); salvar(); atualizarBadges(); renderAvisos();
    }
    // abrir um aviso o marca como lido
    const n = e.target.closest('.notif');
    if (n && n.dataset.atv) {
      const idx = DB.notificacoes.findIndex(x => String(x.atividadeId) === n.dataset.atv && !x.lida);
      if (idx >= 0) { DB.notificacoes[idx].lida = true; salvar(); atualizarBadges(); }
    }
  });
  document.addEventListener('change', e => {
    if (e.target.id === 'cfgLembretes') { DB.config.lembretes = e.target.checked; if (e.target.checked) gerarLembretes(); }
    if (e.target.id === 'cfgDias')      { DB.config.antecedenciaDias = Number(e.target.value); gerarLembretes(); }
    if (e.target.id === 'cfgAlt')       { DB.config.avisosAlteracao = e.target.checked; }
    if (['cfgLembretes', 'cfgDias', 'cfgAlt'].includes(e.target.id)) { salvar(); atualizarBadges(); toast('Preferências salvas.'); renderAvisos(); }
  });

  /* ---------- Perfil (HU2) ---------- */
  function renderPerfil() {
    const e = DB.estudante;
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
            <div class="section-title">Editar informações</div>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Nome</label><input id="pfNome" class="form-control" value="${esc(e.nome)}" required></div>
              <div class="col-md-6"><label class="form-label">E-mail institucional</label><input class="form-control" value="${esc(e.email)}" disabled></div>
              <div class="col-md-6"><label class="form-label">Curso</label><input id="pfCurso" class="form-control" value="${esc(e.curso)}"></div>
              <div class="col-md-6"><label class="form-label">Período letivo</label><input id="pfSem" class="form-control" value="${esc(e.semestre)}"></div>
            </div>
            <div class="mt-4 d-flex gap-2 flex-wrap">
              <button class="btn btn-primary" type="submit">Salvar alterações</button>
              <button class="btn btn-danger-soft" type="button" id="btnResetar">Restaurar dados de exemplo</button>
            </div>
          </form>
        </div>
      </div>`;
  }
  document.addEventListener('submit', e => {
    if (e.target.id !== 'formPerfil') return;
    e.preventDefault();
    DB.estudante.nome = $('#pfNome').value.trim() || DB.estudante.nome;
    DB.estudante.curso = $('#pfCurso').value.trim();
    DB.estudante.semestre = $('#pfSem').value.trim();
    salvar(); renderPerfil(); atualizarTopo(); toast('Perfil atualizado.');
  });
  document.addEventListener('click', e => {
    if (e.target.closest('#btnResetar') && confirm('Apagar seus dados locais e voltar aos dados de exemplo?')) {
      localStorage.removeItem(KEY); location.reload();
    }
  });

  /* ---------- Inicialização ---------- */
  function atualizarTopo() { $('#topAvatar').textContent = iniciais(DB.estudante.nome); }
  function atualizarTudo() {
    atualizarBadges(); atualizarTopo();
    if (calendario) calendario.refetchEvents();
    ({ home: renderHome, calendario: renderCalendario, disciplinas: renderDisciplinas, sync: renderSync, notificacoes: renderAvisos, perfil: renderPerfil })[viewAtual]();
  }
  function iniciarApp() {
    gerarLembretes();
    atualizarBadges(); atualizarTopo();
    ir('home');
  }
  mostrarTela();
})();
