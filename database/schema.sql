-- =====================================================================
-- MsCell - Sistema de Gestão de Produtos, Vendas e Estoque
-- Schema do banco de dados (MySQL / MariaDB)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS mscell
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE mscell;

-- ---------------------------------------------------------------------
-- lojas: cada loja fisica do negocio. Cada uma tem seu proprio catalogo
-- de produtos/estoque e, opcionalmente, seu proprio numero de WhatsApp
-- (usado pela ponte Node para saber de qual loja veio a mensagem).
-- ---------------------------------------------------------------------
CREATE TABLE lojas (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome             VARCHAR(120) NOT NULL,
    endereco         VARCHAR(255) NULL,
    numero_whatsapp  VARCHAR(30) NULL UNIQUE,
    ativa            TINYINT(1) NOT NULL DEFAULT 1,
    criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- usuarios: contas de acesso ao sistema (admin / funcionario / usuario)
-- loja_id: loja a qual o usuario esta vinculado. NULL so faz sentido
-- para papel "admin" (enxerga/gerencia todas as lojas); funcionario e
-- usuario sempre tem uma loja definida.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    senha_hash      VARCHAR(255) NOT NULL,
    papel           ENUM('admin', 'funcionario', 'usuario') NOT NULL DEFAULT 'usuario',
    loja_id         INT UNSIGNED NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_loja FOREIGN KEY (loja_id) REFERENCES lojas(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- categorias: agrupamento de produtos (celulares, acessórios, peças...)
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome    VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- produtos: catálogo com controle de estoque, sempre pertencente a uma
-- loja (cada loja tem seu proprio catalogo/estoque independente).
-- apelidos: termos alternativos separados por "|" usados para casar
-- mensagens livres do WhatsApp com o produto certo
-- (ex: "iphone 15 pro max|15 pro max|15pm")
-- ---------------------------------------------------------------------
CREATE TABLE produtos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id             INT UNSIGNED NOT NULL,
    nome                VARCHAR(150) NOT NULL,
    apelidos            VARCHAR(255) NULL,
    categoria_id        INT UNSIGNED NULL,
    sku                 VARCHAR(60) NULL,
    preco_custo         DECIMAL(10,2) NOT NULL DEFAULT 0,
    preco_venda         DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantidade_estoque  INT NOT NULL DEFAULT 0,
    estoque_minimo      INT NOT NULL DEFAULT 0,
    ativo               TINYINT(1) NOT NULL DEFAULT 1,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_produtos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_produtos_loja FOREIGN KEY (loja_id) REFERENCES lojas(id),
    INDEX idx_produtos_nome (nome),
    INDEX idx_produtos_ativo (ativo),
    INDEX idx_produtos_loja (loja_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- vendas: cabeçalho de cada venda (manual ou via WhatsApp), sempre
-- vinculada a loja onde ocorreu.
-- ---------------------------------------------------------------------
CREATE TABLE vendas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loja_id         INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NULL,
    cliente_nome    VARCHAR(150) NULL,
    forma_pagamento ENUM('dinheiro', 'pix', 'debito', 'credito', 'outro') NOT NULL DEFAULT 'outro',
    valor_total     DECIMAL(10,2) NOT NULL DEFAULT 0,
    origem          ENUM('sistema', 'whatsapp') NOT NULL DEFAULT 'sistema',
    status          ENUM('concluida', 'cancelada') NOT NULL DEFAULT 'concluida',
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vendas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_vendas_loja FOREIGN KEY (loja_id) REFERENCES lojas(id),
    INDEX idx_vendas_criado_em (criado_em),
    INDEX idx_vendas_origem (origem),
    INDEX idx_vendas_loja (loja_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- itens_venda: produtos vendidos em cada venda
-- ---------------------------------------------------------------------
CREATE TABLE itens_venda (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venda_id        INT UNSIGNED NOT NULL,
    produto_id      INT UNSIGNED NOT NULL,
    quantidade      INT NOT NULL DEFAULT 1,
    preco_unitario  DECIMAL(10,2) NOT NULL,
    subtotal        DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_itens_venda_venda FOREIGN KEY (venda_id) REFERENCES vendas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_itens_venda_produto FOREIGN KEY (produto_id) REFERENCES produtos(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- movimentacoes_estoque: histórico de entradas/saídas/ajustes
-- ---------------------------------------------------------------------
CREATE TABLE movimentacoes_estoque (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    produto_id  INT UNSIGNED NOT NULL,
    tipo        ENUM('entrada', 'saida', 'ajuste') NOT NULL,
    quantidade  INT NOT NULL,
    motivo      VARCHAR(255) NULL,
    venda_id    INT UNSIGNED NULL,
    usuario_id  INT UNSIGNED NULL,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_produto FOREIGN KEY (produto_id) REFERENCES produtos(id),
    CONSTRAINT fk_mov_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE SET NULL,
    CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_mov_produto (produto_id),
    INDEX idx_mov_criado_em (criado_em)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- whatsapp_mensagens_log: auditoria de mensagens recebidas e fila de
-- revisão para o que o parser não conseguiu interpretar com confiança
-- loja_id e resolvida a partir de lojas.numero_whatsapp (quem recebeu
-- a mensagem), null se o numero nao pertencer a nenhuma loja cadastrada.
-- ---------------------------------------------------------------------
CREATE TABLE whatsapp_mensagens_log (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_origem       VARCHAR(30) NOT NULL,
    loja_id             INT UNSIGNED NULL,
    mensagem_bruta      TEXT NOT NULL,
    interpretacao_json  JSON NULL,
    venda_id            INT UNSIGNED NULL,
    status              ENUM('processada', 'revisao', 'falha') NOT NULL DEFAULT 'revisao',
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_wpp_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE SET NULL,
    CONSTRAINT fk_wpp_log_loja FOREIGN KEY (loja_id) REFERENCES lojas(id),
    INDEX idx_wpp_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- whatsapp_pendencias: quando o parser acha um valor mas nao reconhece
-- o produto, fica aguardando aqui uma confirmacao (proxima mensagem do
-- mesmo numero) para cadastrar o produto novo (na loja correta) e
-- registrar a venda.
-- ---------------------------------------------------------------------
CREATE TABLE whatsapp_pendencias (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_origem   VARCHAR(30) NOT NULL,
    loja_id         INT UNSIGNED NULL,
    texto_produto   VARCHAR(255) NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    quantidade      INT NOT NULL DEFAULT 1,
    status          ENUM('aguardando', 'confirmada', 'cancelada', 'expirada') NOT NULL DEFAULT 'aguardando',
    criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_em       DATETIME NOT NULL,
    CONSTRAINT fk_wpp_pend_loja FOREIGN KEY (loja_id) REFERENCES lojas(id),
    INDEX idx_pendencias_numero_status (numero_origem, status)
) ENGINE=InnoDB;
