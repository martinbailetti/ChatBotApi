<?php
declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/bootstrap.php';

// ─── CORS ────────────────────────────────────────────────────────────────────
$allowedOrigins = array_filter(
    array_map('trim', explode(',', Config::get('CORS_ALLOWED_ORIGINS', '*')))
);

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if ($origin !== '') {
    if (in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
} else {
    if (in_array('*', $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: *');
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Router ──────────────────────────────────────────────────────────────────
$router = new Router();

// Auth (públicos: login, logout; protegido: me)
$router->post('/api/auth/login',  ['AuthController', 'login']);
$router->get('/api/auth/me',      ['AuthController', 'me']);
$router->post('/api/auth/logout', ['AuthController', 'logout']);

// Health check (público)
$router->get('/api/health', ['HealthController', 'index']);

// Configuracion del servidor (protegida, solo admin)
$router->get('/api/config/server', ['ServerConfigController', 'index']);

// Usuarios (protegidos)
$router->get('/api/users',  ['UserController', 'index']);
$router->post('/api/users', ['UserController', 'create']);

// Perfil del usuario autenticado
$router->put('/api/auth/profile',  ['AuthController', 'updateProfile']);
$router->put('/api/auth/password', ['AuthController', 'changePassword']);

// Chat / rag proxy (protegido)
$router->post('/api/chat/query', ['DocsController', 'query']);

// Mensajes — admin only
$router->get('/api/chat/messages', ['ConversationController', 'allMessages']);

// Conversaciones (protegidas)
$router->get('/api/chat/conversations',                       ['ConversationController', 'index']);
$router->post('/api/chat/conversations',                      ['ConversationController', 'store']);
$router->get('/api/chat/conversations/{id}/messages',         ['ConversationController', 'messages']);
$router->patch('/api/chat/conversations/{id}/title',          ['ConversationController', 'updateTitle']);
$router->delete('/api/chat/conversations/{id}',               ['ConversationController', 'destroy']);

// Documentos — proxy a ChatIA (protegidas)
$router->get('/api/documents',        ['DocumentsController', 'index']);
$router->get('/api/documents/detail', ['DocumentsController', 'detail']);
$router->get('/api/documents/file',   ['DocumentsController', 'download']);
$router->delete('/api/documents',     ['DocumentsController', 'destroy']);

// Ingesta — proxy a ChatIA (protegidas)
$router->get('/api/ingestion/status', ['IngestionController', 'status']);
$router->get('/api/ingestion/env',    ['IngestionController', 'env']);
$router->post('/api/ingestion/sync',  ['IngestionController', 'sync']);

// FAQs (archivo Markdown; escritura solo ADMIN)
$router->get('/api/faqs',  ['FaqController', 'index']);
$router->post('/api/faqs', ['FaqController', 'save']);

// ─── Dispatch ────────────────────────────────────────────────────────────────
$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);
