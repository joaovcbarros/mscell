-- =====================================================================
-- MsCell - Dados iniciais
-- Rode este arquivo DEPOIS do schema.sql
--
-- IMPORTANTE: rode com --default-character-set=utf8mb4, senão os
-- acentos (ex: "Película", "Acessórios") ficam corrompidos no cliente
-- mysql no Windows. Exemplo:
--   mysql --default-character-set=utf8mb4 -u root < seed.sql
-- =====================================================================

USE mscell;

-- Loja inicial. Cadastre as demais lojas pela tela "Lojas" (admin) depois
-- do primeiro login. numero_whatsapp e o numero que vai parear com a
-- ponte Node dessa loja (ver whatsapp-bridge/).
INSERT INTO lojas (nome, numero_whatsapp) VALUES ('Loja Principal', NULL);

-- Usuário admin inicial (sem loja_id: admin enxerga/gerencia todas as lojas)
-- Login:  admin@mscell.local
-- Senha:  MsCell@2026   (troque assim que fizer o primeiro login)
INSERT INTO usuarios (nome, email, senha_hash, papel, loja_id, ativo)
VALUES (
    'Administrador',
    'admin@mscell.local',
    '$2y$10$Y0UiRvm6g.OOyhewio/6mO1CoelhkA/oQYOJxCTAJN2IqfvtA38yq',
    'admin',
    NULL,
    1
);

-- Categorias de exemplo (compartilhadas entre todas as lojas)
INSERT INTO categorias (nome) VALUES
    ('Celulares'),
    ('Acessórios'),
    ('Peças'),
    ('Serviços');

-- Produtos de exemplo, todos na Loja Principal (id 1)
-- (o campo "apelidos" ajuda o parser do WhatsApp a reconhecer o produto
-- em mensagens de texto livre)
INSERT INTO produtos (loja_id, nome, apelidos, categoria_id, sku, preco_custo, preco_venda, quantidade_estoque, estoque_minimo, ativo)
VALUES
    (1, 'iPhone 15 Pro Max', 'iphone 15 pro max|15 pro max|15pm', 1, 'IPH15PM', 4200.00, 5000.00, 3, 1, 1),
    (1, 'iPhone 13', 'iphone 13|13 comum', 1, 'IPH13', 2200.00, 2800.00, 5, 2, 1),
    (1, 'Fone Bluetooth JBL', 'fone bluetooth|fone jbl|jbl', 2, 'FONEJBL', 60.00, 120.00, 15, 5, 1),
    (1, 'Película de Vidro', 'pelicula|pelicula de vidro|vidro', 2, 'PEL001', 5.00, 25.00, 50, 10, 1),
    (1, 'Troca de Tela iPhone 13', 'troca de tela 13|tela iphone 13', 3, 'SERV-TELA13', 350.00, 600.00, 999, 0, 1);
