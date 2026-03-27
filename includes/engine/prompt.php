<?php
/**
 * Prompt builder.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Recommended prompt fixed blocks (non-editable).
if (!function_exists('cbia_prompt_recommended_header_template')) {
    function cbia_prompt_recommended_header_template(): string {
        return
            "Write a COMPLETE POST in {IDIOMA_POST} and HTML for \"{title}\", optimized for Google Discover, with an approximate length of 1800-2100 words (+/-10%)."
            ."\n\nLANGUAGE RULE (MANDATORY)"
            ."\n- ALL content must be written EXCLUSIVELY in {IDIOMA_POST}."
            ."\n- This includes titles, headings, FAQs and answers."
            ."\n- Using any other language in the content is FORBIDDEN (except title {title} if it is already in another language)."
            ."\n\nContent should prioritize human interest, smooth readability, cultural context and real experience."
            ."\nAvoid traditional SEO style and do not force exact-match keywords.";
    }
}

if (!function_exists('cbia_prompt_recommended_header_template_es')) {
    function cbia_prompt_recommended_header_template_es(): string {
        return
            "Escribe un POST COMPLETO en {IDIOMA_POST} y en HTML para \"{title}\", optimizado para Google Discover, con una extension aproximada de 1800-2100 palabras (+/-10%)."
            ."\n\nREGLA DE IDIOMA (OBLIGATORIA)"
            ."\n- TODO el contenido debe estar escrito EXCLUSIVAMENTE en {IDIOMA_POST}."
            ."\n- Esto incluye titulos, encabezados, preguntas frecuentes y respuestas."
            ."\n- Esta PROHIBIDO usar cualquier otro idioma en el contenido (salvo el titulo {title} si viene en otro idioma)."
            ."\n\nEl contenido debe priorizar interes humano, lectura fluida, contexto cultural y experiencia real."
            ."\nEvita el enfoque de SEO tradicional y no fuerces keywords exactas.";
    }
}

if (!function_exists('cbia_prompt_recommended_editable_default')) {
    function cbia_prompt_recommended_editable_default(): string {
        return
            "TONE AND STYLE"
            ."\n- Professional, close and natural."
            ."\n- Editorial and cultural, not encyclopedic."
            ."\n- Narrative when appropriate, with judgment and point of view."
            ."\n- Designed for readers who were not actively searching for this topic."
            ."\n\nMANDATORY STRUCTURE (do not add or remove sections)"
            ."\n"
            ."\n1) One heading using <h2>"
            ."\n   Opening section using 2 paragraphs with <p>."
            ."\n   - DO NOT use the word \"Introduction\" or equivalents."
            ."\n   - Total length: 180-220 words across both paragraphs."
            ."\n   - Target 70-110 words per paragraph."
            ."\n"
            ."\n2) Three main blocks, each with:"
            ."\n   - (Optional) a subtitle using <h3> ONLY if it adds real clarity."
            ."\n   - Total length: 250-300 words per block."
            ."\n   - Split each block into 2 or 3 paragraphs using <p>."
            ."\n   - Target 70-110 words per paragraph."
            ."\n   - Lists ONLY when they improve understanding (<ul> and <li>)."
            ."\n"
            ."\n3) FAQ section:"
            ."\n   - One <h2> heading written in {IDIOMA_POST}, as the natural equivalent of \"Frequently Asked Questions\" in that language."
            ."\n   - Six FAQs, each with:"
            ."\n     - Question in <h3>."
            ."\n     - Answer in 2 short paragraphs using <p>, with a total of 120-150 words."
            ."\n"
            ."\nREADABILITY RULES"
            ."\n- No paragraph may exceed 140 words."
            ."\n- If a paragraph exceeds 4 sentences, split it into a new paragraph."
            ."\n- Keep most sentences between 12 and 22 words."
            ."\n- No more than 20% of sentences should exceed 24 words."
            ."\n- Use natural transition words in roughly 1 of every 3-4 sentences when they improve flow."
            ."\n- Prefer short, clear sentences over chained clauses."
            ."\n- Do NOT use em dashes or en dashes. Prefer commas, semicolons or full stops.";
    }
}

if (!function_exists('cbia_prompt_recommended_editable_legacy_default')) {
    function cbia_prompt_recommended_editable_legacy_default(): string {
        return
            "TONO Y ESTILO"
            ."\n- Profesional, cercano y natural."
            ."\n- Editorial y cultural, no enciclopedico."
            ."\n- Narrativo cuando sea adecuado, con criterio y punto de vista."
            ."\n- Pensado para lectores que no estaban buscando activamente el tema."
            ."\n\nESTRUCTURA OBLIGATORIA (no anadir ni eliminar secciones)"
            ."\n"
            ."\n1) Un encabezado usando la etiqueta <h2>"
            ."\n   Apertura usando 2 parrafos con la etiqueta <p>."
            ."\n   - NO usar la palabra \"Introduccion\" ni equivalentes."
            ."\n   - Extension total: 180-220 palabras entre ambos parrafos."
            ."\n   - Objetivo: 70-110 palabras por parrafo."
            ."\n"
            ."\n2) Tres bloques principales, cada uno con:"
            ."\n   - (Opcional) un subtitulo usando la etiqueta <h3> SOLO si aporta claridad real."
            ."\n   - Extension total: 250-300 palabras por bloque."
            ."\n   - Divide cada bloque en 2 o 3 parrafos usando <p>."
            ."\n   - Objetivo: 70-110 palabras por parrafo."
            ."\n   - Listas SOLO cuando ayuden a la comprension (etiquetas <ul> y <li>)."
            ."\n"
            ."\n3) Seccion de preguntas frecuentes:"
            ."\n   - Un encabezado <h2> cuyo texto debe estar escrito en {IDIOMA_POST} y ser el equivalente natural a \"Preguntas frecuentes\" en ese idioma."
            ."\n   - Seis preguntas frecuentes, cada una con:"
            ."\n     - Pregunta en etiqueta <h3>."
            ."\n     - Respuesta en 2 parrafos cortos usando <p>, con un total de 120-150 palabras."
            ."\n"
            ."\nREGLAS DE LEGIBILIDAD"
            ."\n- Ningun parrafo puede superar 140 palabras."
            ."\n- Si un parrafo supera 4 frases, dividelo en un nuevo parrafo."
            ."\n- Mantener la mayoria de frases entre 12 y 22 palabras."
            ."\n- No mas del 20% de las frases pueden superar 24 palabras."
            ."\n- Usar conectores naturales en aproximadamente 1 de cada 3-4 frases cuando mejoren el flujo."
            ."\n- Priorizar frases claras y directas frente a clausulas encadenadas."
            ."\n- NO usar raya larga ni dash editorial. Preferir comas, punto y coma o punto.";
    }
}

if (!function_exists('cbia_prompt_recommended_editable_default_previous')) {
    function cbia_prompt_recommended_editable_default_previous(): string {
        return
            "TONE AND STYLE"
            ."\n- Professional, close and natural."
            ."\n- Editorial and cultural, not encyclopedic."
            ."\n- Narrative when appropriate, with judgment and point of view."
            ."\n- Designed for readers who were not actively searching for this topic."
            ."\n\nMANDATORY STRUCTURE (do not add or remove sections)"
            ."\n"
            ."\n1) One heading using <h2>"
            ."\n   Opening paragraph using <p>."
            ."\n   - DO NOT use the word \"Introduction\" or equivalents."
            ."\n   - Length: 180-220 words."
            ."\n"
            ."\n2) Three main blocks, each with:"
            ."\n   - (Optional) a subtitle using <h3> ONLY if it adds real clarity."
            ."\n   - Length: 250-300 words per block."
            ."\n   - Lists ONLY when they improve understanding (<ul> and <li>)."
            ."\n"
            ."\n3) FAQ section:"
            ."\n   - One <h2> heading written in {IDIOMA_POST}, as the natural equivalent of \"Frequently Asked Questions\" in that language."
            ."\n   - Six FAQs, each with:"
            ."\n     - Question in <h3>."
            ."\n     - Answer in <p> (120-150 words).";
    }
}

if (!function_exists('cbia_prompt_recommended_editable_legacy_default_previous')) {
    function cbia_prompt_recommended_editable_legacy_default_previous(): string {
        return
            "TONO Y ESTILO"
            ."\n- Profesional, cercano y natural."
            ."\n- Editorial y cultural, no enciclopedico."
            ."\n- Narrativo cuando sea adecuado, con criterio y punto de vista."
            ."\n- Pensado para lectores que no estaban buscando activamente el tema."
            ."\n\nESTRUCTURA OBLIGATORIA (no anadir ni eliminar secciones)"
            ."\n"
            ."\n1) Un encabezado usando la etiqueta <h2>"
            ."\n   Parrafo inicial usando la etiqueta <p>."
            ."\n   - NO usar la palabra \"Introduccion\" ni equivalentes."
            ."\n   - Extension: 180-220 palabras."
            ."\n"
            ."\n2) Tres bloques principales, cada uno con:"
            ."\n   - (Opcional) un subtitulo usando la etiqueta <h3> SOLO si aporta claridad real."
            ."\n   - Extension: 250-300 palabras por bloque."
            ."\n   - Listas SOLO cuando ayuden a la comprension (etiquetas <ul> y <li>)."
            ."\n"
            ."\n3) Seccion de preguntas frecuentes:"
            ."\n   - Un encabezado <h2> cuyo texto debe estar escrito en {IDIOMA_POST} y ser el equivalente natural a \"Preguntas frecuentes\" en ese idioma."
            ."\n   - Seis preguntas frecuentes, cada una con:"
            ."\n     - Pregunta en etiqueta <h3>."
            ."\n     - Respuesta en etiqueta <p> (120-150 palabras).";
    }
}

if (!function_exists('cbia_prompt_maybe_upgrade_legacy_editable')) {
    function cbia_prompt_maybe_upgrade_legacy_editable($text, $language = ''): string {
        $text = is_string($text) ? $text : '';
        $normalize = static function ($value): string {
            $value = str_replace(array("\r\n", "\r"), "\n", (string)$value);
            return trim($value);
        };

        $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
        $normalized = $normalize($text);

        if ($normalized === $normalize(cbia_prompt_recommended_editable_default_previous())
            || $normalized === $normalize(cbia_prompt_recommended_editable_default())
        ) {
            return cbia_prompt_recommended_editable_default();
        }

        if ($normalized === $normalize(cbia_prompt_recommended_editable_legacy_default_previous())
            || $normalized === $normalize(cbia_prompt_recommended_editable_legacy_default())
        ) {
            return $is_spanish
                ? cbia_prompt_recommended_editable_legacy_default()
                : cbia_prompt_recommended_editable_default();
        }

        return $text;
    }
}

if (!function_exists('cbia_prompt_recommended_footer_template')) {
    function cbia_prompt_recommended_footer_template(): string {
        return
            "CRITICAL INSTRUCTION"
            ."\n- No response should be cut off."
            ."\n- ALL responses must end with a period."
            ."\n\nIMAGES"
            ."\nInsert image markers ONLY where they add value, using this EXACT format:"
            ."\n[IMAGE: short, concrete description, no text or watermark, realistic/editorial style]"
            ."\n\nMANDATORY RULES"
            ."\n- DO NOT use <h1>."
            ."\n- DO NOT add a conclusion section."
            ."\n- DO NOT include a final CTA."
            ."\n- DO NOT use: doctype, html, head, body, script, style, iframe, table, blockquote."
            ."\n- DO NOT link to external websites (use plain text \"(internal link)\" if needed)."
            ."\n- Avoid redundancy and filler phrases."
            ."\n- Do not write using exact-keyword SEO style."
            ."\n\nThe result should read like a premium editorial article, interesting on its own and suitable for Google Discover.";
    }
}

if (!function_exists('cbia_prompt_recommended_footer_template_es')) {
    function cbia_prompt_recommended_footer_template_es(): string {
        return
            "INSTRUCCION CRITICA"
            ."\n- Ninguna respuesta debe cortarse."
            ."\n- TODAS las respuestas deben terminar en punto final."
            ."\n\nIMAGENES"
            ."\nInserta marcadores de imagen SOLO donde aporten valor, usando el formato EXACTO:"
            ."\n[IMAGEN: descripcion breve, concreta, sin texto ni marcas de agua, estilo realista/editorial]"
            ."\n\nREGLAS DE OBLIGADO CUMPLIMIENTO"
            ."\n- NO usar la etiqueta <h1>."
            ."\n- NO anadir seccion de conclusion."
            ."\n- NO incluir CTA final."
            ."\n- NO usar las etiquetas: doctype, html, head, body, script, style, iframe, table, blockquote."
            ."\n- NO enlazar a webs externas (usar el texto plano \"(enlace interno)\" si es necesario)."
            ."\n- Evitar redundancias y muletillas."
            ."\n- No escribir con enfoque SEO por keyword exacta."
            ."\n\nEl resultado debe leerse como un articulo editorial premium, interesante por si mismo y adecuado para aparecer en Google Discover.";
    }
}

if (!function_exists('cbia_prompt_is_spanish')) {
    function cbia_prompt_is_spanish($language): bool {
        $language = strtolower(trim((string)$language));
        return in_array($language, array('spanish', 'espanol', 'español'), true);
    }
}

if (!function_exists('cbia_prompt_recommended_editable_default_for_language')) {
    function cbia_prompt_recommended_editable_default_for_language($language): string {
        return (function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language))
            ? cbia_prompt_recommended_editable_legacy_default()
            : cbia_prompt_recommended_editable_default();
    }
}

if (!function_exists('cbia_prompt_text_equals')) {
    function cbia_prompt_text_equals($a, $b): bool {
        $normalize = static function ($value): string {
            $value = str_replace(array("\r\n", "\r"), "\n", (string)$value);
            return trim($value);
        };
        return $normalize($a) === $normalize($b);
    }
}

if (!function_exists('cbia_prompt_legacy_default_for_language')) {
    function cbia_prompt_legacy_default_for_language($language): string {
        if (function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language)) {
            return "Escribe un articulo de blog en HTML (sin usar H1) sobre: {title}\nIncluye marcadores de imagen del tipo [IMAGEN: descripcion].";
        }
        return "Write a blog article in HTML (without H1) about: {title}\nInclude image markers like [IMAGE: description].";
    }
}

if (!function_exists('cbia_prompt_clean_legacy_template')) {
    function cbia_prompt_clean_legacy_template($text, $language = ''): string {
        $text = trim((string)$text);
        if ($text === '') return $text;
        if (function_exists('cbia_fix_mojibake')) {
            $text = trim((string)cbia_fix_mojibake($text));
        }
        $looks_broken = (
            strpos($text, 'Ã') !== false
            || strpos($text, 'Â') !== false
            || strpos($text, 'artÃ') !== false
            || strpos($text, 'descripciÃ') !== false
            || strpos($text, '(sin )') !== false
        );
        if ($looks_broken) {
            return cbia_prompt_legacy_default_for_language($language);
        }
        return $text;
    }
}

if (!function_exists('cbia_prompt_sanitize_editable_block')) {
    function cbia_prompt_sanitize_editable_block($text): string {
        $text = is_string($text) ? $text : '';
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove attempts to inject blocked sections/tags in editable block.
        $blocked_prefixes = array(
            'INSTRUCCION CRITICA',
            'IMAGENES',
            'REGLAS DE OBLIGADO CUMPLIMIENTO',
            'CRITICAL INSTRUCTION',
            'IMAGES',
            'MANDATORY RULES',
        );
        $blocked_tags = array(
            '<h1', '<script', '<style', '<iframe', '<table', '<blockquote', '<!doctype', '<html', '<head', '<body',
        );

        $clean = array();
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line_trim = ltrim((string)$line);
            $normalized = function_exists('remove_accents') ? remove_accents($line_trim) : $line_trim;
            $upper = strtoupper($normalized);

            $skip = false;
            foreach ($blocked_prefixes as $prefix) {
                if (strpos($upper, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $line_lower = strtolower($line_trim);
            foreach ($blocked_tags as $tag) {
                if (strpos($line_lower, $tag) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $clean[] = $line;
        }

        $out = trim(implode("\n", $clean));
        if ($out === '') {
            $out = cbia_prompt_recommended_editable_default();
        }
        return $out;
    }
}

if (!function_exists('cbia_prompt_build_recommended_template')) {
    function cbia_prompt_build_recommended_template($editable = null, $language = ''): string {
        $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
        $default_editable = $is_spanish
            ? cbia_prompt_recommended_editable_legacy_default()
            : cbia_prompt_recommended_editable_default();
        $header = $is_spanish
            ? cbia_prompt_recommended_header_template_es()
            : cbia_prompt_recommended_header_template();
        $footer = $is_spanish
            ? cbia_prompt_recommended_footer_template_es()
            : cbia_prompt_recommended_footer_template();
        $editable_block = is_string($editable) ? $editable : $default_editable;
        $editable_block = cbia_prompt_sanitize_editable_block($editable_block);
        return $header . "\n\n" . $editable_block . "\n\n" . $footer;
    }
}

if (!function_exists('cbia_prompt_get_mode')) {
    function cbia_prompt_get_mode(array $settings): string {
        // Detect whether mode was explicitly chosen in DB or comes from default merge.
        $stored_raw = get_option('cbia_settings', array());
        $has_explicit_mode = is_array($stored_raw) && array_key_exists('blog_prompt_mode', $stored_raw);

        $mode = sanitize_key((string)($settings['blog_prompt_mode'] ?? ''));
        if ($has_explicit_mode && in_array($mode, array('recommended', 'legacy'), true)) {
            return $mode;
        }

        // Backward compatibility: if historical prompt exists, default to legacy mode.
        $legacy = trim((string)($settings['legacy_full_prompt'] ?? ''));
        if ($legacy === '') $legacy = trim((string)($settings['prompt_single_all'] ?? ''));
        return $legacy !== '' ? 'legacy' : 'recommended';
    }
}

if (!function_exists('cbia_prompt_get_legacy_template')) {
    function cbia_prompt_get_legacy_template(array $settings): string {
        // Backward compatibility with historical prompt_single_all.
        $language = (string)($settings['post_language'] ?? 'Spanish');
        $legacy = trim((string)($settings['legacy_full_prompt'] ?? ''));
        if ($legacy !== '' && function_exists('cbia_prompt_clean_legacy_template')) {
            $legacy = cbia_prompt_clean_legacy_template($legacy, $language);
        }
        if ($legacy !== '') return $legacy;
        $legacy = trim((string)($settings['prompt_single_all'] ?? ''));
        if ($legacy !== '' && function_exists('cbia_prompt_clean_legacy_template')) {
            $legacy = cbia_prompt_clean_legacy_template($legacy, $language);
        }
        return $legacy;
    }
}

if (!function_exists('cbia_build_prompt_for_title')) {
    function cbia_build_prompt_for_title($title) {
        $s = cbia_get_settings();
        $idioma_post = trim((string)($s['post_language'] ?? 'Spanish'));

        // Recommended/legacy mode with backward compatibility.
        $mode = cbia_prompt_get_mode($s);
        if ($mode === 'legacy') {
            $prompt_unico = cbia_prompt_get_legacy_template($s);
            if ($prompt_unico === '') {
                $default_editable = cbia_prompt_is_spanish($idioma_post)
                    ? cbia_prompt_recommended_editable_legacy_default()
                    : cbia_prompt_recommended_editable_default();
                $editable = (string)($s['blog_prompt_editable'] ?? $default_editable);
                if (function_exists('cbia_prompt_maybe_upgrade_legacy_editable')) {
                    $editable = cbia_prompt_maybe_upgrade_legacy_editable($editable, $idioma_post);
                }
                $prompt_unico = cbia_prompt_build_recommended_template($editable, $idioma_post);
            }
        } else {
            $default_editable = cbia_prompt_is_spanish($idioma_post)
                ? cbia_prompt_recommended_editable_legacy_default()
                : cbia_prompt_recommended_editable_default();
            $editable = (string)($s['blog_prompt_editable'] ?? $default_editable);
            if (function_exists('cbia_prompt_maybe_upgrade_legacy_editable')) {
                $editable = cbia_prompt_maybe_upgrade_legacy_editable($editable, $idioma_post);
            }
            $prompt_unico = cbia_prompt_build_recommended_template($editable, $idioma_post);
        }

        $prompt_unico = str_replace('{title}', (string)$title, $prompt_unico);
        // Language is always enforced from selector.
        $prompt_unico = str_replace('{IDIOMA_POST}', $idioma_post, $prompt_unico);

        return (string)$prompt_unico;
    }
}
