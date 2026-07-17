<?php
/**
 * Model selection helpers.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!function_exists('cbia_model_fallback_chain')) {
    function cbia_model_fallback_chain($preferred) {
        $chain = [
            'gpt-5-mini',
            'gpt-5.4-mini',
            'gpt-5.4',
            'gpt-5.5',
            'gpt-5',
            'gpt-5-nano',
            'gpt-5.2',
            'gpt-4.1-mini',
            'gpt-4.1',
            'gpt-4.1-nano',
        ];

        $preferred = trim((string)$preferred);
        if ($preferred !== '') {
            if (!in_array($preferred, $chain, true)) {
                array_unshift($chain, $preferred);
            } else {
                $chain = array_values(array_unique(array_merge([$preferred], $chain)));
            }
        }

        return $chain;
    }
}

if (!function_exists('cbia_openai_text_attempt_chain')) {
    /**
     * OpenAI text hotfix: at most the selected model plus one safe fallback.
     */
    function cbia_openai_text_attempt_chain($preferred) {
        $preferred = trim((string)$preferred);
        if ($preferred === '') $preferred = 'gpt-5-mini';

        $fallbacks = apply_filters('cbia_openai_text_safe_fallbacks', array(
            'gpt-5-mini',
            'gpt-4.1-mini',
        ), $preferred);
        $chain = array($preferred);
        foreach ((array)$fallbacks as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '' && $candidate !== $preferred && cbia_is_responses_model($candidate)) {
                $chain[] = $candidate;
                break;
            }
        }
        return array_slice(array_values(array_unique($chain)), 0, 2);
    }
}

if (!function_exists('cbia_openai_text_model_capabilities')) {
    function cbia_openai_text_model_capabilities($model) {
        $model = strtolower(trim((string)$model));
        $supports_controls = (bool)preg_match('/^gpt-5(?:-(?:mini|nano))?(?:-|$)/', $model);
        return apply_filters('cbia_openai_text_model_capabilities', array(
            'reasoning_effort_minimal' => $supports_controls,
            'text_verbosity' => $supports_controls,
        ), $model);
    }
}

if (!function_exists('cbia_is_responses_model')) {
    function cbia_is_responses_model($m) {
        $m = strtolower(trim((string)$m));
        if ($m === '') return false;

        if (preg_match('/^gpt-5(\.[0-9]+)?(\-|$)/', $m)) return true;
        if ($m === 'gpt-5-mini' || $m === 'gpt-5-nano') return true;

        if (strpos($m, 'gpt-4.1') === 0) return true;
        if ($m === 'gpt-4o-mini') return true;

        return false;
    }
}

if (!function_exists('cbia_pick_model')) {
    function cbia_pick_model() {
        $s = cbia_get_settings();
        $preferred = $s['openai_model'] ?? 'gpt-5-mini';

        $chain = cbia_model_fallback_chain($preferred);
        foreach ($chain as $m) {
            return $m;
        }
        // Si no hay candidatos, devolvemos preferido igualmente
        return $preferred ?: 'gpt-5-mini';
    }
}
