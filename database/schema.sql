-- DDL for required tables

CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    login VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE turmas (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE matriculas (
    id SERIAL PRIMARY KEY,
    usuario_id INT REFERENCES usuarios (id) ON DELETE CASCADE,
    turma_id INT REFERENCES turmas (id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE pagamentos (
    id SERIAL PRIMARY KEY,
    matricula_id INT REFERENCES matriculas (id) ON DELETE CASCADE,
    valor DECIMAL(10, 2) NOT NULL,
    data_pagamento TIMESTAMP DEFAULT NOW()
);

CREATE TABLE frequencia (
    id SERIAL PRIMARY KEY,
    matricula_id INT REFERENCES matriculas (id) ON DELETE CASCADE,
    data TIMESTAMP NOT NULL,
    presente BOOLEAN NOT NULL
);

CREATE TABLE permissoes (
    id SERIAL PRIMARY KEY,
    usuario_id INT REFERENCES usuarios (id) ON DELETE CASCADE,
    permissao VARCHAR(100) NOT NULL
);

CREATE TABLE tecnicas_checklist (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL
);

CREATE TABLE aluna_conquistas (
    id SERIAL PRIMARY KEY,
    usuario_id INT REFERENCES usuarios (id) ON DELETE CASCADE,
    conquista VARCHAR(100) NOT NULL,
    data TIMESTAMP DEFAULT NOW()
);

CREATE TABLE fila_notificacoes (
    id SERIAL PRIMARY KEY,
    usuario_id INT REFERENCES usuarios (id) ON DELETE CASCADE,
    mensagem TEXT NOT NULL,
    data TIMESTAMP DEFAULT NOW()
);

CREATE TABLE evolucao_fisica (
    id SERIAL PRIMARY KEY,
    usuario_id INT REFERENCES usuarios (id) ON DELETE CASCADE,
    data TIMESTAMP NOT NULL,
    descricao TEXT
);

-- Insert default admin user
INSERT INTO usuarios (login, password) VALUES ('admin', password_hash('konex2026'));
