-- Categorías adicionales para las propuestas de la comunidad.
-- No elimina ni renombra categorías ni publicaciones existentes.

INSERT IGNORE INTO categories (name, slug, sort_order) VALUES
('Modpacks', 'modpacks', 13),
('Addons', 'addons', 14),
('Scripts', 'scripts', 15);

-- Activar categorías necesarias para Java y Bedrock.
UPDATE categories
SET is_active = 1
WHERE slug IN ('shaders', 'texture-packs');
