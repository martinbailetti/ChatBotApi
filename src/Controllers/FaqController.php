<?php
declare(strict_types=1);

/**
 * FaqController — gestiona las FAQs almacenadas en un archivo Markdown.
 *
 * Formato del archivo (DOCS_FAQS_FILE):
 *   # Título
 *
 *   Descripción opcional
 *
 *   ---
 *
 *   ## Pregunta 1
 *
 *   Respuesta 1
 *
 * Rutas:
 *   GET  /api/faqs  — Devuelve { title, description, faqs:[{pregunta,respuesta}] }
 *   POST /api/faqs  — Guarda { title, description, faqs:[{pregunta,respuesta}] } (solo ADMIN)
 */
class FaqController extends BaseController
{
    private function filePath()
    {
        $path = Config::get('DOCS_FAQS_FILE', '');
        return $path !== '' ? $path : null;
    }

    private function parseFaqs($content)
    {
        $faqs  = array();
        $parts = preg_split('/^## /m', $content);
        array_shift($parts);

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            $lines     = explode("\n", $part, 2);
            $pregunta  = trim($lines[0]);
            $respuesta = isset($lines[1]) ? trim($lines[1], " \n-") : '';
            if ($pregunta !== '') {
                $faqs[] = array('pregunta' => $pregunta, 'respuesta' => $respuesta);
            }
        }
        return $faqs;
    }

    private function extractHeader($content)
    {
        $title = '';
        if (preg_match('/^# (.+)$/m', $content, $m)) {
            $title = trim($m[1]);
        }
        $beforeH2    = preg_split('/^## /m', $content, 2)[0];
        $beforeH2    = preg_replace('/^# .+\n?/m', '', $beforeH2);
        $description = trim($beforeH2, " \n-");
        return array('title' => $title, 'description' => $description);
    }

    private function buildMarkdown($title, $description, $faqs)
    {
        $lines = array();
        if ($title !== '') { $lines[] = '# ' . $title; $lines[] = ''; }
        if ($description !== '') { $lines[] = $description; $lines[] = ''; }
        foreach ($faqs as $faq) {
            $pregunta  = trim($faq['pregunta'] ?? '');
            $respuesta = trim($faq['respuesta'] ?? '');
            if ($pregunta === '') continue;
            $lines[] = '---'; $lines[] = '';
            $lines[] = '## ' . $pregunta; $lines[] = '';
            if ($respuesta !== '') { $lines[] = $respuesta; $lines[] = ''; }
        }
        return implode("\n", $lines);
    }

    // GET /api/faqs
    public function index(array $params)
    {
        $this->requireAuth();
        $filePath = $this->filePath();
        if (!$filePath) { $this->jsonError('DOCS_FAQS_FILE no está configurado.', 500); return; }

        if (!file_exists($filePath)) {
            $this->jsonSuccess(array('title' => '', 'description' => '', 'faqs' => array()));
            return;
        }

        $content = (string)file_get_contents($filePath);
        $header  = $this->extractHeader($content);
        $this->jsonSuccess(array(
            'title'       => $header['title'],
            'description' => $header['description'],
            'faqs'        => $this->parseFaqs($content),
        ));
    }

    // POST /api/faqs  (solo ADMIN)
    public function save(array $params)
    {
        $payload = $this->requireAuth();
        if (($payload['type'] ?? '') !== 'ADMIN') { $this->jsonError('Acceso denegado.', 403); return; }

        $filePath = $this->filePath();
        if (!$filePath) { $this->jsonError('DOCS_FAQS_FILE no está configurado.', 500); return; }

        $body        = $this->getJsonBody();
        $title       = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        $faqs        = isset($body['faqs']) && is_array($body['faqs']) ? $body['faqs'] : array();

        $dir = dirname($filePath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $markdown = $this->buildMarkdown($title, $description, $faqs);
        if (file_put_contents($filePath, $markdown) === false) {
            $this->jsonError('No se pudo escribir el archivo de FAQs.', 500); return;
        }

        $this->jsonSuccess(array(
            'title'       => $title,
            'description' => $description,
            'faqs'        => $this->parseFaqs($markdown),
        ), 'FAQs guardadas correctamente.');
    }
}