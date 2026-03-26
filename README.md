# CBIAStudio BlogFlow with AI (WordPress) v1.2.0

## Novedades 1.2.0
- Archivo principal estandarizado como `cbiastudio-blogflow-ai.php` para WordPress.org y dependencias PRO/FREE mas limpias.
- Ajustes de `Usage/Costes` para usar mejor el modelo/proveedor real, con tarifas actualizadas y calculo mas fiable del coste real.
- Matriz de proveedores/modelos afinada: alias nuevos de OpenAI, Gemini/Imagen actualizados y DeepSeek limitado a texto.
- Flujo de preview/create afinado para devolver `preview_url` valida en borradores programados.

## Novedades 1.1.7
- Migración de scripts/estilos inline a APIs nativas de WordPress (`wp_add_inline_script` / `wp_add_inline_style`).
- Refuerzo de nonces en AJAX (`check_ajax_referer` obligatorio en stream/start).
- Endurecimiento de sanitización en formularios Config/Blog/Oldposts.
- Ajuste de cabecera de plugin (sin `Domain Path`) y documentación de servicios externos con dominios explícitos.

## Novedades 1.1.6
- Fix de sintaxis PHP en `includes/admin/views/costs.php` para cumplir validaciones del directorio de plugins.

## Novedades 1.1.4
- Limpieza final para Plugin Check (sin errores).
- Ajustes de hooks Yoast para cumplir naming de WordPress Coding Standards.
- Mejoras de i18n/encoding y estabilidad en Config/Preview/Logs.

Genera entradas con IA (texto + imagen destacada) con reanudacion por checkpoint y preview en vivo.

## Novedades 1.1.1
- Correccion del flujo de preview (evita doble disparo y estados bloqueados).
- Validacion de titulo duplicado al iniciar preview.
- Mejoras de proveedor/modelo de imagen en Google (Imagen 3.0.002 + fallback).
- Limpieza de caracteres corruptos en logs (mojibake).

## Novedades 1.1.0
- Preview SSE con fallback.
- Blog y Configuracion como unicas pestanas.
- Solo imagen destacada (sin imagenes en contenido).
