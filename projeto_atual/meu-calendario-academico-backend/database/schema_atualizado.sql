--
-- PostgreSQL database dump
--

\restrict 58DTe3mjra1QzkxwcZvwsEDsKPgn8GyX15eC7abmdJ23eRnY7Xw5hdiSqP9bNBM

-- Dumped from database version 18.7
-- Dumped by pg_dump version 18.7

-- Started on 2026-11-19 21:00:54

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_table_access_method = heap;

--
-- TOC entry 226 (class 1259 OID 16443)
-- Name: atividade; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.atividade (
    id_atividade integer NOT NULL,
    id_disciplina integer NOT NULL,
    titulo character varying(150) NOT NULL,
    descricao text,
    tipo character varying(40) NOT NULL,
    data_hora timestamp with time zone NOT NULL,
    codigo_atv character varying(50) NOT NULL,
    atualizado_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    status character varying(12) DEFAULT 'PUBLICADO'::character varying NOT NULL,
    CONSTRAINT ck_atividade_status CHECK (((status)::text = ANY ((ARRAY['RASCUNHO'::character varying, 'PUBLICADO'::character varying])::text[])))
);


--
-- TOC entry 240 (class 1259 OID 16621)
-- Name: atividade_conclusao; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.atividade_conclusao (
    id_estudante integer NOT NULL,
    id_atividade integer NOT NULL,
    concluida_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- TOC entry 225 (class 1259 OID 16442)
-- Name: atividade_id_atividade_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.atividade ALTER COLUMN id_atividade ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.atividade_id_atividade_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 236 (class 1259 OID 16560)
-- Name: conta; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.conta (
    id_conta integer NOT NULL,
    email character varying(150) NOT NULL,
    senha character varying(255) NOT NULL,
    papel character varying(12) NOT NULL,
    criado_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT conta_papel_check CHECK (((papel)::text = ANY ((ARRAY['ALUNO'::character varying, 'PROFESSOR'::character varying])::text[])))
);


--
-- TOC entry 235 (class 1259 OID 16559)
-- Name: conta_id_conta_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.conta ALTER COLUMN id_conta ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.conta_id_conta_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 222 (class 1259 OID 16407)
-- Name: disciplina; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.disciplina (
    id_disciplina integer NOT NULL,
    nome character varying(150) NOT NULL,
    codigo_disc character varying(30) NOT NULL
);


--
-- TOC entry 221 (class 1259 OID 16406)
-- Name: disciplina_id_disciplina_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.disciplina ALTER COLUMN id_disciplina ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.disciplina_id_disciplina_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 220 (class 1259 OID 16391)
-- Name: estudante; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.estudante (
    id_estudante integer NOT NULL,
    nome character varying(120) NOT NULL,
    email character varying(150) NOT NULL,
    senha character varying(255) NOT NULL,
    criado_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    id_conta integer
);


--
-- TOC entry 219 (class 1259 OID 16390)
-- Name: estudante_id_estudante_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.estudante ALTER COLUMN id_estudante ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.estudante_id_estudante_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 230 (class 1259 OID 16485)
-- Name: historico_alteracao; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.historico_alteracao (
    id_historico integer NOT NULL,
    id_atividade integer NOT NULL,
    descricao_alteracao text NOT NULL,
    data_alteracao timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- TOC entry 229 (class 1259 OID 16484)
-- Name: historico_alteracao_id_historico_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.historico_alteracao ALTER COLUMN id_historico ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.historico_alteracao_id_historico_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 232 (class 1259 OID 16503)
-- Name: lembrete; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lembrete (
    id_lembrete integer NOT NULL,
    id_estudante integer NOT NULL,
    id_atividade integer NOT NULL,
    data_lembrete timestamp with time zone NOT NULL,
    ativo boolean DEFAULT true NOT NULL
);


--
-- TOC entry 231 (class 1259 OID 16502)
-- Name: lembrete_id_lembrete_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.lembrete ALTER COLUMN id_lembrete ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.lembrete_id_lembrete_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 224 (class 1259 OID 16418)
-- Name: matricula; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matricula (
    id_matricula integer NOT NULL,
    id_estudante integer NOT NULL,
    id_disciplina integer NOT NULL,
    semestre smallint NOT NULL,
    ano smallint NOT NULL,
    CONSTRAINT matricula_ano_check CHECK (((ano >= 2020) AND (ano <= 2100))),
    CONSTRAINT matricula_semestre_check CHECK ((semestre = ANY (ARRAY[1, 2])))
);


--
-- TOC entry 223 (class 1259 OID 16417)
-- Name: matricula_id_matricula_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.matricula ALTER COLUMN id_matricula ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.matricula_id_matricula_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 234 (class 1259 OID 16525)
-- Name: notificacao; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notificacao (
    id_notificacao integer NOT NULL,
    id_estudante integer NOT NULL,
    id_atividade integer NOT NULL,
    mensagem text NOT NULL,
    tipo character varying(40) NOT NULL,
    data_envio timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    visualizacao boolean DEFAULT false NOT NULL,
    chave_evento character varying(180)
);


--
-- TOC entry 233 (class 1259 OID 16524)
-- Name: notificacao_id_notificacao_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.notificacao ALTER COLUMN id_notificacao ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.notificacao_id_notificacao_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 238 (class 1259 OID 16580)
-- Name: professor; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.professor (
    id_professor integer NOT NULL,
    id_conta integer NOT NULL,
    nome character varying(120) NOT NULL,
    criado_em timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- TOC entry 239 (class 1259 OID 16597)
-- Name: professor_disciplina; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.professor_disciplina (
    id_professor integer NOT NULL,
    id_disciplina integer NOT NULL
);


--
-- TOC entry 237 (class 1259 OID 16579)
-- Name: professor_id_professor_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.professor ALTER COLUMN id_professor ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.professor_id_professor_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 228 (class 1259 OID 16466)
-- Name: sincronizacao; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sincronizacao (
    id_sincronizacao integer NOT NULL,
    id_disciplina integer NOT NULL,
    data_execucao timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    resultado character varying(30) NOT NULL,
    detalhes text,
    CONSTRAINT sincronizacao_resultado_check CHECK (((resultado)::text = ANY ((ARRAY['SUCESSO'::character varying, 'FALHA'::character varying, 'PARCIAL'::character varying])::text[])))
);


--
-- TOC entry 227 (class 1259 OID 16465)
-- Name: sincronizacao_id_sincronizacao_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.sincronizacao ALTER COLUMN id_sincronizacao ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.sincronizacao_id_sincronizacao_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 4941 (class 2606 OID 16457)
-- Name: atividade atividade_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.atividade
    ADD CONSTRAINT atividade_pkey PRIMARY KEY (id_atividade);


--
-- TOC entry 4962 (class 2606 OID 16571)
-- Name: conta conta_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conta
    ADD CONSTRAINT conta_pkey PRIMARY KEY (id_conta);


--
-- TOC entry 4931 (class 2606 OID 16416)
-- Name: disciplina disciplina_codigo_disc_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.disciplina
    ADD CONSTRAINT disciplina_codigo_disc_key UNIQUE (codigo_disc);


--
-- TOC entry 4933 (class 2606 OID 16414)
-- Name: disciplina disciplina_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.disciplina
    ADD CONSTRAINT disciplina_pkey PRIMARY KEY (id_disciplina);


--
-- TOC entry 4926 (class 2606 OID 16405)
-- Name: estudante estudante_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudante
    ADD CONSTRAINT estudante_email_key UNIQUE (email);


--
-- TOC entry 4928 (class 2606 OID 16403)
-- Name: estudante estudante_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudante
    ADD CONSTRAINT estudante_pkey PRIMARY KEY (id_estudante);


--
-- TOC entry 4951 (class 2606 OID 16496)
-- Name: historico_alteracao historico_alteracao_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.historico_alteracao
    ADD CONSTRAINT historico_alteracao_pkey PRIMARY KEY (id_historico);


--
-- TOC entry 4955 (class 2606 OID 16513)
-- Name: lembrete lembrete_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lembrete
    ADD CONSTRAINT lembrete_pkey PRIMARY KEY (id_lembrete);


--
-- TOC entry 4937 (class 2606 OID 16429)
-- Name: matricula matricula_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matricula
    ADD CONSTRAINT matricula_pkey PRIMARY KEY (id_matricula);


--
-- TOC entry 4959 (class 2606 OID 16540)
-- Name: notificacao notificacao_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificacao
    ADD CONSTRAINT notificacao_pkey PRIMARY KEY (id_notificacao);


--
-- TOC entry 4972 (class 2606 OID 16629)
-- Name: atividade_conclusao pk_atividade_conclusao; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.atividade_conclusao
    ADD CONSTRAINT pk_atividade_conclusao PRIMARY KEY (id_estudante, id_atividade);


--
-- TOC entry 4970 (class 2606 OID 16603)
-- Name: professor_disciplina professor_disciplina_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professor_disciplina
    ADD CONSTRAINT professor_disciplina_pkey PRIMARY KEY (id_professor, id_disciplina);


--
-- TOC entry 4965 (class 2606 OID 16591)
-- Name: professor professor_id_conta_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professor
    ADD CONSTRAINT professor_id_conta_key UNIQUE (id_conta);


--
-- TOC entry 4967 (class 2606 OID 16589)
-- Name: professor professor_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professor
    ADD CONSTRAINT professor_pkey PRIMARY KEY (id_professor);


--
-- TOC entry 4949 (class 2606 OID 16478)
-- Name: sincronizacao sincronizacao_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sincronizacao
    ADD CONSTRAINT sincronizacao_pkey PRIMARY KEY (id_sincronizacao);


--
-- TOC entry 4946 (class 2606 OID 16459)
-- Name: atividade uq_atividade_disciplina_codigo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.atividade
    ADD CONSTRAINT uq_atividade_disciplina_codigo UNIQUE (id_disciplina, codigo_atv);


--
-- TOC entry 4939 (class 2606 OID 16431)
-- Name: matricula uq_matricula_periodo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matricula
    ADD CONSTRAINT uq_matricula_periodo UNIQUE (id_estudante, id_disciplina, ano, semestre);


--
-- TOC entry 4942 (class 1259 OID 16554)
-- Name: idx_atividade_data_hora; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_atividade_data_hora ON public.atividade USING btree (data_hora);


--
-- TOC entry 4943 (class 1259 OID 16553)
-- Name: idx_atividade_disciplina; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_atividade_disciplina ON public.atividade USING btree (id_disciplina);


--
-- TOC entry 4944 (class 1259 OID 16618)
-- Name: idx_atividade_status_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_atividade_status_data ON public.atividade USING btree (status, data_hora);


--
-- TOC entry 4952 (class 1259 OID 16557)
-- Name: idx_historico_atividade; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_historico_atividade ON public.historico_alteracao USING btree (id_atividade);


--
-- TOC entry 4953 (class 1259 OID 16556)
-- Name: idx_lembrete_ativo_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_lembrete_ativo_data ON public.lembrete USING btree (ativo, data_lembrete);


--
-- TOC entry 4934 (class 1259 OID 16552)
-- Name: idx_matricula_disciplina; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_matricula_disciplina ON public.matricula USING btree (id_disciplina);


--
-- TOC entry 4935 (class 1259 OID 16551)
-- Name: idx_matricula_estudante; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_matricula_estudante ON public.matricula USING btree (id_estudante);


--
-- TOC entry 4956 (class 1259 OID 16555)
-- Name: idx_notificacao_estudante_lida; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificacao_estudante_lida ON public.notificacao USING btree (id_estudante, visualizacao);


--
-- TOC entry 4957 (class 1259 OID 16620)
-- Name: idx_notificacao_usuario_data; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notificacao_usuario_data ON public.notificacao USING btree (id_estudante, data_envio DESC);


--
-- TOC entry 4968 (class 1259 OID 16614)
-- Name: idx_professor_disciplina_disciplina; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_professor_disciplina_disciplina ON public.professor_disciplina USING btree (id_disciplina);


--
-- TOC entry 4947 (class 1259 OID 16558)
-- Name: idx_sincronizacao_disciplina; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sincronizacao_disciplina ON public.sincronizacao USING btree (id_disciplina);


--
-- TOC entry 4963 (class 1259 OID 16572)
-- Name: uq_conta_email_normalizado; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_conta_email_normalizado ON public.conta USING btree (lower((email)::text));


--
-- TOC entry 4929 (class 1259 OID 16573)
-- Name: uq_estudante_id_conta; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_estudante_id_conta ON public.estudante USING btree (id_conta);


--
-- TOC entry 4960 (class 1259 OID 16619)
-- Name: uq_notificacao_chave_evento; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_notificacao_chave_evento ON public.notificacao USING btree (id_estudante, chave_evento) WHERE (chave_evento IS NOT NULL);


--
-- TOC entry 4976 (class 2606 OID 16460)
-- Name: atividade fk_atividade_disciplina; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.atividade
    ADD CONSTRAINT fk_atividade_disciplina FOREIGN KEY (id_disciplina) REFERENCES public.disciplina(id_disciplina) ON DELETE CASCADE;


--
-- TOC entry 4986 (class 2606 OID 16635)
-- Name: atividade_conclusao fk_conclusao_atividade; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.atividade_conclusao
    ADD CONSTRAINT fk_conclusao_atividade FOREIGN KEY (id_atividade) REFERENCES public.atividade(id_atividade) ON DELETE CASCADE;


--
-- TOC entry 4987 (class 2606 OID 16630)
-- Name: atividade_conclusao fk_conclusao_estudante; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.atividade_conclusao
    ADD CONSTRAINT fk_conclusao_estudante FOREIGN KEY (id_estudante) REFERENCES public.estudante(id_estudante) ON DELETE CASCADE;


--
-- TOC entry 4973 (class 2606 OID 16574)
-- Name: estudante fk_estudante_conta; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.estudante
    ADD CONSTRAINT fk_estudante_conta FOREIGN KEY (id_conta) REFERENCES public.conta(id_conta) ON DELETE SET NULL;


--
-- TOC entry 4978 (class 2606 OID 16497)
-- Name: historico_alteracao fk_historico_atividade; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.historico_alteracao
    ADD CONSTRAINT fk_historico_atividade FOREIGN KEY (id_atividade) REFERENCES public.atividade(id_atividade) ON DELETE CASCADE;


--
-- TOC entry 4979 (class 2606 OID 16519)
-- Name: lembrete fk_lembrete_atividade; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lembrete
    ADD CONSTRAINT fk_lembrete_atividade FOREIGN KEY (id_atividade) REFERENCES public.atividade(id_atividade) ON DELETE CASCADE;


--
-- TOC entry 4980 (class 2606 OID 16514)
-- Name: lembrete fk_lembrete_estudante; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lembrete
    ADD CONSTRAINT fk_lembrete_estudante FOREIGN KEY (id_estudante) REFERENCES public.estudante(id_estudante) ON DELETE CASCADE;


--
-- TOC entry 4974 (class 2606 OID 16437)
-- Name: matricula fk_matricula_disciplina; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matricula
    ADD CONSTRAINT fk_matricula_disciplina FOREIGN KEY (id_disciplina) REFERENCES public.disciplina(id_disciplina) ON DELETE CASCADE;


--
-- TOC entry 4975 (class 2606 OID 16432)
-- Name: matricula fk_matricula_estudante; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matricula
    ADD CONSTRAINT fk_matricula_estudante FOREIGN KEY (id_estudante) REFERENCES public.estudante(id_estudante) ON DELETE CASCADE;


--
-- TOC entry 4981 (class 2606 OID 16546)
-- Name: notificacao fk_notificacao_atividade; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificacao
    ADD CONSTRAINT fk_notificacao_atividade FOREIGN KEY (id_atividade) REFERENCES public.atividade(id_atividade) ON DELETE CASCADE;


--
-- TOC entry 4982 (class 2606 OID 16541)
-- Name: notificacao fk_notificacao_estudante; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificacao
    ADD CONSTRAINT fk_notificacao_estudante FOREIGN KEY (id_estudante) REFERENCES public.estudante(id_estudante) ON DELETE CASCADE;


--
-- TOC entry 4977 (class 2606 OID 16479)
-- Name: sincronizacao fk_sincronizacao_disciplina; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sincronizacao
    ADD CONSTRAINT fk_sincronizacao_disciplina FOREIGN KEY (id_disciplina) REFERENCES public.disciplina(id_disciplina) ON DELETE CASCADE;


--
-- TOC entry 4984 (class 2606 OID 16609)
-- Name: professor_disciplina professor_disciplina_id_disciplina_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professor_disciplina
    ADD CONSTRAINT professor_disciplina_id_disciplina_fkey FOREIGN KEY (id_disciplina) REFERENCES public.disciplina(id_disciplina) ON DELETE CASCADE;


--
-- TOC entry 4985 (class 2606 OID 16604)
-- Name: professor_disciplina professor_disciplina_id_professor_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professor_disciplina
    ADD CONSTRAINT professor_disciplina_id_professor_fkey FOREIGN KEY (id_professor) REFERENCES public.professor(id_professor) ON DELETE CASCADE;


--
-- TOC entry 4983 (class 2606 OID 16592)
-- Name: professor professor_id_conta_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.professor
    ADD CONSTRAINT professor_id_conta_fkey FOREIGN KEY (id_conta) REFERENCES public.conta(id_conta) ON DELETE CASCADE;


-- Completed on 2026-11-19 21:00:54

--
-- PostgreSQL database dump complete
--

\unrestrict 58DTe3mjra1QzkxwcZvwsEDsKPgn8GyX15eC7abmdJ23eRnY7Xw5hdiSqP9bNBM
