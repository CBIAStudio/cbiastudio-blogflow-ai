<?php
/**
 * Prompt builder.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Recommended prompt fixed blocks (non-editable).
if (!function_exists('cbia_prompt_recommended_header_template')) {
    function cbia_prompt_recommended_header_template(): string {
        return
            "Write a COMPLETE POST in {IDIOMA_POST} and HTML for \"{title}\", optimized for Google Discover."
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
            "Escribe un POST COMPLETO en {IDIOMA_POST} y en HTML para \"{title}\", optimizado para Google Discover."
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
            strpos($text, 'Ãƒ') !== false
            || strpos($text, 'Ã‚') !== false
            || strpos($text, 'artÃƒ') !== false
            || strpos($text, 'descripciÃƒ') !== false
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

if (!function_exists('cbia_prompt_has_explicit_profile')) {
    function cbia_prompt_has_explicit_profile(): bool {
        $stored_raw = get_option('cbia_settings', array());
        return is_array($stored_raw) && array_key_exists('blog_prompt_profile', $stored_raw);
    }
}

if (!function_exists('cbia_prompt_should_use_profile_mode')) {
    function cbia_prompt_should_use_profile_mode(array $settings): bool {
        foreach (array('blog_prompt_profile', 'include_faq', 'include_practical_examples', 'search_intent_strength', 'blog_prompt_custom_instructions') as $key) {
            if (array_key_exists($key, $settings)) {
                return true;
            }
        }
        return cbia_prompt_has_explicit_profile();
    }
}

if (!function_exists('cbia_prompt_get_profile_options')) {
    function cbia_prompt_get_profile_options(): array {
        return array(
            'discover_editorial' => array(
                'label' => 'Editorial / Discover',
                'description' => 'Natural, human and editorial. Best for Discover-style reading.',
            ),
            'seo_balanced' => array(
                'label' => 'SEO Balanced',
                'description' => 'Clearer search intent, stronger scanability and useful early answers.',
            ),
            'how_to' => array(
                'label' => 'How-to / Practical Guide',
                'description' => 'Practical, actionable and structured around steps, mistakes and recommendations.',
            ),
        );
    }
}

if (!function_exists('cbia_get_prompt_profile')) {
    function cbia_get_prompt_profile(array $settings): string {
        $profile = sanitize_key((string)($settings['blog_prompt_profile'] ?? 'discover_editorial'));
        return array_key_exists($profile, cbia_prompt_get_profile_options()) ? $profile : 'discover_editorial';
    }
}

if (!function_exists('cbia_prompt_sanitize_custom_instructions')) {
    function cbia_prompt_sanitize_custom_instructions($text): string {
        $text = is_string($text) ? $text : '';
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim((string)$text);
    }
}

if (!function_exists('cbia_prompt_get_profile_settings')) {
    function cbia_prompt_get_profile_settings(array $settings): array {
        $strength = sanitize_key((string)($settings['search_intent_strength'] ?? 'balanced'));
        if (!in_array($strength, array('soft', 'balanced', 'strong'), true)) {
            $strength = 'balanced';
        }
        $length_variant = sanitize_key((string)($settings['post_length_variant'] ?? 'medium'));
        if (!in_array($length_variant, array('short', 'medium', 'long'), true)) {
            $length_variant = 'medium';
        }

        return array(
            'profile' => cbia_get_prompt_profile($settings),
            'include_faq' => !empty($settings['include_faq']),
            'include_practical_examples' => !empty($settings['include_practical_examples']),
            'post_length_variant' => $length_variant,
            'search_intent_strength' => $strength,
            'custom_instructions' => cbia_prompt_sanitize_custom_instructions((string)($settings['blog_prompt_custom_instructions'] ?? '')),
        );
    }
}

if (!function_exists('cbia_prompt_search_intent_line')) {
    function cbia_prompt_search_intent_line($strength, $language = ''): string {
        $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
        switch ((string)$strength) {
            case 'soft':
                return $is_spanish
                    ? '- Prioriza lectura natural, contexto y fluidez editorial por encima de la optimizacion SEO clasica.'
                    : '- Prioritize natural reading, context and editorial flow over classic SEO optimization.';
            case 'strong':
                return $is_spanish
                    ? '- Responde pronto a la intencion principal, deja claras las respuestas practicas y refuerza relevancia semantica en <h2>/<h3> sin keyword stuffing.'
                    : '- Surface the main intent early, make practical answers explicit and reinforce semantic relevance in <h2>/<h3> without keyword stuffing.';
            default:
                return $is_spanish
                    ? '- Equilibra fluidez editorial con una intencion de busqueda clara y respuestas utiles desde la apertura.'
                    : '- Balance editorial flow with clear search intent and useful answers from the opening.';
        }
    }
}

if (!function_exists('cbia_prompt_build_length_policy_block')) {
    function cbia_prompt_build_length_policy_block(array $opts, $language = ''): string {
        $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
        $variant = sanitize_key((string)($opts['post_length_variant'] ?? 'medium'));
        if (!in_array($variant, array('short', 'medium', 'long'), true)) {
            $variant = 'medium';
        }
        $faq = !empty($opts['include_faq']);

        if ($variant === 'short') {
            if ($faq) {
                return $is_spanish
                    ? "POLITICA DE LONGITUD (PRIORIDAD ABSOLUTA)\n- Objetivo total obligatorio: 950-1100 palabras reales (minimo 950).\n- Distribucion obligatoria: apertura 150-190; cada bloque principal 150-180; cada respuesta FAQ 45-55; cierre 80-100.\n- Esta politica prevalece sobre cualquier rango anterior del prompt."
                    : "LENGTH POLICY (ABSOLUTE PRIORITY)\n- Mandatory total target: 950-1100 real words (minimum 950).\n- Mandatory distribution: opening 150-190; each main block 150-180; each FAQ answer 45-55; closing 80-100.\n- This policy overrides any previous range in this prompt.";
            }
            return $is_spanish
                ? "POLITICA DE LONGITUD (PRIORIDAD ABSOLUTA)\n- Objetivo total obligatorio: 950-1100 palabras reales (minimo 950).\n- Distribucion obligatoria: apertura 180-220; cada bloque principal 220-260; cierre 100-130.\n- Esta politica prevalece sobre cualquier rango anterior del prompt."
                : "LENGTH POLICY (ABSOLUTE PRIORITY)\n- Mandatory total target: 950-1100 real words (minimum 950).\n- Mandatory distribution: opening 180-220; each main block 220-260; closing 100-130.\n- This policy overrides any previous range in this prompt.";
        }

        if ($variant === 'long') {
            if ($faq) {
                return $is_spanish
                    ? "POLITICA DE LONGITUD (PRIORIDAD ABSOLUTA)\n- Objetivo total obligatorio: 2000-2200 palabras reales (minimo 2000).\n- Distribucion obligatoria: apertura 280-320; cada bloque principal 340-370; cada respuesta FAQ 90-100; cierre 170-200.\n- Esta politica prevalece sobre cualquier rango anterior del prompt."
                    : "LENGTH POLICY (ABSOLUTE PRIORITY)\n- Mandatory total target: 2000-2200 real words (minimum 2000).\n- Mandatory distribution: opening 280-320; each main block 340-370; each FAQ answer 90-100; closing 170-200.\n- This policy overrides any previous range in this prompt.";
            }
            return $is_spanish
                ? "POLITICA DE LONGITUD (PRIORIDAD ABSOLUTA)\n- Objetivo total obligatorio: 2000-2200 palabras reales (minimo 2000).\n- Distribucion obligatoria: apertura 320-360; cada bloque principal 500-540; cierre 190-220.\n- Esta politica prevalece sobre cualquier rango anterior del prompt."
                : "LENGTH POLICY (ABSOLUTE PRIORITY)\n- Mandatory total target: 2000-2200 real words (minimum 2000).\n- Mandatory distribution: opening 320-360; each main block 500-540; closing 190-220.\n- This policy overrides any previous range in this prompt.";
        }

        if ($faq) {
            return $is_spanish
                ? "POLITICA DE LONGITUD (PRIORIDAD ABSOLUTA)\n- Objetivo total obligatorio: 1800-2000 palabras reales (minimo 1800).\n- Distribucion obligatoria: apertura 260-300; cada bloque principal 320-340; cada respuesta FAQ 80-88; cierre 160-180.\n- Esta politica prevalece sobre cualquier rango anterior del prompt."
                : "LENGTH POLICY (ABSOLUTE PRIORITY)\n- Mandatory total target: 1800-2000 real words (minimum 1800).\n- Mandatory distribution: opening 260-300; each main block 320-340; each FAQ answer 80-88; closing 160-180.\n- This policy overrides any previous range in this prompt.";
        }

        return $is_spanish
            ? "POLITICA DE LONGITUD (PRIORIDAD ABSOLUTA)\n- Objetivo total obligatorio: 1800-2000 palabras reales (minimo 1800).\n- Distribucion obligatoria: apertura 300-340; cada bloque principal 450-490; cierre 170-190.\n- Esta politica prevalece sobre cualquier rango anterior del prompt."
            : "LENGTH POLICY (ABSOLUTE PRIORITY)\n- Mandatory total target: 1800-2000 real words (minimum 1800).\n- Mandatory distribution: opening 300-340; each main block 450-490; closing 170-190.\n- This policy overrides any previous range in this prompt.";
    }
}

if (!function_exists('cbia_prompt_profile_discover_editorial')) {
    function cbia_prompt_profile_discover_editorial(array $opts, $language = ''): string {
        $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
        $variant = sanitize_key((string)($opts['post_length_variant'] ?? 'medium'));
        if (!in_array($variant, array('short', 'medium', 'long'), true)) $variant = 'medium';
        $faq = !empty($opts['include_faq']);

        if ($variant === 'short') {
            if ($faq) {
                $opening_range = '150-190';
                $block_range = '150-180';
                $faq_range = '45-55';
                $closing_range = '80-100';
            } else {
                $opening_range = '180-220';
                $block_range = '220-260';
                $faq_range = '120-150';
                $closing_range = '100-130';
            }
        } elseif ($variant === 'long') {
            if ($faq) {
                $opening_range = '280-320';
                $block_range = '340-370';
                $faq_range = '90-100';
                $closing_range = '170-200';
            } else {
                $opening_range = '320-360';
                $block_range = '500-540';
                $faq_range = '120-150';
                $closing_range = '190-220';
            }
        } else {
            if ($faq) {
                $opening_range = '260-300';
                $block_range = '320-340';
                $faq_range = '80-88';
                $closing_range = '160-180';
            } else {
                $opening_range = '300-340';
                $block_range = '450-490';
                $faq_range = '120-150';
                $closing_range = '170-190';
            }
        }

        $lines = array(
            $is_spanish ? 'TONO Y ESTILO' : 'TONE AND STYLE',
            $is_spanish ? '- Profesional, cercano y natural.' : '- Professional, close and natural.',
            $is_spanish ? '- Editorial y humano, no enciclopedico.' : '- Editorial and human, not encyclopedic.',
            $is_spanish ? '- Narrativo cuando aporte contexto o interes real.' : '- Narrative when it adds context or genuine interest.',
            $is_spanish ? '- Pensado para lectores que no estaban buscando activamente el tema.' : '- Designed for readers who were not actively searching for this topic.',
        );
        if (!empty($opts['include_practical_examples'])) {
            $lines[] = $is_spanish
                ? '- OBLIGATORIO: incluye al menos 3 ejemplos practicos concretos (uno por bloque principal). Cada ejemplo debe incluir contexto real, accion aplicada y resultado esperado medible.'
                : '- MANDATORY: include at least 3 concrete practical examples (one per main block). Each example must include real context, applied action, and measurable expected result.';
        }
        $lines[] = '';
        $lines[] = $is_spanish ? 'ENFOQUE DE BUSQUEDA' : 'SEARCH APPROACH';
        $lines[] = cbia_prompt_search_intent_line((string)$opts['search_intent_strength'], $language);
        $lines[] = '';
        $lines[] = $is_spanish ? 'ESTRUCTURA OBLIGATORIA (no anadir ni eliminar secciones)' : 'MANDATORY STRUCTURE (do not add or remove sections)';
        $lines[] = $is_spanish ? '1) Un encabezado usando la etiqueta <h2>' : '1) One heading using <h2>';
        $lines[] = $is_spanish ? '   Apertura usando 2 parrafos con la etiqueta <p>.' : '   Opening section using 2 paragraphs with <p>.';
        $lines[] = $is_spanish ? '   - NO usar la palabra "Introduccion" ni equivalentes.' : '   - DO NOT use the word "Introduction" or equivalents.';
        $lines[] = $is_spanish ? ('   - Extension total: ' . $opening_range . ' palabras entre ambos parrafos.') : ('   - Total length: ' . $opening_range . ' words across both paragraphs.');
        $lines[] = $is_spanish ? '   - Objetivo: 70-110 palabras por parrafo.' : '   - Target 70-110 words per paragraph.';
        $lines[] = '';
        $lines[] = $is_spanish ? '2) Tres bloques principales, cada uno con:' : '2) Three main blocks, each with:';
        $lines[] = $is_spanish ? '   - (Opcional) un subtitulo usando la etiqueta <h3> SOLO si aporta claridad real.' : '   - (Optional) a subtitle using <h3> ONLY if it adds real clarity.';
        $lines[] = $is_spanish ? ('   - Extension total: ' . $block_range . ' palabras por bloque.') : ('   - Total length: ' . $block_range . ' words per block.');
        $lines[] = $is_spanish ? '   - Divide cada bloque en 2 o 3 parrafos usando <p>.' : '   - Split each block into 2 or 3 paragraphs using <p>.';
        $lines[] = $is_spanish ? '   - Objetivo: 70-110 palabras por parrafo.' : '   - Target 70-110 words per paragraph.';
        $lines[] = $is_spanish ? '   - Listas SOLO cuando ayuden a la comprension (etiquetas <ul> y <li>).' : '   - Lists ONLY when they improve understanding (<ul> and <li>).';
        if (!empty($opts['include_faq'])) {
            $lines[] = '';
            $lines[] = $is_spanish ? '3) Seccion de preguntas frecuentes:' : '3) FAQ section:';
            $lines[] = $is_spanish ? '   - Un encabezado <h2> cuyo texto debe estar escrito en {IDIOMA_POST} y ser el equivalente natural a "Preguntas frecuentes" en ese idioma.' : '   - One <h2> heading written in {IDIOMA_POST}, as the natural equivalent of "Frequently Asked Questions" in that language.';
            $lines[] = $is_spanish ? '   - Seis preguntas frecuentes, cada una con:' : '   - Six FAQs, each with:';
            $lines[] = $is_spanish ? '     - Pregunta en etiqueta <h3>.' : '     - Question in <h3>.';
            $lines[] = $is_spanish ? ('     - Respuesta en 2 parrafos cortos usando <p>, con un total de ' . $faq_range . ' palabras.') : ('     - Answer in 2 short paragraphs using <p>, with a total of ' . $faq_range . ' words.');
        } else {
            $lines[] = '';
            $lines[] = $is_spanish ? '3) NO incluir seccion de preguntas frecuentes (FAQ).' : '3) DO NOT include an FAQ section.';
        }
        $lines[] = '';
        $lines[] = $is_spanish ? '4) Cierre obligatorio:' : '4) Mandatory closing:';
        $lines[] = $is_spanish ? '   - Un encabezado final en <h2> para cerrar el articulo con una recomendacion accionable.' : '   - One final <h2> heading that closes the article with an actionable recommendation.';
        $lines[] = $is_spanish ? ('   - Extension total del cierre: ' . $closing_range . ' palabras en 2 parrafos <p>.') : ('   - Total closing length: ' . $closing_range . ' words in 2 <p> paragraphs.');
        $lines[] = '';
        $lines[] = $is_spanish ? 'REGLAS DE LEGIBILIDAD' : 'READABILITY RULES';
        $lines[] = $is_spanish ? '- Ningun parrafo puede superar 140 palabras.' : '- No paragraph may exceed 140 words.';
        $lines[] = $is_spanish ? '- Si un parrafo supera 4 frases, dividelo en un nuevo parrafo.' : '- If a paragraph exceeds 4 sentences, split it into a new paragraph.';
        $lines[] = $is_spanish ? '- Mantener la mayoria de frases entre 12 y 22 palabras.' : '- Keep most sentences between 12 and 22 words.';
        $lines[] = $is_spanish ? '- Usar conectores naturales cuando mejoren el flujo.' : '- Use natural transition words when they improve flow.';
        $lines[] = $is_spanish ? '- NO usar raya larga ni dash editorial. Preferir comas, punto y coma o punto.' : '- Do NOT use em dashes or en dashes. Prefer commas, semicolons or full stops.';
        return implode("\n", $lines);
    }
}

if (!function_exists('cbia_prompt_profile_seo_balanced')) {
    function cbia_prompt_profile_seo_balanced(array $opts, $language = ''): string {
        $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
        $lines = array(
            $is_spanish ? 'TONO Y ESTILO' : 'TONE AND STYLE',
            $is_spanish ? '- Claro, util y profesional.' : '- Clear, useful and professional.',
            $is_spanish ? '- Mas orientado a resolver la intencion de busqueda que el perfil editorial.' : '- More oriented to solving search intent than the editorial profile.',
            $is_spanish ? '- Escaneable, pero natural. Sin keyword stuffing.' : '- Scan-friendly, but natural. No keyword stuffing.',
        );
        if (!empty($opts['include_practical_examples'])) {
            $lines[] = $is_spanish
                ? '- OBLIGATORIO: incluye al menos 3 ejemplos/casos practicos concretos distribuidos en los 3 bloques principales. Cada caso debe terminar con impacto o KPI esperado.'
                : '- MANDATORY: include at least 3 concrete practical examples/cases across the 3 main blocks. Each case must end with expected impact or KPI.';
        }
        $lines[] = '';
        $lines[] = $is_spanish ? 'ENFOQUE DE BUSQUEDA' : 'SEARCH APPROACH';
        $lines[] = cbia_prompt_search_intent_line((string)$opts['search_intent_strength'], $language);
        $lines[] = '';
        $lines[] = $is_spanish ? 'ESTRUCTURA OBLIGATORIA (no anadir ni eliminar secciones)' : 'MANDATORY STRUCTURE (do not add or remove sections)';
        $lines[] = $is_spanish ? '1) Un encabezado usando la etiqueta <h2>' : '1) One heading using <h2>';
        $lines[] = $is_spanish ? '   Apertura usando 2 parrafos con la etiqueta <p>.' : '   Opening section using 2 paragraphs with <p>.';
        $lines[] = $is_spanish ? '   - La primera respuesta util debe aparecer dentro de los primeros 120-160 palabras.' : '   - The first useful answer should appear within the first 120-160 words.';
        $lines[] = $is_spanish ? '   - Extension total: 170-220 palabras entre ambos parrafos.' : '   - Total length: 170-220 words across both paragraphs.';
        $lines[] = '';
        $lines[] = $is_spanish ? '2) Tres bloques principales, cada uno con:' : '2) Three main blocks, each with:';
        $lines[] = $is_spanish ? '   - Subtitulo <h3> si ayuda a escanear mejor la respuesta.' : '   - A <h3> subtitle if it improves scanability.';
        $lines[] = $is_spanish ? '   - 220-290 palabras por bloque.' : '   - 220-290 words per block.';
        $lines[] = $is_spanish ? '   - 2 o 3 parrafos usando <p>.' : '   - 2 or 3 paragraphs using <p>.';
        $lines[] = $is_spanish ? '   - Prioriza definiciones claras, respuesta practica y subtitulos semanticos.' : '   - Prioritize clear definitions, practical answers and semantic subheadings.';
        $lines[] = $is_spanish ? '   - Las listas (<ul>/<li>) se permiten cuando mejoren claridad o comparacion.' : '   - Lists (<ul>/<li>) are allowed when they improve clarity or comparison.';
        if (!empty($opts['include_faq'])) {
            $lines[] = '';
            $lines[] = $is_spanish ? '3) Seccion de preguntas frecuentes:' : '3) FAQ section:';
            $lines[] = $is_spanish ? '   - Un encabezado <h2> escrito en {IDIOMA_POST} como equivalente natural de "Preguntas frecuentes".' : '   - One <h2> heading written in {IDIOMA_POST} as the natural equivalent of "Frequently Asked Questions".';
            $lines[] = $is_spanish ? '   - Seis preguntas frecuentes centradas en dudas de busqueda reales.' : '   - Six FAQs focused on real search-driven questions.';
            $lines[] = $is_spanish ? '   - Cada pregunta en <h3> y cada respuesta en 2 parrafos cortos con <p>.' : '   - Each question in <h3> and each answer in 2 short paragraphs with <p>.';
        } else {
            $lines[] = '';
            $lines[] = $is_spanish ? '3) NO incluir seccion de preguntas frecuentes (FAQ).' : '3) DO NOT include an FAQ section.';
        }
        $lines[] = '';
        $lines[] = $is_spanish ? 'REGLAS DE LEGIBILIDAD' : 'READABILITY RULES';
        $lines[] = $is_spanish ? '- Frases claras, directas y orientadas a resolver dudas.' : '- Use clear, direct sentences aimed at solving questions.';
        $lines[] = $is_spanish ? '- Mantener la mayoria de frases entre 10 y 22 palabras.' : '- Keep most sentences between 10 and 22 words.';
        $lines[] = $is_spanish ? '- Evita repetir el mismo termino exacto en exceso.' : '- Avoid over-repeating the same exact term.';
        return implode("\n", $lines);
    }
}

if (!function_exists('cbia_prompt_profile_how_to')) {
    function cbia_prompt_profile_how_to(array $opts, $language = ''): string {
        $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
        $lines = array(
            $is_spanish ? 'TONO Y ESTILO' : 'TONE AND STYLE',
            $is_spanish ? '- Practico, claro y accionable.' : '- Practical, clear and actionable.',
            $is_spanish ? '- Orientado a guiar al lector paso a paso o por decisiones concretas.' : '- Oriented to guide the reader step by step or through concrete decisions.',
            $is_spanish ? '- Util y directo, sin perder naturalidad.' : '- Useful and direct, without losing naturalness.',
        );
        if (!empty($opts['include_practical_examples'])) {
            $lines[] = $is_spanish
                ? '- OBLIGATORIO: incluye al menos 3 ejemplos de aplicacion (pasos reales, errores comunes o mini escenarios). Cada ejemplo: problema, accion y resultado esperado.'
                : '- MANDATORY: include at least 3 application examples (real steps, common mistakes, or mini scenarios). Each example: problem, action, and expected result.';
        }
        $lines[] = '';
        $lines[] = $is_spanish ? 'ENFOQUE DE BUSQUEDA' : 'SEARCH APPROACH';
        $lines[] = cbia_prompt_search_intent_line((string)$opts['search_intent_strength'], $language);
        $lines[] = '';
        $lines[] = $is_spanish ? 'ESTRUCTURA OBLIGATORIA (no anadir ni eliminar secciones)' : 'MANDATORY STRUCTURE (do not add or remove sections)';
        $lines[] = $is_spanish ? '1) Un encabezado usando la etiqueta <h2>' : '1) One heading using <h2>';
        $lines[] = $is_spanish ? '   Apertura usando 2 parrafos con la etiqueta <p>.' : '   Opening section using 2 paragraphs with <p>.';
        $lines[] = $is_spanish ? '   - Debe dejar claro que aprendera o resolvera el lector.' : '   - It must make clear what the reader will learn or solve.';
        $lines[] = $is_spanish ? '   - Extension total: 160-210 palabras.' : '   - Total length: 160-210 words.';
        $lines[] = '';
        $lines[] = $is_spanish ? '2) Tres bloques practicos, cada uno con:' : '2) Three practical blocks, each with:';
        $lines[] = $is_spanish ? '   - Subtitulo <h3> si mejora claridad o secuencia.' : '   - A <h3> subtitle if it improves clarity or sequence.';
        $lines[] = $is_spanish ? '   - 220-290 palabras por bloque.' : '   - 220-290 words per block.';
        $lines[] = $is_spanish ? '   - 2 o 3 parrafos con <p>.' : '   - 2 or 3 paragraphs with <p>.';
        $lines[] = $is_spanish ? '   - Explica pasos, decisiones, errores comunes o recomendaciones practicas.' : '   - Explain steps, decisions, common mistakes or practical recommendations.';
        $lines[] = $is_spanish ? '   - Las listas (<ul>/<li>) se permiten si ayudan a ordenar pasos o consejos.' : '   - Lists (<ul>/<li>) are allowed if they help order steps or advice.';
        if (!empty($opts['include_faq'])) {
            $lines[] = '';
            $lines[] = $is_spanish ? '3) Seccion de preguntas frecuentes:' : '3) FAQ section:';
            $lines[] = $is_spanish ? '   - Un encabezado <h2> escrito en {IDIOMA_POST} como equivalente natural de "Preguntas frecuentes".' : '   - One <h2> heading written in {IDIOMA_POST} as the natural equivalent of "Frequently Asked Questions".';
            $lines[] = $is_spanish ? '   - Seis preguntas frecuentes orientadas a dudas practicas y de aplicacion.' : '   - Six FAQs focused on practical and application-oriented doubts.';
            $lines[] = $is_spanish ? '   - Cada respuesta en 2 parrafos cortos con <p>.' : '   - Each answer in 2 short paragraphs with <p>.';
        } else {
            $lines[] = '';
            $lines[] = $is_spanish ? '3) NO incluir seccion de preguntas frecuentes (FAQ).' : '3) DO NOT include an FAQ section.';
        }
        $lines[] = '';
        $lines[] = $is_spanish ? 'REGLAS DE LEGIBILIDAD' : 'READABILITY RULES';
        $lines[] = $is_spanish ? '- Prioriza claridad y accion frente a teoria innecesaria.' : '- Prioritize clarity and action over unnecessary theory.';
        $lines[] = $is_spanish ? '- Usa frases cortas y directas cuando expliques pasos.' : '- Use short, direct sentences when explaining steps.';
        $lines[] = $is_spanish ? '- Mantener la mayoria de frases entre 10 y 20 palabras.' : '- Keep most sentences between 10 and 20 words.';
        return implode("\n", $lines);
    }
}

if (!function_exists('cbia_build_prompt_profile_block')) {
    function cbia_build_prompt_profile_block($profile, array $opts, $language = ''): string {
        switch ((string)$profile) {
            case 'seo_balanced':
                $block = cbia_prompt_profile_seo_balanced($opts, $language);
                break;
            case 'how_to':
                $block = cbia_prompt_profile_how_to($opts, $language);
                break;
            case 'discover_editorial':
            default:
                $block = cbia_prompt_profile_discover_editorial($opts, $language);
                break;
        }

        $length_policy = trim((string)cbia_prompt_build_length_policy_block($opts, $language));
        if ($length_policy !== '') {
            $block .= "\n\n" . $length_policy;
        }

        $custom = trim((string)($opts['custom_instructions'] ?? ''));
        if ($custom !== '') {
            $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($language);
            $block .= "\n\n" . ($is_spanish ? 'INSTRUCCIONES EDITORIALES ADICIONALES' : 'ADDITIONAL EDITORIAL INSTRUCTIONS');
            $block .= "\n- " . str_replace("\n", "\n- ", $custom);
        }

        return $block;
    }
}

if (!function_exists('cbia_prompt_get_recommended_generated_block')) {
    function cbia_prompt_get_recommended_generated_block(array $settings, $language = ''): string {
        $opts = cbia_prompt_get_profile_settings($settings);
        return cbia_build_prompt_profile_block((string)$opts['profile'], $opts, $language);
    }
}

if (!function_exists('cbia_prompt_get_recommended_editable_block')) {
    function cbia_prompt_get_recommended_editable_block(array $settings, $language = ''): string {
        $default_editable = function_exists('cbia_prompt_recommended_editable_default_for_language')
            ? cbia_prompt_recommended_editable_default_for_language($language)
            : (function_exists('cbia_prompt_recommended_editable_default') ? cbia_prompt_recommended_editable_default() : '');

        if (!cbia_prompt_should_use_profile_mode($settings)) {
            $editable = (string)($settings['blog_prompt_editable'] ?? $default_editable);
            if (function_exists('cbia_prompt_maybe_upgrade_legacy_editable')) {
                $editable = cbia_prompt_maybe_upgrade_legacy_editable($editable, $language);
            }
            return function_exists('cbia_prompt_sanitize_editable_block')
                ? cbia_prompt_sanitize_editable_block($editable)
                : $editable;
        }

        $generated = cbia_prompt_get_recommended_generated_block($settings, $language);
        $editable = (string)($settings['blog_prompt_editable'] ?? '');
        if (function_exists('cbia_prompt_sanitize_editable_block')) {
            $editable = cbia_prompt_sanitize_editable_block($editable);
        } else {
            $editable = sanitize_textarea_field($editable);
        }

        return trim($editable) !== '' ? $editable : $generated;
    }
}

if (!function_exists('cbia_prompt_build_recommended_template_from_settings')) {
    function cbia_prompt_build_recommended_template_from_settings(array $settings, $language = ''): string {
        $language = $language !== '' ? (string)$language : (string)($settings['post_language'] ?? 'English');
        $editable = cbia_prompt_get_recommended_editable_block($settings, $language);
        return cbia_prompt_build_recommended_template($editable, $language);
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
        $language = (string)($settings['post_language'] ?? 'English');
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
        $idioma_post = trim((string)($s['post_language'] ?? 'English'));

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
            $prompt_unico = function_exists('cbia_prompt_build_recommended_template_from_settings')
                ? cbia_prompt_build_recommended_template_from_settings($s, $idioma_post)
                : cbia_prompt_build_recommended_template((string)($s['blog_prompt_editable'] ?? ''), $idioma_post);
        }

        $prompt_unico = str_replace('{title}', (string)$title, $prompt_unico);
        // Language is always enforced from selector.
        $prompt_unico = str_replace('{IDIOMA_POST}', $idioma_post, $prompt_unico);

        $variant = sanitize_key((string)($s['post_length_variant'] ?? 'medium'));
        if (!in_array($variant, array('short', 'medium', 'long'), true)) $variant = 'medium';
        if (function_exists('cbia_pick_length_target_words')) {
            list($min_words, $max_words) = cbia_pick_length_target_words($variant, !empty($s['include_faq']));
            $is_spanish = function_exists('cbia_prompt_is_spanish') && cbia_prompt_is_spanish($idioma_post);
            if ($is_spanish) {
                $prompt_unico .= "\n\nCONTROL FINAL DE LONGITUD (OBLIGATORIO)\n";
                $prompt_unico .= "- Antes de devolver la respuesta, valida el total real de palabras del HTML.\n";
                $prompt_unico .= "- Debe quedar entre {$min_words} y {$max_words} palabras.\n";
                $prompt_unico .= "- Si queda corto, amplia en la MISMA respuesta con mas contenido util hasta cumplir minimo.\n";
                $prompt_unico .= "- Si supera el maximo, recorta manteniendo contenido util.\n";
            } else {
                $prompt_unico .= "\n\nFINAL LENGTH CHECK (MANDATORY)\n";
                $prompt_unico .= "- Before returning, validate the real total word count of the HTML.\n";
                $prompt_unico .= "- It must be between {$min_words} and {$max_words} words.\n";
                $prompt_unico .= "- If it is short, expand in the SAME response with useful content until minimum is met.\n";
                $prompt_unico .= "- If it exceeds the maximum, trim while preserving useful content.\n";
            }
        }

        return (string)$prompt_unico;
    }
}
