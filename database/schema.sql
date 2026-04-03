-- =====================================================================
-- DDL Completo — Sistema Guerreiras Thai (MySQL / InnoDB)
-- Banco: iubsit15_academia
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------
-- 1. Usuarios (Alunas, Treinadoras, Admin)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(120)  NOT NULL,
    login         VARCHAR(50)   NOT NULL UNIQUE,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    senha         VARCHAR(255)  NOT NULL COMMENT 'password_hash() do PHP',
    whatsapp      VARCHAR(20)   DEFAULT NULL,
    data_nasc     DATE          DEFAULT NULL,
    cpf           VARCHAR(14)   DEFAULT NULL UNIQUE,
    nivel         ENUM('admin','treinadora','aluna') NOT NULL DEFAULT 'aluna',
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 2. Turmas
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS turmas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(100)  NOT NULL,
    descricao     TEXT          DEFAULT NULL,
    horario       VARCHAR(50)   DEFAULT NULL,
    dia_semana    VARCHAR(80)   DEFAULT NULL,
    max_alunas    INT UNSIGNED  DEFAULT 20,
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 3. Matriculas
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS matriculas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED  NOT NULL,
    turma_id      INT UNSIGNED  NOT NULL,
    data_inicio   DATE          NOT NULL,
    data_fim      DATE          DEFAULT NULL,
    status        ENUM('ativa','cancelada','trancada') NOT NULL DEFAULT 'ativa',
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id)   REFERENCES turmas(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 4. Pagamentos
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagamentos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricula_id  INT UNSIGNED  NOT NULL,
    valor         DECIMAL(10,2) NOT NULL,
    data_venc     DATE          NOT NULL,
    data_pgto     DATE          DEFAULT NULL,
    metodo        ENUM('pix','cartao','dinheiro','boleto') DEFAULT NULL,
    status        ENUM('pendente','pago','atrasado','cancelado') NOT NULL DEFAULT 'pendente',
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 5. Frequencia
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS frequencia (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED  NOT NULL,
    turma_id      INT UNSIGNED  NOT NULL,
    data_aula     DATE          NOT NULL,
    presente      TINYINT(1)    NOT NULL DEFAULT 0,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_freq (usuario_id, turma_id, data_aula),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (turma_id)   REFERENCES turmas(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 6. Permissoes
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permissoes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED  NOT NULL,
    permissao     VARCHAR(100)  NOT NULL,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_perm (usuario_id, permissao),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 7. Tecnicas_Checklist
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tecnicas_checklist (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(100)  NOT NULL,
    categoria     VARCHAR(60)   DEFAULT NULL COMMENT 'ex: Soco, Chute, Defesa, Clinch',
    nivel_minimo  TINYINT UNSIGNED DEFAULT 1,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 8. Aluna_Conquistas (Badges / Nivelamento)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS aluna_conquistas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED  NOT NULL,
    tecnica_id    INT UNSIGNED  DEFAULT NULL,
    conquista     VARCHAR(120)  NOT NULL,
    data_conquista DATE         NOT NULL,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)            ON DELETE CASCADE,
    FOREIGN KEY (tecnica_id) REFERENCES tecnicas_checklist(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 9. Fila_Notificacoes (Bot WhatsApp)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fila_notificacoes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED  NOT NULL,
    tipo          VARCHAR(60)   DEFAULT 'geral',
    mensagem      TEXT          NOT NULL,
    status        ENUM('pendente','enviada','erro') NOT NULL DEFAULT 'pendente',
    tentativas    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_em    DATETIME      DEFAULT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- 10. Evolucao_Fisica (Saúde / Anamnese)
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS evolucao_fisica (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT UNSIGNED  NOT NULL,
    data_registro    DATE          NOT NULL,
    peso_kg          DECIMAL(5,2)  DEFAULT NULL,
    altura_cm        DECIMAL(5,1)  DEFAULT NULL,
    bf_percent       DECIMAL(4,1)  DEFAULT NULL COMMENT 'Body Fat %',
    cintura_cm       DECIMAL(5,1)  DEFAULT NULL,
    quadril_cm       DECIMAL(5,1)  DEFAULT NULL,
    braco_cm         DECIMAL(5,1)  DEFAULT NULL,
    coxa_cm          DECIMAL(5,1)  DEFAULT NULL,
    restricoes_med   TEXT          DEFAULT NULL COMMENT 'Restrições médicas / anamnese',
    observacoes      TEXT          DEFAULT NULL,
    criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------
-- Dados iniciais: Técnicas padrão de Muay Thai
-- -----------------------------------------------------------------
INSERT INTO tecnicas_checklist (nome, categoria, nivel_minimo) VALUES
('Jab',                    'Soco',   1),
('Direto',                 'Soco',   1),
('Cruzado',                'Soco',   1),
('Uppercut',               'Soco',   2),
('Chute Baixo (Low Kick)', 'Chute',  1),
('Chute Médio',            'Chute',  1),
('Chute Alto (Head Kick)', 'Chute',  2),
('Teep (Empurrão)',        'Chute',  1),
('Joelhada',               'Joelho', 2),
('Cotovelada',             'Cotovelo',3),
('Clinch Básico',          'Clinch', 2),
('Clinch com Joelhada',    'Clinch', 3),
('Defesa de Chute',        'Defesa', 1),
('Defesa de Soco',         'Defesa', 1),
('Esquiva',                'Defesa', 2);

SET FOREIGN_KEY_CHECKS = 1;

-- NOTA: O INSERT do administrador padrão deve ser feito pelo setup.php
-- usando password_hash() do PHP, pois MySQL não possui essa função.
