<?php

namespace App\Services;

use App\Models\FormDefinition;
use DOMDocument;
use DOMXPath;

class QuoteInitialView
{
    public function render(string $html, string $serviceKey = ''): string
    {
        $auto = str_contains($html, 'id="auto-quote-wizard"');
        if (! $auto && ! str_contains($html, 'id="service-quote-wizard"')) {
            return $html;
        }
        $options = FormDefinition::data('auto-options');
        $copy = FormDefinition::data('quote-progress-copy');
        $options['progress'] = $copy;
        $catalog = $auto ? [] : FormDefinition::data('services');
        $serviceKey = $serviceKey ?: (is_string(request('service')) ? request('service') : '');
        $service = $catalog['services'][$serviceKey] ?? null;
        $picker = ! $auto && ! $service;
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="quote-render-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $find = fn ($query) => $xpath->query($query)->item(0);
        $replace = function ($node, string $content) use ($dom) {
            if (! $node) {
                return;
            }
            while ($node->firstChild) {
                $node->removeChild($node->firstChild);
            }
            $fragment = new DOMDocument('1.0', 'UTF-8');
            $previous = libxml_use_internal_errors(true);
            $fragment->loadHTML('<?xml encoding="UTF-8"><div id="fragment-root">'.$content.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            foreach ($fragment->getElementById('fragment-root')->childNodes as $child) {
                $node->appendChild($dom->importNode($child, true));
            }
        };
        $class = fn ($name) => '//*[contains(concat(" ",normalize-space(@class)," ")," '.$name.' ")]';
        $replace($dom->getElementById('quote-step-content'), view('content.quote-initial', compact('picker', 'catalog', 'copy', 'auto', 'service'))->render());
        if ($picker) {
            $layout = $find($class('quote-layout'));
            $layout->setAttribute('class', $layout->getAttribute('class').' service-picker-layout');
            foreach (['quote-milestones', 'quote-meter', 'wizard-actions', 'quote-help'] as $name) {
                $find($class($name))?->setAttribute('hidden', '');
            }
        } else {
            $stages = $auto ? ['Contact', 'Vehicles', 'Coverage', 'Drivers', 'Address', 'Review'] : ['Contact', 'Details', 'Coverage', 'Personal', 'Review'];
            $items = '';
            foreach ($stages as $index => $stage) {
                $items .= '<li data-stage="'.$stage.'"'.($index === 0 ? ' class="active" aria-current="step"' : '').'><span>'.($index + 1).'</span>'.e($copy['milestones'][$stage] ?? $stage).'</li>';
            }
            $ol = $find($class('quote-milestones').'/ol');
            $replace($ol, $items);
            $ol->setAttribute('style', '--quote-stage-count:'.count($stages));
            $replace($dom->getElementById('quote-stage'), 'Contact');
            $dom->getElementById('wizard-back')->setAttribute('hidden', '');
            $replace($dom->getElementById('quote-side-summary'), $auto ? '<strong>Your request so far</strong><p>0 vehicles added<br>0 drivers added</p>' : '<strong>Your coverage</strong><p>'.e($service['name']).' insurance</p><p>'.e($service['intro']).'</p>');
            $state = $dom->createElement('p');
            $state->setAttribute('class', 'quote-save-state');
            $state->setAttribute('role', 'status');
            $state->setAttribute('aria-live', 'polite');
            $actions = $find($class('wizard-actions'));
            $actions->parentNode->insertBefore($state, $actions->nextSibling);
        }
        $root = $dom->getElementById('quote-render-root');
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        $bootstrap = ['options' => $options];
        if (! $auto) {
            $bootstrap['catalog'] = $catalog;
        }

        return $result.'<script type="application/json" id="quote-bootstrap">'.json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).'</script>';
    }
}
